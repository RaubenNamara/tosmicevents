<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/components.php';

$packages = q('SELECT id, name, price, events FROM packages WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll();
$eventChoices = [];
foreach ($packages as $p) $eventChoices = array_merge($eventChoices, lines($p['events']));
$pkgIds = array_map('intval', array_column($packages, 'id'));

$errors = [];
$old = [
    'package' => (string) ($_GET['package'] ?? ''),
    'full_name' => '', 'phone' => '', 'email' => '', 'event_type' => '', 'event_date' => '',
    'start_time' => '', 'duration_hours' => '', 'venue' => '', 'guests' => '', 'message' => '',
];

if (is_post()) {
    csrf_check();
    foreach ($old as $k => $_) $old[$k] = post($k);

    // spam guards: hidden honeypot field, too-fast submissions, and a per-session limit
    $tooFast = (time() - (int) ($_SESSION['booking_form_at'] ?? 0)) < 3;
    $recent = array_filter($_SESSION['booking_times'] ?? [], fn($t) => $t > time() - 3600);
    if (post('website_url') !== '' || $tooFast) {
        $errors['form'] = 'Please wait a moment and try sending the form again.';
    } elseif (count($recent) >= 5 || (int) q('SELECT COUNT(*) FROM bookings WHERE ip = ? AND created_at > NOW() - INTERVAL 1 HOUR', [client_ip()])->fetchColumn() >= 5) {
        $errors['form'] = 'You have sent several requests already. Please call or WhatsApp us instead.';
    }

    if (mb_strlen($old['full_name']) < 2) $errors['full_name'] = 'Please enter your name.';
    $digits = preg_replace('/\D/', '', $old['phone']);
    if (strlen($digits) < 9 || strlen($digits) > 15) $errors['phone'] = 'Please enter a valid phone number.';
    if ($old['email'] !== '' && !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'That email address does not look right.';
    $packageId = ctype_digit($old['package']) && in_array((int) $old['package'], $pkgIds, true) ? (int) $old['package'] : null;
    if ($packageId === null && $old['package'] !== 'custom') $errors['package'] = 'Please choose a package or a custom quotation.';
    $date = DateTime::createFromFormat('Y-m-d', $old['event_date']);
    if (!$date || $date->format('Y-m-d') !== $old['event_date']) {
        $errors['event_date'] = 'Please choose the event date.';
    } elseif ($old['event_date'] < date('Y-m-d')) {
        $errors['event_date'] = 'The event date cannot be in the past.';
    }
    if (mb_strlen($old['venue']) < 2) $errors['venue'] = 'Please tell us where the event will be.';
    if ($old['duration_hours'] !== '' && (!is_numeric($old['duration_hours']) || $old['duration_hours'] <= 0 || $old['duration_hours'] > 48)) $errors['duration_hours'] = 'Enter the number of hours.';
    if ($old['guests'] !== '' && (!ctype_digit($old['guests']) || (int) $old['guests'] > 100000)) $errors['guests'] = 'Enter a number of guests.';
    if (mb_strlen($old['message']) > 2000) $errors['message'] = 'Please keep the message under 2000 characters.';

    if (!$errors) {
        $ref = '';
        for ($try = 0; $try < 5; $try++) {
            $ref = 'TE-' . date('ym') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
            if (!q('SELECT 1 FROM bookings WHERE ref = ?', [$ref])->fetchColumn()) break;
        }
        q('INSERT INTO bookings (ref, full_name, phone, email, package_id, event_type, event_date, start_time, duration_hours, venue, guests, message, ip)
           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)', [
            $ref, mb_substr($old['full_name'], 0, 120), mb_substr($old['phone'], 0, 40), $old['email'] ?: null, $packageId,
            mb_substr($old['event_type'], 0, 120) ?: null, $old['event_date'], mb_substr($old['start_time'], 0, 20) ?: null,
            $old['duration_hours'] !== '' ? (float) $old['duration_hours'] : null, mb_substr($old['venue'], 0, 255),
            $old['guests'] !== '' ? (int) $old['guests'] : null, $old['message'] ?: null, client_ip(),
        ]);
        $_SESSION['booking_times'] = [...$recent, time()];
        $_SESSION['last_booking'] = ['ref' => $ref, 'name' => $old['full_name']];
        redirect('booking.php?sent=1');
    }
}
$_SESSION['booking_form_at'] = time();
$done = isset($_GET['sent']) ? ($_SESSION['last_booking'] ?? null) : null;

