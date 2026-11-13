# Tosmic Events website

PHP 8 + MySQL. Public site (home slideshow, about, packages, gallery, booking) and an admin panel at `/admin`.

## Local (XAMPP)
- Site: http://localhost/tosmicevents/
- Admin: http://localhost/tosmicevents/admin/ (change the starter password under My account)

## New server (cPanel)
1. Upload the folder, create a MySQL database and user.
2. Create `config.local.php` next to `config.php`:
   ```php
   <?php return ['db_name' => '...', 'db_user' => '...', 'db_pass' => '...', 'debug' => false];
   ```
3. Run `php install/setup.php "Your Name" you@example.com "StrongPassword"` (or import `install/schema.sql` in phpMyAdmin and add an admin).
4. Make sure `uploads/` is writable.

Content (slides, packages, gallery, testimonials, phones, socials, about text) is all edited from the admin panel.

## Clean addresses
Pages open without `.php` (`/about`, `/packages`, `/admin/bookings`). `.htaccess` maps them to the PHP files and
301-redirects old `.php` links. It needs Apache `mod_rewrite` with `.htaccess` allowed (standard on cPanel).
In code, keep writing `url('about.php')`: the `url()` helper strips the extension when printing links.

## Security
- **SQL injection:** every query uses PDO prepared statements (real server-side prepares, `ATTR_EMULATE_PREPARES` off).
  The few queries assembled in code only join fixed SQL fragments and integers, never visitor input.
- **XSS:** all output goes through `e()` (htmlspecialchars). A strict Content-Security-Policy only allows our own scripts
  plus one inline line carrying a per-request nonce, so injected `<script>` or `onerror=` cannot run.
- **CSRF:** every form carries a session token checked with `hash_equals`; posts without it get 403. Cookies are `SameSite=Lax`.
- **Sessions:** HttpOnly, Secure on HTTPS, strict mode, cookie-only, new ID on sign-in, 4-hour idle sign-out,
  and changing the password signs out every other device.
- **Brute force / spam:** failed logins are counted in the `login_attempts` table per IP (10 / 15 min) and per email
  (5 / 15 min), so clearing cookies does not help an attacker. Booking (5/hour) and feedback (3/day) are limited per IP in the database.
- **Uploads:** images are re-encoded by GD (strips hidden payloads) with random names; videos are checked by content type.
  `uploads/` serves only image/video files and can never run PHP.
- **Errors:** visitors see a plain error page; details go to `storage/logs/php-errors.log` (blocked from the web).
  `'debug' => 'auto'` in config.php shows errors only on localhost.
- **Blocked from the web:** config files, `includes/`, `install/`, `storage/`, `_` partials, dotfiles, SQL/MD/log/backup files,
  directory listings, and HTTP methods other than GET/POST/HEAD. Live sites are forced onto HTTPS (with HSTS).

### Live server checklist
1. Run `install/migrations/002_client_feedback.sql`, `003_login_attempts.sql`, `004_receipts.sql`, `005_paid_status.sql` and `006_admin_logins.sql` (in that order, after a backup) on an existing database.
2. Change the starter admin password (the dashboard warns until you do).
3. Make sure the domain has an SSL certificate (cPanel AutoSSL) before going live, since HTTP redirects to HTTPS.
4. Ask the host to confirm `TraceEnable Off` and `ServerTokens Prod` (server-level settings `.htaccess` cannot change).
