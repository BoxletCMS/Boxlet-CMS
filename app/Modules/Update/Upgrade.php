<?php

namespace App\Modules\Update;

use App\Core\Db;
use App\Core\Migrator;
use App\Modules\Design\Design;
use App\Modules\Pages\Sitemap;
use RuntimeException;
use ZipArchive;

/**
 * Putting a new version in place of the running one (PLAN.md D-140), after the backup the
 * controller has made, in steps:
 *
 *   unpack    the package taken apart beside the site, in storage/update/new/
 *   swap      each folder and file of the code moved aside to storage/update/old/ and the
 *             new one moved into its place: renames, so each is instant and whole
 *   migrate   the new code's migrations, run by the new code in the next request
 *   finish    the design's stylesheet compiled by the new code, the sitemap, maintenance off
 *
 * The old code stays in storage/update/old/ until the next update, which is what rollBack()
 * puts back. The data the migrations changed comes back from the backup, which the controller
 * restores.
 *
 * The state file is read by the new version's copy of this class after the swap, so what it
 * holds is kept plain: a phase, a position and some names.
 */
final class Upgrade
{
    /** The .htaccess files releases shipped before they carried BEGIN/END markers. */
    private const KNOWN_UNMARKED = [
        '640034f1ec232c793a9e561eee9bc8f5d727340a4d57120d2f2f68cdb99952bc', // public/, v0.1.0-preview
        'efbe6399da99be8ff780c5dc669eb34b2cee5828609117370e9102409aa5fd98', // public/, v0.1.0-preview.2
        '315cac908468223c7da8c0db292e66b23e9485fa7bfb2cc907e88143d43ebfb4', // root, v0.1.0-preview.2
    ];

    private const BEGIN = '# BEGIN Boxlet';
    private const END = '# END Boxlet';

    public function __construct(
        private readonly Db $db,
        private readonly string $root,
        private readonly string $storage,
        private readonly string $cache,
        private readonly string $public,
    ) {
    }

    public function dir(): string
    {
        return $this->storage . '/update';
    }

    /**
     * The state of the last update: under way, done, or rolled back. Null when there has
     * been none.
     *
     * @return array<string, mixed>|null
     */
    public function state(): ?array
    {
        $file = $this->dir() . '/state.json';
        $state = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return is_array($state) ? $state : null;
    }

    public function running(): bool
    {
        return in_array($this->state()['phase'] ?? null, ['unpack', 'swap', 'migrate', 'finish'], true);
    }

    /** Begins putting $version in place, from the package already at dir()/package.zip. */
    public function start(string $version, string $from, string $backup): void
    {
        $maintenance = new Maintenance($this->storage);
        $wasOn = $maintenance->isOn();
        if (!$wasOn) {
            $maintenance->turnOn(Maintenance::UPDATE);
        }
        // The last update's old code goes now: one version can be rolled back to, not two.
        self::remove($this->dir() . '/old');
        self::remove($this->dir() . '/new');
        $this->save([
            'phase' => 'unpack',
            'version' => $version,
            'from' => $from,
            'backup' => $backup,
            'maintenance' => $wasOn,
            'entry' => 0,
            'swapped' => [],
            'warnings' => [],
        ]);
    }

    /**
     * @return array{done: bool, phase: string}
     */
    public function step(float $until): array
    {
        $state = $this->state() ?? throw new RuntimeException(t('updates.none'));

        if ($state['phase'] === 'unpack') {
            $state = $this->unpack($state, $until);
            $this->save($state);
        } elseif ($state['phase'] === 'swap') {
            // The last thing this request does with its own code: from here on the files it
            // would load are the new version's.
            $state = $this->swap($state);
            $this->save($state);
        } elseif ($state['phase'] === 'migrate') {
            (new Migrator($this->db, $this->root . '/migrations'))->migrate();
            $state['phase'] = 'finish';
            $this->save($state);
        } elseif ($state['phase'] === 'finish') {
            Design::publish($this->db, $this->cache);
            Sitemap::publish($this->db, $this->public);
            self::remove($this->dir() . '/new');
            if (is_file($this->dir() . '/package.zip')) {
                unlink($this->dir() . '/package.zip');
            }
            if (!$state['maintenance']) {
                (new Maintenance($this->storage))->turnOff(Maintenance::UPDATE);
            }
            $state['phase'] = 'done';
            $this->save($state);
        }

        return ['done' => $state['phase'] === 'done', 'phase' => (string) $state['phase']];
    }

    /**
     * Puts the old code back where the new is. The data comes back from the backup, which
     * the caller restores after this.
     */
    public function rollBack(): void
    {
        $state = $this->state() ?? throw new RuntimeException(t('updates.none'));
        $old = $this->dir() . '/old';
        $trash = $this->dir() . '/rolled-back';
        self::remove($trash);
        foreach (array_reverse($state['swapped']) as $relative) {
            $current = $this->root . '/' . $relative;
            if (!file_exists($old . '/' . $relative)) {
                continue;
            }
            self::move($current, $trash . '/' . $relative);
            self::move($old . '/' . $relative, $current);
        }
        $state['phase'] = 'rolled-back';
        $this->save($state);
        self::reset();
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function unpack(array $state, float $until): array
    {
        $zip = new ZipArchive();
        if ($zip->open($this->dir() . '/package.zip') !== true) {
            throw new RuntimeException(t('updates.not_a_zip'));
        }
        $new = $this->dir() . '/new';
        $first = true;
        try {
            while ($state['entry'] < $zip->numFiles && ($first || microtime(true) < $until)) {
                $first = false;
                $name = (string) $zip->getNameIndex($state['entry']);
                $relative = Package::target($name);
                if ($relative !== null) {
                    $target = $new . '/' . $relative;
                    if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0755, true) && !is_dir(dirname($target))) {
                        throw new RuntimeException(t('updates.cannot_write', ['path' => dirname($target)]));
                    }
                    if (file_put_contents($target, (string) $zip->getFromIndex($state['entry'])) === false) {
                        throw new RuntimeException(t('updates.cannot_write', ['path' => $target]));
                    }
                }
                $state['entry']++;
            }
            if ($state['entry'] >= $zip->numFiles) {
                $state['phase'] = 'swap';
            }
        } finally {
            $zip->close();
        }

