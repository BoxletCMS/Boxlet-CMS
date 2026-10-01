# Boxlet

**A small, self-hosted CMS for people who build lots of small websites.**

Upload a ZIP, open a page in your browser, answer four questions. Fifteen minutes later you
have a multilingual site that looks designed, not like a template with the logo swapped. No
shell, no Composer, no Node, no build step. An FTP client and a browser are enough.

> **This is a preview.** Boxlet works and installs cleanly, but it hasn't reached v0.1 yet.
> Until then, things may still change between versions in ways that need a fresh install.
> Try it, poke at it, tell us what breaks. Don't bet a client's site on it just yet.

![The Boxlet admin: the overview screen](https://raw.githubusercontent.com/BoxletCMS/boxlet-cms-dev/main/tools/release/readme/overview.png)

## Why another CMS?

You know the drill. A client needs a five-page site. You install WordPress, then spend the
afternoon *removing* things: the comments, the widgets, the emoji script. Then you add
things back as plugins: one for SEO, one for forms, one for image optimisation, one for
security, and one for the cookie banner that all the others made necessary. A year later
half of them want a paid upgrade.

Boxlet does the handful of things a small site needs, does them properly, and does them out
of the box. It doesn't try to do anything else.

What it will never be, on purpose: a blogging platform, a shop, a page builder with
free-form dragging, or a plugin marketplace. There is one administrator and no user
accounts. It is small, it is boring where boring is good, and it spends its effort on the
part that decides whether a site looks good.

## No cookies. No cookie banner.

**Boxlet doesn't set a single cookie for your visitors.** Not on pages, not on the 404, not
when someone sends a form. We checked every one of those in a real browser, and the
visitor's cookie jar stayed empty.

- **Statistics without cookies**, and without a tracking script, as the next section explains.
- **Forms** store a salted hash of the sender's IP address, used for spam limits, and never
  the address itself.
- **Fonts are served from your own server.** No Google Fonts, no CDN, so a visit doesn't
  report anything to anyone.
- **YouTube videos** are embedded through `youtube-nocookie.com`, and a map can be
  OpenStreetMap instead of Google.
- **Almost no JavaScript** on the public site. The only script is a small one that folds the
  menu on a phone, and it loads only when the header has a menu to fold.
- **A suggested privacy-policy paragraph** for the statistics, written from your own
  settings, in every language the site speaks.

So in the usual case you don't need a consent banner at all. The fine print is that Boxlet
takes care of what *Boxlet* does. If you embed a Google Map or a Vimeo video, that provider
sets its own cookies and its rules apply. And we're a CMS, not your lawyer: Boxlet is built
not to collect personal data about visitors, but whether a site complies with GDPR depends
on the whole site, not just its software.

## Statistics on your own server

![Statistics in Boxlet](https://raw.githubusercontent.com/BoxletCMS/boxlet-cms-dev/main/tools/release/readme/statistics.png)

Visits are counted by the site itself, on your server. Nothing is sent anywhere and nothing
is added to the page.

- Visitors and page views by day, with the previous period to compare against, or any range
  you choose.
- **Traffic data:** the pages people read, where they came from, and their devices, browsers
  and operating systems.
- **Location:** countries on a world map, and optionally regions and cities, from a location
  database kept on your server (by DB-IP).
- **How a visitor is counted:** an anonymous key that changes every day, so nobody can be
  followed from one day to the next. The IP address is used for that key and never stored.
- **Left out of the counts:** you, while logged in, and well-known bots.
- **Your data:** you choose how long counts are kept, and Do Not Track can be honoured.
  Everything can be exported as CSV.

Already using another analytics tool? Switch Boxlet's statistics off in Settings.

## Design, without writing CSS

This is the part Boxlet is really about.

- **Five characters.** Editorial, Minimal, Bold, Soft and Brutalist. Each one changes how a
  page is *composed*, not just its colours: reading width, vertical rhythm, section edges,
  and how a hero is arranged.
- **Eight decisions.** Main colour and an optional second one, typeface pairing, type scale,
  spacing, corners, shadows, content width and surface contrast. The whole palette is worked
  out from those.
- **Contrast is enforced.** A colour combination that would make any text hard to read is
  refused, and Boxlet tells you which pair failed.
- **Bounded on purpose.** Sections choose from a few surfaces built from your palette, so
  there's no way to end up with red text on orange. Every combination looks acceptable.
- **Header and footer.** Several arrangements for each, a logo for light and dark
  backgrounds, a real mobile menu, and a boxed or full-width page.
- **Live preview**, with the real header and footer, while you change things.

## Writing pages

- **A visual editor.** You edit the page on a canvas that shows it as visitors will see it,
  add blocks from a library that shows each block as it looks, and drag them to reorder.
- **Fifteen blocks:** hero, text, image and text, picture, gallery, columns, quote, numbers,
  call to action, accordion, logo strip, downloads, form, video or map embed, and divider.
- **Rich text that stays clean.** Pasting from Word leaves no inline styles, font tags or
  leftover classes, and what's saved is checked and cleaned on the server.
- **The page tree.** Drag a page right to put it under another, left to take it out, up or
  down to reorder, and Undo if you change your mind. Its translations follow.
- **No JavaScript? Still works.** Every page can also be edited in a plain form.
- **Nothing is saved until you save.** If you try to leave with unsaved changes, Boxlet asks
  first.

## Pictures and files

- **Upload once.** Boxlet makes every size a page needs, in **AVIF** and **WebP** when your
  server can produce them, with the original format as a fallback for older browsers.
- **Served straight from disk** by the web server, without PHP.
- **Modern markup.** Pictures go out as `<picture>` with `srcset`, width and height always
  set so the page doesn't jump, and lazy loading below the fold.
- **Done for you:** a phone photo's EXIF orientation is corrected, and you can set a focal
  point and a crop.
- **A media library** with search, "used on" for every picture, alt text and captions in
  each language, and replace: every page showing a picture shows the new one, with no page
  edited. A picture that is still in use can't be deleted by mistake.
- **Files as well.** PDFs, documents, spreadsheets and ZIPs sit in the same library,
  each with its own icon. The Downloads block offers them to visitors and counts the
  downloads.

## Fast without trying

On 28 September 2026, Google PageSpeed Insights gave the demo site **100 for performance on
a computer and 98 on a phone**. SEO and best practices scored 100 on both. That was before
Boxlet had a page cache. Now it also keeps each page as a file and hands it to the next
visitor without touching the database, and empties it the moment you change anything.

It gets there by leaving things out:
- **Next to no JavaScript,** so nothing blocks the page (0 ms of blocking time).
- **Pictures in AVIF and WebP** at the size the screen needs.
- **Width and height on every picture,** so nothing jumps while the page loads.
- **Fonts from your own server,** with no third-party requests.

## SEO that's just there

- **Clean addresses.** Subpages nest, as in `/services/web-design`.
- **Address history.** Change a page's address and the old one redirects to the new one
  with a permanent 301, so search engines update their index and old links keep working.
- **Your own redirects**, for addresses from an old site, each with a count of how often it
  was used.
- **In every page's head:** a canonical link, `hreflang` for each translation, and a title,
  description and sharing image of its own.
- **Structured data:** a BreadcrumbList that tells search engines where the page sits.
- **Written for you:** `sitemap.xml` and `robots.txt` stay up to date as pages change.

## Multilingual from day one

- **Adding a language** takes one screen in the admin. Each language gets its own addresses
  (`/`, `/hr/`, `/de/`…).
- **Translations are linked.** Each page knows its translations, so the language switcher and
  `hreflang` come for free.
- **Built-in words** such as form labels, buttons and "page not found" are already translated
  into English, German, Spanish, French, Croatian, Italian and Slovenian.

## Forms and email

- **A form builder** with text, email, phone, long text, choice and checkbox fields.
- **Messages are kept in the admin**, and emailed to you as they arrive, with an optional
  automatic reply to the sender.
- **Spam protection with no CAPTCHA:** a hidden field, a timing check and a per-sender limit.
- **Email through SMTP or [Resend](https://resend.com)**, whose web API still works on hosts
  that block SMTP. A test button tells you it works before a client finds out it doesn't.

## An admin that's nice to be in

- **Its own design,** light or dark. It never takes on the site's colours, so a bad colour
  choice can't lock you out of the screen that fixes it.
- **The overview** shows what changed lately, what needs attention (such as pictures with no
  description) and which pages people read most.
- **⌘K search** across pages, pictures and settings.
- **Two-step login** with any authenticator app, and ten recovery codes. It's optional and
  never forced.
- **Protected login.** Repeated wrong passwords get locked out. A forgotten password can be
  reset by email, or with a file uploaded over FTP when the site can't send mail.
- **Maintenance mode** for working in peace while visitors see a short message.
- **Backups and updates** from the admin, in steps that suit a slow shared host, with a
  backup made before every update and a way back from each.

## Requirements

- PHP 8.1 or newer, with pdo, mbstring, fileinfo, json and session
- MySQL or MariaDB (recommended), or SQLite for a small single site
- Apache with `mod_rewrite` (which is what cPanel runs), or nginx
- Imagick for AVIF. Without it Boxlet uses GD and serves WebP.

That's ordinary shared hosting. The installer checks all of it, and names anything that's
missing in plain words.

## Installing

Unpack the ZIP. Inside is a `boxlet/` folder, and what goes on your server is **what's
inside it**. Where it goes depends on your host.

**On cPanel, or any host where your domain always serves from `public_html`:**

1. Upload everything inside `boxlet/` straight into `public_html`.
2. There's no step two. The `.htaccess` file that comes with Boxlet serves the site from its
   `public/` folder and keeps everything else out of reach. Addresses stay clean: `/about`,
   never `/public/about`.

If `public_html` already has a `.htaccess` file (cPanel sometimes writes your PHP version
into one), don't overwrite it. Put Boxlet's lines below the ones already there. Boxlet's
rules sit between `# BEGIN Boxlet` and `# END Boxlet`, and an update replaces only those,
so your own lines stay.

**If your host lets you choose the folder a domain points at:** upload everything inside
`boxlet/` to a folder of your choice, and point the domain at the `public/` folder inside
it. This is the tidiest setup: the rest of Boxlet then sits outside the web entirely.

**Then, in both cases:**

1. For MySQL, create an empty database. cPanel often creates them in `latin1`, and that's fine:
   the installer switches an empty database to `utf8mb4` by itself.
2. Open `https://your-site/install.php` and follow the four steps.

**Checked, not assumed.** Before it asks you for anything, the installer checks that
nothing beside `public/` can be opened in a browser. That includes the settings file with
your database password. If the server ignores the `.htaccess` file, the installer says so
and stops.

**Proving you own the server.** The installer writes a token to
`storage/install-token.txt` and asks you to paste it in. Open the file over FTP or in your
host's file manager.

**The primary language can't be changed later**, so choose it with care.

If you like, the installer adds a demo site, so you can try the design on real pages
straight away.

When it's done, the installer deletes itself. If it can't, it tells you, and you delete
`public/install.php` by hand. Then log in at `/admin`.

## Updating

Open **Updates** in the admin.

- **Look for a newer version.** Boxlet asks GitHub for its releases, and only when you
  press the button. It downloads the one you pick and checks the file against the
  checksum GitHub publishes.
- **Or upload the release ZIP yourself**, on a server that cannot reach GitHub.

Before anything changes, Boxlet makes a backup of the whole site. Visitors see a short
"being updated" page while it runs. Your pictures, settings and uploads
stay where they are, and so do your own lines in `.htaccess`.

If something is wrong afterwards, **Roll back** puts the previous version back, together
with the site as it was before the update.

## Backups

**Backups** in the admin makes a backup of the whole site in one ZIP: pages, settings,
messages, every uploaded file and every picture size. From the same screen you can download
a backup, or restore one with a single button. A backup of the site as it is is made before
any restore, so a restore can be undone too.

Backups are kept in `storage/backups`, on the same server. Download one now and then, and
keep it somewhere else.

## Locked out?

Both ways back in need only FTP or your host's file manager.

- **Forgot your password?** Use the link on the login page, which emails you a one-hour link
  when the site can send mail. If it can't, upload a file named `storage/reset-password`
  whose only line is the new password (12 characters or more). The next visit to the login
  page sets it and deletes the file.
- **Lost the phone with your two-step login?** Upload an empty file named
  `storage/disable-2fa`. Your next login switches two-step login off and deletes the file.

## For developers

The source, the tests and the issue tracker live in
[BoxletCMS/boxlet-cms-dev](https://github.com/BoxletCMS/boxlet-cms-dev). This repository
holds only the finished install code, written by each release.

## License

MIT. See [LICENSE](LICENSE).
