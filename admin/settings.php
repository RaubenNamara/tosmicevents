<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require __DIR__ . '/_layout.php';
require_admin();

$groups = [
    'Business' => [
        'site_name' => ['Business name', 'text'],
        'tagline' => ['Tagline (shown in the footer and search results)', 'text'],
        'location' => ['Location', 'text'],
        'website' => ['Website address', 'text'],
    ],
    'Contacts' => [
        'phone1' => ['Main phone', 'text'],
        'phone2' => ['Second phone', 'text'],
        'whatsapp' => ['WhatsApp number (country code, digits only, e.g. 256750547775)', 'text'],
        'email' => ['Email (leave empty to hide)', 'email'],
        'instagram' => ['Instagram username (without @)', 'text'],
        'tiktok' => ['TikTok username (without @)', 'text'],
    ],
    'About page' => [
        'about_title' => ['Heading', 'text'],
        'about_text' => ['Story (a blank line starts a new paragraph)', 'textarea'],
    ],
    'Packages' => [
        'package_includes' => ['What every package includes (one per line)', 'textarea'],
        'package_note' => ['Note under the packages', 'textarea'],
    ],
];

if (is_post() && post('action') === 'video') {
    csrf_check();
    $set = db()->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)');
    try {
        $reset = (string) ($_POST['reset_video'] ?? '');
        if (isset(HERO_VIDEO_SLOTS[$reset])) {
            $old = setting($reset);
            $set->execute([$reset, '']);
            delete_upload($old ?: null);
            flash('success', 'That slot is back to the built-in video.');
        } else {
            $n = 0;
            foreach (array_keys(HERO_VIDEO_SLOTS) as $key) {
                if (empty($_FILES[$key]['name'])) continue;
                $old = setting($key);
                $set->execute([$key, save_video_upload($_FILES[$key])]);
                delete_upload($old ?: null);
                $n++;
            }
            $set->execute(['hero_video_on', isset($_POST['hero_video_on']) ? '1' : '0']);
            flash('success', $n ? "Saved. $n new video" . ($n > 1 ? 's are' : ' is') . ' now on the home page.' : 'Home page video settings saved.');
        }
    } catch (RuntimeException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('admin/settings.php');
}

if (is_post()) {
    csrf_check();
    $st = db()->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)');
    foreach ($groups as $fields) {
        foreach ($fields as $key => [$label, $type]) {
            $v = mb_substr(post($key), 0, 5000);
            if ($key === 'whatsapp') $v = preg_replace('/\D/', '', $v);
            if ($key === 'instagram' || $key === 'tiktok') $v = ltrim($v, '@');
            if ($type === 'email' && $v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
                flash('error', 'The email address does not look right, so it was not saved.');
                continue;
            }
            $st->execute([$key, $v]);
        }
    }
    flash('success', 'Settings saved. The website shows the new details straight away.');
    redirect('admin/settings.php');
}

$vals = settings_all();
admin_top('Site settings', 'settings');
$videoOn = setting('hero_video_on', '1') === '1';
?>
<section class="card" style="margin-bottom:20px">
  <div class="card-head"><h2>Home page background videos</h2><span class="muted">Video 1 plays, then video 2, then back to 1</span></div>
  <form method="post" enctype="multipart/form-data" class="card-body" style="display:grid;gap:18px">
    <?= csrf_field() ?><input type="hidden" name="action" value="video">
    <div class="grid grid-2">
      <?php $slotNo = 0; foreach (HERO_VIDEO_SLOTS as $key => $slot): $slotNo++; $v = hero_video_slot($key); ?>
        <div style="display:grid;gap:10px;align-content:start;padding:16px;border:1px solid var(--line);border-radius:14px">
          <div style="display:flex;justify-content:space-between;gap:10px;align-items:center;flex-wrap:wrap">
            <strong style="font-family:var(--font-head);text-transform:uppercase;font-size:1.05rem">Video <?= $slotNo ?></strong>
            <span class="pill <?= $v['custom'] ? 'pill-confirmed' : 'pill-completed' ?>"><?= $v['custom'] ? 'Your upload' : 'Built-in: ' . e($slot['label']) ?></span>
          </div>
          <video src="<?= e($v['src']) ?>" muted loop autoplay playsinline style="width:100%;border-radius:12px;background:#000;aspect-ratio:16/9;object-fit:cover"></video>
          <label class="fld"><span>Replace with</span><input type="file" name="<?= e($key) ?>" accept="video/mp4,video/webm"></label>
          <?php if ($v['custom']): ?><button class="btn btn-light btn-sm" name="reset_video" value="<?= e($key) ?>" data-confirm-click="Remove your upload and use the built-in video in this slot?"><i class="fa-solid fa-rotate-left"></i> Use built-in video</button><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <small class="hint">MP4 or WebM, landscape, 10 to 20 seconds each, ideally 1920×1080 and under 15 MB so it loads fast on phones. Videos play silently behind the slideshow with a dark tint and cross-fade into each other. Clips of DJ Tosmic's own events work best.</small>
    <label class="switch"><input type="checkbox" name="hero_video_on" <?= $videoOn ? 'checked' : '' ?>> Play background videos on the home page</label>
    <div class="form-actions" style="justify-content:flex-start"><button class="btn btn-pink"><i class="fa-solid fa-floppy-disk"></i> Save video settings</button></div>
  </form>
</section>

<form method="post" class="grid">
  <?= csrf_field() ?>
  <div class="grid grid-2">
  <?php foreach ($groups as $title => $fields): ?>
    <section class="card">
      <div class="card-head"><h2><?= e($title) ?></h2></div>
      <div class="card-body" style="display:grid;gap:14px">
        <?php foreach ($fields as $key => [$label, $type]): ?>
          <label class="fld"><span><?= e($label) ?></span>
            <?php if ($type === 'textarea'): ?>
              <textarea name="<?= e($key) ?>" rows="<?= $key === 'about_text' ? 8 : 4 ?>"><?= e($vals[$key] ?? '') ?></textarea>
            <?php else: ?>
              <input type="<?= e($type) ?>" name="<?= e($key) ?>" value="<?= e($vals[$key] ?? '') ?>">
            <?php endif; ?>
          </label>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
  </div>
  <div class="form-actions"><button class="btn btn-pink"><i class="fa-solid fa-floppy-disk"></i> Save settings</button></div>
</form>
<?php admin_bottom(); ?>
