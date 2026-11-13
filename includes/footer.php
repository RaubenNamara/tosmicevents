</main>

<?php $footPackages = q('SELECT id, name FROM packages WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll(); ?>
<footer class="site-footer">
  <div class="eq-bars eq-bars--footer" aria-hidden="true"><?php for ($i = 0; $i < 48; $i++): ?><span style="--i:<?= $i ?>"></span><?php endfor; ?></div>
  <div class="container footer-grid">
    <div class="footer-brand">
      <a href="<?= e(url('index.php')) ?>" class="brand">
        <img src="<?= e(url('assets/img/seed/logo.jpg')) ?>" alt="" width="54" height="54">
        <span class="brand-text"><strong>TOSMIC</strong><small>EVENTS</small></span>
      </a>
      <p><?= e(setting('tagline')) ?></p>
      <div class="socials">
        <?php if (setting('instagram')): ?><a href="https://instagram.com/<?= e(setting('instagram')) ?>" target="_blank" rel="noopener" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a><?php endif; ?>
        <?php if (setting('tiktok')): ?><a href="https://www.tiktok.com/@<?= e(setting('tiktok')) ?>" target="_blank" rel="noopener" aria-label="TikTok"><i class="fa-brands fa-tiktok"></i></a><?php endif; ?>
        <?php if (setting('whatsapp')): ?><a href="<?= e(whatsapp_link()) ?>" target="_blank" rel="noopener" aria-label="WhatsApp"><i class="fa-brands fa-whatsapp"></i></a><?php endif; ?>
      </div>
    </div>

    <div>
      <h4>Explore</h4>
      <ul class="footer-links">
        <li><a href="<?= e(url('index.php')) ?>">Home</a></li>
        <li><a href="<?= e(url('about.php')) ?>">About DJ Tosmic</a></li>
        <li><a href="<?= e(url('packages.php')) ?>">Booking packages</a></li>
        <li><a href="<?= e(url('gallery.php')) ?>">Gallery</a></li>
        <li><a href="<?= e(url('feedback.php')) ?>">Client reviews</a></li>
        <li><a href="<?= e(url('booking.php')) ?>">Book an event</a></li>
        <li><a href="<?= e(url('verify.php')) ?>">Verify a receipt</a></li>
      </ul>
    </div>

    <div>
      <h4>Packages</h4>
      <ul class="footer-links">
        <?php foreach ($footPackages as $fp): ?>
          <li><a href="<?= e(url('packages.php#package-' . $fp['id'])) ?>"><?= e($fp['name']) ?></a></li>
        <?php endforeach; ?>
        <li><a href="<?= e(url('booking.php?package=custom')) ?>">Custom quotation</a></li>
      </ul>
    </div>

    <div>
      <h4>Get in touch</h4>
      <ul class="footer-contact">
        <li><i class="fa-solid fa-phone"></i><a href="<?= e(phone_href(setting('phone1'))) ?>"><?= e(setting('phone1')) ?></a></li>
        <?php if (setting('phone2')): ?><li><i class="fa-solid fa-phone"></i><a href="<?= e(phone_href(setting('phone2'))) ?>"><?= e(setting('phone2')) ?></a></li><?php endif; ?>
        <?php if (setting('email')): ?><li><i class="fa-solid fa-envelope"></i><a href="mailto:<?= e(setting('email')) ?>"><?= e(setting('email')) ?></a></li><?php endif; ?>
        <?php if (setting('website')): ?><li><i class="fa-solid fa-globe"></i><span><?= e(setting('website')) ?></span></li><?php endif; ?>
        <?php if (setting('location')): ?><li><i class="fa-solid fa-location-dot"></i><span><?= e(setting('location')) ?></span></li><?php endif; ?>
      </ul>
    </div>
  </div>
  <div class="container footer-bottom">
    <span>&copy; <?= date('Y') ?> <?= e(setting('site_name', 'Tosmic Events')) ?>. All rights reserved.</span>
    <span>Sound equipment excluded from packages · Rates vary with distance and duration</span>
  </div>
</footer>

<?php if (setting('whatsapp')): ?>
<a class="wa-float" href="<?= e(whatsapp_link('Hello Tosmic Events, I would like to book DJ Tosmic.')) ?>" target="_blank" rel="noopener" aria-label="Chat on WhatsApp">
  <i class="fa-brands fa-whatsapp"></i>
</a>
<?php endif; ?>
<button class="to-top" id="toTop" aria-label="Back to top"><i class="fa-solid fa-arrow-up"></i></button>

<script src="<?= e(asset('js/main.js')) ?>" defer></script>
</body>
</html>
