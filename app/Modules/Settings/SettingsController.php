<?php

namespace App\Modules\Settings;

use App\Core\Container;
use App\Core\Db;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Modules\Admin\Activity;
use App\Modules\Admin\AdminView;
use App\Modules\Ai\AiController;
use App\Modules\Mcp\McpSettingsController;
use App\Modules\Auth\TwoFactor;
use App\Modules\Languages\Locales;
use App\Modules\Mailer\MailController;
use App\Modules\Pages\LlmsTxt;
use App\Modules\Media\MediaReference;
use App\Support\PageCache;
use App\Support\Dates;
use App\Support\Url;
use DateTimeZone;

/**
 * Site settings (PLAN.md D-028): what the installer wrote, edited afterwards, plus the
 * pictures a site falls back to and the maintenance message.
 *
 * IN TABS, EACH ITS OWN ADDRESS (D-294; the owner, 2026-10-24: one long page of anchors was
 * confusing): General at /admin/settings, then Languages, Email, AI (translation, AI access,
 * AI search), Your login and Maintenance under /admin/settings/…, as Navigation's tabs are
 * (D-281). Each gathers only what it shows. Statistics' settings are on the Statistics screen.
 *
 * No new table. `settings` is `key` and value_json (migration 0003) and holds all of it
 * through App\Core\Settings, which is also what the installer and the admin shell use.
 *
 * The maintenance SWITCH is on this screen but posts to its own route, unchanged: the
 * flag is a file rather than a settings row on purpose (D-021 — maintenance is exactly
 * when the database may be unavailable), and giving it a second write path here would
 * have split one mechanism in two. Only the message lives in settings.
 */
final class SettingsController
{
    /** The keys this screen owns. site_name and timezone are the installer's, edited here. */
    private const PICTURES = ['site_logo', 'site_logo_dark', 'site_favicon', 'site_share_image'];

    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function show(Request $request, string $locale, array $params): Response
    {
        return $this->form($this->stored(), [], null);
    }

    /**
     * @param array<string, string> $params
     */
    public function save(Request $request, string $locale, array $params): Response
    {
        $db = $this->db();
        $values = [
            'site_name' => trim($request->input('site_name')),
            'timezone' => trim($request->input('timezone')),
            // One line in the site's footer saying what made it (O-20). Off unless asked
            // for: what a visitor reads belongs to the site's owner, not to Boxlet.
            'site_credit' => $request->input('site_credit') === '1',
        ];

        // The same list the installer checks against. Two screens writing one setting
        // against two different ideas of what is valid is how they end up disagreeing.
        if (!in_array($values['timezone'], DateTimeZone::listIdentifiers(), true)) {
            $values += $this->storedPictures();

            return $this->form($values, ['timezone' => t('settings.timezone_invalid')], null, 422);
        }

        // An id that names no picture becomes null, the rule MediaReference sets for block
        // content: nothing stops a library row being deleted after it was chosen here.
        $known = array_column(MediaReference::choices($db), 'id');
        $cleared = false;
        foreach (self::PICTURES as $key) {
            $chosen = (int) $request->input($key);
            if ($chosen > 0 && !in_array($chosen, $known, true)) {
                $chosen = 0;
                $cleared = true;
            }
            $values[$key] = $chosen > 0 ? $chosen : null;
        }

        foreach ($values as $key => $value) {
            Settings::set($db, $key, $value);
        }
        // llms.txt opens with the site's name (D-151).
        LlmsTxt::publish($db, $this->publicPath());
        Activity::record($db, 'settings', 'saved', null, '');

        $this->container->get('session')->set(
            'flash',
            $cleared ? t('settings.saved') . ' ' . t('settings.picture_gone') : t('settings.saved'),
        );

        return Response::redirect(Url::admin('settings'));
    }

    /**
     * The message visitors see while the site is in maintenance, saved on its own beside
     * the switch it belongs to (D-038). Empty means the standard wording.
     *
     * @param array<string, string> $params
     */
    public function saveMessage(Request $request, string $locale, array $params): Response
    {
        Settings::set($this->db(), 'maintenance_message', trim($request->input('maintenance_message')));
        // Where the closed page can read it without the database (D-241).
        $this->container->get('maintenance')->remember($this->db());
        $this->container->get('session')->set('flash', t('settings.maintenance_message_saved'));

        return Response::redirect(Url::admin('settings', 'maintenance'));
    }

    /**
     * Everything the screen shows, in one query for the text and one for the pictures.
     *
     * @return array<string, mixed>
     */
    private function stored(): array
    {
        $db = $this->db();
        $text = Settings::many($db, ['site_name', 'timezone', 'maintenance_message'], '');
        $credit = Settings::get($db, 'site_credit') === true;

        return [
            'site_name' => is_string($text['site_name']) ? $text['site_name'] : '',
            'timezone' => is_string($text['timezone']) ? $text['timezone'] : '',
            'maintenance_message' => is_string($text['maintenance_message']) ? $text['maintenance_message'] : '',
            'site_credit' => $credit,
        ] + $this->storedPictures();
    }

    /**
     * @return array<string, int|null>
     */
    private function storedPictures(): array
    {
        $db = $this->db();
        $pictures = [];
        foreach (self::PICTURES as $key) {
            $pictures[$key] = Settings::mediaId($db, $key);
        }
        // The logo the header actually draws, whichever setting it still comes from.
        $pictures['site_logo'] = SiteChrome::logo($db);

        return $pictures;
    }

