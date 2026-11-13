(() => {
  'use strict';
  const paper = document.getElementById('receipt');
  const toast = document.getElementById('rcToast');
  /* QR code: highest error correction (H, ~30%) so the small logo in the middle never stops it scanning */
  document.querySelectorAll('.rc-qr[data-qr]').forEach(box => {
    const canvas = box.querySelector('canvas');
    if (typeof qrcode !== 'function' || !canvas) { box.style.display = 'none'; return; }
    const qr = qrcode(0, 'H');
    qr.addData(box.dataset.qr);
    qr.make();
    const n = qr.getModuleCount(), quiet = 2, size = canvas.width, cell = size / (n + quiet * 2);
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#ffffff'; ctx.fillRect(0, 0, size, size);
    ctx.fillStyle = '#1b1a22';
    for (let r = 0; r < n; r++) for (let c = 0; c < n; c++) {
      if (qr.isDark(r, c)) ctx.fillRect(Math.floor((c + quiet) * cell), Math.floor((r + quiet) * cell), Math.ceil(cell), Math.ceil(cell));
    }
  });

  const say = (msg) => { if (!toast) return; toast.textContent = msg; toast.classList.add('show'); setTimeout(() => toast.classList.remove('show'), 2400); };

  const download = async (btn, type) => {
    if (typeof html2canvas !== 'function') { say('Download is not available, use Print / PDF instead.'); return; }
    btn.disabled = true;
    try {
      await document.fonts.ready;
      // render without the torn-edge mask so the image has clean edges, at 3x for a sharp file
      const canvas = await html2canvas(paper, {
        scale: 3, backgroundColor: '#ffffff', useCORS: true,
        onclone: (doc) => { const p = doc.getElementById('receipt'); p.style.webkitMask = 'none'; p.style.mask = 'none'; p.style.boxShadow = 'none'; }
      });
      const a = document.createElement('a');
      a.download = (btn.dataset.file || 'receipt') + (type === 'png' ? '.png' : '.jpg');
      a.href = canvas.toDataURL(type === 'png' ? 'image/png' : 'image/jpeg', 0.95);
      document.body.appendChild(a); a.click(); a.remove();
      say('Receipt downloaded.');
    } catch (e) {
      say('Could not create the image. Use Print / PDF instead.');
    } finally {
      btn.disabled = false;
    }
  };

  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-act]');
    if (!btn) return;
    const act = btn.dataset.act;
    if (act === 'print') window.print();
    if (act === 'png' || act === 'jpg') download(btn, act);
    if (act === 'copy') {
      try { await navigator.clipboard.writeText(btn.dataset.link); say('Receipt link copied.'); }
      catch { window.prompt('Copy this receipt link:', btn.dataset.link); }
    }
  });
})();
