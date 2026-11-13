(() => {
  'use strict';
  // sidebar on small screens
  const burger = document.getElementById('admBurger');
  const backdrop = document.getElementById('admBackdrop');
  burger?.addEventListener('click', () => document.body.classList.add('side-open'));
  backdrop?.addEventListener('click', () => document.body.classList.remove('side-open'));
  document.addEventListener('keydown', e => { if (e.key === 'Escape') document.body.classList.remove('side-open'); });

  // dismiss flashes
  document.querySelectorAll('.flash-x').forEach(b => b.addEventListener('click', () => b.closest('.flash').remove()));
  setTimeout(() => document.querySelectorAll('.flash-success').forEach(f => { f.style.transition = 'opacity .4s'; f.style.opacity = '0'; setTimeout(() => f.remove(), 400); }), 5000);

  // confirm before destructive actions (forms with data-confirm, single buttons with data-confirm-click)
  document.addEventListener('submit', e => {
    const msg = e.target.dataset.confirm;
    if (msg && !window.confirm(msg)) e.preventDefault();
  });
  document.addEventListener('click', e => {
    const btn = e.target.closest('[data-confirm-click]');
    if (btn && !window.confirm(btn.dataset.confirmClick)) { e.preventDefault(); e.stopPropagation(); }
  }, true);

  // live image preview
  document.querySelectorAll('[data-preview-input]').forEach(input => {
    input.addEventListener('change', () => {
      const box = input.closest('.img-input')?.querySelector('[data-preview]');
      const f = input.files?.[0];
      if (!box || !f) return;
      const img = document.createElement('img');
      img.src = URL.createObjectURL(f);
      box.innerHTML = '';
      box.appendChild(img);
    });
  });

  // show / hide password
  document.querySelectorAll('[data-toggle-pw]').forEach(b => b.addEventListener('click', () => {
    const inp = document.getElementById(b.dataset.togglePw);
    if (!inp) return;
    const show = inp.type === 'password';
    inp.type = show ? 'text' : 'password';
    b.innerHTML = show ? '<i class="fa-regular fa-eye-slash"></i>' : '<i class="fa-regular fa-eye"></i>';
  }));

  // gallery drop zone
  const dz = document.querySelector('.dropzone');
  if (dz) {
    const input = dz.querySelector('input[type=file]');
    const count = dz.querySelector('.drop-count');
    const update = () => { if (count) count.textContent = input.files.length ? input.files.length + ' photo(s) ready to upload' : ''; };
    ['dragenter', 'dragover'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.add('is-over'); }));
    ['dragleave', 'drop'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.remove('is-over'); }));
    dz.addEventListener('drop', e => { input.files = e.dataTransfer.files; update(); });
    input.addEventListener('change', update);
  }

  // auto-submit selects that ask for it
  document.querySelectorAll('[data-autosubmit]').forEach(el => el.addEventListener('change', () => el.form.submit()));
})();

/* booking status: ticking Paid also ticks Confirmed; choosing New, Contacted or Cancelled clears Paid */
(() => {
  const paid = document.querySelector('[data-paid-box]');
  if (!paid) return;
  const radios = [...document.querySelectorAll('[data-status-box]')];
  paid.addEventListener('change', () => {
    if (paid.checked) { const c = radios.find(r => r.value === 'confirmed'); if (c) c.checked = true; }
  });
  radios.forEach(r => r.addEventListener('change', () => { if (r.checked && r.value !== 'confirmed') paid.checked = false; }));
})();

/* booking money: agreed, amount paid now, "Full amount" tick and a live balance */
(() => {
  const box = document.querySelector('.money');
  if (!box) return;
  const agreed = document.getElementById('mAgreed');
  const now = document.getElementById('mNow');
  const full = document.getElementById('mFull');
  const out = document.getElementById('mBalance');
  const outBox = document.getElementById('mBalanceBox');
  const save = document.querySelector('#statusSave span');
  const paidBox = document.querySelector('[data-paid-box]');
  const already = parseInt(box.dataset.already || '0', 10);
  const num = (el) => el ? parseInt((el.value || '').replace(/\D/g, ''), 10) || 0 : 0;
  const fmt = (n) => n.toLocaleString('en-US');
  const remaining = () => Math.max(0, num(agreed) - already);

  const update = () => {
    const a = num(agreed), n = num(now);
    if (!a) { out.textContent = '-'; outBox.className = 'money-balance'; }
    else {
      const bal = a - already - n;
      out.textContent = (bal < 0 ? '- UGX ' : 'UGX ') + fmt(Math.abs(bal));
      outBox.className = 'money-balance ' + (bal < 0 ? 'is-over' : bal === 0 ? 'is-zero' : 'is-due');
      if (bal < 0) out.textContent += ' (more than agreed)';
    }
    if (save) save.textContent = n > 0 ? 'Save & create receipt' : 'Save changes';
  };
  const tidy = (el) => { if (!el) return; const v = num(el); el.value = v ? fmt(v) : ''; };

  agreed && agreed.addEventListener('input', () => { if (full && full.checked) now.value = fmt(remaining()); update(); });
  agreed && agreed.addEventListener('blur', () => tidy(agreed));
  if (now) {
    now.addEventListener('input', () => { if (full) full.checked = num(now) > 0 && num(now) === remaining(); update(); });
    now.addEventListener('blur', () => tidy(now));
  }
  if (full) full.addEventListener('change', () => {
    if (full.checked) {
      now.value = remaining() ? fmt(remaining()) : '';
      if (paidBox && remaining()) { paidBox.checked = true; paidBox.dispatchEvent(new Event('change')); }
    } else {
      now.value = '';
    }
    update();
  });
  // ticking "Paid" in the status row fills in the full amount when something is still owed
  if (paidBox && full) paidBox.addEventListener('change', () => {
    if (paidBox.checked && remaining() > 0 && !num(now)) { full.checked = true; now.value = fmt(remaining()); update(); }
    if (!paidBox.checked && full.checked) { full.checked = false; now.value = ''; update(); }
  });
  update();
})();

/* My account: show / hide the add-admin form */
(() => {
  const btn = document.getElementById('addAdminBtn'), form = document.getElementById('addAdminForm');
  if (!btn || !form) return;
  btn.addEventListener('click', () => { form.hidden = !form.hidden; if (!form.hidden) form.querySelector('input[name=name]').focus(); });
})();
