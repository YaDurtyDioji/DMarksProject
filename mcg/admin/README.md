# MCG Biokinetics — Admin Portal

A self-contained PHP admin portal for the MCG Biokinetics website. No database or
Composer dependencies are required: content is stored in protected JSON files and
generated pages are written as real `.html` files.

## Requirements

- PHP **8.1+** (8.2/8.3 recommended)
- `session`, `json`, `fileinfo`, `mbstring` (recommended)
- **GD** extension (required to safely re-encode uploaded images)
- Apache with `.htaccess` support, or equivalent Nginx rules (see below)
- A working PHP `mail()` transport, or a configured SMTP relay on the server

## First run

1. Upload the `admin/` folder to the website root (next to `index.html`).
2. Make sure `admin/data/` and `admin/uploads/` are writable by PHP.
3. Open **`/admin/login.php`**.
4. If no admin account exists, the login screen becomes a **Create admin account**
   form. Create the first account immediately.
5. Sign in and open **Settings** to set the sender email, site URL, booking URL and
   social links.

> There is deliberately no default username or password. The setup form disappears
> once the first account exists.

## Pages

| File | Purpose |
| --- | --- |
| `index.php` | Dashboard with counts, latest messages, campaigns and health checks |
| `pages.php` | List and delete managed pages |
| `page-edit.php` | Block editor: headings, paragraphs, images, quotes and lists |
| `preview.php` | Private preview of a page, including drafts |
| `gallery.php` | Photo uploads, captions, categories and viewing order |
| `reviews.php` | Add, edit, approve, order and delete client reviews |
| `messages.php` | Contact inbox with filters, spam controls and message reading |
| `mail.php` | Bulk email composer, live preview, test send and delivery log |
| `subscribers.php` | Mailing list and imports |
| `settings.php` | Identity, links, mail limits and password |
| `api/reviews.php` | Public JSON feed of approved reviews |

### Page generation

A published page is written into the selected concept folder:

- Atelier → `Style1/<slug>.html`
- Emerald → `Style2/<slug>.html`
- Kinetic → `Style3/<slug>.html`

The generated page reuses that concept's header, footer and `style.css`, so it
matches the site design. Existing core pages cannot be overwritten. Drafts are
private until published.

### Gallery publishing

Saving any gallery change automatically regenerates:

- `Style1/Gallery.html`
- `Style2/Gallery.html`
- `Style3/Gallery.html`

Photos are grouped by category, sorted by the order field, and the public pages
include category filters and a lightbox. The seed data points at the existing
practice photographs in `/images`; newly uploaded files go to `admin/uploads/`.

### Reviews

Approved reviews are available at `/admin/api/reviews.php` as JSON, and are also
published as `Style1/Reviews.html`, `Style2/Reviews.html` and `Style3/Reviews.html`
whenever reviews are saved (or via **Publish reviews**).

## Contact form integration

`sendEmail.php` now starts with the shared intake library:

```php
require_once __DIR__ . '/admin/lib/contact-intake.php';
```

The intake handler:

1. Rejects attachments and executable content.
2. Validates the name, email address and message length.
3. Rejects header injection and control characters.
4. Scores spam (links, disposable domains, spam phrases, code-like content).
5. Applies rate limits:
   - 3 messages per 10 minutes per IP
   - 12 messages per 24 hours per IP
   - 80 messages per hour globally
6. Stores the message as sanitised plain text for the admin inbox.
7. Returns `429` when rate limited; the existing forms redirect to `Oops.html`.

Legitimate messages continue to be emailed to the admin. Flagged messages are
stored in the **Spam** tab and are not emailed. The notification email's user
content is escaped, and `Reply-To` is set to the validated sender.

## Virus / malicious-content filtering

This portal is not a full antivirus scanner, but it removes the practical vectors:

- **Uploads:** only JPEG, PNG and WebP are accepted; MIME and image structure are
  verified; the image is decoded and re-encoded through GD as JPEG, which discards
  metadata and embedded payloads. Filenames are randomised. PHP/HTML/script
  execution is denied in `admin/uploads/.htaccess`.
- **No email attachments:** the campaign image is linked by URL, never attached.
- **Email content:** the composer only accepts plain text. It is escaped into the
  HTML template, so scripts/HTML cannot be injected into outbound mail.
- **Contact messages:** stored as plain text with tags and control characters
  stripped; attachments are rejected; header injection is blocked; spam scoring
  and rate limits apply.
- **XSS:** all admin output is escaped with `htmlspecialchars`.

For a high-assurance deployment, add a server-side antivirus scanner (ClamAV) in
front of uploads and use a transactional SMTP provider for outbound mail.

## Bulk email notes

- Sending uses PHP `mail()` in bounded batches. There is a 3-campaigns-per-30-minute
  rate limit and a configurable recipient cap (default 300).
- The campaign image, booking button and configured social buttons are inserted into
  a table-based responsive email template.
- No attachments are supported by design.
- For reliable inbox delivery, configure **SPF**, **DKIM** and **DMARC** and use an
  SMTP relay/provider. For larger volumes, replace `mcg_send_campaign()` in
  `admin/lib/mailer.php` with an API-based mailer.
- The unsubscribe link opens a mailto request; add a proper unsubscribe workflow if
  the list grows beyond a small practice list.

## Security checklist

- Serve the site over **HTTPS**.
- Keep `admin/data/` outside backups that are publicly accessible; it contains
  messages and password hashes.
- Back up `admin/data/*.json` regularly (not committed by `.gitignore`).
- Set strong, unique admin passwords; rotate them from **Settings**.
- Restrict `/admin/` by IP or HTTP auth if the host allows it.
- The login form rate-limits failed attempts (6 per 15 minutes per IP/username).
- Sessions expire after two hours of inactivity and use HttpOnly/SameSite cookies.
- All POST actions use CSRF tokens.

### Nginx

Apache `.htaccess` files are included, but on Nginx add the equivalent:

```nginx
location ^~ /admin/data/ {
    deny all;
    return 403;
}

location ^~ /admin/uploads/ {
    location ~* \.(php|phtml|phar|pl|py|cgi|sh|exe|html?|js)$ {
        deny all;
    }
}
```

## File layout

```
admin/
├── assets/
│   ├── admin.css
│   └── admin.js
├── api/
│   └── reviews.php
├── data/                 # protected JSON store + secret
│   ├── .htaccess
│   ├── .gitignore
│   └── index.html
├── lib/
│   ├── auth.php
│   ├── bootstrap.php
│   ├── contact-intake.php
│   ├── core.php
│   ├── gallery-render.php
│   ├── layout.php
│   ├── mailer.php
│   ├── page-render.php
│   └── reviews-render.php
├── uploads/              # re-encoded images only
│   ├── .htaccess
│   ├── .gitignore
│   └── index.html
├── index.php
├── login.php
├── logout.php
├── mail.php
├── messages.php
├── page-edit.php
├── pages.php
├── preview.php
├── reviews.php
├── settings.php
└── subscribers.php
```

## Backup

Back up `admin/data/` and `admin/uploads/`. The generated HTML pages can always be
rebuilt from the JSON data by re-saving a page or clicking **Republish gallery**.

## Troubleshooting

- **Blank page:** check the PHP error log; `display_errors` is intentionally off.
- **Uploads rejected:** confirm GD is installed (`php -m | grep gd`).
- **No mail:** check `mail()` is enabled and the domain has SPF/DKIM. Use
  **Send test to me** in Bulk email.
- **Gallery changes not public:** check the concept folders are writable and click
  **Republish gallery**.
