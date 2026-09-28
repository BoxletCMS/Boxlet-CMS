<?php

use App\Core\ErrorHandler;
use App\Core\Request;
use App\Core\Response;
use App\Core\RewriteCheck;
use App\Modules\Stats\Tracker;
use App\Modules\Update\UpdateGate;
use App\Support\Url;
use Dotenv\Dotenv;

// URL rewriting is required. On Apache without mod_rewrite, public/.htaccess sends
// every 404 here as an ErrorDocument, which Apache marks with REDIRECT_STATUS=404.
// Explain the fix before anything else boots: no autoloader, templates or database.
if (($_SERVER['REDIRECT_STATUS'] ?? '') === '404') {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    // The Apache rules below must match public/.htaccess.
    echo <<<'HTML'
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>URL rewriting is required</title></head>
<body>
<h1>URL rewriting is required</h1>
<p>Boxlet needs the web server to send every request that is not a real file to
<code>index.php</code>. It is not doing that on this server yet.</p>
<p>In every case the document root must be the <code>public/</code> directory.</p>

<h2>Apache</h2>
<p>Enable <code>mod_rewrite</code> (on a shared host, ask your provider) and allow
<code>.htaccess</code> overrides. <code>public/.htaccess</code> already contains:</p>
<pre>&lt;IfModule mod_rewrite.c&gt;
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^ index.php [QSA,L]
&lt;/IfModule&gt;</pre>

<h2>nginx</h2>
<p>Add this to the server block (on a managed host, ask your provider):</p>
<pre>location / {
    try_files $uri $uri/ /index.php?$query_string;
}</pre>
</body>
</html>

HTML;
    exit;
}

$root = dirname(__DIR__);

if (!is_file($root . '/vendor/autoload.php')) {
    http_response_code(500);
    echo 'Dependencies are missing. Run composer install, or upload the release ZIP which includes vendor/.';
    exit;
}

require $root . '/vendor/autoload.php';

Dotenv::createImmutable($root)->safeLoad();
ErrorHandler::register(filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOL));

// The installer probes this before any database exists, so answer it before booting.
if (Request::fromGlobals()->path === RewriteCheck::PROBE_PATH) {
    RewriteCheck::response()->send();
    exit;
}

$container = require $root . '/app/bootstrap.php';

if (!$container->get('installed')) {
    Response::redirect(Url::asset('install.php'))->send();
    exit;
}

// Before the router is built, because building it reads the locales table — and a
// pending migration is exactly the case where reading the database is what fails
// (PLAN.md D-019). Router::dispatch() checks the same gate, so a request that gets
// past here is refused there instead; this is the copy that runs first.
$gate = UpdateGate::check($container, $container->get('request'));
if ($gate !== null) {
    $gate->send();
    exit;
}

// bar() as well as check(): Router::dispatch() appends the maintenance bar too, and if
// only one of the two call sites did it, the tests and the live site would disagree about
// whether the owner can see that their site is hidden.
$request = $container->get('request');
$response = UpdateGate::bar($container, $request, $container->get('router')->dispatch($request));
$response->send();

// Visit statistics (PLAN.md D-051), counted after the page has gone: the connection is
// released first where PHP-FPM can do that, so the visitor never waits for the count, and
// a failure is logged and never shown. wanted() needs no database, so the admin's own
// requests, redirects and errors stop there.
if (Tracker::wanted($request, $response)) {
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    try {
        Tracker::record($container->get('db'), $request, $response, null, (string) $container->get('config')->get('app.storage_path'));
    } catch (Throwable $e) {
        error_log('Statistics: ' . get_class($e) . ': ' . $e->getMessage());
    }
}