    /**
     * General, the tab /admin/settings opens on: the site's name, its time, its pictures (D-294).
     *
     * @param array<string, mixed> $values
     * @param array<string, string> $errors
     */
    private function form(array $values, array $errors, ?string $notice, int $status = 200): Response
    {
        return $this->render('general', [
            // The picker's own stylesheets and script, the same set the page editor loads.
            'styles' => ['admin-media.css', 'admin-picker.css', 'admin-browser.css', 'vendor/cropper.min.css', 'admin-crop.css'],
            // settings-anchors.js: an address kept from the one long page (#mail, #ai…) opens its tab.
            'scripts' => ['vendor/cropper.min.js', 'media-browser-upload.js', 'media-browser.js', 'media-picker.js', 'file-sends.js', 'settings-anchors.js'],
            'values' => $values,
            'errors' => $errors,
            'notice' => $notice,
            'pictures' => MediaReference::choices($this->db()),
            'timezones' => DateTimeZone::listIdentifiers(),
            // The SVG logos (D-142), shown under the pickers they stand in for.
            'logoSvg' => LogoSvg::all($this->db()),
            'lastSaved' => $this->lastSaved(),
        ], $status);
    }

    /**
     * A tab other than General (D-294): each its own address, and only what it shows gathered.
     * The route admits only these names.
     *
     * @param array<string, string> $params
     */
    public function tab(Request $request, string $locale, array $params): Response
    {
        $db = $this->db();

        return match ($params['tab'] ?? '') {
            'languages' => $this->render('languages', ['languages' => Locales::all($db), 'addable' => Locales::addable($db)]),
            'mail' => $this->render('mail', ['scripts' => ['mail-settings.js']] + MailController::panel($this->container)),
            'account' => $this->render('account', [
                'styles' => ['admin-two-step.css'],
                'twoStep' => $this->twoStep(),
                // Your login (D-132): the address the admin logs in with.
                'accountEmail' => (string) ($db->one('SELECT email FROM admin WHERE id = ?', [(int) $this->container->get('session')->get('admin_id')])['email'] ?? ''),
            ]),
            'maintenance' => $this->render('maintenance', [
                'values' => $this->stored(),
                'maintenanceOn' => $this->container->get('maintenance')->isOn(),
                // The page cache (D-053): on unless switched off, and how many pages it holds.
                'pageCache' => ['on' => Settings::get($db, 'page_cache', '1') !== '0', 'count' => PageCache::count()],
            ]),
            default => throw new \LogicException('No settings tab ' . ($params['tab'] ?? '') . '; the route admits only the tabs here.'),
        };
    }

    /**
     * AI, in three: translation, AI access over MCP, and llms.txt for AI search (D-294).
     *
     * @param array<string, string> $params
     */
    public function ai(Request $request, string $locale, array $params): Response
    {
        $sub = $params['sub'] ?? 'translation';
        $data = match ($sub) {
            'translation' => ['scripts' => ['ai-settings.js']] + AiController::panel($this->container),
            'access' => McpSettingsController::panel($this->container),
            'search' => ['llms' => [
                // llms.txt (D-151): on unless switched off, and whether the owner has one of their own.
                'on' => LlmsTxt::on($this->db()),
                'ownersOwn' => LlmsTxt::ownersOwn($this->publicPath()),
                'exists' => is_file($this->publicPath() . '/llms.txt'),
            ]],
            default => throw new \LogicException('No AI tab ' . $sub . '; the route admits only the tabs here.'),
        };

        return $this->render('ai', ['aiTab' => $sub] + $data);
    }

    /**
     * The screen: its tabs down the left, the one asked for beside them.
     *
     * @param array<string, mixed> $data
     */
    private function render(string $tab, array $data, int $status = 200): Response
    {
        $styles = is_array($data['styles'] ?? null) ? $data['styles'] : [];
        $scripts = is_array($data['scripts'] ?? null) ? $data['scripts'] : [];

        return AdminView::render($this->container, __DIR__ . '/views', 'settings', [
            'title' => t('settings.title'),
            'nav' => 'settings',
            'tab' => $tab,
            'styles' => [...$styles, 'admin-settings.css'],
            'scripts' => $scripts,
        ] + $data, $status);
    }

    /**
     * Whether two-step login is on for the admin looking at the screen (D-050).
     *
     * @return array{on: bool, codesLeft: int}
     */
    private function twoStep(): array
    {
        $twoFactor = new TwoFactor($this->db(), (string) $this->container->get('config')->get('app.key'));
        $id = (int) $this->container->get('session')->get('admin_id');

        return ['on' => $twoFactor->enabled($id), 'codesLeft' => $twoFactor->codesLeft($id)];
    }

    /** When the site settings were last saved, from the activity log (D-052); null if never. */
    private function lastSaved(): ?string
    {
        $row = $this->db()->one("SELECT occurred_at FROM activity WHERE kind = 'settings' AND action = 'saved' ORDER BY occurred_at DESC LIMIT 1");

        return $row === null ? null : Dates::local((string) $row['occurred_at'], Dates::zone($this->db()));
    }

    private function publicPath(): string
    {
        return (string) (($this->container->get('config')->get('app', []))['public_path'] ?? '');
    }

    private function db(): Db
    {
        return $this->container->get('db');
    }
}
