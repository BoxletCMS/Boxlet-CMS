# Boxlet CMS

**A small, self-hosted CMS for people who build lots of small websites.**

Upload a ZIP, open a page in your browser, answer four questions. Fifteen minutes later you
have a multilingual site that looks designed, not like a template with the logo swapped. No
shell, no Composer, no Node, no build step. An FTP client and a browser are enough.

**[⬇ Download the latest Boxlet](https://github.com/BoxletCMS/Boxlet-CMS/releases/latest)** ·
[Try the demo](https://demo.boxletcms.org)

> **v0.1 is the first release.** From here on, updates keep your site as it is: no fresh
> install between versions, and each update is taken from the admin.

![The Boxlet CMS page builder: the demo's home page, its hero selected](https://raw.githubusercontent.com/BoxletCMS/boxlet-cms-dev/main/tools/release/readme/builder.jpg)

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
- **Videos and maps load only when pressed.** Until a visitor presses Play, nothing of
  YouTube's, Vimeo's or Google's is loaded, and YouTube then comes from
  `youtube-nocookie.com`. A map can be OpenStreetMap instead, which stores nothing.
- **Almost no JavaScript** on the public site: four small scripts, each loaded only on a page
  that needs it. One folds the menu on a phone, one starts a video or a map when it is
  pressed, one brings a section in as it is scrolled to, and one opens a picture larger,
  where you asked for those.
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

- **Twenty-one design sets.** Thirteen ready from the start (Editorial, Minimal, Bold, Soft,
  Brutalist, Terra, Clinic, Gallery, Launch, Commons, Zine, Couture and Riso) and eight more
  in the library, a click away (Coast, Event, Mono, Playground, Portfolio, Retreat, Terminal
  and Workshop). Each one changes how a page is *composed*, not just its colours: reading
  width, vertical rhythm, section edges, how a hero is arranged and which layout each block
  takes.
- **Then make it yours.** A main colour and an optional second one, 27 self-hosted typefaces
  to pair, the type scale, spacing, corners, shadows, content width and surface contrast. The
  whole palette is worked out from your colours, for a light site or a dark one.
- **Designs travel.** Export a design as a file and import it on another site.
- **Contrast is enforced.** A colour combination that would make any text hard to read is
  refused, and Boxlet tells you which pair failed.
- **Bounded on purpose.** Sections choose from a few surfaces built from your palette, so
  there's no way to end up with red text on orange. Every combination looks acceptable.
- **Header and footer.** Several arrangements for each, a logo for light and dark
  backgrounds, a real mobile menu, and a boxed or full-width page.
- **Live preview**, with the real header and footer, while you change things.

## Writing pages

- **You write on the page itself.** Click a heading and type. Select words for bold, a link,
  a list or the alignment. What you see is what visitors get, on a computer, a tablet or a
  phone, a press away.
- **Sections and columns.** A page is made of sections, and a section holds one to four
  columns: halves, thirds, quarters, two thirds and one, or a sidebar. Each section has a
  surface (plain, tinted, contrast, a picture or a gradient), an edge (a line, a slant or a
  curve), its spacing and height, an optional entrance animation, and can be hidden on a
  phone, a tablet or a computer.
- **Fifteen blocks:** hero, text, image and text, picture, gallery, cards, quote, numbers,
  call to action, questions, logos, downloads, form, video or map, and space. Most have
  several layouts, and each follows the design set until you choose otherwise.
- **The structure beside the page.** Every section and block in a tree: drag a block into
  another section or column, or a section up or down. Save a section as a pattern to use it
  again, or start a page from one of three templates.
- **Drafts that save themselves.** Every change is kept as a draft while you work, with undo
  and redo, and a preview of the draft in its own tab. Publish puts it on the site. The last
  five published versions are kept, and any of them can be restored.
- **Snippets and tags.** Write the opening hours or a phone number once, as a snippet, and
  place it in any text: change it once, and every page has it. `{{year}}` is always this
  year, and the language switcher can stand inside a sentence.
- **Rich text that stays clean.** Pasting from Word leaves no inline styles, font tags or
  leftover classes, and what's saved is checked and cleaned on the server.
- **The page tree.** Drag a page right to put it under another, left to take it out, up or
  down to reorder, and Undo if you change your mind. Its translations follow. Tick several
  pages to delete them at once; the menu items that led to them go too.
- **No JavaScript? Still works.** Every page can also be edited in a plain form.

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
- **Tidy up in one go.** Tick pictures and files to delete them together. Anything a page
  still shows is kept, and Boxlet tells you where it is used.

## Fast without trying

On 12 October 2026, Google PageSpeed Insights gave the demo site **100 for performance on
a computer and 99 on a phone**. Accessibility, best practices and SEO scored 100 on both.
Boxlet keeps each page as a file and hands it to the next visitor without touching the
database, and empties that page cache the moment you change anything.

It gets there by leaving things out, and by keeping what it can:
- **Next to no JavaScript,** so nothing blocks the page (0 ms of blocking time).
- **One stylesheet for the whole site,** next to the one your design compiles to, instead of
  a file per part. On Apache, Boxlet's `.htaccess` also compresses pages, styles and scripts.
- **Kept by the browser.** Styles, scripts, fonts and pictures carry a version in their
  address, so a browser keeps them for a year and a second visit fetches almost nothing.
  When a file changes, its address changes with it.
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
  description and sharing picture of its own (the site's, where it has none).
- **Link previews:** Open Graph and Twitter tags made from the page's title, description,
  address and picture, so a link shared in a chat or on social media shows what it is.
- **Structured data:** a BreadcrumbList that tells search engines where the page sits, and
  on each language's home page the site's name (WebSite).
- **Written for you:** `sitemap.xml` and `robots.txt` stay up to date as pages change.
- **`llms.txt` for AI tools.** A plain list of your published pages and their descriptions,
  in every language, kept up to date with the sitemap. One switch in Settings turns it off.

## Multilingual from day one

- **Adding a language** takes one screen in the admin. Each language gets its own addresses
  (`/`, `/hr/`, `/de/`…).
- **Translations are linked.** Each page knows its translations, so the language switcher and
  `hreflang` come for free.
- **Built-in words** such as form labels, buttons and "page not found" are already translated
  into English, German, Spanish, French, Croatian, Italian and Portuguese.
- **Copying a page into another language** brings its place in that language's menus and its
  forms with it. Tick several pages to copy, publish or unpublish them at once.

## Translating with AI

- **Optional, with your own key.** Paste an Anthropic API key in Settings → AI translation and
  choose a model. The list comes from Anthropic, so new models appear on their own. Nothing is
  sent until a key is saved, and you pay Anthropic only for what you use.
- **One page, or many at once.** Translate a page from its row or from the editor, or tick
  several and use Copy into language with "and translate with AI". Each part goes into the
  page's draft, and nothing is published until you have read it.
- **Everything a page brings with it:** its title, address and description for search engines,
  every block's words, its pictures' descriptions, its forms, and that language's menus and
  header and footer words.
- **Boxlet keeps the structure.** Links to your own pages lead to the right language's pages,
  and menus keep their order and levels. Words you wrote yourself are never replaced.
- **Checked, not trusted.** Markup, `{{tags}}`, addresses and the words you list as never to
  translate stay as they are. An answer that fails a check keeps the original words and says so.

## Forms and email

- **A form builder** with text, email, phone, long text, choice and checkbox fields.
- **Messages are emailed to you** as they arrive, with an optional automatic reply to the
  sender. Keeping them in the admin as well is your choice for each form, and off until you
  turn it on: what a visitor writes is not stored on your server unless you decide it should
  be.
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
- **In your language.** The admin speaks the site's main language, the one chosen when it is
  installed: Croatian today, English otherwise, more to come.
- **Maintenance mode** for working in peace while visitors see a short message of your own,
  in the site's language.
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
straight away: **The Printworks**, an arts centre in an old printing works, in the Couture
design set. Fourteen pages, among them one that shows every block in every layout, with the
home page and Visit in Croatian too, its forms, menus and footer. Its pictures are fetched
from GitHub while it installs, every size already made. They aren't covered by the Boxlet
licence; each one's source and licence is in
[CREDITS.md](https://github.com/BoxletCMS/boxlet-cms-dev/blob/main/demo_images/CREDITS.md).

When you're ready for your own content, clear the demo in two steps: in **Pages** tick every
page and delete them, then in **Media** choose **Unused**, tick everything and delete it. The
demo's forms, footer words and site name are yours to change or remove.

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
