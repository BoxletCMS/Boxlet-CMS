<?php

namespace App\Modules\Settings;

use App\Core\Container;
use App\Core\Db;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Modules\Admin\Activity;
use App\Modules\Admin\AdminView;
use App\Modules\Auth\TwoFactor;
use App\Modules\Languages\Locales;
use App\Modules\Mailer\MailController;
use App\Modules\Mailer\MailSettings;
use App\Modules\Media\MediaReference;
use App\Modules\Stats\Geo;
use App\Modules\Stats\GeoDownload;
use App\Modules\Stats\PrivacyText;
use App\Modules\Stats\Tracker;
use App\Support\Bytes;
use App\Support\Dates;
use App\Support\Url;
use DateTimeZone;

/**
 * Site settings (PLAN.md D-028): what the installer wrote, edited afterwards, plus the
 * pictures a site falls back to and the maintenance message.
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
        // The logo field shows the one the header draws, which may still be the header's
        // older setting; once saved here, this is the only one (D-038).
        SiteChrome::retireHeaderLogo($db);
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
        $this->container->get('session')->set('flash', t('settings.maintenance_message_saved'));

        return Response::redirect(Url::admin('settings'));
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
     * @param array<string, mixed> $values
     * @param array<string, string> $errors
     */
    private function form(array $values, array $errors, ?string $notice, int $status = 200): Response
    {
        return AdminView::render($this->container, __DIR__ . '/views', 'settings', [
            'title' => t('settings.title'),
            'nav' => 'settings',
            // The picker's own stylesheets and script, the same set the page editor loads.
            'styles' => ['admin-media.css', 'admin-picker.css', 'admin-two-step.css', 'admin-settings.css'],
            'scripts' => ['media-picker.js', 'mail-settings.js', 'settings-nav.js', 'auto-continue.js'],
            'values' => $values,
            'errors' => $errors,
            'notice' => $notice,
            'pictures' => MediaReference::choices($this->db()),
            'timezones' => DateTimeZone::listIdentifiers(),
            'maintenanceOn' => $this->container->get('maintenance')->isOn(),
            'languages' => Locales::all($this->db()),
            'addable' => Locales::addable($this->db()),
            'twoStep' => $this->twoStep(),
            // Your login (D-132): the address the admin logs in with.
            'accountEmail' => (string) ($this->db()->one('SELECT email FROM admin WHERE id = ?', [(int) $this->container->get('session')->get('admin_id')])['email'] ?? ''),
            'lastSaved' => $this->lastSaved(),
            'trustedProxies' => Settings::text($this->db(), 'trusted_proxies'),
            'stats' => $stats = Tracker::settings($this->db()),
            'geo' => $geo = Geo::status($storage = (string) $this->container->get('config')->get('app.storage_path')),
            // A city database that is part-way down (D-055): the panel draws how far it got
            // and a Continue, which a script presses by itself.
            'geoDownload' => GeoDownload::progress($storage),
            'privacy' => PrivacyText::all($stats, $geo !== null),
            'uploadLimit' => Bytes::limits()['fileLabel'],
        ] + $this->mail(), $status);
    }

    /**
     * The Mail panel's values (D-045): what is stored, or what was typed into a refused
     * save, with its errors — shown once. Secrets never reach the screen, only whether
     * one is set.
     *
     * @return array{mail: array<string, string>, mailErrors: array<string, string>, mailSecrets: array{smtp_password: string, resend_key: string}}
     */
    private function mail(): array
    {
        $stored = MailSettings::stored($this->db());
        $appKey = (string) $this->container->get('config')->get('app.key');
        $session = $this->container->get('session');
        $refused = $session->get('mail_form');
        $session->remove('mail_form');
        $old = is_array($refused) && is_array($refused['old'] ?? null) ? $refused['old'] : [];
        $errors = is_array($refused) && is_array($refused['errors'] ?? null) ? $refused['errors'] : [];

        $values = [];
        foreach (MailController::FIELDS as $field) {
            $values[$field] = in_array($field, ['smtp_password', 'resend_key'], true)
                ? ''
                : (is_string($old[$field] ?? null) ? $old[$field] : $stored[$field]);
        }

        return [
            'mail' => $values,
            'mailErrors' => array_filter($errors, 'is_string'),
            // What each saved secret shows in its empty field: a trace, never the secret.
            'mailSecrets' => [
                'smtp_password' => MailSettings::trace($stored['smtp_password'], $appKey, false),
                'resend_key' => MailSettings::trace($stored['resend_key'], $appKey, true),
            ],
        ];
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

    private function db(): Db
    {
        return $this->container->get('db');
    }
}
