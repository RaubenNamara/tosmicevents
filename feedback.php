<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/components.php';

$eventChoices = [];
foreach (q('SELECT events FROM packages WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll(PDO::FETCH_COLUMN) as $ev) {
    $eventChoices = array_merge($eventChoices, lines($ev));
}

$errors = [];
$old = ['name' => '', 'role' => '', 'rating' => '', 'message' => '', 'phone' => '', 'booking_ref' => '', 'consent' => ''];

if (is_post()) {
    csrf_check();
    foreach ($old as $k => $_) $old[$k] = post($k);

    $tooFast = (time() - (int) ($_SESSION['feedback_form_at'] ?? 0)) < 4;
    $recent = array_filter($_SESSION['feedback_times'] ?? [], fn($t) => $t > time() - 86400);
    if (post('website_url') !== '' || $tooFast) {
        $errors['form'] = 'Please wait a moment and send your feedback again.';
    } elseif (count($recent) >= 3 || (int) q('SELECT COUNT(*) FROM testimonials WHERE ip = ? AND created_at > NOW() - INTERVAL 1 DAY', [client_ip()])->fetchColumn() >= 3) {
        $errors['form'] = 'Thank you, we have already received your feedback today.';
    }

    if (mb_strlen($old['name']) < 2) $errors['name'] = 'Please enter your name.';
    if (!in_array($old['rating'], ['1', '2', '3', '4', '5'], true)) $errors['rating'] = 'Please choose a rating.';
    if (mb_strlen($old['message']) < 20) $errors['message'] = 'Please tell us a little more (at least 20 characters).';
    if (mb_strlen($old['message']) > 1500) $errors['message'] = 'Please keep it under 1500 characters.';
    if (preg_match_all('#https?://|www\.#i', $old['message']) > 0) $errors['message'] = 'Please leave out web links.';
    $digits = preg_replace('/\D/', '', $old['phone']);
    if ($old['phone'] !== '' && (strlen($digits) < 9 || strlen($digits) > 15)) $errors['phone'] = 'That phone number does not look right.';
    if ($old['consent'] !== '1') $errors['consent'] = 'Please tick the box so we can show your review.';

    if (!$errors) {
        // A review is "verified" when the booking reference and the phone number both match a real booking.
        $bookingId = null;
        if ($old['booking_ref'] !== '' && $digits !== '') {
            $b = q('SELECT id, phone FROM bookings WHERE ref = ?', [strtoupper(trim($old['booking_ref']))])->fetch();
            if ($b && substr(preg_replace('/\D/', '', $b['phone']), -9) === substr($digits, -9)) $bookingId = (int) $b['id'];
        }
        q('INSERT INTO testimonials (name, role, phone, booking_id, source, message, rating, sort_order, is_active, reviewed, ip)
           VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, 0, ?)', [
            mb_substr($old['name'], 0, 120), mb_substr($old['role'], 0, 160) ?: null, mb_substr($old['phone'], 0, 40) ?: null,
            $bookingId, 'client', $old['message'], (int) $old['rating'], client_ip(),
        ]);
        $_SESSION['feedback_times'] = [...$recent, time()];
        $_SESSION['feedback_done'] = ['name' => $old['name'], 'verified' => $bookingId !== null, 'tried_verify' => $old['booking_ref'] !== ''];
        redirect('feedback.php?sent=1#share');
    }
}
$_SESSION['feedback_form_at'] = time();
$done = isset($_GET['sent']) ? ($_SESSION['feedback_done'] ?? null) : null;

$reviews = q('SELECT * FROM testimonials WHERE is_active = 1 ORDER BY sort_order, created_at DESC')->fetchAll();
$sum = review_summary();
$dist = array_fill(1, 5, 0);
foreach ($reviews as $r) $dist[(int) $r['rating']]++;

$pageTitle = 'Client Reviews';
$activePage = 'reviews';
$pageDescription = 'What clients say about DJ Tosmic and Tosmic Events, and a place to share your own experience.';
require __DIR__ . '/includes/header.php';
echo page_hero('Client Reviews', 'Your feedback', 'Real words from people who booked DJ Tosmic. Had us at your event? Tell us how it went.');

$err = fn(string $k) => isset($errors[$k]) ? '<span class="err">' . e($errors[$k]) . '</span>' : '';
$cls = fn(string $k) => isset($errors[$k]) ? ' has-err' : '';
?>