$pageTitle = 'Book DJ Tosmic';
$activePage = 'booking';
$pageDescription = 'Book DJ Tosmic for your event or request a custom quotation from Tosmic Events.';
require __DIR__ . '/includes/header.php';
echo page_hero('Book DJ Tosmic', 'Contact & booking', 'Send your event details and we will confirm availability and your final rate.');

$err = fn(string $k) => isset($errors[$k]) ? '<span class="err">' . e($errors[$k]) . '</span>' : '';
$cls = fn(string $k) => isset($errors[$k]) ? ' has-err' : '';
?>

<section class="section">
  <div class="container booking-grid">
    <div class="form-card reveal">
      <?php if ($done): ?>
        <div class="success-card">
          <div class="tick"><i class="fa-solid fa-check"></i></div>
          <h2>Thank you, <?= e(explode(' ', $done['name'])[0]) ?>!</h2>
          <p>Your booking request has been received. Keep this reference:</p>
          <div class="ref-code"><?= e($done['ref']) ?></div>
          <p>We will call or WhatsApp you shortly to confirm availability and your final rate.</p>
          <div class="slide-actions" style="justify-content:center;margin-top:20px">
            <a href="<?= e(whatsapp_link('Hello Tosmic Events, my booking reference is ' . $done['ref'] . '.')) ?>" target="_blank" rel="noopener" class="btn btn-pink"><i class="fa-brands fa-whatsapp"></i> Follow up on WhatsApp</a>
            <a href="<?= e(url('index.php')) ?>" class="btn btn-outline-dark">Back to home</a>
          </div>
        </div>
      <?php else: ?>
        <div class="bf-head">
          <div>
            <h2>Booking request</h2>
            <p>Just 5 quick fields. We reply on call or WhatsApp.</p>
          </div>
          <span class="bf-badge"><i class="fa-solid fa-lock"></i> Private &amp; secure</span>
        </div>
        <?php if (isset($errors['form'])): ?><div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i><?= e($errors['form']) ?></div>
        <?php elseif ($errors): ?><div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i>Please check the highlighted fields.</div><?php endif; ?>

        <form method="post" action="<?= e(url('booking.php')) ?>" novalidate data-once class="bf bf--short">
          <?= csrf_field() ?>
          <div class="hp" aria-hidden="true"><label>Website <input type="text" name="website_url" tabindex="-1" autocomplete="off"></label></div>

          <div class="field<?= $cls('package') ?>">
            <label id="pkgLabel">Package <span class="req">*</span></label>
            <div class="pkg-pick pkg-pick--compact" role="radiogroup" aria-labelledby="pkgLabel">
              <?php $pkgIcons = ['fa-solid fa-briefcase', 'fa-solid fa-champagne-glasses', 'fa-solid fa-music', 'fa-solid fa-star']; ?>
              <?php foreach ($packages as $i => $p): ?>
                <label>
                  <input type="radio" name="package" value="<?= (int) $p['id'] ?>" <?= $old['package'] === (string) $p['id'] ? 'checked' : '' ?>>
                  <span class="opt">
                    <i class="opt-ico <?= e($pkgIcons[$i % count($pkgIcons)]) ?>"></i>
                    <span class="opt-text"><strong><?= e(preg_replace('/\s+Events?$/i', '', $p['name']) ?: $p['name']) ?></strong><small class="opt-price">UGX <?= e(number_format((int) $p['price'])) ?></small></span>
                    <i class="opt-check fa-solid fa-circle-check"></i>
                  </span>
                </label>
              <?php endforeach; ?>
              <label>
                <input type="radio" name="package" value="custom" <?= $old['package'] === 'custom' ? 'checked' : '' ?>>
                <span class="opt">
                  <i class="opt-ico fa-solid fa-wand-magic-sparkles"></i>
                  <span class="opt-text"><strong>Custom</strong><small>Get a quote</small></span>
                  <i class="opt-check fa-solid fa-circle-check"></i>
                </span>
              </label>
            </div>
            <?= $err('package') ?>
          </div>

          <div class="form-grid">
            <div class="field<?= $cls('full_name') ?>"><label for="f_name">Full name <span class="req">*</span></label><div class="ico-input"><i class="fa-regular fa-user"></i><input id="f_name" name="full_name" value="<?= e($old['full_name']) ?>" autocomplete="name" required maxlength="120" placeholder="Your name"></div><?= $err('full_name') ?></div>
            <div class="field<?= $cls('phone') ?>"><label for="f_phone">Phone / WhatsApp <span class="req">*</span></label><div class="ico-input"><i class="fa-solid fa-phone"></i><input id="f_phone" name="phone" type="tel" value="<?= e($old['phone']) ?>" autocomplete="tel" placeholder="+256 7XX XXX XXX" required maxlength="40" inputmode="tel"></div><?= $err('phone') ?></div>
            <div class="field<?= $cls('event_date') ?>"><label for="f_date">Event date <span class="req">*</span></label><div class="ico-input"><i class="fa-regular fa-calendar"></i><input id="f_date" name="event_date" type="date" min="<?= date('Y-m-d') ?>" value="<?= e($old['event_date']) ?>" required></div><?= $err('event_date') ?></div>
            <div class="field<?= $cls('venue') ?>"><label for="f_venue">Venue / location <span class="req">*</span></label><div class="ico-input"><i class="fa-solid fa-location-dot"></i><input id="f_venue" name="venue" value="<?= e($old['venue']) ?>" placeholder="Venue and town" required maxlength="255"></div><?= $err('venue') ?></div>
          </div>

          <?php $moreOpen = isset($errors['email']) || isset($errors['duration_hours']) || isset($errors['guests']) || isset($errors['message'])
              || $old['email'] !== '' || $old['event_type'] !== '' || $old['start_time'] !== '' || $old['duration_hours'] !== '' || $old['guests'] !== '' || $old['message'] !== ''; ?>
          <details class="bf-more"<?= $moreOpen ? ' open' : '' ?>>
            <summary><i class="fa-solid fa-plus"></i> Add more details <span class="opt-label">optional</span></summary>
            <div class="form-grid">
              <div class="field<?= $cls('email') ?>"><label for="f_email">Email</label><div class="ico-input"><i class="fa-regular fa-envelope"></i><input id="f_email" name="email" type="email" value="<?= e($old['email']) ?>" autocomplete="email" maxlength="190" placeholder="you@example.com"></div><?= $err('email') ?></div>
              <div class="field"><label for="f_type">Type of event</label><div class="ico-input"><i class="fa-solid fa-tag"></i><input id="f_type" name="event_type" value="<?= e($old['event_type']) ?>" placeholder="e.g. Wedding reception" maxlength="120" list="eventTypes"></div>
                <datalist id="eventTypes"><?php foreach ($eventChoices as $ev): ?><option value="<?= e($ev) ?>"><?php endforeach; ?></datalist>
              </div>
              <div class="field"><label for="f_time">Start time</label><div class="ico-input"><i class="fa-regular fa-clock"></i><input id="f_time" name="start_time" type="time" value="<?= e($old['start_time']) ?>"></div></div>
              <div class="field<?= $cls('duration_hours') ?>"><label for="f_dur">Duration</label>
                <div class="ico-input ico-input--suffix"><i class="fa-solid fa-hourglass-half"></i><input id="f_dur" name="duration_hours" type="number" min="1" max="48" step="0.5" value="<?= e($old['duration_hours']) ?>" placeholder="0"><span class="suffix">hours</span></div>
                <div class="chips" data-for="f_dur"><?php foreach ([3, 4, 6, 8] as $v): ?><button type="button" data-value="<?= $v ?>"><?= $v ?> h</button><?php endforeach; ?></div>
                <?= $err('duration_hours') ?>
              </div>
              <div class="field<?= $cls('guests') ?>"><label for="f_guests">Expected guests</label>
                <div class="ico-input ico-input--suffix"><i class="fa-solid fa-users"></i><input id="f_guests" name="guests" type="number" min="1" value="<?= e($old['guests']) ?>" placeholder="0"><span class="suffix">people</span></div>
                <div class="chips" data-for="f_guests"><?php foreach ([50, 100, 200, 500] as $v): ?><button type="button" data-value="<?= $v ?>"><?= $v === 500 ? '500+' : $v ?></button><?php endforeach; ?></div>
                <?= $err('guests') ?>
              </div>
              <div class="field full<?= $cls('message') ?>"><label for="f_msg">Notes</label><textarea id="f_msg" name="message" maxlength="2000" rows="3" placeholder="Programme, music preferences, special requests..."><?= e($old['message']) ?></textarea><?= $err('message') ?></div>
            </div>
          </details>

          <div class="bf-submit">
            <button type="submit" class="btn btn-pink btn-lg btn-block"><i class="fa-regular fa-paper-plane"></i> Send booking request</button>
            <p><i class="fa-solid fa-shield-halved"></i> We only use your details to reply about this booking. No payment is taken online.</p>
          </div>
        </form>
      <?php endif; ?>
    </div>

    <aside class="contact-cards contact-cards--sticky">
      <div class="cc-panel reveal">
        <h3 class="cc-title">Prefer to talk?</h3>
        <a class="c-row" href="<?= e(phone_href(setting('phone1'))) ?>"><i class="fa-solid fa-phone"></i><span><small>Call us</small><?= e(setting('phone1')) ?></span></a>
        <?php if (setting('phone2')): ?><a class="c-row" href="<?= e(phone_href(setting('phone2'))) ?>"><i class="fa-solid fa-phone"></i><span><small>Or</small><?= e(setting('phone2')) ?></span></a><?php endif; ?>
        <?php if (setting('whatsapp')): ?>
          <a class="btn btn-wa btn-block" href="<?= e(whatsapp_link('Hello Tosmic Events, I would like to book DJ Tosmic.')) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> Chat on WhatsApp</a>
        <?php endif; ?>
        <?php if (setting('email')): ?><a class="c-row" href="mailto:<?= e(setting('email')) ?>"><i class="fa-regular fa-envelope"></i><span><small>Email</small><?= e(setting('email')) ?></span></a><?php endif; ?>
        <div class="cc-social">
          <?php if (setting('instagram')): ?><a href="https://instagram.com/<?= e(setting('instagram')) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-instagram"></i> @<?= e(setting('instagram')) ?></a><?php endif; ?>
          <?php if (setting('tiktok')): ?><a href="https://www.tiktok.com/@<?= e(setting('tiktok')) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-tiktok"></i> @<?= e(setting('tiktok')) ?></a><?php endif; ?>
        </div>
      </div>
      <div class="cc-panel cc-steps reveal" style="--d:100ms">
        <h3 class="cc-title">What happens next</h3>
        <ol>
          <li><b>We check the date</b><span>and confirm DJ Tosmic is free.</span></li>
          <li><b>We agree the rate</b><span>based on distance and duration.</span></li>
          <li><b>Your date is locked in</b><span>and we plan the music with you.</span></li>
        </ol>
      </div>
      <div class="cc-note reveal" style="--d:200ms"><i class="fa-solid fa-circle-info"></i> Packages include DJ equipment, booth, basic lighting and smoke. Sound equipment is excluded.</div>
    </aside>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
