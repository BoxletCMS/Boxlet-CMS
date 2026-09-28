<?php

namespace App\Modules\Install;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Modules\Auth\Password;
use App\Support\Url;
use Closure;
use DateTimeZone;
use RuntimeException;

/**
 * install.php: requirements + token → database → admin → site → install → done.
 *
 * Progress lives in the session and nothing is written until the last step. Every
 * step after the first needs the token from storage/install-token.txt, which proves
 * the person installing can read the server's disk.
 */
final class InstallController
{
    private const STEPS = ['requirements', 'database', 'admin', 'site'];

    /**
     * @param Closure(): bool $rewriteWorks
     */
    public function __construct(
        private readonly string $root,
        private readonly string $storage,
        private readonly string $envPath,
        private readonly string $script,
        private readonly Session $session,
        private readonly Closure $rewriteWorks,
        private readonly ?string $cacheDirectory = null,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (is_file($this->storage . '/install.lock')) {
            return $this->page('locked', ['deleted' => Installer::deleteScript($this->script)], 403);
        }

        $state = $this->state();
        $step = $this->tokenAccepted($state) ? (string) ($state['step'] ?? 'database') : 'requirements';

        if ($request->method !== 'POST') {
            return $this->show($step);
        }
        if (!$this->session->validCsrf($request->body['_csrf'] ?? null)) {
            return $this->show($step, t('csrf.invalid'), 403);
        }
        if ($request->input('action') === 'restart') {
            $this->session->remove('install');

            return Response::redirect(Url::asset('install.php'));
        }

        try {
            return match ($step) {
                'requirements' => $this->submitToken($request),
                'database' => $this->submitDatabase($request),
                'admin' => $this->submitAdmin($request),
                default => $this->submitSite($request, $state),
            };
        } catch (RuntimeException $e) {
            return $this->show($step, $e->getMessage(), 422, $request->body);
        }
    }

    private function submitToken(Request $request): Response
    {
        if (Requirements::blocked($this->checks())) {
            throw new RuntimeException(t('install.req.blocked'));
        }
        if (!hash_equals($this->token(), trim($request->input('token')))) {
            throw new RuntimeException(t('install.token.wrong'));
        }
        $this->session->regenerate();
        $this->save(['token' => hash('sha256', $this->token()), 'step' => 'database']);

        return Response::redirect(Url::asset('install.php'));
    }

    private function submitDatabase(Request $request): Response
    {
        if ($request->input('driver') === 'sqlite') {
            $path = trim($request->input('path'));
            DatabaseSetup::sqlite($this->root, $path);
            $env = ['DB_DRIVER' => 'sqlite', 'DB_PATH' => $path];
        } else {
            $port = trim($request->input('port'));
            $mysql = [
                'host' => trim($request->input('host')),
                'port' => $port === '' ? 3306 : (int) $port,
                'database' => trim($request->input('database')),
                'username' => trim($request->input('username')),
                'password' => $request->input('password'),
            ];
            DatabaseSetup::mysql($mysql);
            $env = [
                'DB_DRIVER' => 'mysql',
                'DB_HOST' => $mysql['host'],
                'DB_PORT' => (string) $mysql['port'],
                'DB_DATABASE' => $mysql['database'],
                'DB_USERNAME' => $mysql['username'],
                'DB_PASSWORD' => $mysql['password'],
            ];
        }
        $this->save(['db' => $env, 'step' => 'admin']);

        return Response::redirect(Url::asset('install.php'));
    }

    private function submitAdmin(Request $request): Response
    {
        $email = mb_strtolower(trim($request->input('email')));
        $password = $request->input('password');
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException(t('install.admin.bad_email'));
        }
        $problem = Password::problem($password, $request->input('password_confirm'));
        if ($problem !== null) {
            throw new RuntimeException($problem);
        }
        $this->save([
            'admin' => ['email' => $email, 'password_hash' => password_hash($password, PASSWORD_DEFAULT)],
            'step' => 'site',
        ]);

