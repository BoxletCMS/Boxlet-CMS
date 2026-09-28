# Boxlet

A small self-hosted PHP CMS for people who build many small sites.

## Requirements

- PHP 8.1 or newer
- Extensions: pdo, mbstring, fileinfo, json, session, and pdo_mysql or pdo_sqlite
- MySQL/MariaDB with a `utf8mb4` database (recommended), or SQLite for small
  single-site installs
- URL rewriting: Apache `mod_rewrite` or nginx `try_files` (required, see Deployment)
- Composer (development only; release ZIPs ship with `vendor/`)

## Development server

```sh
composer install
PHP_CLI_SERVER_WORKERS=4 php -S localhost:8000 -t public
```

Then open <http://localhost:8000/install.php>. `PHP_CLI_SERVER_WORKERS` matters: the
installer checks URL rewriting by requesting the site itself, which a single-worker dev
server cannot answer while it is busy with the installer. After installing, add
`APP_DEBUG="true"` to `.env` for readable error traces.

## Installation

1. Upload the files and point the document root at `public/` (see Deployment).
2. For MySQL, create an empty database with `utf8mb4` as its character set.
3. Open `https://your-site/install.php` and follow the steps:
   - **Requirements.** Anything required blocks installation. The page asks for the
     install token, which the installer has just written to
     `storage/install-token.txt`; open that file via FTP or your host's file manager
     and paste its contents. This proves you control the server.
   - **Database.** MySQL (preselected) or SQLite. Connection problems are named.
   - **Admin account.** Email and a password of at least 12 characters.
   - **Site.** Name, time zone and the primary language. **The primary language
     cannot be changed later.**
4. The installer writes `.env` and `storage/install.lock`, then deletes itself. If it
   cannot, delete `public/install.php` by hand. Log in at `/admin`.

To reinstall, delete `storage/install.lock` and `.env`, and start from an empty
database.

## Locked out

Both ways back in work with FTP or your host's file manager alone, no shell needed.

- **A forgotten password.** "Forgot your password?" on the login page emails a link that
  works once, for an hour, when the site can send mail (Settings → Email). Without mail,
  put a file named `storage/reset-password` on the server holding the new password as its
  only line, at least 12 characters. The next visit to the login page sets it and deletes
  the file. If Boxlet cannot delete the file it does not use it.
- **A lost phone, with two-step login on.** Put an empty file named `storage/disable-2fa`
  on the server. The next login switches two-step login off and deletes the file.

## Pages

Log in at `/admin` and open **Pages**. A new page can start from a template, which
pre-fills its blocks. Edit the blocks, reorder them (drag, or Move up / Move down),
and press **Save page**; nothing is saved until you do, and leaving with unsaved
changes asks first. Editing and saving also work with JavaScript turned off.

A page is a draft until published; visitors get a 404 for drafts. The page with an
empty address is the home page of its language: `/` for the primary language, `/hr/`
for Croatian. Addresses cannot be language codes such as `de`, even for languages
that are not enabled, nor paths Boxlet uses itself such as `admin`.

### Design

**Design** in the admin sets how the whole site looks, without writing CSS:

- **Characters.** Editorial, Minimal, Bold, Soft and Brutalist each set every decision
  at once, and each composes a page differently: reading measure, vertical rhythm,
  alignment, section edges and how a hero is arranged. Using one fills in the form; the
  site changes when you press Save design. On a site that already has pages you choose
  explicitly between saving the design alone and also resetting every section's style.
- **Eight decisions.** Main colour (and an optional second), typeface pairing, type
  scale, spacing, corners, shadows, content width and surface contrast. Everything else
  (the palette, sizes, spacing scale) is derived and shown, not edited.
- **Contrast is enforced.** A colour that would make any text unreadable (below WCAG AA)
  is refused, with the failing pair named next to the decision that caused it.
- **Live preview** while you change values, when JavaScript is on. Saving works without.

Each block in the page editor also has a **Section style** (surface, rhythm, width,
alignment, top edge) and, where the block offers several, a **Layout**.

The compiled stylesheet is `public/cache/tokens.{hash}.css`; the name changes on every
save, so visitors never see a stale design. Fonts are served from the site itself
(`public/assets/fonts`, SIL Open Font License), never from Google Fonts.

The admin has a fixed design system of its own and never renders with the site's tokens,
so the tool you judge a design with does not change as you change the design. The only
place the site's own design appears in the admin is the preview pane.

### Vendored front-end assets

Boxlet has no build step, so the few front-end libraries it uses are committed to the
repository and loaded with a plain script tag. Each must be permissively licensed,
dependency-free and a single file.

They live in `public/assets/vendor/`, and each file records its version and source URL in
its own header. A vendored asset is added only when it earns its place, one at a time.
To update one, download the new pinned release over it and change both.

