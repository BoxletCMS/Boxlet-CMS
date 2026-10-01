<?php

use App\Core\Blocks;
use App\Core\Config;
use App\Core\Container;
use App\Core\Db;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Modules\Admin\ActivityController;
use App\Modules\Admin\ThemeController;
use App\Modules\Admin\DashboardController;
use App\Modules\Admin\RequireAdmin;
use App\Modules\Admin\SearchController;
use App\Modules\Auth\AccountController;
use App\Modules\Auth\AuthController;
use App\Modules\Auth\ForgotController;
use App\Modules\Auth\TwoFactorController;
use App\Modules\Appearance\AppearanceController;
use App\Modules\Appearance\AppearancePreview;
use App\Modules\Backup\Backup;
use App\Modules\Backup\BackupsController;
use App\Modules\Backup\Restore;
use App\Modules\Design\Design;
use App\Modules\Forms\FormsController;
use App\Modules\Forms\FormSubmitController;
use App\Modules\Forms\MessagesController;
use App\Modules\Languages\LanguagesController;
use App\Modules\Mailer\MailController;
use App\Modules\Mailer\MailSettings;
use App\Modules\Media\MediaController;
use App\Modules\Media\MediaCropController;
use App\Modules\Media\MediaPickController;
use App\Modules\Media\EmbedPoster;
use App\Modules\Media\MediaEncoder;
use App\Modules\Media\MediaItemController;
use App\Modules\Media\MediaLibrary;
use App\Modules\Media\MediaRemake;
use App\Modules\Media\MediaRemakeController;
use App\Modules\Media\MediaUpload;
use App\Modules\Media\MediaVariants;
use App\Modules\Media\MediaWriter;
use App\Modules\Menus\MenusController;
use App\Modules\Pages\PageBlockController;
use App\Modules\Pages\PageBuilderController;
use App\Modules\Pages\PageController;
use App\Modules\Pages\PageEditorController;
use App\Modules\Pages\PagePaths;
use App\Modules\Pages\PagesController;
use App\Modules\Pages\Slug;
use App\Modules\Pages\TranslationController;
use App\Modules\Redirects\RedirectsController;
use App\Modules\Settings\CacheController;
use App\Modules\Settings\LogoController;
use App\Modules\Settings\SettingsController;
use App\Modules\Stats\StatsController;
use App\Modules\Stats\StatsDataController;
use App\Modules\Stats\StatsSettingsController;
use App\Modules\Update\Maintenance;
use App\Modules\Update\MaintenanceController;
use App\Modules\Update\Update;
use App\Modules\Update\Releases;
use App\Modules\Update\UpdateController;
use App\Modules\Update\UpdatesController;
use App\Modules\Update\Upgrade;
use App\Support\PageCache;
use App\Support\Url;
use App\Support\Version;

/**
 * Builds the container for the current request and registers routes. Expects
 * vendor/autoload.php and .env to be loaded already. Shared by public/index.php and
 * tests/support.php, so tests dispatch exactly as production does.
 */

$root = dirname(__DIR__);
$config = new Config($root . '/config');
$storage = (string) $config->get('app.storage_path');
$cache = (string) $config->get('app.cache_path');

// Loaded eagerly: a malformed block definition must fail at boot, not at render.
$blocks = Blocks::discover($root . '/app/Blocks');

// The site's header and footer: the SAME machine over a different directory (PLAN.md
// D-030), so they inherit the design tokens and the section style layers. Two registries
// rather than one directory with a scope flag, because the page editor's library IS
// $blocks — so a header cannot be offered as page content by construction, rather than by
// a check somebody has to remember. Eager for the same reason as above.
$chrome = Blocks::discover($root . '/app/Chrome');

$request = Request::fromGlobals();
Url::configure($request->basePath, '');
Url::usePublicPath($root . '/public');
// Where visitors' pages are kept (D-053): beside the compiled stylesheet. public/index.php
// sets the same before it boots, to answer from it; this is for emptying and counting.
PageCache::use($cache);

