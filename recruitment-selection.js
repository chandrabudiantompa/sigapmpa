'use strict';

(() => {
  const popup = document.querySelector('[data-selection-popup]');
  if (!popup || typeof popup.showModal !== 'function') return;
  let destination = null;
  let starting = false;
  document.querySelectorAll('.test-choice').forEach(card => {
    card.addEventListener('click', event => {
      if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button > 0) return;
      event.preventDefault();
      if (popup.open) return;
      destination = { href: card.href, test: card.dataset.test, duration: Number(card.dataset.durationMinutes) * 60000 };
      const title = card.querySelector('h2') ? card.querySelector('h2').textContent.trim() : '';
      const isWorkstyle = (card.classList && card.classList.contains('workstyle-choice')) || card.dataset.test === 'workstyle';

      const titleEl = popup.querySelector('#selection-popup-title');
      if (titleEl) titleEl.textContent = `Tes ${title.replace(/^Tes\s*/i, '')}`;

      const metaSpans = card.querySelectorAll ? card.querySelectorAll('.choice-meta span') : [];
      const metaEl = popup.querySelector('#selection-popup-meta');
      if (metaEl) {
        metaEl.textContent = metaSpans.length
          ? [...metaSpans].map(s => s.textContent).join(' · ')
          : (isWorkstyle ? '24 grup pernyataan · 45 menit' : '50 butir soal · 60 menit');
      }

      const instrEl = popup.querySelector('#selection-instruction-text');
      if (instrEl) {
        instrEl.textContent = isWorkstyle
          ? 'Pilih satu Paling Mirip dan satu Paling Tidak Mirip pada pernyataan berbeda di setiap grup.'
          : 'Pilih satu jawaban untuk setiap soal. Anda dapat menandai soal untuk ditinjau kembali.';
      }

      const errBanner = popup.querySelector('#selection-popup-error');
      if (errBanner) errBanner.hidden = true;

      const msgEl = popup.querySelector('#selection-popup-message');
      if (msgEl) msgEl.textContent = '';

      popup.returnValue = '';
      popup.showModal();
      popup.querySelector('[data-popup-cancel]').focus();
    });
  });
  popup.addEventListener('cancel', event => { if (starting) event.preventDefault(); });
  popup.querySelector('form').addEventListener('submit', async event => {
    if (event.submitter?.value !== 'continue') return;
    event.preventDefault();
    if (starting || !destination) return;
    starting = true;
    const target = destination;
    const confirm = popup.querySelector('[data-popup-confirm]');
    const cancel = popup.querySelector('[data-popup-cancel]');
    confirm.disabled = cancel.disabled = true;
    confirm.textContent = 'Memulai…';
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 15000);
    async function request(options = {}) {
      const response = await fetch(`recruitment-api.php?test=${target.test}`, { credentials: 'same-origin', cache: 'no-store', signal: controller.signal, ...options });
      const data = await response.json();
      if (!response.ok || !data.ok) throw new Error(data.message || 'Tes belum dapat dimulai.');
      return data;
    }
    try {
      let data = await request();
      if (!data.result && !data.attempt) {
        data = await request({ method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': data.csrf }, body: JSON.stringify({ test: target.test, action: 'start' }) });
      }
      if (data.expired) throw new Error('Waktu sesi tes ini sudah habis. Hubungi HR untuk tindak lanjut.');
      try {
        if (typeof document !== 'undefined' && document.documentElement?.requestFullscreen && !document.fullscreenElement) {
          await document.documentElement.requestFullscreen();
        }
      } catch { /* Handled on target page proctor gate */ }
      window.location.assign(target.href);
    } catch (error) {
      const errBanner = popup.querySelector('#selection-popup-error');
      if (errBanner) errBanner.hidden = false;
      popup.querySelector('#selection-popup-message').textContent = error.name === 'AbortError' ? 'Koneksi terlalu lama. Coba lagi; waktu sesi yang sudah dimulai tetap berjalan.' : error.message;
    } finally {
      clearTimeout(timeout);
      starting = false;
      confirm.disabled = cancel.disabled = false;
      confirm.textContent = 'Mulai tes';
    }
  });
})();
