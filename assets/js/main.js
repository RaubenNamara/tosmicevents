(() => {
  'use strict';
  document.documentElement.classList.add('js');
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- Header + back to top ---------- */
  const header = document.getElementById('siteHeader');
  const toTop = document.getElementById('toTop');
  const hasHero = !!document.querySelector('.hero, .page-hero');
  if (!hasHero && header) header.classList.add('is-solid');
  const onScroll = () => {
    const y = window.scrollY;
    header?.classList.toggle('is-scrolled', y > 40);
    toTop?.classList.toggle('is-visible', y > 700);
  };
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();
  toTop?.addEventListener('click', () => window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' }));

  /* ---------- Mobile menu ---------- */
  const toggle = document.getElementById('navToggle');
  const setMenu = (open) => {
    document.body.classList.toggle('nav-open', open);
    header?.classList.toggle('nav-is-open', open);
    toggle?.setAttribute('aria-expanded', String(open));
    toggle?.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
  };
  toggle?.addEventListener('click', () => setMenu(!document.body.classList.contains('nav-open')));
  document.querySelectorAll('#mainNav a').forEach(a => a.addEventListener('click', () => setMenu(false)));
  document.addEventListener('keydown', e => { if (e.key === 'Escape') setMenu(false); });
  window.addEventListener('resize', () => { if (window.innerWidth > 960) setMenu(false); });

  /* ---------- Reveal on scroll ---------- */
  const reveals = document.querySelectorAll('.reveal');
  const settle = (el) => {
    const d = parseInt(getComputedStyle(el).getPropertyValue('--d')) || 0;
    // once it has arrived, drop the reveal classes so hover effects use their own transitions
    setTimeout(() => el.classList.remove('reveal', 'is-in'), 900 + d);
  };
  if ('IntersectionObserver' in window && !reduceMotion) {
    const io = new IntersectionObserver((entries) => {
      entries.forEach(en => {
        if (en.isIntersecting) { en.target.classList.add('is-in'); settle(en.target); io.unobserve(en.target); }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    reveals.forEach(el => io.observe(el));
  } else {
    reveals.forEach(el => el.classList.remove('reveal'));
  }

  /* ---------- Hero slideshow ---------- */
  const hero = document.getElementById('hero');
  if (hero) {
    const slides = [...hero.querySelectorAll('.slide')];
    const dots = [...hero.querySelectorAll('.dot')];
    const counter = document.getElementById('slideNow');
    const DURATION = 6500;
    hero.style.setProperty('--slide-ms', DURATION + 'ms');
    let current = 0, timer = null, paused = false;

    // place a slide instantly (no transition) or let it glide there
    const place = (el, pct, animate) => {
      el.style.transition = animate ? '' : 'none';
      el.style.transform = `translate3d(${pct}%,0,0)`;
    };
    slides.forEach((s, i) => { if (i !== 0) place(s, 100, false); });

    const go = (n, forceDir) => {
      if (slides.length < 2) return;
      const next = (n + slides.length) % slides.length;
      if (next === current) return;
      // forward unless we asked for the previous slide (or a dot to the left)
      const dir = forceDir ?? (n < current && !(current === slides.length - 1 && next === 0) ? -1 : 1);
      const cur = slides[current], inc = slides[next];
      hero.style.setProperty('--dir', dir);
      place(inc, dir * 100, false);
      void inc.offsetWidth;
      place(inc, 0, true);
      place(cur, -dir * 100, true);
      hero.classList.remove('wipe-next', 'wipe-prev');
      void hero.offsetWidth;
      hero.classList.add(dir > 0 ? 'wipe-next' : 'wipe-prev');
      cur.classList.remove('is-active');
      cur.setAttribute('aria-hidden', 'true');
      inc.classList.add('is-active');
      inc.removeAttribute('aria-hidden');
      dots.forEach((d, i) => {
        d.classList.toggle('is-done', i < next);
        d.setAttribute('aria-selected', String(i === next));
        d.classList.remove('is-active');
      });
      // restart the progress animation on the active dot
      void dots[next]?.offsetWidth;
      dots[next]?.classList.add('is-active');
      if (counter) counter.textContent = String(next + 1).padStart(2, '0');
      current = next;
      schedule();
    };
    const schedule = () => {
      clearTimeout(timer);
      if (!paused && !reduceMotion && slides.length > 1) timer = setTimeout(() => go(current + 1), DURATION);
    };
    const pause = (p) => { paused = p; hero.classList.toggle('is-paused', p); if (p) clearTimeout(timer); else schedule(); };

    document.getElementById('nextSlide')?.addEventListener('click', () => go(current + 1, 1));
    document.getElementById('prevSlide')?.addEventListener('click', () => go(current - 1, -1));
    dots.forEach((d, i) => d.addEventListener('click', () => go(i, i < current ? -1 : 1)));

    // background videos: play one after the other and cross-fade; never play for reduced motion
    const wrap = document.getElementById('heroVideos');
    if (wrap) {
      const vids = [...wrap.querySelectorAll('video')];
      const FADE = 1.4; // seconds, matches the CSS opacity transition
      let vi = 0, switching = false;
      const playV = (v) => v.play?.().catch(() => {});
      const nextVideo = () => {
        if (switching) return;
        switching = true;
        const cur = vids[vi];
        vi = (vi + 1) % vids.length;
        const nxt = vids[vi];
        nxt.currentTime = 0;
        playV(nxt);
        nxt.classList.add('is-on');
        cur.classList.remove('is-on');
        setTimeout(() => { cur.pause(); switching = false; }, FADE * 1000);
        // start loading the one after this, so it is ready in time
        const after = vids[(vi + 1) % vids.length];
        if (after !== nxt) after.preload = 'auto';
      };
      if (vids.length === 1) vids[0].loop = true;
      vids.forEach(v => {
        v.addEventListener('timeupdate', () => {
          if (vids.length > 1 && v === vids[vi] && v.duration && v.duration - v.currentTime < FADE) nextVideo();
        });
        v.addEventListener('ended', () => { if (v === vids[vi]) { if (vids.length > 1) nextVideo(); else { v.currentTime = 0; playV(v); } } });
        v.addEventListener('error', () => { if (v === vids[vi] && vids.length > 1) nextVideo(); }, true);
      });
      if (reduceMotion) vids.forEach(v => { v.removeAttribute('autoplay'); v.pause(); });
      else { vids[1] && (vids[1].preload = 'auto'); playV(vids[0]); }
      document.addEventListener('visibilitychange', () => {
        if (document.hidden) vids.forEach(v => v.pause());
        else if (!reduceMotion) playV(vids[vi]);
      });
    }

    // pause while the pointer is over the text/buttons or the tab is hidden
    hero.querySelectorAll('.slide-inner').forEach(el => {
      el.addEventListener('mouseenter', () => pause(true));
      el.addEventListener('mouseleave', () => pause(false));
    });
    // keyboard focus pauses; a mouse click on a dot or arrow must not stop autoplay for good
    hero.addEventListener('focusin', e => { if (e.target.matches(':focus-visible')) pause(true); });
    hero.addEventListener('focusout', () => { if (paused) pause(false); });
    document.addEventListener('visibilitychange', () => pause(document.hidden));

    hero.addEventListener('keydown', e => {
      if (e.key === 'ArrowRight') go(current + 1);
      if (e.key === 'ArrowLeft') go(current - 1);
    });

    // swipe
    let sx = 0, sy = 0;
    hero.addEventListener('touchstart', e => { sx = e.touches[0].clientX; sy = e.touches[0].clientY; }, { passive: true });
    hero.addEventListener('touchend', e => {
      const dx = e.changedTouches[0].clientX - sx, dy = e.changedTouches[0].clientY - sy;
      if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy)) go(current + (dx < 0 ? 1 : -1));
    });

    if (reduceMotion) hero.classList.add('is-paused');
    schedule();
  }

  /* ---------- Gallery filter ---------- */
  const filterBar = document.querySelector('.filter-bar');
  if (filterBar) {
    filterBar.addEventListener('click', e => {
      const btn = e.target.closest('button[data-filter]');
      if (!btn) return;
      filterBar.querySelectorAll('button').forEach(b => b.classList.toggle('is-active', b === btn));
      const f = btn.dataset.filter;
      document.querySelectorAll('.g-item[data-cat]').forEach(it => {
        it.classList.toggle('is-hidden', f !== 'all' && it.dataset.cat !== f);
      });
    });
  }

  /* ---------- Lightbox ---------- */
  const lbLinks = () => [...document.querySelectorAll('a[data-lightbox]')].filter(a => !a.classList.contains('is-hidden'));
  if (document.querySelector('a[data-lightbox]')) {
    const lb = document.createElement('div');
    lb.className = 'lightbox';
    lb.setAttribute('role', 'dialog');
    lb.setAttribute('aria-modal', 'true');
    lb.setAttribute('aria-label', 'Photo viewer');
    lb.innerHTML = '<img alt=""><p class="lb-caption"></p>' +
      '<button class="lb-btn lb-close" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>' +
      '<button class="lb-btn lb-prev" aria-label="Previous photo"><i class="fa-solid fa-arrow-left"></i></button>' +
      '<button class="lb-btn lb-next" aria-label="Next photo"><i class="fa-solid fa-arrow-right"></i></button>';
    document.body.appendChild(lb);
    const img = lb.querySelector('img'), cap = lb.querySelector('.lb-caption');
    let idx = 0, lastFocus = null;
    const show = (i) => {
      const list = lbLinks();
      if (!list.length) return;
      idx = (i + list.length) % list.length;
      img.src = list[idx].href;
      img.alt = list[idx].dataset.caption || '';
      cap.textContent = list[idx].dataset.caption || '';
    };
    const open = (i) => { lastFocus = document.activeElement; show(i); lb.classList.add('is-open'); document.body.style.overflow = 'hidden'; lb.querySelector('.lb-close').focus(); };
    const close = () => { lb.classList.remove('is-open'); document.body.style.overflow = ''; lastFocus?.focus(); };
    document.addEventListener('click', e => {
      const a = e.target.closest('a[data-lightbox]');
      if (!a) return;
      e.preventDefault();
      open(lbLinks().indexOf(a));
    });
    lb.querySelector('.lb-close').addEventListener('click', close);
    lb.querySelector('.lb-prev').addEventListener('click', () => show(idx - 1));
    lb.querySelector('.lb-next').addEventListener('click', () => show(idx + 1));
    lb.addEventListener('click', e => { if (e.target === lb) close(); });
    document.addEventListener('keydown', e => {
      if (!lb.classList.contains('is-open')) return;
      if (e.key === 'Escape') close();
      if (e.key === 'ArrowRight') show(idx + 1);
      if (e.key === 'ArrowLeft') show(idx - 1);
    });
    let tx = 0;
    lb.addEventListener('touchstart', e => { tx = e.touches[0].clientX; }, { passive: true });
    lb.addEventListener('touchend', e => { const dx = e.changedTouches[0].clientX - tx; if (Math.abs(dx) > 50) show(idx + (dx < 0 ? 1 : -1)); });
  }

  /* ---------- Booking form: block double submits ---------- */
  document.querySelectorAll('form[data-once]').forEach(f => f.addEventListener('submit', () => {
    const b = f.querySelector('button[type=submit]');
    if (b) { b.disabled = true; b.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sending...'; }
  }));
})();

/* booking form: quick-pick chips fill their input */
document.querySelectorAll('.chips[data-for]').forEach(group => {
  const input = document.getElementById(group.dataset.for);
  if (!input) return;
  const sync = () => group.querySelectorAll('button').forEach(b => b.classList.toggle('is-on', b.dataset.value === input.value));
  group.addEventListener('click', e => {
    const b = e.target.closest('button[data-value]');
    if (!b) return;
    input.value = b.dataset.value;
    input.dispatchEvent(new Event('input', { bubbles: true }));
    sync();
  });
  input.addEventListener('input', sync);
  sync();
});