$container = new Container();
$container->set('config', fn () => $config);
$container->set('request', fn () => $request);
$container->set('blocks', fn () => $blocks);
$container->set('chrome', fn () => $chrome);
$container->set('installed', fn () => is_file($storage . '/install.lock'));
$container->set('db', fn () => Db::fromConfig($config->get('database', [])));
$container->set('session', fn () => Session::start($storage . '/sessions', $request->https));
$container->set('locales', fn (Container $c) => $c->get('db')->all(
    'SELECT code, label, is_primary FROM locales WHERE enabled = 1 ORDER BY sort, code'
));
// Updating an existing install (D-019). The SQLite path is passed in rather than read
// back out of the connection: only MySQL sites have nothing to copy.
// Fetched as ['path'] rather than by the dotted key 'database.path': a guard scrapes
// this file for ->get('...') and ->post('...') and rejects any that ends in a file
// extension, because managed nginx answers such a URL from disk and never passes the
// miss to PHP. The dotted key is not a route, but it is indistinguishable from one here.
$databasePath = (string) (($config->get('database', []))['path'] ?? '');
// Maintenance mode (D-021): a file, not a settings row, so it still works when the
// database is unavailable or mid-update.
$container->set('maintenance', fn () => new Maintenance($storage));
// How the site sends mail (D-045), built from its settings when first asked for. Tests
// replace it with one that keeps what it is given.
// The key is fetched as ['key'] rather than by the dotted 'app.key', for the reason
// $databasePath gives above: the route guard reads any get('...') ending in an extension.
$container->set('mail_transport', fn (Container $c) => MailSettings::transport($c->get('db'), (string) (($c->get('config')->get('app', []))['key'] ?? '')));
// Media (SPEC §5.5). The encoder probes what this server can actually write, so a host
// without a WebP delegate refuses clearly instead of writing files nobody can open.
$container->set('media_encoder', fn () => new MediaEncoder());
$container->set('media_writer', fn (Container $c) => new MediaWriter($c->get('media_encoder')));
$container->set('media_upload', fn (Container $c) => new MediaUpload($c->get('db'), $storage, $c->get('media_encoder')));
// A video's cover picture, fetched once from YouTube or Vimeo by the server (D-147).
$container->set('embed_poster', fn () => new EmbedPoster());
// Backups and restoring one (D-139): the site's own folders, and the key that seals its
// secrets, which a backup records a print of. Read as ['key'] and so on rather than by
// dotted keys, for the route guard's reason above.
$app = (array) $config->get('app', []);
$container->set('backups_path', fn () => $storage . '/backups');
$container->set('backup', fn (Container $c) => new Backup(
    $c->get('db'),
    $root,
    $storage . '/backups',
    $storage . '/uploads',
    (string) ($app['public_path'] ?? '') . '/m',
    (string) ($app['env_path'] ?? ''),
    (string) ($app['key'] ?? ''),
));
$container->set('restore', fn (Container $c) => new Restore(
    $c->get('db'),
    $root,
    $storage . '/backups',
    $storage . '/uploads',
    (string) ($app['public_path'] ?? '') . '/m',
    (string) ($app['env_path'] ?? ''),
    (string) ($app['key'] ?? ''),
    $storage,
    $cache,
    (string) ($app['public_path'] ?? ''),
));
// Updating to a new version (D-140): which version this is, where releases come from, and
// the update itself, which puts the new code where the running code is.
$container->set('version', fn () => Version::current($root));
$container->set('releases', fn () => new Releases());
$container->set('upgrade', fn (Container $c) => new Upgrade(
    $c->get('db'),
    $root,
    $storage,
    $cache,
    (string) ($app['public_path'] ?? ''),
));
// Making every picture's sizes again, step by step (D-048).
$container->set('media_remake', fn (Container $c) => new MediaRemake(
    $c->get('db'),
    $c->get('media_encoder'),
    $c->get('media_writer'),
    $c->get('media_variants'),
    $storage,
    $root . '/public',
));
$container->set('media_variants', fn (Container $c) => new MediaVariants(
    $c->get('db'),
    $c->get('media_encoder'),
    $c->get('media_writer'),
    $storage,
    $root . '/public',
));
// The library needs the block registry as well as the database: which fields can hold a
// picture is declared by the blocks, so a block added later is covered without editing it.
$container->set('media_library', fn (Container $c) => new MediaLibrary(
    $c->get('db'),
    $c->get('blocks'),
    $storage,
    $root . '/public',
));
$container->set('update', fn (Container $c) => new Update(
    $c->get('db'),
    $root . '/migrations',
    $storage,
    $c->get('db')->driver === 'sqlite' ? $databasePath : null,
));