        return $state;
    }

    /**
     * Every folder and file at the top of the new code in place of the running one's, and
     * public/'s one by one, since public/ also holds the site's pictures and cache. The two
     * .htaccess files are merged rather than moved: see htaccess().
     *
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function swap(array $state): array
    {
        $new = $this->dir() . '/new';
        $items = [];
        foreach (self::children($new) as $name) {
            // public/ and designs/ child by child: each holds a folder that is the site's own
            // and no release has — public/m and public/cache, designs/custom (D-155) — and a
            // whole-folder swap would carry it off to old/ with the code.
            if ($name === 'public' || $name === 'designs') {
                foreach (self::children($new . '/' . $name) as $child) {
                    $items[] = $name . '/' . $child;
                }
            } else {
                $items[] = $name;
            }
        }
        foreach ($items as $relative) {
            if (in_array($relative, $state['swapped'], true)) {
                continue;
            }
            if (basename($relative) === '.htaccess') {
                $warning = $this->htaccess($relative);
                if ($warning !== null) {
                    $state['warnings'][] = $warning;
                }
            } else {
                $current = $this->root . '/' . $relative;
                // Resumable: a step cut short between the two moves left the old one aside.
                if (file_exists($current)) {
                    self::move($current, $this->dir() . '/old/' . $relative);
                }
                self::move($new . '/' . $relative, $current);
            }
            $state['swapped'][] = $relative;
            $this->save($state);
        }
        $state['phase'] = 'migrate';
        self::reset();

        return $state;
    }

    /**
     * The new rules in place of Boxlet's own block, and whatever the owner (or the host) put
     * around it kept. A file with no block that is not one a release shipped is somebody's
     * own: it is left as it is, the new rules are written beside it, and the owner is told.
     *
     * @return string|null what to tell the owner, or null when it was merged
     */
    private function htaccess(string $relative): ?string
    {
        $current = $this->root . '/' . $relative;
        $incoming = (string) file_get_contents($this->dir() . '/new/' . $relative);
        $kept = $this->dir() . '/old/' . $relative;
        if (!is_dir(dirname($kept))) {
            mkdir(dirname($kept), 0755, true);
        }
        $existing = is_file($current) ? (string) file_get_contents($current) : null;
        if ($existing !== null) {
            file_put_contents($kept, $existing);
        }

        $begin = $existing === null ? false : strpos($existing, self::BEGIN);
        $end = $existing === null ? false : strpos($existing, self::END);
        if ($existing === null || in_array(hash('sha256', $existing), self::KNOWN_UNMARKED, true)) {
            $merged = $incoming;
        } elseif ($begin !== false && $end !== false && $end > $begin) {
            $after = $end + strlen(self::END);
            $merged = substr($existing, 0, $begin) . rtrim($incoming, "\n") . substr($existing, $after);
        } else {
            file_put_contents($current . '.boxlet-new', $incoming);

            return t('updates.htaccess_kept', ['file' => $relative]);
        }
        file_put_contents($current . '.updating', $merged);
        rename($current . '.updating', $current);

        return null;
    }

    /** @return list<string> */
    private static function children(string $directory): array
    {
        return is_dir($directory) ? array_values(array_diff(scandir($directory) ?: [], ['.', '..'])) : [];
    }

    private static function move(string $from, string $to): void
    {
        if (!is_dir(dirname($to)) && !mkdir(dirname($to), 0755, true) && !is_dir(dirname($to))) {
            throw new RuntimeException(t('updates.cannot_write', ['path' => dirname($to)]));
        }
        if (!rename($from, $to)) {
            throw new RuntimeException(t('updates.cannot_move', ['path' => $from]));
        }
    }

    /** Emptied from PHP's cache of compiled files, which would go on running the old code. */
    private static function reset(): void
    {
        clearstatcache();
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
    }

    private static function remove(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            unlink($path);

            return;
        }
        if (!is_dir($path)) {
            return;
        }
        foreach (self::children($path) as $child) {
            self::remove($path . '/' . $child);
        }
        rmdir($path);
    }

    /**
     * @param array<string, mixed> $state
     */
    private function save(array $state): void
    {
        if (!is_dir($this->dir()) && !mkdir($this->dir(), 0770, true) && !is_dir($this->dir())) {
            throw new RuntimeException(t('updates.cannot_write', ['path' => $this->dir()]));
        }
        $file = $this->dir() . '/state.json';
        file_put_contents($file . '.tmp', json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        rename($file . '.tmp', $file);
    }
}