        return Response::redirect(Url::asset('install.php'));
    }

    /**
     * @param array<mixed> $state
     */
    private function submitSite(Request $request, array $state): Response
    {
        $site = [
            'name' => trim($request->input('name')),
            'locale' => $request->input('locale'),
            'timezone' => $request->input('timezone'),
        ];
        if ($site['name'] === '' || mb_strlen($site['name']) > 100) {
            throw new RuntimeException(t('install.site.bad_name'));
        }
        if (!isset(self::languages()[$site['locale']])) {
            throw new RuntimeException(t('install.site.bad_locale'));
        }
        if (!in_array($site['timezone'], DateTimeZone::listIdentifiers(), true)) {
            throw new RuntimeException(t('install.site.bad_timezone'));
        }
        if (Requirements::blocked($this->checks())) {
            throw new RuntimeException(t('install.req.changed'));
        }
        if (!is_array($state['db'] ?? null) || !is_array($state['admin'] ?? null)) {
            throw new RuntimeException(t('install.state_lost'));
        }

        $db = DatabaseSetup::fromEnv($this->root, $state['db']);
        $cache = $this->cacheDirectory ?? $this->root . '/public/cache';
        $installer = new Installer($this->root, $this->storage, $this->envPath, $cache);
        $installer->run($db, $state['db'], $state['admin'], $site, $request->input('demo') === '1');

        $this->session->remove('install');
        $this->session->regenerate();

        return $this->page('done', ['deleted' => Installer::deleteScript($this->script)]);
    }

    /**
     * @param array<mixed> $old submitted fields to refill after an error
     */
    private function show(string $step, ?string $error = null, int $status = 200, array $old = []): Response
    {
        $data = ['error' => $error, 'old' => $old];
        if ($step === 'requirements') {
            $data['checks'] = $this->checks();
            $data['blocked'] = Requirements::blocked($data['checks']);
            $data['tokenPath'] = $this->storage . '/install-token.txt';
            if (!$data['blocked']) {
                $this->token();
            }
        }
        if ($step === 'site') {
            $data['languages'] = self::languages();
            $data['timezones'] = DateTimeZone::listIdentifiers();
        }

        return $this->page($step, $data, $status);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function page(string $template, array $data, int $status = 200): Response
    {
        $data += [
            'title' => t('install.title'),
            'csrf' => $this->session->csrfToken(),
            'step' => $template,
            'steps' => self::STEPS,
            'error' => null,
            'old' => [],
        ];
        $html = (new View(__DIR__ . '/views'))->render($template, 'en', $data);

        return Response::admin($html, $status);
    }

    /**
     * @return list<array{id: string, label: string, ok: bool, required: bool, detail: string}>
     */
    private function checks(): array
    {
        return Requirements::check($this->root, $this->storage, $this->envPath, $this->rewriteWorks);
    }

    /**
     * The token in storage/install-token.txt, created on the first visit.
     */
    private function token(): string
    {
        $file = $this->storage . '/install-token.txt';
        if (!is_file($file)) {
            file_put_contents($file, bin2hex(random_bytes(16)) . "\n");
            chmod($file, 0600);
        }

        return trim((string) file_get_contents($file));
    }

    /**
     * @param array<mixed> $state
     */
    private function tokenAccepted(array $state): bool
    {
        $file = $this->storage . '/install-token.txt';

        return is_string($state['token'] ?? null)
            && is_file($file)
            && hash_equals(hash('sha256', trim((string) file_get_contents($file))), $state['token']);
    }

    /**
     * @return array<mixed>
     */
    private function state(): array
    {
        $state = $this->session->get('install');

        return is_array($state) ? $state : [];
    }

    /**
     * @param array<string, mixed> $changes
     */
    private function save(array $changes): void
    {
        $this->session->set('install', $changes + $this->state());
    }

    /**
     * @return array<string, string> ISO 639-1 code => native name
     */
    private static function languages(): array
    {
        return require dirname(__DIR__) . '/I18n/languages.php';
    }
}