$container->set('router', function (Container $c) use ($request, $cache): Router {
    $locales = $c->get('locales');
    $primary = '';
    foreach ($locales as $locale) {
        if ((int) $locale['is_primary'] === 1) {
            $primary = (string) $locale['code'];
        }
    }
    $origin = Url::origin($request->https, (string) ($_SERVER['SERVER_NAME'] ?? 'localhost'), (int) ($_SERVER['SERVER_PORT'] ?? 0));
    Url::configure($request->basePath, $primary, $origin);
    // A page's address is its slug under its parents' (D-129), worked out on first use.
    Url::usePaths(PagePaths::resolver(static fn () => $c->get('db')));
    // The compiled design stylesheet; its hashed name changes whenever the design is saved.
    Url::useStylesheet(Url::asset('cache/' . Design::stylesheet($c->get('db'), $cache)));
    $router = new Router($c, array_column($locales, 'code'), $primary);

    // Admin routes never carry a locale prefix (SPEC §5.1). They are registered before
    // the page route, whose variable pattern would otherwise shadow them.
    $requireAdmin = [[RequireAdmin::class, 'handle']];
    $router->get('/admin/login', [AuthController::class, 'showLogin']);
    // A forgotten password (D-132): a link by email, good for an hour and once.
    $router->get('/admin/forgot', [ForgotController::class, 'show']);
    $router->post('/admin/forgot', [ForgotController::class, 'send']);
    $router->get('/admin/reset/{token:[0-9a-f]{64}}', [ForgotController::class, 'showReset']);
    $router->post('/admin/reset/{token:[0-9a-f]{64}}', [ForgotController::class, 'reset']);
    $router->post('/admin/login', [AuthController::class, 'login']);
    // The second step of logging in, when two-step login is on (D-050).
    $router->get('/admin/login/code', [AuthController::class, 'showCode']);
    $router->post('/admin/login/code', [AuthController::class, 'code']);
    $router->post('/admin/logout', [AuthController::class, 'logout'], $requireAdmin);
    $router->get('/admin', [DashboardController::class, 'index'], $requireAdmin);
    // The only route that applies a migration, and the only admin screen the update gate
    // lets through while one is pending (D-019).
    $router->get('/admin/update', [UpdateController::class, 'show'], $requireAdmin);
    $router->post('/admin/update', [UpdateController::class, 'run'], $requireAdmin);
    // A new version of Boxlet (D-140): from GitHub when asked, or from a ZIP, after a backup.
    $router->get('/admin/updates', [UpdatesController::class, 'index'], $requireAdmin);
    $router->post('/admin/updates/check', [UpdatesController::class, 'check'], $requireAdmin);
    $router->post('/admin/updates/github', [UpdatesController::class, 'github'], $requireAdmin);
    $router->post('/admin/updates/upload', [UpdatesController::class, 'upload'], $requireAdmin);
    $router->post('/admin/updates/step', [UpdatesController::class, 'step'], $requireAdmin);
    $router->post('/admin/updates/roll-back', [UpdatesController::class, 'rollBack'], $requireAdmin);
    // Maintenance mode (D-021). The GET is where the bar's link goes; it only brings the
    // owner back to the dashboard, because switching off is a POST with a token.
    $router->get('/admin/maintenance', [MaintenanceController::class, 'show'], $requireAdmin);
    $router->post('/admin/maintenance', [MaintenanceController::class, 'toggle'], $requireAdmin);
    $router->get('/admin/pages', [PagesController::class, 'index'], $requireAdmin);
    $router->get('/admin/pages/new', [PagesController::class, 'create'], $requireAdmin);
    $router->post('/admin/pages', [PagesController::class, 'store'], $requireAdmin);
    // The visual editor is the page editor; the plain form stays reachable as the
    // fallback for a browser without JavaScript or a canvas that will not load.
    $router->get('/admin/pages/{id:\d+}', [PageBuilderController::class, 'edit'], $requireAdmin);
    $router->get('/admin/pages/{id:\d+}/canvas', [PageBuilderController::class, 'canvas'], $requireAdmin);
    // One block, drawn for the canvas and the panel. Its own controller since 4c: the
    // builder renders the editor, this answers for a single block and writes nothing.
    $router->post('/admin/pages/{id:\d+}/block', [PageBlockController::class, 'insert'], $requireAdmin);
    // And one band, for the choices a block cannot show: the number of columns is the
    // markup AROUND the blocks, so redrawing one of them can never put a column there.
    $router->post('/admin/pages/{id:\d+}/section', [PageBlockController::class, 'band'], $requireAdmin);
    $router->get('/admin/pages/{id:\d+}/form', [PageEditorController::class, 'edit'], $requireAdmin);
    $router->post('/admin/pages/{id:\d+}', [PageEditorController::class, 'update'], $requireAdmin);
    $router->post('/admin/pages/order', [PagesController::class, 'reorder'], $requireAdmin);
    // Under another page, or out of one (D-133): → and ←, the drag, and Undo.
    $router->post('/admin/pages/{id:\d+}/place', [PagesController::class, 'place'], $requireAdmin);
    $router->post('/admin/pages/{id:\d+}/status', [PagesController::class, 'status'], $requireAdmin);
    // A page's version in another language, made as a draft copy (D-043).
    $router->post('/admin/pages/{id:\d+}/translate', [TranslationController::class, 'create'], $requireAdmin);
    $router->post('/admin/pages/{id:\d+}/blocks/{block:\d+}/current', [TranslationController::class, 'current'], $requireAdmin);
    $router->post('/admin/pages/{id:\d+}/delete', [PagesController::class, 'delete'], $requireAdmin);

    // Pictures (SPEC §5.5). The generated variants live under /m/ and are served from
    // disk by the web server; no route here ever answers for one.
    $router->get('/admin/media', [MediaController::class, 'index'], $requireAdmin);
    $router->post('/admin/media', [MediaController::class, 'store'], $requireAdmin);
    // The media browser a picture field opens (D-145): a page of cards, and an upload from
    // inside the editor, cropped on the way in if asked.
    $router->get('/admin/media/pick', [MediaPickController::class, 'index'], $requireAdmin);
    $router->post('/admin/media/pick', [MediaPickController::class, 'store'], $requireAdmin);
    $router->post('/admin/media/pick/{id:\d+}/finish', [MediaPickController::class, 'finish'], $requireAdmin);
    // An Embed block's cover, taken from the video it shows (D-147).
    $router->post('/admin/media/pick/poster', [MediaPickController::class, 'poster'], $requireAdmin);
    // Making every picture's sizes again, a step per request (D-048).
    $router->post('/admin/media/remake', [MediaRemakeController::class, 'start'], $requireAdmin);
    $router->post('/admin/media/remake/step', [MediaRemakeController::class, 'step'], $requireAdmin);
    $router->get('/admin/media/{id:\d+}', [MediaItemController::class, 'show'], $requireAdmin);
    $router->post('/admin/media/{id:\d+}', [MediaItemController::class, 'save'], $requireAdmin);
    $router->post('/admin/media/{id:\d+}/crop', [MediaCropController::class, 'crop'], $requireAdmin);
    $router->post('/admin/media/{id:\d+}/focal', [MediaItemController::class, 'focal'], $requireAdmin);
    $router->post('/admin/media/{id:\d+}/replace', [MediaItemController::class, 'replace'], $requireAdmin);
    $router->post('/admin/media/{id:\d+}/delete', [MediaItemController::class, 'delete'], $requireAdmin);
    $router->post('/admin/media/{id:\d+}/finish', [MediaController::class, 'finish'], $requireAdmin);

    // Menus (D-028, resolving O-7). One ordering route takes both paths, D-011: a drag
    // posts a whole sibling order, a button posts one move.
    $router->get('/admin/menus', [MenusController::class, 'index'], $requireAdmin);
    $router->post('/admin/menus', [MenusController::class, 'store'], $requireAdmin);
    $router->get('/admin/menus/{id:\d+}', [MenusController::class, 'edit'], $requireAdmin);
    $router->post('/admin/menus/{id:\d+}/rename', [MenusController::class, 'rename'], $requireAdmin);
    $router->post('/admin/menus/{id:\d+}/delete', [MenusController::class, 'delete'], $requireAdmin);
    $router->post('/admin/menus/{id:\d+}/items', [MenusController::class, 'addItem'], $requireAdmin);
    $router->post('/admin/menus/{id:\d+}/items/{item:\d+}', [MenusController::class, 'updateItem'], $requireAdmin);
    $router->post('/admin/menus/{id:\d+}/items/{item:\d+}/delete', [MenusController::class, 'deleteItem'], $requireAdmin);
    $router->post('/admin/menus/{id:\d+}/order', [MenusController::class, 'order'], $requireAdmin);

    // Redirects (D-129): the owner's rules for old addresses, and the ones Boxlet kept.
    $router->get('/admin/redirects', [RedirectsController::class, 'index'], $requireAdmin);
    $router->post('/admin/redirects', [RedirectsController::class, 'store'], $requireAdmin);
    $router->post('/admin/redirects/{id:\d+}/delete', [RedirectsController::class, 'delete'], $requireAdmin);

    // Backups (D-139): made and restored in steps, downloaded, deleted. The name is a
    // backup's own (Backups::validName), checked again by everything it reaches.
    $backupName = '{name:\d{4}-\d{2}-\d{2}-\d{6}-(?:manual|update|restore)}';
    $router->get('/admin/backups', [BackupsController::class, 'index'], $requireAdmin);
    $router->post('/admin/backups', [BackupsController::class, 'start'], $requireAdmin);
    $router->post('/admin/backups/step', [BackupsController::class, 'step'], $requireAdmin);
    $router->get('/admin/backups/' . $backupName . '/download', [BackupsController::class, 'download'], $requireAdmin);
    $router->get('/admin/backups/' . $backupName . '/restore', [BackupsController::class, 'confirm'], $requireAdmin);
    $router->post('/admin/backups/' . $backupName . '/restore', [BackupsController::class, 'restore'], $requireAdmin);
    $router->post('/admin/backups/' . $backupName . '/delete', [BackupsController::class, 'delete'], $requireAdmin);

    // Forms (D-046): the list, a new one, and its edit screen, where every button saves.
    $router->get('/admin/forms', [FormsController::class, 'index'], $requireAdmin);
    $router->post('/admin/forms', [FormsController::class, 'store'], $requireAdmin);
    $router->get('/admin/forms/{id:\d+}', [FormsController::class, 'edit'], $requireAdmin);
    $router->post('/admin/forms/{id:\d+}', [FormsController::class, 'update'], $requireAdmin);
    $router->post('/admin/forms/{id:\d+}/delete', [FormsController::class, 'delete'], $requireAdmin);
    $router->get('/admin/forms/{id:\d+}/messages', [MessagesController::class, 'index'], $requireAdmin);
    $router->get('/admin/forms/{id:\d+}/messages/{message:\d+}', [MessagesController::class, 'show'], $requireAdmin);
    $router->post('/admin/forms/{id:\d+}/messages/{message:\d+}/delete', [MessagesController::class, 'delete'], $requireAdmin);
    $router->get('/admin/forms/{id:\d+}/export', [MessagesController::class, 'export'], $requireAdmin);

    // Site settings (D-028): the one screen that edits what the installer wrote, plus the
    // maintenance message and its switch, which moved off the dashboard.
    // Search across the admin (D-052): the screen, and the ⌘K palette's fragment of it.
    $router->get('/admin/search', [SearchController::class, 'index'], $requireAdmin);
    // The activity log in full (D-052); the Overview shows its start.
    $router->get('/admin/activity', [ActivityController::class, 'index'], $requireAdmin);
    // Light, dark or the machine's own (D-054): the switch in the strip posts here.
    $router->post('/admin/theme', [ThemeController::class, 'save'], $requireAdmin);
    $router->get('/admin/settings', [SettingsController::class, 'show'], $requireAdmin);
    $router->post('/admin/settings', [SettingsController::class, 'save'], $requireAdmin);
    $router->post('/admin/settings/maintenance-message', [SettingsController::class, 'saveMessage'], $requireAdmin);
    $router->post('/admin/settings/cache', [CacheController::class, 'save'], $requireAdmin);
    // An SVG logo, cleaned on upload (D-142).
    $router->post('/admin/settings/logo-svg', [LogoController::class, 'save'], $requireAdmin);
    // Mail (D-045): how the site sends, and a test message to prove it does.
    $router->post('/admin/settings/mail', [MailController::class, 'save'], $requireAdmin);
    $router->post('/admin/settings/mail/test', [MailController::class, 'test'], $requireAdmin);
    // Two-step login (D-050): set up, confirm, new recovery codes, off.
    // Your login (D-132): the admin's own email and password, each behind the current one.
    $router->post('/admin/account/email', [AccountController::class, 'email'], $requireAdmin);
    $router->post('/admin/account/password', [AccountController::class, 'password'], $requireAdmin);
    $router->get('/admin/two-step', [TwoFactorController::class, 'setup'], $requireAdmin);
    $router->post('/admin/two-step', [TwoFactorController::class, 'confirm'], $requireAdmin);
    $router->post('/admin/two-step/codes', [TwoFactorController::class, 'renew'], $requireAdmin);
    $router->post('/admin/two-step/off', [TwoFactorController::class, 'off'], $requireAdmin);
    // Visit statistics (D-051): the screen, the Settings panel's settings, and deleting
    // every count.
    $router->get('/admin/statistics', [StatsController::class, 'index'], $requireAdmin);
    // Taking the counts out and putting them back (O-20).
    $router->get('/admin/statistics/export', [StatsDataController::class, 'export'], $requireAdmin);
    $router->post('/admin/settings/statistics', [StatsSettingsController::class, 'save'], $requireAdmin);
    $router->post('/admin/settings/statistics/erase', [StatsSettingsController::class, 'erase'], $requireAdmin);
    $router->post('/admin/settings/statistics/countries', [StatsSettingsController::class, 'geoDownload'], $requireAdmin);
    $router->post('/admin/settings/statistics/countries/upload', [StatsSettingsController::class, 'geoUpload'], $requireAdmin);
    // The city database, which comes down in pieces (D-055): begin, carry on, give up.
    $router->post('/admin/settings/statistics/cities', [StatsSettingsController::class, 'cityStart'], $requireAdmin);
    $router->post('/admin/settings/statistics/cities/step', [StatsSettingsController::class, 'cityStep'], $requireAdmin);
    $router->post('/admin/settings/statistics/cities/cancel', [StatsSettingsController::class, 'cityCancel'], $requireAdmin);
    // The site's languages (D-043), a panel on the Settings screen with its own forms. A
    // code is two letters, the ISO 639-1 list the installer offers.
    $router->post('/admin/languages', [LanguagesController::class, 'add'], $requireAdmin);
    $router->post('/admin/languages/{code:[a-z]{2}}/enabled', [LanguagesController::class, 'enabled'], $requireAdmin);
    $router->post('/admin/languages/{code:[a-z]{2}}/move', [LanguagesController::class, 'move'], $requireAdmin);
    $router->post('/admin/languages/{code:[a-z]{2}}/delete', [LanguagesController::class, 'remove'], $requireAdmin);

    // HOW THE SITE LOOKS, ON ONE SCREEN (D-059): the character, the ten decisions, the
    // header and the footer, with a picture of all of it. Design and Header & footer were
    // one screen cut in half — one had the choices and no picture, the other a picture that
    // deliberately drew no chrome.
    $router->get('/admin/appearance', [AppearanceController::class, 'show'], $requireAdmin);
    $router->post('/admin/appearance', [AppearanceController::class, 'save'], $requireAdmin);
    $router->get('/admin/appearance/preview', [AppearancePreview::class, 'page'], $requireAdmin);
    $router->get('/admin/appearance/stylesheet', [AppearancePreview::class, 'stylesheet'], $requireAdmin);
    $router->get('/admin/appearance/check', [AppearancePreview::class, 'check'], $requireAdmin);
    // The six typefaces, for the cards that choose between them (D-065).
    $router->get('/admin/appearance/typefaces', [AppearancePreview::class, 'typefaces'], $requireAdmin);

    // Pages: the home page of a locale has the empty slug. A page under a parent is
    // addressed by the slugs above it too, /usluge/web-dizajn (D-129); the last one finds it.
    // A visitor sending a form (D-046). Unprefixed: the form knows its own language.
    $router->visitorPost('/form/{id:\d+}', [FormSubmitController::class, 'submit']);
    // The sitemap for a host where public/sitemap.xml cannot be written (D-049).
    // A file from the library, to save (D-126). Registered before the pages, whose nested
    // addresses (D-129) would otherwise be able to claim /download/{id}/{name}; 'download'
    // is a system word no page may take as its slug.
    $router->get('/download/{id:\d+}/{name:[^/]+}', [\App\Modules\Media\DownloadController::class, 'download']);
    $router->get('/sitemap', [PageController::class, 'sitemap']);
    $router->get('/', [PageController::class, 'show']);
    // Never under a system word: /admin/anything that no admin route answers stays the
    // admin's 404, and a POST to it is not turned into "method not allowed" by this route.
    $system = implode('|', array_map(static fn (string $word): string => preg_quote($word, '~'), Slug::SYSTEM));
    $router->get('/{slug:(?!(?:' . $system . ')(?:/|$))[a-z0-9]+(?:-[a-z0-9]+)*(?:/[a-z0-9]+(?:-[a-z0-9]+)*)*}', [PageController::class, 'show']);
    $router->setNotFound([PageController::class, 'notFound']);

    return $router;
});

return $container;