One of them, the TipTap editor bundle, is not published as a single file, so it is built
once by a maintainer from the recipe in `tools/tiptap/` and committed like the rest. That
build happens outside the project and never on your server: installing Boxlet still builds
nothing, and needs no Node and no npm.

### Demo site

The installer can add a demo site: four pages that use every block and section style.
On an installed site without pages, add it from the command line:

```sh
php migrations/seed.php
```

### max_input_vars

The editor sends a whole page as one form, and PHP silently drops fields beyond its
`max_input_vars` setting (default 1000, roughly a hundred blocks). Boxlet detects
this and refuses the save with a message rather than saving a page with content
missing. The installer shows the current value. To raise it, set in `php.ini`,
`.user.ini` or your hosting panel:

```ini
max_input_vars = 3000
```

On Apache with mod_php, `php_value max_input_vars 3000` in `.htaccess` also works.

### Upload size

Pictures arrive as one request, and PHP discards anything larger than its limits
without reporting an error — the same silent failure as `max_input_vars`. A photograph
from a modern phone is easily 8–12 MB, so the defaults on shared hosting (often 2M)
drop ordinary files.

Two settings, and both matter:

- `upload_max_filesize` — the largest single file.
- `post_max_size` — the largest request. It has to be **at least as large** as
  `upload_max_filesize`, because the file arrives inside the request. Raising only
  the first achieves nothing.

The installer reports both, on the requirements step, as
`Uploads: … per file, … per request` filled in with what your server is set to. It
does not block on them. Set them in `php.ini`, `.user.ini` or your hosting panel:

```ini
upload_max_filesize = 16M
post_max_size = 16M
```

On Apache with mod_php, `php_value upload_max_filesize 16M` in `.htaccess` also works.
On PHP-FPM, `.user.ini` is read per directory; a pool configuration uses
`php_admin_value[upload_max_filesize] = 16M`, which `.user.ini` cannot override.

**Nginx has its own limit.** `client_max_body_size` defaults to 1M and rejects the
request with a 413 before PHP ever sees it, so the browser shows nginx's error page
rather than anything from Boxlet. Raise it alongside the PHP settings, in the `http`,
`server` or `location` block (see Deployment):

```nginx
client_max_body_size 16M;
```

## Tests

Run `php tests/run.php`. It needs no web server; exits non-zero on failure.

Tests run against SQLite always, and against MySQL when a database that exists only
for tests is configured: copy `.env.test.example` to `.env.test` and fill it in, or
set the same variables in the environment. Every table in that database is dropped
on each run, so never point it at a real site. Without it, MySQL tests are skipped.

Static analysis: `composer install` (includes dev tools), then `vendor/bin/phpstan analyse`.

## Deployment

In every case the document root must point at `public/`. `app/`, `config/`,
`storage/` and `vendor/` must not be reachable from the web.

**URL rewriting is required, not optional.** Every request that is not a real file
must reach `public/index.php`. There is no fallback URL mode. The installer checks
this and refuses to continue without it.

### Apache (shared hosting)

Set the document root to `public/`, enable `mod_rewrite` and allow `.htaccess`
overrides. `public/.htaccess` already contains the rules:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^ index.php [QSA,L]
</IfModule>
```

If `mod_rewrite` is missing, the site shows a page explaining how to enable it
instead of a bare 404.

The `.htaccess` files containing `Require all denied` in `app/`, `config/` and
`storage/` are a safety net for hosts that cannot move the document root. They are
not a substitute for it.

### Nginx

Set the document root to `public/` and route unknown paths to the front controller:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

Nginx ignores `.htaccess` files entirely, so the document root is the only
protection for `app/`, `config/` and `storage/`. On a managed host without access to
the server block, ask the provider to add the `try_files` line.

**Pass PHP only for the two entry points.** A configuration that hands every `.php` path
to PHP-FPM will execute any `.php` file that ever reaches a public directory. Boxlet has
exactly two: `index.php` and `install.php`. Name them rather than matching `\.php$`:

```nginx
location = /index.php {
    include fastcgi_params;
    fastcgi_pass unix:/run/php/php8.1-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}

location = /install.php {
    include fastcgi_params;
    fastcgi_pass unix:/run/php/php8.1-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}

# Everything else that is not a real file goes to the front controller.
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

This is belt and braces rather than the main protection: original uploads are stored
outside the web root in `storage/uploads/`, and only generated image variants are public,
so there is nothing under `public/` for a stray handler to execute. The two rules together
mean a file that should not run cannot run, whichever one you forget.

Uploads also need `client_max_body_size` raised — see **Upload size** above, where the PHP
settings that go with it are covered.

## License

MIT, see [LICENSE](LICENSE).
