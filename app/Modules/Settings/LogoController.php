<?php

namespace App\Modules\Settings;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Admin\Activity;
use App\Support\SvgSanitizer;
use App\Support\Url;
use RuntimeException;

/**
 * An SVG logo uploaded under Branding, or taken away (PLAN.md D-142). The upload is read,
 * cleaned by SvgSanitizer, and only the cleaned document is written; anything the cleaner
 * refuses is refused here with its reason, and nothing is stored.
 */
final class LogoController
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function save(Request $request, string $locale, array $params): Response
    {
        $slot = $request->input('slot');
        $session = $this->container->get('session');
        if (!in_array($slot, LogoSvg::SLOTS, true)) {
            return Response::redirect(Url::admin('settings') . '#branding');
        }
        $db = $this->container->get('db');
        $public = (string) (((array) $this->container->get('config')->get('app', []))['public_path'] ?? '');

        try {
            if ($request->input('action') === 'remove') {
                LogoSvg::remove($db, $public, $slot);
                Activity::record($db, 'settings', 'logo_svg_removed', null, '');
                $session->set('flash', t('svg.removed'));
            } else {
                $file = $request->files['svg'] ?? null;
                if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
                    throw new RuntimeException(t('svg.no_file'));
                }
                if ((int) ($file['size'] ?? 0) > SvgSanitizer::MAX_BYTES) {
                    throw new RuntimeException(t('svg.too_large', ['size' => (string) intdiv(SvgSanitizer::MAX_BYTES, 1024)]));
                }
                $clean = SvgSanitizer::clean((string) file_get_contents((string) $file['tmp_name']));
                LogoSvg::store($db, $public, $slot, $clean['svg'], $clean['width'], $clean['height']);
                Activity::record($db, 'settings', 'logo_svg', null, '');
                $session->set('flash', t('svg.stored'));
            }
            $session->set('flash_kind', 'success');
        } catch (RuntimeException $e) {
            $session->set('flash', $e->getMessage());
            $session->set('flash_kind', 'error');
        }

        return Response::redirect(Url::admin('settings') . '#branding');
    }
}
