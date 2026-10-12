<?php

namespace App\Modules\Stats;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Support\Bytes;
use App\Support\Url;
use DateTimeImmutable;
use RuntimeException;

/**
 * The Statistics panel's actions (PLAN.md D-051): its settings, deleting every count, and
 * the country database. Switching the module off keeps what was counted; only erase
 * deletes it.
 */
final class StatsSettingsController
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function save(Request $request, string $locale, array $params): Response
    {
        $db = $this->container->get('db');
        $retention = (int) $request->input('stats_retention');
        Settings::set($db, 'stats_enabled', $request->input('stats_enabled') === '1');
        Settings::set($db, 'stats_dnt', $request->input('stats_dnt') === '1');
        Settings::set($db, 'stats_missing', $request->input('stats_missing') === '1');
        Settings::set($db, 'stats_group', $request->input('stats_group') === '1');
        $location = $request->input('stats_location');
        Settings::set($db, 'stats_location', in_array($location, Place::LEVELS, true) ? $location : 'country');
        // Checked for emptiness before it is cast, unlike the retention above: 0 is a real
        // answer here ("as long as the counts"), so a form that sent no field at all would
        // otherwise be read as asking for it.
        $cityMonths = $request->input('stats_city_months');
        Settings::set($db, 'stats_city_months', $cityMonths !== '' && in_array((int) $cityMonths, Tracker::CITY_MONTHS, true)
            ? (int) $cityMonths
            : Tracker::settings($db)['cityMonths']);
        /* The floor under the cities (D-109). The same shape as the months above and for the
           same reason: a form that sent no field must not be read as asking for the lowest
           value, which here is the one that names a person. */
        $cityMin = $request->input('stats_city_min');
        Settings::set($db, 'stats_city_min', $cityMin !== '' && in_array((int) $cityMin, PlaceQuery::MIN_CHOICES, true)
            ? (int) $cityMin
            : Tracker::settings($db)['cityMin']);
        // Not stats_*: the addresses that may speak for a visitor are read by the form and
        // login limits too (O-20).
        Settings::set($db, 'trusted_proxies', mb_substr(trim($request->input('trusted_proxies')), 0, 2000));
        Settings::set($db, 'stats_retention', in_array($retention, Tracker::RETENTION, true) ? $retention : Tracker::settings($db)['retention']);

        return $this->back(t('stats.saved'));
    }

    /**
     * @param array<string, string> $params
     */
    public function erase(Request $request, string $locale, array $params): Response
    {
        Tracker::erase($this->container->get('db'));

        return $this->back(t('stats.erased'));
    }

    /**
     * Fetches the country database from DB-IP (D-051): the one request statistics make to
     * anyone, made only when the owner presses the button.
     *
     * @param array<string, string> $params
     */
    /**
     * The country database (D-051), fetched as the city one is since D-237: one piece, the same
     * progress in the panel.
     *
     * @param array<string, string> $params
     */
    public function geoDownload(Request $request, string $locale, array $params): Response
    {
        return $this->begin($request, Geo::SOURCE);
    }

    /**
     * The country database uploaded by hand, for a server that cannot reach DB-IP: the
     * .mmdb file, or the .mmdb.gz DB-IP offers.
     *
     * @param array<string, string> $params
     */
    public function geoUpload(Request $request, string $locale, array $params): Response
    {
        $file = $request->files['geo_file'] ?? null;
        $error = is_array($file) ? (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
        $temporary = is_array($file) ? (string) ($file['tmp_name'] ?? '') : '';
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            return $this->answer($request, [], t('stats.geo_upload_too_big', ['limit' => Bytes::limits()['fileLabel']]), true);
        }
        if ($error !== UPLOAD_ERR_OK || $temporary === '' || !is_uploaded_file($temporary)) {
            return $this->answer($request, [], t('stats.geo_upload_none'), true);
        }
        try {
            Geo::install($this->storage(), $temporary);
        } catch (RuntimeException $e) {
            return $this->answer($request, [], $e->getMessage(), true);
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }

        return $this->answer($request, [], $this->installed());
    }

    /**
     * Begins fetching the city database (D-055). It arrives in pieces, so this button only
     * finds out which month DB-IP has published and how big it is.
     *
     * @param array<string, string> $params
     */
    /** @param array<string, string> $params */
    public function cityStart(Request $request, string $locale, array $params): Response
    {
        return $this->begin($request, Geo::CITY_SOURCE);
    }

    /**
     * The next piece, or the database put in use once the last is in. The panel's script asks
     * this over and over, and shows how far it is; without the script it is a button.
     *
     * @param array<string, string> $params
     */
    public function cityStep(Request $request, string $locale, array $params): Response
    {
        $kind = GeoDownload::kind($this->storage()) ?? 'city';
        try {
            $progress = GeoDownload::step($this->storage(), $this->container->get('geo_fetch'));
        } catch (RuntimeException $e) {
            return $this->answer($request, ['phase' => 'error', 'message' => $e->getMessage()], $e->getMessage(), true);
        }
        if ($progress['finished']) {
            $said = $this->installed();

            return $this->answer($request, ['phase' => 'installed', 'kind' => $kind, 'message' => $said, 'inUse' => $this->inUse()], $said);
        }

        return $this->answer($request, $this->downloading($progress['done'], $progress['total'], $kind), '');
    }

    /** @param array<string, string> $params */
    public function cityCancel(Request $request, string $locale, array $params): Response
    {
        GeoDownload::cancel($this->storage());

        return $this->answer($request, ['phase' => 'idle', 'message' => t('stats.geo_cancelled')], t('stats.geo_cancelled'));
    }

    /** A download begun: the month found, nothing fetched yet beyond a byte. */
    private function begin(Request $request, string $source): Response
    {
        try {
            GeoDownload::start($this->storage(), new DateTimeImmutable(), $source, $this->container->get('geo_fetch'));
        } catch (RuntimeException $e) {
            return $this->answer($request, ['phase' => 'error', 'message' => $e->getMessage()], $e->getMessage(), true);
        }
        $progress = GeoDownload::progress($this->storage()) ?? ['done' => 0, 'total' => 0];

        return $this->answer($request, $this->downloading($progress['done'], $progress['total'], GeoDownload::kind($this->storage()) ?? 'city'), '');
    }

    /**
     * How far a download is, in the words the panel shows.
     *
     * @return array<string, mixed>
     */
    private function downloading(int $done, int $total, string $kind): array
    {
        return [
            'phase' => 'downloading',
            'kind' => $kind,
            'done' => $done,
            'total' => $total,
            // The last piece is also when the file is unpacked and checked: said, so the
            // longest wait is not a bar standing still at the end.
            'last' => $total - $done <= GeoDownload::CHUNK,
            'message' => t($kind === 'city' ? 'stats.geo_downloading' : 'stats.geo_downloading_country', ['done' => Bytes::human($done), 'total' => Bytes::human($total)]),
        ];
    }

    /** What a database put in use means, for the one it is: a city one knows more (D-237). */
    private function installed(): string
    {
        return t((Geo::status($this->storage())['cities'] ?? false) ? 'stats.geo_installed_city' : 'stats.geo_installed');
    }

    /** The panel's sentence for the database in use, as it is drawn. */
    private function inUse(): string
    {
        $geo = Geo::status($this->storage());

        return $geo === null ? t('stats.geo_none') : t('stats.geo_in_use', ['date' => $geo['built']]) . ' ' . t($geo['cities'] ? 'stats.geo_kind_city' : 'stats.geo_kind_country');
    }

    /**
     * THE ANSWER WHERE THE OWNER IS LOOKING (D-237): JSON for the panel's script, which shows
     * it beside the buttons; without the script, the settings again, at the maps' part, with
     * the words there rather than in the bar at the top of a page scrolled away from it.
     *
     * @param array<string, mixed> $json
     */
    private function answer(Request $request, array $json, string $notice, bool $error = false): Response
    {
        if (str_contains((string) $request->header('accept'), 'application/json')) {
            return Response::json($json, $error ? 422 : 200);
        }
        $session = $this->container->get('session');
        if ($notice !== '') {
            $session->set('geo_notice', ['text' => $notice, 'error' => $error]);
        }

        return Response::redirect(Url::admin('settings') . '#geo');
    }

    private function storage(): string
    {
        return (string) $this->container->get('config')->get('app.storage_path');
    }

    private function back(string $message, bool $error = false): Response
    {
        $this->container->get('session')->set('flash', $message);
        if ($error) {
            $this->container->get('session')->set('flash_kind', 'error');
        }

        return Response::redirect(Url::admin('settings') . '#statistics');
    }
}