<section class="section">
  <div class="container reviews-layout">
    <div>
      <?php if ($sum['count']): ?>
        <div class="rating-summary reveal">
          <div class="rs-score">
            <strong><?= e(number_format($sum['avg'], 1)) ?></strong>
            <div class="stars"><?php for ($s = 1; $s <= 5; $s++): ?><i class="fa-<?= $s <= round($sum['avg']) ? 'solid' : 'regular' ?> fa-star"></i><?php endfor; ?></div>
            <span><?= $sum['count'] ?> review<?= $sum['count'] > 1 ? 's' : '' ?></span>
          </div>
          <div class="rs-bars">
            <?php for ($s = 5; $s >= 1; $s--): $pct = $sum['count'] ? round($dist[$s] / $sum['count'] * 100) : 0; ?>
              <div class="rs-row"><span><?= $s ?> <i class="fa-solid fa-star"></i></span><div class="rs-track"><i style="width:<?= $pct ?>%"></i></div><b><?= $dist[$s] ?></b></div>
            <?php endfor; ?>
          </div>
        </div>
        <div class="reviews-list">
          <?php foreach ($reviews as $i => $t) echo review_card($t, ($i % 2) * 100); ?>
        </div>
      <?php else: ?>
        <div class="empty-state reveal"><i class="fa-regular fa-comments"></i><h3>No reviews yet</h3><p>Booked DJ Tosmic for your event? Be the first to share how it went.</p></div>
      <?php endif; ?>
    </div>

    <aside class="form-card reveal feedback-card" id="share">
      <?php if ($done): ?>
        <div class="success-card" style="padding:20px 0">
          <div class="tick"><i class="fa-solid fa-heart"></i></div>
          <h2>Thank you, <?= e(explode(' ', $done['name'])[0]) ?>!</h2>
          <p>Your feedback has been received. It will appear here after a quick check by our team.</p>
          <?php if ($done['verified']): ?><p class="verified" style="justify-content:center"><i class="fa-solid fa-circle-check"></i> Matched to your booking, so it will show as a verified booking.</p>
          <?php elseif ($done['tried_verify']): ?><p style="color:var(--ink-soft);font-size:.9rem">We could not match that booking reference to the phone number, so it was sent without the verified badge.</p><?php endif; ?>
          <a href="<?= e(url('feedback.php')) ?>" class="btn btn-outline-dark" style="margin-top:10px">Back to reviews</a>
        </div>
      <?php else: ?>
        <h2>Share your experience</h2>
        <p>Your honest feedback helps others choose, and helps us get better.</p>
        <?php if (isset($errors['form'])): ?><div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i><?= e($errors['form']) ?></div>
        <?php elseif ($errors): ?><div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i>Please check the highlighted fields.</div><?php endif; ?>
        <form method="post" action="<?= e(url('feedback.php')) ?>#share" novalidate data-once>
          <?= csrf_field() ?>
          <div class="hp" aria-hidden="true"><label>Website <input type="text" name="website_url" tabindex="-1" autocomplete="off"></label></div>
          <div class="form-grid" style="grid-template-columns:1fr">
            <div class="field<?= $cls('rating') ?>">
              <label id="rate-label">Your rating <span class="req">*</span></label>
              <div class="star-input" role="radiogroup" aria-labelledby="rate-label">
                <?php for ($s = 5; $s >= 1; $s--): ?>
                  <input type="radio" id="star<?= $s ?>" name="rating" value="<?= $s ?>" <?= $old['rating'] === (string) $s ? 'checked' : '' ?>>
                  <label for="star<?= $s ?>" title="<?= $s ?> star<?= $s > 1 ? 's' : '' ?>"><i class="fa-solid fa-star"></i><span class="sr"><?= $s ?> stars</span></label>
                <?php endfor; ?>
              </div>
              <?= $err('rating') ?>
            </div>
            <div class="field<?= $cls('name') ?>"><label for="r_name">Your name <span class="req">*</span></label><input id="r_name" name="name" value="<?= e($old['name']) ?>" maxlength="120" autocomplete="name" placeholder="As you want it shown"><?= $err('name') ?></div>
            <div class="field"><label for="r_role">Your event</label><input id="r_role" name="role" value="<?= e($old['role']) ?>" maxlength="160" list="eventList" placeholder="e.g. Wedding reception"><datalist id="eventList"><?php foreach ($eventChoices as $ev): ?><option value="<?= e($ev) ?>"><?php endforeach; ?></datalist></div>
            <div class="field<?= $cls('message') ?>"><label for="r_msg">Your feedback <span class="req">*</span></label><textarea id="r_msg" name="message" maxlength="1500" placeholder="How was the music, the setup and the service?"><?= e($old['message']) ?></textarea><?= $err('message') ?></div>
            <div class="verify-box">
              <p><i class="fa-solid fa-circle-check"></i> <strong>Get a "Verified booking" badge</strong> (optional): enter your booking reference and the phone number you booked with. Neither is shown publicly.</p>
              <div class="form-grid">
                <div class="field"><label for="r_ref">Booking reference</label><input id="r_ref" name="booking_ref" value="<?= e($old['booking_ref']) ?>" maxlength="20" placeholder="TE-2611-XXXXX" style="text-transform:uppercase"></div>
                <div class="field<?= $cls('phone') ?>"><label for="r_phone">Phone</label><input id="r_phone" name="phone" type="tel" value="<?= e($old['phone']) ?>" maxlength="40" autocomplete="tel"><?= $err('phone') ?></div>
              </div>
            </div>
            <div class="field<?= $cls('consent') ?>">
              <label class="check"><input type="checkbox" name="consent" value="1" <?= $old['consent'] === '1' ? 'checked' : '' ?>> I am sharing my own genuine experience, and Tosmic Events may show my name and review on this website.</label>
              <?= $err('consent') ?>
            </div>
            <button type="submit" class="btn btn-pink btn-lg btn-block"><i class="fa-regular fa-paper-plane"></i> Send feedback</button>
          </div>
        </form>
      <?php endif; ?>
    </aside>
  </div>
</section>

<?= cta_band() ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
