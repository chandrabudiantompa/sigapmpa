'use strict';

(() => {
  const questions = [...document.querySelectorAll('.test-question')];
  const links = [...document.querySelectorAll('.question-numbers a')];
  const pager = document.querySelector('.question-pager');
  if (!questions.length || !pager) return;
  const previous = pager.querySelector('[data-previous]');
  const next = pager.querySelector('[data-next]');
  const status = pager.querySelector('[data-question-status]');
  let current = 0;
  let startTimer = () => {};
  let attemptId = null;
  let expired = false;
  const testType = questions[0].id.split('-')[0];
  let submitted = false;
  let submitting = false;
  const errorBox = document.querySelector('[data-submit-error]');
  const resultBox = document.querySelector('[data-test-result]');
  const popup = document.querySelector('[data-test-popup]');
  let popupPending = false;

  const MAX_VIOLATIONS = 3;
  const VIOLATION_COOLDOWN_MS = 2500;
  const violationKey = 'sigap-test-violations-v1-' + testType;
  let violationCount = 0;
  try {
    const savedV = Number(sessionStorage.getItem(violationKey));
    if (Number.isFinite(savedV) && savedV >= 0) violationCount = Math.min(MAX_VIOLATIONS, savedV);
  } catch {}
  let lastViolationAt = 0;
  let proctorActive = false;
  let forcedByViolation = false;
  let proctorCountdownInterval = null;
  let countdownFinished = false;
  let resultReady = null;

  const proctorOverlay = document.querySelector('[data-proctor-overlay]');
  const proctorCard = proctorOverlay?.querySelector('.proctor-card');
  const proctorTitle = proctorOverlay?.querySelector('[data-proctor-title]');
  const proctorMessage = proctorOverlay?.querySelector('[data-proctor-message]');
  const proctorBtn = proctorOverlay?.querySelector('[data-proctor-btn]');
  const proctorStrikes = proctorOverlay?.querySelector('[data-proctor-strikes]');
  const proctorStrikeText = proctorOverlay?.querySelector('[data-proctor-strike-text]');
  const proctorBadge = document.querySelector('[data-proctor-badge]');

  function updateProctorUI() {
    if (proctorBadge) {
      proctorBadge.textContent = `${violationCount}/${MAX_VIOLATIONS} Pelanggaran`;
      proctorBadge.classList.toggle('is-warning', violationCount > 0 && violationCount < MAX_VIOLATIONS);
      proctorBadge.classList.toggle('is-danger', violationCount >= MAX_VIOLATIONS);
    }
    if (proctorStrikes) {
      proctorStrikes.hidden = false;
      const dots = proctorStrikes.querySelectorAll?.('[data-strike]') || [];
      dots.forEach((dot, idx) => {
        dot.classList.toggle('is-active', idx < violationCount);
      });
    }
    if (proctorStrikeText) {
      proctorStrikeText.textContent = `Pelanggaran: ${violationCount} / ${MAX_VIOLATIONS}`;
    }
  }

  async function enterFullscreen() {
    try {
      if (typeof document !== 'undefined' && document.documentElement && typeof document.documentElement.requestFullscreen === 'function') {
        if (!document.fullscreenElement) {
          await document.documentElement.requestFullscreen();
        }
      }
    } catch (err) {
      console.warn('Fullscreen request rejected or not supported:', err);
    }
    if (proctorOverlay && violationCount < MAX_VIOLATIONS) {
      proctorOverlay.hidden = true;
    }
  }

  function showProctorGate() {
    if (!proctorOverlay) return;
    proctorCard?.classList.remove('is-warning', 'is-danger');
    if (proctorTitle) proctorTitle.textContent = 'Mode Layar Penuh Diperlukan';
    if (proctorMessage) {
      proctorMessage.textContent = 'Untuk menjaga keadilan dan integritas ujian, tes ini wajib dikerjakan dalam mode Layar Penuh (Full Screen).\n\n• Dilarang berpindah tab atau meminimalkan browser.\n• Dilarang keluar dari mode layar penuh.\n• Toleransi maksimal 3 kali pelanggaran sebelum tes otomatis diselesaikan.';
    }
    if (proctorStrikes) proctorStrikes.hidden = true;
    if (proctorBtn) {
      proctorBtn.hidden = false;
      proctorBtn.disabled = false;
      proctorBtn.textContent = 'Masuk Layar Penuh & Buka Soal';
    }
    proctorOverlay.hidden = false;
  }

  function showProctorWarning(reason) {
    if (!proctorOverlay) return;
    proctorCard?.classList.remove('is-danger');
    proctorCard?.classList.add('is-warning');
    if (proctorTitle) proctorTitle.textContent = `Peringatan Pelanggaran (${violationCount}/${MAX_VIOLATIONS})`;
    const remaining = MAX_VIOLATIONS - violationCount;
    if (proctorMessage) {
      proctorMessage.textContent = `Terdeteksi: ${reason}.\n\nDilarang berpindah tab, meminimalkan layar, atau keluar dari mode layar penuh selama tes.\n\nSisa toleransi: ${remaining} kali lagi. Jika mencapai ${MAX_VIOLATIONS} kali pelanggaran, tes Anda akan otomatis diselesaikan dan dikirim ke sistem.`;
    }
    updateProctorUI();
    if (proctorBtn) {
      proctorBtn.hidden = false;
      proctorBtn.disabled = false;
      proctorBtn.textContent = 'Kembali ke Layar Penuh & Lanjutkan';
    }
    proctorOverlay.hidden = false;
  }

  function showProctorLocked(reason, countdownSeconds = 10) {
    if (!proctorOverlay) {
      countdownFinished = true;
      return;
    }
    proctorCard?.classList.remove('is-warning');
    proctorCard?.classList.add('is-danger');
    if (proctorTitle) proctorTitle.textContent = `Batas Pelanggaran Tercapai (${MAX_VIOLATIONS}/${MAX_VIOLATIONS})`;
    if (proctorStrikes) proctorStrikes.hidden = false;
    updateProctorUI();

    let remaining = countdownSeconds;
    function updateCountdownDisplay() {
      if (proctorMessage) {
        proctorMessage.textContent = `Terdeteksi: ${reason}.\n\nAnda telah mencapai batas maksimal 3 kali pelanggaran integritas ujian (keluar layar penuh / berpindah tab).\n\nTes dihentikan secara otomatis. Jawaban Anda sedang dikirim dan Anda akan dialihkan ke halaman hasil dalam ${remaining} detik…`;
      }
      if (proctorBtn) {
        proctorBtn.hidden = false;
        proctorBtn.disabled = true;
        proctorBtn.textContent = `Beralih ke hasil tes dalam ${remaining} detik…`;
      }
    }
    updateCountdownDisplay();
    proctorOverlay.hidden = false;

    if (typeof clearInterval === 'function') clearInterval(proctorCountdownInterval);
    if (typeof setInterval === 'function') {
      proctorCountdownInterval = setInterval(() => {
        remaining--;
        if (remaining <= 0) {
          if (typeof clearInterval === 'function') clearInterval(proctorCountdownInterval);
          countdownFinished = true;
          if (proctorBtn) {
            proctorBtn.textContent = 'Memuat hasil tes…';
          }
          if (resultReady) {
            if (proctorOverlay) proctorOverlay.hidden = true;
            showResult(resultReady);
          }
        } else {
          updateCountdownDisplay();
        }
      }, 1000);
    } else {
      countdownFinished = true;
      if (resultReady) {
        if (proctorOverlay) proctorOverlay.hidden = true;
        showResult(resultReady);
      }
    }
  }

  function handleViolation(reason) {
    if (!proctorActive || submitted || expired || submitting || popupPending) return;
    const now = performance.now();
    if (now - lastViolationAt < VIOLATION_COOLDOWN_MS) return;
    lastViolationAt = now;

    violationCount = Math.min(MAX_VIOLATIONS, violationCount + 1);
    try { sessionStorage.setItem(violationKey, String(violationCount)); } catch {}

    updateProctorUI();

    if (violationCount >= MAX_VIOLATIONS) {
      forcedByViolation = true;
      showProctorLocked(reason, 10);
      submitAnswers(true);
    } else {
      showProctorWarning(reason);
    }
  }

  proctorBtn?.addEventListener('click', enterFullscreen);

  if (typeof document !== 'undefined' && typeof document.addEventListener === 'function') {
    document.addEventListener('fullscreenchange', () => {
      if (!proctorActive || submitted || expired) return;
      if (!document.fullscreenElement) {
        handleViolation('Keluar dari mode layar penuh (Full Screen)');
      } else {
        if (proctorOverlay && violationCount < MAX_VIOLATIONS) {
          proctorOverlay.hidden = true;
        }
      }
    });

    document.addEventListener('visibilitychange', () => {
      if (!proctorActive || submitted || expired) return;
      if (document.hidden) {
        handleViolation('Berpindah tab atau meminimalkan browser');
      }
    });
  }

  if (typeof window !== 'undefined' && typeof window.addEventListener === 'function') {
    window.addEventListener('blur', () => {
      if (!proctorActive || submitted || expired || popupPending) return;
      handleViolation('Fokus layar berpindah ke luar jendela ujian');
    });

    window.addEventListener('beforeunload', event => {
      if (!proctorActive || submitted || expired) return;
      event.preventDefault();
      event.returnValue = '';
    });
  }

  function expireTest() {
    if (expired || submitted) return;
    expired = true;
    proctorActive = false;
    if (typeof clearInterval === 'function') clearInterval(proctorCountdownInterval);
    if (proctorOverlay) proctorOverlay.hidden = true;
    questions.forEach(question => question.querySelectorAll('input, button').forEach(input => { input.disabled = true; }));
    if (popup.open) popup.close('cancel');
    showError('Waktu tes sudah habis. Jawaban tidak dapat diubah atau dikirim lagi. Hubungi HR untuk tindak lanjut.');
    render();
  }

  function showPopup(message, confirmation = false) {
    if (popupPending) return Promise.resolve(false);
    popupPending = true;
    popup.querySelector('#popup-title').textContent = confirmation ? 'Kirim jawaban Anda?' : 'Periksa kembali';
    popup.querySelector('#popup-message').textContent = message;
    const cancel = popup.querySelector('[data-popup-cancel]');
    const confirm = popup.querySelector('[data-popup-confirm]');
    cancel.hidden = !confirmation;
    confirm.textContent = confirmation ? 'Ya, submit jawaban' : 'Mengerti';
    popup.returnValue = '';
    return new Promise(resolve => {
      popup.addEventListener('close', () => {
        popupPending = false;
        resolve(popup.returnValue === 'confirm');
      }, { once: true });
      popup.showModal();
      (confirmation ? cancel : confirm).focus();
    });
  }

  function showError(message) {
    errorBox.textContent = message;
    errorBox.hidden = !message;
    if (message) showPopup(message);
  }
  async function apiRequest(options = {}) {
    const response = await fetch(`recruitment-api.php?test=${testType}`, {
      credentials: 'same-origin', cache: 'no-store', ...options
    });
    let data;
    try { data = await response.json(); } catch {
      throw new Error('Layanan penilaian tidak tersedia. Jalankan halaman melalui server PHP dan coba lagi.');
    }
    if (!response.ok || !data.ok) {
      if (data.error === 'test_expired') expireTest();
      throw new Error(data.message || 'Jawaban belum berhasil dikirim. Silakan coba lagi.');
    }
    return data;
  }
  function showResult(result, focus = true) {
    submitted = true;
    proctorActive = false;
    if (typeof clearInterval === 'function') clearInterval(proctorCountdownInterval);
    if (proctorOverlay) proctorOverlay.hidden = true;
    showError('');
    const container = resultBox.querySelector('[data-result-content]');
    container.replaceChildren();
    function line(label, value) {
      const row = document.createElement('p');
      const strong = document.createElement('strong');
      strong.textContent = label + ': ';
      row.append(strong, document.createTextNode(String(value)));
      container.append(row);
    }
    if (result.test === 'aptitude') {
      line('Nilai', `${result.percentage} / 100`);
      line('Benar', result.correct);
      line('Salah', result.incorrect);
      line('Tidak dijawab', result.unanswered);
      line('Total soal', result.total);
    } else {
      line('Kecenderungan dominan', result.dominant.join(', '));
      const table = document.createElement('table');
      table.className = 'result-table';
      const header = table.createTHead().insertRow();
      ['Dimensi', 'Paling Mirip', 'Paling Tidak Mirip', 'Skor'].forEach(label => {
        const cell = document.createElement('th'); cell.scope = 'col'; cell.textContent = label; header.append(cell);
      });
      const body = table.createTBody();
      for (const dimension of ['D', 'I', 'S', 'C']) {
        const row = body.insertRow();
        [dimension, result.most[dimension], result.least[dimension], result.net[dimension]].forEach(value => { row.insertCell().textContent = String(value); });
      }
      container.append(table);
      line('Catatan', 'Profil gaya kerja draft internal untuk bahan wawancara HR, bukan penentu lulus atau gagal.');
    }
    if (forcedByViolation || violationCount >= MAX_VIOLATIONS) {
      line('Catatan Pengawas', `Tes otomatis diselesaikan dan disubmit karena mencapai batas toleransi ${MAX_VIOLATIONS} kali pelanggaran (keluar layar penuh / berpindah tab).`);
    } else if (violationCount > 0) {
      line('Catatan Pengawas', `Tercatat ${violationCount} kali pelanggaran integritas (keluar layar penuh / berpindah tab) selama pengerjaan tes.`);
    }
    line('Waktu submit', new Date(result.submitted_at).toLocaleString('id-ID'));
    line('Penyimpanan', 'Hasil tersimpan sementara dalam sesi server. Belum terhubung ke daftar hasil HR.');
    document.querySelector('.question-layout').hidden = true;
    document.querySelector('[data-save-notice]').hidden = true;
    document.querySelector('[data-test-instructions]').hidden = true;
    resultBox.hidden = false;
    if (focus) { resultBox.querySelector('h2').focus(); resultBox.scrollIntoView({ block: 'start' }); }
  }
  async function submitAnswers(forced = false) {
    if (submitting || submitted || (!forced && popupPending) || expired || !attemptId) return;
    const answers = {};
    let missing = 0;
    let firstMissing = null;
    questions.forEach((question, index) => {
      const selected = [...question.querySelectorAll('[data-answer]:checked')];
      if (testType === 'aptitude') {
        if (selected.length) answers[index + 1] = selected[0].value;
        else missing++;
      } else {
        const most = selected.find(input => input.dataset.mode === 'most')?.value;
        const least = selected.find(input => input.dataset.mode === 'least')?.value;
        if (!most || !least || most === least) { missing++; firstMissing ??= question.id; }
        else answers[index + 1] = { most, least };
      }
    });
    if (!forced && testType === 'workstyle' && missing) {
      location.hash = firstMissing;
      showError(`Masih ada ${missing} grup belum lengkap. Pilih Paling Mirip dan Paling Tidak Mirip pada pernyataan berbeda.`);
      return;
    }
    if (forced && testType === 'workstyle') {
      questions.forEach((question, index) => {
        const qNum = index + 1;
        if (!answers[qNum] || !answers[qNum].most || !answers[qNum].least || answers[qNum].most === answers[qNum].least) {
          const inputs = [...question.querySelectorAll('[data-answer]')];
          const selectedMost = inputs.find(input => input.dataset.mode === 'most' && input.checked)?.value;
          const selectedLeast = inputs.find(input => input.dataset.mode === 'least' && input.checked)?.value;
          let most = selectedMost;
          let least = selectedLeast;
          if (!most) most = 'A';
          if (!least || least === most) least = most === 'A' ? 'B' : 'A';
          answers[qNum] = { most, least };
        }
      });
    }
    if (!forced) {
      const marked = questions.filter(question => question.classList.contains('is-marked')).length;
      const prompt = `${missing ? `${missing} soal belum dijawab dan akan bernilai 0.\n` : ''}${marked ? `${marked} soal masih ditandai.\n` : ''}Submit jawaban sekarang? Jawaban tidak dapat diubah setelah dikirim.`;
      if (!await showPopup(prompt, true)) return;
    }
    if (submitted || expired) return;
    submitting = true;
    showError('');
    next.disabled = true;
    next.textContent = 'Mengirim…';
    questions.forEach(question => question.querySelectorAll('input, button').forEach(input => { input.disabled = true; }));
    try {
      const session = await apiRequest();
      if (session.result) { showResult(session.result); return; }
      if (session.expired) { expireTest(); return; }
      if (session.attempt?.id !== attemptId) throw new Error('Sesi tes berubah. Muat ulang halaman sebelum melanjutkan.');
      const data = await apiRequest({ method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': session.csrf }, body: JSON.stringify({ test: testType, action: 'submit', attempt_id: attemptId, answers }) });
      if (forcedByViolation && !countdownFinished) {
        resultReady = data.result;
      } else {
        showResult(data.result);
      }
    } catch (error) {
      if (forcedByViolation && proctorMessage) {
        proctorMessage.textContent = 'Gagal mengirim jawaban secara otomatis: ' + (error.message || 'Koneksi terputus') + '.\n\nSilakan coba kirim ulang.';
        if (proctorBtn) {
          proctorBtn.disabled = false;
          proctorBtn.textContent = 'Coba Kirim Ulang';
          proctorBtn.onclick = () => { submitAnswers(true); };
        }
      } else {
        showError(error.message || 'Koneksi terputus. Jawaban tetap tersedia; silakan coba submit lagi.');
      }
    } finally {
      submitting = false;
      if (!submitted) {
        questions.forEach(question => question.querySelectorAll('input, button').forEach(input => { input.disabled = expired || (forcedByViolation && violationCount >= MAX_VIOLATIONS); }));
        render();
      }
    }
  }

  const draftKey = 'sigap-test-answers-v1-' + questions[0].id.split('-')[0];
  let draft = {};
  try {
    const saved = JSON.parse(sessionStorage.getItem(draftKey));
    if (saved && typeof saved === 'object' && !Array.isArray(saved)) draft = saved;
  } catch { /* Start with an empty draft if storage is unavailable. */ }
  function persistDraft() {
    try { sessionStorage.setItem(draftKey, JSON.stringify(draft)); }
    catch {
      document.querySelector('[data-save-notice]').textContent = 'Pilihan hanya tersedia selama halaman ini terbuka. Penyimpanan browser tidak tersedia; jawaban belum dikirim ke HR.';
    }
  }
  questions.forEach((question, index) => {
    const inputs = [...question.querySelectorAll('[data-answer]')];
    const flag = question.querySelector('[data-flag]');
    const saved = draft[question.id];
    let marked = saved?.marked === true;
    inputs.forEach(input => {
      input.checked = typeof saved?.answers?.[input.name] === 'string' && saved.answers[input.name] === input.value;
    });
    const most = inputs.find(input => input.dataset.mode === 'most' && input.checked);
    inputs.filter(input => input.dataset.mode === 'least' && input.checked && input.value === most?.value).forEach(input => { input.checked = false; });

    function updateDraft() {
      const answers = {};
      inputs.filter(input => input.checked).forEach(input => { answers[input.name] = input.value; });
      draft[question.id] = { answers, marked };
      const complete = Object.keys(answers).length === (inputs.some(input => input.dataset.mode) ? 2 : 1);
      links[index].classList.toggle('is-answered', complete);
      links[index].classList.toggle('is-marked', marked);
      links[index].setAttribute('aria-label', `${question.querySelector('h3').textContent}${complete ? ', sudah dijawab' : ', belum lengkap'}${marked ? ', ditandai' : ''}`);
      question.classList.toggle('is-marked', marked);
      flag.setAttribute('aria-pressed', String(marked));
      flag.querySelector('[data-flag-label]').textContent = marked ? 'Ditandai' : 'Tandai soal';
    }
    inputs.forEach(input => input.addEventListener('change', () => {
      // Moving a workstyle choice to the other column clears the conflicting choice.
      if (input.dataset.mode && input.checked) {
        inputs.filter(other => other.dataset.mode !== input.dataset.mode && other.value === input.value).forEach(other => { other.checked = false; });
      }
      updateDraft();
      persistDraft();
    }));
    flag.addEventListener('click', () => { marked = !marked; updateDraft(); persistDraft(); });
    updateDraft();
  });

  const timer = document.querySelector('[data-test-timer]');
  if (timer) {
    const display = timer.querySelector('[data-time-left]');
    const message = timer.querySelector('[data-timer-message]');
    let remainingAtSync = null;
    let syncedAt = 0;
    let interval;

    function updateTimer() {
      if (submitted) { clearInterval(interval); return; }
      if (remainingAtSync === null) return;
      const seconds = Math.max(0, Math.ceil((remainingAtSync - (performance.now() - syncedAt)) / 1000));
      display.textContent = `${String(Math.floor(seconds / 60)).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
      timer.classList.toggle('timer-warning', seconds > 0 && seconds <= 300);
      timer.classList.toggle('timer-expired', seconds === 0);
      const text = seconds === 0 ? 'Waktu habis. Sesi waktu telah selesai.' : seconds <= 300 ? 'Waktu tersisa kurang dari atau sama dengan 5 menit.' : 'Waktu berjalan. Pergantian soal tidak menghentikan timer.';
      if (message.textContent !== text) message.textContent = text;
      if (seconds === 0) { clearInterval(interval); expireTest(); }
    }

    startTimer = data => {
      remainingAtSync = Math.max(0, data.attempt.deadline - data.server_now) * 1000;
      syncedAt = performance.now();
      clearInterval(interval);
      interval = setInterval(updateTimer, 1000);
      updateTimer();
    };
    async function syncTimer() {
      if (!attemptId || submitted) return;
      try {
        const data = await apiRequest();
        if (data.result) { showResult(data.result, false); return; }
        if (data.attempt?.id !== attemptId) { expireTest(); return; }
        startTimer(data);
      } catch { updateTimer(); }
    }
    document.addEventListener('visibilitychange', () => { if (!document.hidden) syncTimer(); });
    window.addEventListener('pageshow', syncTimer);
  }

  function render(moveFocus = false) {
    if (submitted) return;
    const requested = questions.findIndex(question => '#' + question.id === location.hash);
    current = requested < 0 ? 0 : requested;
    questions.forEach((question, index) => { question.hidden = index !== current; });
    links.forEach((link, index) => {
      if (index === current) link.setAttribute('aria-current', 'step');
      else link.removeAttribute('aria-current');
    });
    previous.disabled = current === 0;
    next.disabled = submitting || (expired && current === questions.length - 1);
    next.textContent = submitting ? 'Mengirim…' : current === questions.length - 1 ? 'Submit jawaban' : 'Selanjutnya →';
    next.classList.toggle('submit-answers', current === questions.length - 1);
    status.textContent = `${current + 1} / ${questions.length}`;
    pager.hidden = false;
    if (moveFocus && requested >= 0) {
      const heading = questions[current].querySelector('h3');
      heading.tabIndex = -1;
      heading.focus({ preventScroll: true });
      questions[current].scrollIntoView({ block: 'start' });
    }
  }

  previous.addEventListener('click', () => {
    if (current > 0) location.hash = questions[current - 1].id;
  });
  next.addEventListener('click', () => {
    if (current < questions.length - 1) location.hash = questions[current + 1].id;
    else submitAnswers();
  });
  links.forEach(link => link.addEventListener('click', () => {
    if (link.hash === location.hash) render(true);
  }));
  window.addEventListener('hashchange', () => render(true));
  render();
  const loading = document.querySelector('[data-test-loading]');
  const loadingMessage = document.querySelector('[data-loading-message]');
  const retry = document.querySelector('[data-loading-retry]');
  async function loadSession() {
    retry.hidden = true;
    loadingMessage.textContent = 'Memuat status tes…';
    try {
      const data = await apiRequest();
      if (data.result) showResult(data.result, false);
      else {
        if (!data.attempt) {
          loadingMessage.textContent = 'Mulai tes dari halaman pilihan agar sesi dan waktu tes dapat disiapkan.';
          document.querySelector('[data-start-link]').hidden = false;
          return;
        }
        attemptId = data.attempt.id;
        document.querySelector('.question-layout').hidden = false;
        document.querySelector('[data-save-notice]').hidden = false;
        document.querySelector('[data-test-instructions]').hidden = false;
        render(questions.some(question => '#' + question.id === location.hash));
        startTimer(data);
        if (data.expired) expireTest();
        else {
          updateProctorUI();
          if (violationCount >= MAX_VIOLATIONS) {
            proctorActive = true;
            forcedByViolation = true;
            showProctorLocked('Batas maksimal pelanggaran telah tercapai.', 10);
            submitAnswers(true);
          } else {
            proctorActive = true;
            if (typeof document !== 'undefined' && typeof document.fullscreenElement !== 'undefined') {
              if (!document.fullscreenElement) {
                showProctorGate();
              }
            }
          }
        }
      }
      loading.hidden = true;
    } catch {
      loadingMessage.textContent = 'Status tes belum dapat dimuat. Periksa koneksi lalu coba lagi.';
      retry.hidden = false;
    }
  }
  retry.addEventListener('click', loadSession);
  loadSession();
})();
