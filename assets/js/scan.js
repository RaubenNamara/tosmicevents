(() => {
  'use strict';
  const video = document.getElementById('camVideo');
  const canvas = document.getElementById('camCanvas');
  const ctx = canvas.getContext('2d', { willReadFrequently: true });
  const idle = document.getElementById('vfIdle');
  const vf = document.getElementById('viewfinder');
  const msg = document.getElementById('camMsg');
  const btnStart = document.getElementById('camStart');
  const btnStop = document.getElementById('camStop');
  const btnSwitch = document.getElementById('camSwitch');
  const result = document.getElementById('scanResult');
  const csrf = document.getElementById('scanCsrf').value;
  const endpoint = document.getElementById('scanEndpoint').value;

  let stream = null, raf = null, busy = false, facing = 'environment', lastCode = '', lastAt = 0;
  const detector = ('BarcodeDetector' in window) ? (() => { try { return new BarcodeDetector({ formats: ['qr_code'] }); } catch { return null; } })() : null;

  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const ugx = (n) => n === null || n === undefined ? '-' : 'UGX ' + Number(n).toLocaleString('en-US');

  /* ---------- camera ---------- */
  async function start() {
    if (!navigator.mediaDevices?.getUserMedia) { msg.textContent = 'This browser cannot use the camera. Use "Scan from a photo" instead.'; return; }
    msg.textContent = 'Starting camera...';
    try {
      stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: facing }, width: { ideal: 1280 }, height: { ideal: 720 } }, audio: false });
      video.srcObject = stream;
      await video.play();
      vf.classList.add('is-live'); idle.hidden = true; btnStop.hidden = false;
      const cams = (await navigator.mediaDevices.enumerateDevices()).filter(d => d.kind === 'videoinput');
      btnSwitch.hidden = cams.length < 2;
      msg.textContent = '';
      loop();
    } catch (e) {
      msg.textContent = e.name === 'NotAllowedError' ? 'Camera permission was refused. Allow the camera in the browser bar, or scan from a photo.'
        : e.name === 'NotFoundError' ? 'No camera found on this device. Use "Scan from a photo" or type the number.'
        : 'Could not start the camera (' + e.name + '). The site must be opened over https or on localhost.';
    }
  }
  function stop() {
    cancelAnimationFrame(raf); raf = null;
    stream?.getTracks().forEach(t => t.stop()); stream = null;
    vf.classList.remove('is-live'); idle.hidden = false; btnStop.hidden = true; btnSwitch.hidden = true;
  }
  async function readFrame() {
    if (video.readyState < 2) return null;
    if (detector) {
      try { const codes = await detector.detect(video); if (codes[0]) return codes[0].rawValue; } catch { /* fall back to jsQR */ }
    }
    const w = 640, h = Math.round(video.videoHeight / video.videoWidth * w) || 360;
    canvas.width = w; canvas.height = h;
    ctx.drawImage(video, 0, 0, w, h);
    const img = ctx.getImageData(0, 0, w, h);
    const code = typeof jsQR === 'function' ? jsQR(img.data, w, h, { inversionAttempts: 'dontInvert' }) : null;
    return code ? code.data : null;
  }
  async function loop() {
    if (!stream) return;
    if (!busy) {
      const text = await readFrame();
      // ignore the same code for a few seconds so one receipt is not looked up again and again
      if (text && !(text === lastCode && Date.now() - lastAt < 4000)) { lastCode = text; lastAt = Date.now(); lookup(text); }
    }
    raf = requestAnimationFrame(loop);
  }

  /* ---------- photo ---------- */
  document.getElementById('scanFile').addEventListener('change', (e) => {
    const f = e.target.files?.[0];
    if (!f) return;
    const img = new Image();
    img.onload = () => {
      const scale = Math.min(1, 1600 / Math.max(img.width, img.height));
      canvas.width = Math.round(img.width * scale); canvas.height = Math.round(img.height * scale);
      ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
      const data = ctx.getImageData(0, 0, canvas.width, canvas.height);
      const code = jsQR(data.data, canvas.width, canvas.height);
      URL.revokeObjectURL(img.src);
      if (code) lookup(code.data); else show({ state: 'invalid', message: 'No QR code could be read in that photo. Try a sharper, closer photo of the code.' });
    };
    img.src = URL.createObjectURL(f);
    e.target.value = '';
  });

  /* ---------- typed number ---------- */
  document.getElementById('manualForm').addEventListener('submit', (e) => {
    e.preventDefault();
    const v = document.getElementById('manualNo').value.trim().toUpperCase();
    if (v) lookup(v);
  });

  /* ---------- server check ---------- */
  async function lookup(code) {
    busy = true;
    result.innerHTML = '<div class="sr-empty"><i class="fa-solid fa-spinner fa-spin"></i><h3>Checking...</h3></div>';
    try {
      const body = new FormData(); body.append('_csrf', csrf); body.append('code', code);
      const res = await fetch(endpoint, { method: 'POST', body, credentials: 'same-origin' });
      if (!res.ok) throw new Error(res.status === 403 ? 'Your session expired. Refresh the page.' : 'Server error ' + res.status);
      show(await res.json());
    } catch (err) {
      show({ state: 'invalid', message: err.message || 'Could not check the receipt.' });
    } finally {
      busy = false;
    }
  }

  function show(d) {
    const good = d.state === 'paid' || d.state === 'part';
    if (navigator.vibrate) navigator.vibrate(good ? 80 : [60, 60, 60]);
    const head = {
      paid: ['fa-circle-check', 'Valid receipt · Paid in full', 'ok'],
      part: ['fa-circle-half-stroke', 'Valid receipt · Part payment', 'part'],
      void: ['fa-ban', 'Receipt was voided', 'bad'],
      forged: ['fa-triangle-exclamation', 'QR does not match · possible fake', 'bad'],
      notfound: ['fa-circle-xmark', 'Receipt not found', 'bad'],
      invalid: ['fa-circle-question', 'Not a receipt code', 'bad'],
    }[d.state] || ['fa-circle-question', 'Unknown result', 'bad'];

    let html = `<div class="sr-head sr-${head[2]}"><i class="fa-solid ${head[0]}"></i><div><h3>${esc(head[1])}</h3>`;
    if (d.receipt_no) html += `<span>${esc(d.receipt_no)}${d.booking_ref ? ' · booking ' + esc(d.booking_ref) : ''}</span>`;
    html += '</div></div>';

    if (d.amount !== undefined) {
      html += `<div class="sr-amount"><small>Paid on this receipt</small><b>${ugx(d.amount)}</b><span>${esc(d.method)} · ${esc(d.paid_on)}</span></div>`;
      html += '<dl class="sr-dl">'
        + `<dt>Client</dt><dd>${esc(d.client)}<small>${esc(d.phone)}</small></dd>`
        + `<dt>For</dt><dd>${esc(d.package)}${d.event_date ? '<small>Event ' + esc(d.event_date) + '</small>' : ''}</dd>`
        + `<dt>Agreed total</dt><dd>${ugx(d.agreed)}</dd>`
        + `<dt>Paid so far</dt><dd class="ok">${ugx(d.paid_total)}</dd>`
        + `<dt>Balance now</dt><dd class="${d.balance ? 'due' : 'ok'}">${ugx(d.balance)}</dd>`
        + `<dt>Booking</dt><dd>${esc(d.booking_status)}${d.booking_paid ? ' · <span class="ok">Paid</span>' : ''}</dd>`
        + `<dt>Received by</dt><dd>${esc(d.received_by)}</dd>`
        + (d.state === 'void' ? `<dt>Void reason</dt><dd class="due">${esc(d.void_reason || 'Voided by admin')}</dd>` : '')
        + '</dl>';
      if (d.checked_by === 'number') html += '<p class="sr-note"><i class="fa-solid fa-circle-info"></i> Checked by number only. Scan the QR code to also confirm the printed receipt is genuine.</p>';
      html += `<div class="sr-actions"><a class="btn btn-light btn-sm" href="${esc(d.receipt_url)}"><i class="fa-solid fa-receipt"></i> Open receipt</a><a class="btn btn-light btn-sm" href="${esc(d.booking_url)}"><i class="fa-regular fa-eye"></i> View booking</a></div>`;
    } else {
      html += `<p class="sr-msg">${esc(d.message)}</p>`;
    }
    html += '<button type="button" class="btn btn-pink btn-block sr-next" id="scanNext"><i class="fa-solid fa-qrcode"></i> Scan another</button>';
    result.innerHTML = html;
    document.getElementById('scanNext').addEventListener('click', () => {
      lastCode = '';
      result.innerHTML = '<div class="sr-empty"><i class="fa-solid fa-receipt"></i><h3>Waiting for a receipt</h3><p class="muted">Point the camera at the next QR code.</p></div>';
      if (!stream) start();
    });
  }

  btnStart.addEventListener('click', start);
  btnStop.addEventListener('click', stop);
  btnSwitch.addEventListener('click', () => { facing = facing === 'environment' ? 'user' : 'environment'; stop(); start(); });
  window.addEventListener('pagehide', stop);
  document.addEventListener('visibilitychange', () => { if (document.hidden) stop(); });
})();
