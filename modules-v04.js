'use strict';

(() => {
  STORAGE.variableComponents = 'mpa_workforce_variable_components_v1';
  state.variableComponents = [];
  state.previewVariableId = null;

  const categoryLabels = {
    project_bonus: 'Bonus Proyek',
    project_incentive: 'Insentif Proyek',
    travel_allowance: 'Uang Harian/Tunjangan Dinas',
    salary_adjustment: 'Rapel/Penyesuaian Gaji',
    other_income: 'Pendapatan Lain',
    other_deduction: 'Potongan Lain',
    reimbursement: 'Reimbursement'
  };

  const treatmentLabels = {
    earning: 'Penghasilan',
    deduction: 'Potongan',
    reimbursement: 'Reimbursement'
  };

  function categoryTreatment(category) {
    if (category === 'other_deduction') return 'deduction';
    if (category === 'reimbursement') return 'reimbursement';
    return 'earning';
  }

  function componentLabel(component) {
    return component.label || categoryLabels[component.category] || 'Komponen Variabel';
  }

  function componentStatusLabel(status) {
    return ({ draft: 'Draft', approved: 'Disetujui', void: 'Dibatalkan' })[status] || status;
  }

  function tripSettlementLabel(trip) {
    return trip.settlementStatus === 'settled' ? 'Selesai' : trip.status === 'approved' ? 'Menunggu realisasi' : '-';
  }

  function isEmployeePeriodLocked(period, nik) {
    return state.payslips.some(slip => slip.period === period && slip.nik === nik && slip.status === 'published');
  }

  function seedDemoVariableComponents() {
    const period = monthKey();
    state.variableComponents = [
      {
        id: randomId('VAR'), period, nik: 'MPA001', name: 'Karyawan Demo', project: 'Proyek Demo MPA',
        category: 'project_bonus', label: 'Bonus Penyelesaian Tahap Proyek', treatment: 'earning', amount: 750000,
        description: 'Contoh bonus proyek bulanan yang sudah disetujui.', status: 'approved', source: 'manual',
        createdAt: new Date().toISOString(), createdBy: 'Administrator MPA', approvedAt: new Date().toISOString(), approvedBy: 'Administrator MPA'
      },
      {
        id: randomId('VAR'), period, nik: 'MPA001', name: 'Karyawan Demo', project: 'Proyek Demo MPA',
        category: 'reimbursement', label: 'Reimbursement Transport Proyek', treatment: 'reimbursement', amount: 250000,
        description: 'Contoh biaya transport aktual yang dibayarkan bersama payroll tetapi tidak menambah bruto gaji.', status: 'approved', source: 'manual',
        createdAt: new Date().toISOString(), createdBy: 'Administrator MPA', approvedAt: new Date().toISOString(), approvedBy: 'Administrator MPA'
      }
    ];
  }

  function seedDemoTrip() {
    if (state.requests.some(request => request.type === 'trip')) return;
    const start = new Date();
    start.setDate(start.getDate() + 6);
    const end = new Date(start);
    end.setDate(end.getDate() + 2);
    state.requests.push({
      id: randomId('REQ'), nik: 'MPA003', name: 'Rina Maharani', department: 'Purchasing', type: 'trip',
      project: 'Pengadaan Proyek Demo', destination: 'Malang', startDate: dateKey(start), endDate: dateKey(end), days: 3,
      requestedAdvance: 1500000, estimatedExpense: 1200000, estimatedAllowance: 450000,
      reason: 'Koordinasi vendor dan verifikasi material proyek.', status: 'pending', settlementStatus: 'not_started',
      createdAt: new Date().toISOString(), decisionNote: ''
    });
  }

  const basePersist = persist;
  persist = function persistV04() {
    basePersist();
    saveJSON(STORAGE.variableComponents, state.variableComponents || []);
  };

  const baseInitData = initData;
  initData = function initDataV04() {
    const hasComponents = Boolean(localStorage.getItem(STORAGE.variableComponents));
    state.variableComponents = loadJSON(STORAGE.variableComponents, []);
    if (!hasComponents) seedDemoVariableComponents();
    const hadRequests = Boolean(localStorage.getItem(STORAGE.requests));
    baseInitData();
    if (!hadRequests) seedDemoTrip();
    persist();
  };

  const baseInitElements = initElements;
  initElements = function initElementsV04() {
    baseInitElements();
    [
      'openTripFormBtn','tripDialog','tripForm','tripProject','tripDestination','tripStart','tripEnd','tripAdvance','tripEstimatedExpense','tripEstimatedAllowance','tripPurpose',
      'adminVariablePanel','variableMonth','addVariableBtn','exportVariableBtn','variableIncomeTotal','variableDeductionTotal','variableReimbursementTotal','tripOpenCount','variableComponentBody','businessTripBody',
      'variableDialog','variableForm','variableDialogTitle','variableId','variablePeriod','variableNik','variableProject','variableCategory','variableTreatment','variableAmount','variableDescription','variableStatus',
      'tripSettlementDialog','tripSettlementForm','tripSettlementTitle','tripSettlementId','tripPayrollMonth','tripAdvancePaid','tripActualExpense','tripFinalAllowance','tripReimbursementPayable','tripRefundDue','tripDeductRefund','tripSettlementNote','tripSettlementSummary'
    ].forEach(id => { els[id] = $(id); });
  };

  const baseBindEvents = bindEvents;
  bindEvents = function bindEventsV04() {
    baseBindEvents();
    els.openTripFormBtn?.addEventListener('click', openTripDialog);
    els.tripForm?.addEventListener('submit', submitTripRequest);
    els.variableMonth?.addEventListener('change', renderVariableModule);
    els.addVariableBtn?.addEventListener('click', () => openVariableDialog());
    els.exportVariableBtn?.addEventListener('click', exportVariableExcel);
    els.variableForm?.addEventListener('submit', saveVariableComponent);
    els.variableCategory?.addEventListener('change', () => { els.variableTreatment.value = categoryTreatment(els.variableCategory.value); });
    els.variableComponentBody?.addEventListener('click', handleVariableAction);
    els.businessTripBody?.addEventListener('click', handleTripAdminAction);
    els.tripSettlementForm?.addEventListener('submit', saveTripSettlement);
    [els.tripAdvancePaid, els.tripActualExpense, els.tripFinalAllowance, els.tripReimbursementPayable, els.tripRefundDue].forEach(input => input?.addEventListener('input', updateTripSettlementSummary));
  };

  const baseRenderAdmin = renderAdmin;
  renderAdmin = function renderAdminV04() {
    baseRenderAdmin();
    if (els.variableMonth) els.variableMonth.value ||= els.payrollMonth.value || monthKey();
    renderVariableModule();
  };

  const baseShowAdminPanel = showAdminPanel;
  showAdminPanel = function showAdminPanelV04(panelId, button) {
    baseShowAdminPanel(panelId, button);
    if (panelId === 'adminVariablePanel') renderVariableModule();
  };

  const baseRequestLabel = requestLabel;
  requestLabel = function requestLabelV04(request) {
    if (request?.type === 'trip') return 'Perjalanan Dinas';
    return baseRequestLabel(request);
  };

  requestItemHtml = function requestItemHtmlV04(request) {
    let period;
    if (request.type === 'overtime') period = `${formatDate(request.date)} · ${request.startTime}–${request.endTime} (${request.hours} jam)`;
    else if (request.type === 'trip') period = `${formatDate(request.startDate)}–${formatDate(request.endDate)} (${request.days} hari) · ${request.destination}`;
    else period = `${formatDate(request.startDate)}–${formatDate(request.endDate)} (${request.days} hari)`;
    const settlement = request.type === 'trip' && request.status === 'approved'
      ? `<p class="request-reason"><strong>Realisasi:</strong> ${escapeHtml(tripSettlementLabel(request))}${request.payrollPeriod ? ` · Payroll ${escapeHtml(formatPeriodLabel(request.payrollPeriod))}` : ''}</p>`
      : '';
    return `<article class="request-item"><div class="request-head"><strong>${escapeHtml(requestLabel(request))}</strong><span class="status-chip ${escapeHtml(request.status)}">${escapeHtml(statusLabel(request.status))}</span></div><div class="request-meta"><span>${escapeHtml(period)}</span><span>Diajukan ${escapeHtml(formatDateTime(request.createdAt))}</span></div>${request.type === 'trip' ? `<p class="request-reason"><strong>Proyek:</strong> ${escapeHtml(request.project)} · Uang muka ${escapeHtml(formatCurrency(request.requestedAdvance || 0))}</p>` : ''}<p class="request-reason">${escapeHtml(request.reason)}</p>${settlement}${request.decisionNote ? `<p class="request-reason"><strong>Catatan:</strong> ${escapeHtml(request.decisionNote)}</p>` : ''}</article>`;
  };

  renderEmployeeActivity = function renderEmployeeActivityV04() {
    const attendanceItems = state.records.filter(record => record.nik === state.user.nik).map(record => ({
      time: record.updatedAt || record.createdAt,
      title: `Absensi ${formatDate(record.date)}`,
      detail: `${record.checkIn || '--:--'}–${record.checkOut || '--:--'} · ${record.lateMinutes ? `telat ${record.lateMinutes} menit` : 'tepat waktu'}`,
      level: record.lateMinutes ? 'warning' : 'success'
    }));
    const requestItems = state.requests.filter(request => request.nik === state.user.nik).map(request => ({
      time: request.decidedAt || request.createdAt,
      title: `${requestLabel(request)} · ${statusLabel(request.status)}`,
      detail: request.type === 'overtime'
        ? `${formatDate(request.date)} · ${request.hours} jam`
        : request.type === 'trip'
          ? `${request.project} · ${request.destination} · ${request.days} hari`
          : `${formatDate(request.startDate)}–${formatDate(request.endDate)}`,
      level: request.status === 'approved' ? 'success' : request.status === 'rejected' ? 'danger' : 'warning'
    }));
    const items = [...attendanceItems, ...requestItems].sort((a, b) => new Date(b.time) - new Date(a.time)).slice(0, 6);
    els.employeeActivity.innerHTML = items.length ? items.map(item => timelineHtml(item)).join('') : emptyState('Belum ada aktivitas', 'Absensi dan pengajuan akan tampil di sini.');
  };

  approvalCardHtml = function approvalCardHtmlV04(request) {
    let period;
    let amount;
    if (request.type === 'overtime') {
      period = `${formatDate(request.date)} · ${request.startTime}–${request.endTime}`;
      amount = `${request.hours} jam`;
    } else if (request.type === 'trip') {
      period = `${formatDate(request.startDate)}–${formatDate(request.endDate)} · ${request.destination}`;
      amount = `${request.days} hari · UM ${formatCurrency(request.requestedAdvance || 0)}`;
    } else {
      period = `${formatDate(request.startDate)}–${formatDate(request.endDate)}`;
      amount = `${request.days} hari`;
    }
    const extra = request.type === 'trip'
      ? `<p class="request-reason"><strong>Proyek:</strong> ${escapeHtml(request.project)} · Estimasi operasional ${escapeHtml(formatCurrency(request.estimatedExpense || 0))} · Uang harian ${escapeHtml(formatCurrency(request.estimatedAllowance || 0))}</p>`
      : '';
    return `<article class="approval-card"><div class="approval-top"><div><h3>${escapeHtml(request.name)}</h3><p class="muted">${escapeHtml(request.nik)} · ${escapeHtml(request.department)}</p></div><span class="status-chip ${escapeHtml(request.status)}">${escapeHtml(statusLabel(request.status))}</span></div><div class="approval-grid"><div><small>Jenis</small><strong>${escapeHtml(requestLabel(request))}</strong></div><div><small>Periode</small><strong>${escapeHtml(period)}</strong></div><div><small>Durasi/Nilai</small><strong>${escapeHtml(amount)}</strong></div><div><small>Diajukan</small><strong>${escapeHtml(formatDateTime(request.createdAt))}</strong></div></div>${extra}<p class="request-reason">${escapeHtml(request.reason)}</p>${request.decisionNote ? `<p class="request-reason"><strong>Catatan:</strong> ${escapeHtml(request.decisionNote)}</p>` : ''}${request.status === 'pending' ? `<div class="approval-actions"><button class="btn small secondary" data-decision="rejected" data-request-id="${escapeHtml(request.id)}" type="button">Tolak</button><button class="btn small primary" data-decision="approved" data-request-id="${escapeHtml(request.id)}" type="button">Setujui</button></div>` : ''}</article>`;
  };

  const baseSubmitDecision = submitDecision;
  submitDecision = function submitDecisionV04(event) {
    baseSubmitDecision(event);
    renderVariableModule();
  };

  function openTripDialog() {
    const today = dateKey();
    els.tripProject.value = '';
    els.tripDestination.value = '';
    els.tripStart.value = today;
    els.tripEnd.value = today;
    els.tripAdvance.value = '0';
    els.tripEstimatedExpense.value = '0';
    els.tripEstimatedAllowance.value = '0';
    els.tripPurpose.value = '';
    els.tripDialog.showModal();
  }

  function submitTripRequest(event) {
    event.preventDefault();
    const project = els.tripProject.value.trim();
    const destination = els.tripDestination.value.trim();
    const startDate = els.tripStart.value;
    const endDate = els.tripEnd.value;
    const reason = els.tripPurpose.value.trim();
    if (!project || !destination || !startDate || !endDate || !reason) return toast('Lengkapi data perjalanan dinas.');
    if (endDate < startDate) return toast('Tanggal selesai tidak boleh sebelum tanggal mulai.');
    const days = countCalendarDays(startDate, endDate);
    if (days > 60) return toast('Durasi perjalanan maksimal 60 hari per pengajuan.');
    state.requests.push({
      id: randomId('REQ'), nik: state.user.nik, name: state.user.name, department: state.user.department,
      type: 'trip', project, destination, startDate, endDate, days,
      requestedAdvance: Math.max(0, Number(els.tripAdvance.value || 0)),
      estimatedExpense: Math.max(0, Number(els.tripEstimatedExpense.value || 0)),
      estimatedAllowance: Math.max(0, Number(els.tripEstimatedAllowance.value || 0)),
      reason, status: 'pending', settlementStatus: 'not_started', createdAt: new Date().toISOString(), decisionNote: ''
    });
    addAudit(state.user.nik, 'Pengajuan perjalanan dinas', `${project} · ${destination} · ${startDate} s.d. ${endDate}`, 'info');
    persist();
    els.tripDialog.close();
    renderEmployeeRequests();
    renderEmployeeActivity();
    toast('Pengajuan perjalanan dinas berhasil dikirim.');
  }

  function populateEmployeeSelect() {
    if (!els.variableNik) return;
    const current = els.variableNik.value;
    els.variableNik.innerHTML = state.employees.filter(employee => employee.active).map(employee => `<option value="${escapeHtml(employee.nik)}">${escapeHtml(employee.nik)} · ${escapeHtml(employee.name)}</option>`).join('');
    if (state.employees.some(employee => employee.nik === current && employee.active)) els.variableNik.value = current;
  }

  function renderVariableModule() {
    if (!els.variableMonth) return;
    const period = els.variableMonth.value || els.payrollMonth?.value || monthKey();
    els.variableMonth.value = period;
    populateEmployeeSelect();
    const components = state.variableComponents.filter(item => item.period === period).sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));
    const approved = components.filter(item => item.status === 'approved');
    const totals = approved.reduce((out, item) => {
      out[item.treatment] = (out[item.treatment] || 0) + Number(item.amount || 0);
      return out;
    }, { earning: 0, deduction: 0, reimbursement: 0 });
    els.variableIncomeTotal.textContent = formatCurrency(totals.earning);
    els.variableDeductionTotal.textContent = formatCurrency(totals.deduction);
    els.variableReimbursementTotal.textContent = formatCurrency(totals.reimbursement);
    const openTrips = state.requests.filter(request => request.type === 'trip' && request.status !== 'rejected' && request.settlementStatus !== 'settled');
    els.tripOpenCount.textContent = String(openTrips.length);

    els.variableComponentBody.innerHTML = components.length ? components.map(item => {
      const locked = isEmployeePeriodLocked(item.period, item.nik);
      let actions = '';
      if (item.status === 'draft') actions = `<button class="text-btn" data-variable-action="edit" data-id="${escapeHtml(item.id)}" type="button">Edit</button><button class="text-btn" data-variable-action="approve" data-id="${escapeHtml(item.id)}" type="button">Setujui</button><button class="text-btn danger" data-variable-action="delete" data-id="${escapeHtml(item.id)}" type="button">Hapus</button>`;
      else if (item.status === 'approved') actions = `<button class="text-btn danger" data-variable-action="void" data-id="${escapeHtml(item.id)}" type="button">Batalkan</button>`;
      return `<tr><td><strong>${escapeHtml(item.name)}</strong><small>${escapeHtml(item.nik)}</small></td><td>${escapeHtml(formatPeriodLabel(item.period))}</td><td>${escapeHtml(item.project || '-')}</td><td><strong>${escapeHtml(componentLabel(item))}</strong><span class="component-note">${escapeHtml(item.description || '')}${item.source === 'business_trip' ? ' · dari realisasi dinas' : ''}</span></td><td>${escapeHtml(treatmentLabels[item.treatment] || item.treatment)}</td><td class="salary-sensitive"><strong>${formatCurrency(item.amount)}</strong></td><td><span class="status-chip ${escapeHtml(item.status)}">${escapeHtml(componentStatusLabel(item.status))}</span>${locked ? '<span class="component-note">Slip sudah terbit</span>' : ''}</td><td><div class="action-stack">${locked ? '<span class="muted">Terkunci</span>' : actions || '-'}</div></td></tr>`;
    }).join('') : `<tr><td colspan="8">Belum ada komponen variabel pada periode ini.</td></tr>`;

    const trips = state.requests.filter(request => request.type === 'trip' && ((request.startDate || '').startsWith(period) || request.payrollPeriod === period)).sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));
    els.businessTripBody.innerHTML = trips.length ? trips.map(trip => {
      const locked = trip.payrollPeriod && isEmployeePeriodLocked(trip.payrollPeriod, trip.nik);
      const settlementClass = trip.settlementStatus === 'settled' ? 'settled' : trip.status === 'approved' ? 'open' : 'pending';
      let action = '-';
      if (trip.status === 'pending') action = '<span class="muted">Proses di Approval</span>';
      if (trip.status === 'approved') action = `<button class="text-btn" data-trip-action="settle" data-id="${escapeHtml(trip.id)}" type="button">${trip.settlementStatus === 'settled' ? 'Lihat Realisasi' : 'Input Realisasi'}</button>`;
      return `<tr><td><strong>${escapeHtml(trip.name)}</strong><small>${escapeHtml(trip.nik)}</small></td><td><strong>${escapeHtml(trip.project)}</strong><small>${escapeHtml(trip.destination)}</small></td><td>${escapeHtml(formatDate(trip.startDate))}–${escapeHtml(formatDate(trip.endDate))}</td><td>${formatCurrency(trip.requestedAdvance || 0)}</td><td><span class="status-chip ${escapeHtml(trip.status)}">${escapeHtml(statusLabel(trip.status))}</span></td><td><span class="status-chip ${settlementClass}">${escapeHtml(tripSettlementLabel(trip))}</span>${trip.payrollPeriod ? `<span class="component-note">Payroll ${escapeHtml(formatPeriodLabel(trip.payrollPeriod))}${locked ? ' · terkunci' : ''}</span>` : ''}</td><td>${locked ? '<span class="muted">Terkunci</span>' : action}</td></tr>`;
    }).join('') : `<tr><td colspan="7">Belum ada perjalanan dinas pada periode ini.</td></tr>`;
  }

  function openVariableDialog(id = null) {
    populateEmployeeSelect();
    const component = id ? state.variableComponents.find(item => item.id === id) : null;
    els.variableDialogTitle.textContent = component ? 'Edit Komponen Variabel' : 'Tambah Komponen Variabel';
    els.variableId.value = component?.id || '';
    els.variablePeriod.value = component?.period || els.variableMonth.value || monthKey();
    els.variableNik.value = component?.nik || state.employees.find(employee => employee.active)?.nik || '';
    els.variableProject.value = component?.project || '';
    els.variableCategory.value = component?.category || 'project_bonus';
    els.variableTreatment.value = component?.treatment || categoryTreatment(els.variableCategory.value);
    els.variableAmount.value = component?.amount || 0;
    els.variableDescription.value = component?.description || '';
    els.variableStatus.value = component?.status === 'approved' ? 'approved' : 'draft';
    els.variableDialog.showModal();
  }

  function saveVariableComponent(event) {
    event.preventDefault();
    const id = els.variableId.value;
    const period = els.variablePeriod.value;
    const employee = state.employees.find(item => item.nik === els.variableNik.value);
    const amount = Math.max(0, Number(els.variableAmount.value || 0));
    const description = els.variableDescription.value.trim();
    if (!period || !employee || !amount || !description) return toast('Lengkapi periode, karyawan, nominal, dan keterangan.');
    if (isEmployeePeriodLocked(period, employee.nik)) return toast('Slip gaji karyawan pada periode ini sudah terbit. Tarik slip sebelum mengubah komponen.');
    const existing = id ? state.variableComponents.find(item => item.id === id) : null;
    const next = {
      id: existing?.id || randomId('VAR'), period, nik: employee.nik, name: employee.name,
      project: els.variableProject.value.trim(), category: els.variableCategory.value,
      label: categoryLabels[els.variableCategory.value], treatment: els.variableTreatment.value,
      amount, description, status: els.variableStatus.value, source: existing?.source || 'manual', tripId: existing?.tripId || null,
      createdAt: existing?.createdAt || new Date().toISOString(), createdBy: existing?.createdBy || state.user?.name || 'Administrator MPA',
      updatedAt: new Date().toISOString(), updatedBy: state.user?.name || 'Administrator MPA'
    };
    if (next.status === 'approved') {
      next.approvedAt = existing?.approvedAt || new Date().toISOString();
      next.approvedBy = existing?.approvedBy || state.user?.name || 'Administrator MPA';
    }
    if (existing) Object.assign(existing, next);
    else state.variableComponents.push(next);
    addAudit('ADMIN', existing ? 'Ubah komponen payroll' : 'Tambah komponen payroll', `${period} · ${employee.nik} · ${componentLabel(next)} · ${formatCurrency(amount)}`, next.status === 'approved' ? 'success' : 'info');
    persist();
    els.variableDialog.close();
    renderVariableModule();
    renderPayroll();
    toast('Komponen variabel berhasil disimpan. Generate ulang draft slip untuk menerapkan perubahan.');
  }

  function handleVariableAction(event) {
    const button = event.target.closest('[data-variable-action]');
    if (!button) return;
    const item = state.variableComponents.find(component => component.id === button.dataset.id);
    if (!item) return;
    if (isEmployeePeriodLocked(item.period, item.nik)) return toast('Periode terkunci karena slip gaji sudah terbit.');
    const action = button.dataset.variableAction;
    if (action === 'edit') return openVariableDialog(item.id);
    if (action === 'approve') {
      item.status = 'approved';
      item.approvedAt = new Date().toISOString();
      item.approvedBy = state.user?.name || 'Administrator MPA';
      addAudit('ADMIN', 'Setujui komponen payroll', `${item.period} · ${item.nik} · ${componentLabel(item)}`, 'success');
    }
    if (action === 'void') {
      if (!confirm(`Batalkan ${componentLabel(item)} untuk ${item.name}?`)) return;
      item.status = 'void';
      item.voidedAt = new Date().toISOString();
      item.voidedBy = state.user?.name || 'Administrator MPA';
      addAudit('ADMIN', 'Batalkan komponen payroll', `${item.period} · ${item.nik} · ${componentLabel(item)}`, 'warning');
    }
    if (action === 'delete') {
      if (!confirm('Hapus komponen draft ini?')) return;
      state.variableComponents = state.variableComponents.filter(component => component.id !== item.id);
      addAudit('ADMIN', 'Hapus draft komponen payroll', `${item.period} · ${item.nik} · ${componentLabel(item)}`, 'warning');
    }
    persist();
    renderVariableModule();
    renderPayroll();
    toast('Data komponen diperbarui.');
  }

  function handleTripAdminAction(event) {
    const button = event.target.closest('[data-trip-action]');
    if (!button) return;
    const trip = state.requests.find(request => request.id === button.dataset.id && request.type === 'trip');
    if (!trip) return;
    if (button.dataset.tripAction === 'settle') openTripSettlement(trip);
  }

  function openTripSettlement(trip) {
    const period = trip.payrollPeriod || monthKey(new Date(`${trip.endDate}T00:00:00`));
    if (isEmployeePeriodLocked(period, trip.nik)) return toast('Realisasi terkunci karena slip gaji periode tersebut sudah terbit.');
    els.tripSettlementId.value = trip.id;
    els.tripSettlementTitle.textContent = `${trip.name} · ${trip.project}`;
    els.tripPayrollMonth.value = period;
    els.tripAdvancePaid.value = Number(trip.advancePaid ?? trip.requestedAdvance ?? 0);
    els.tripActualExpense.value = Number(trip.actualExpense ?? trip.estimatedExpense ?? 0);
    els.tripFinalAllowance.value = Number(trip.finalAllowance ?? trip.estimatedAllowance ?? 0);
    els.tripReimbursementPayable.value = Number(trip.reimbursementPayable ?? 0);
    els.tripRefundDue.value = Number(trip.refundDue ?? 0);
    els.tripDeductRefund.checked = Boolean(trip.deductRefundFromPayroll);
    els.tripSettlementNote.value = trip.settlementNote || '';
    updateTripSettlementSummary();
    els.tripSettlementDialog.showModal();
  }

  function updateTripSettlementSummary() {
    if (!els.tripSettlementSummary) return;
    const advance = Number(els.tripAdvancePaid.value || 0);
    const expense = Number(els.tripActualExpense.value || 0);
    const allowance = Number(els.tripFinalAllowance.value || 0);
    const reimbursement = Number(els.tripReimbursementPayable.value || 0);
    const refund = Number(els.tripRefundDue.value || 0);
    const accountability = expense + allowance - advance;
    els.tripSettlementSummary.innerHTML = `<strong>Kontrol pertanggungjawaban</strong><div class="trip-summary-grid"><div><small>Total biaya + tunjangan</small><strong>${formatCurrency(expense + allowance)}</strong></div><div><small>Selisih vs uang muka</small><strong class="${accountability >= 0 ? 'money-positive' : 'money-negative'}">${formatCurrency(Math.abs(accountability))} ${accountability >= 0 ? 'kurang bayar' : 'kelebihan UM'}</strong></div><div><small>Reimbursement via payroll</small><strong>${formatCurrency(reimbursement)}</strong></div><div><small>Pengembalian karyawan</small><strong>${formatCurrency(refund)}</strong></div></div><p>Nilai reimbursement dan pengembalian merupakan keputusan final Finance; sistem tidak memaksakan keduanya sama dengan selisih estimasi.</p>`;
  }

  function saveTripSettlement(event) {
    event.preventDefault();
    const trip = state.requests.find(request => request.id === els.tripSettlementId.value && request.type === 'trip');
    if (!trip || trip.status !== 'approved') return toast('Perjalanan dinas belum disetujui atau tidak ditemukan.');
    const period = els.tripPayrollMonth.value;
    if (!period) return toast('Pilih periode payroll.');
    if (isEmployeePeriodLocked(period, trip.nik)) return toast('Slip gaji periode ini sudah terbit. Tarik slip sebelum mengubah realisasi.');
    const note = els.tripSettlementNote.value.trim();
    if (!note) return toast('Catatan realisasi wajib diisi.');
    const advancePaid = Math.max(0, Number(els.tripAdvancePaid.value || 0));
    const actualExpense = Math.max(0, Number(els.tripActualExpense.value || 0));
    const finalAllowance = Math.max(0, Number(els.tripFinalAllowance.value || 0));
    const reimbursementPayable = Math.max(0, Number(els.tripReimbursementPayable.value || 0));
    const refundDue = Math.max(0, Number(els.tripRefundDue.value || 0));
    const deductRefundFromPayroll = els.tripDeductRefund.checked;

    const oldComponents = state.variableComponents.filter(component => component.tripId === trip.id && component.source === 'business_trip');
    if (oldComponents.some(component => isEmployeePeriodLocked(component.period, component.nik))) return toast('Komponen realisasi sebelumnya terkunci oleh slip yang sudah terbit.');
    state.variableComponents = state.variableComponents.filter(component => !(component.tripId === trip.id && component.source === 'business_trip'));

    const common = {
      period, nik: trip.nik, name: trip.name, project: trip.project, source: 'business_trip', tripId: trip.id,
      status: 'approved', createdAt: new Date().toISOString(), createdBy: state.user?.name || 'Administrator MPA',
      approvedAt: new Date().toISOString(), approvedBy: state.user?.name || 'Administrator MPA'
    };
    if (finalAllowance > 0) state.variableComponents.push({ ...common, id: randomId('VAR'), category: 'travel_allowance', label: 'Uang Harian/Tunjangan Dinas', treatment: 'earning', amount: finalAllowance, description: `${trip.destination} · ${formatDate(trip.startDate)}–${formatDate(trip.endDate)}` });
    if (reimbursementPayable > 0) state.variableComponents.push({ ...common, id: randomId('VAR'), category: 'reimbursement', label: 'Reimbursement Perjalanan Dinas', treatment: 'reimbursement', amount: reimbursementPayable, description: `${trip.destination} · biaya aktual terverifikasi Finance` });
    if (refundDue > 0 && deductRefundFromPayroll) state.variableComponents.push({ ...common, id: randomId('VAR'), category: 'other_deduction', label: 'Pengembalian Uang Muka Dinas', treatment: 'deduction', amount: refundDue, description: `${trip.destination} · dipotong melalui payroll` });

    Object.assign(trip, {
      payrollPeriod: period, advancePaid, actualExpense, finalAllowance, reimbursementPayable, refundDue, deductRefundFromPayroll,
      settlementNote: note, settlementStatus: 'settled', settledAt: new Date().toISOString(), settledBy: state.user?.name || 'Administrator MPA'
    });
    addAudit('ADMIN', 'Realisasi perjalanan dinas', `${trip.nik} · ${trip.project} · payroll ${period} · tunjangan ${formatCurrency(finalAllowance)} · reimbursement ${formatCurrency(reimbursementPayable)}`, 'success');
    persist();
    els.tripSettlementDialog.close();
    renderVariableModule();
    renderPayroll();
    toast('Realisasi perjalanan dinas dan komponen payroll berhasil disimpan.');
  }

  const baseCalculatePayroll = calculatePayroll;
  calculatePayroll = function calculatePayrollV04(month) {
    const rows = baseCalculatePayroll(month);
    rows.forEach(row => {
      const variableItems = state.variableComponents.filter(item => item.period === month && item.nik === row.nik && item.status === 'approved');
      row.variableItems = variableItems;
      row.variableIncome = variableItems.filter(item => item.treatment === 'earning').reduce((sum, item) => sum + Number(item.amount || 0), 0);
      row.variableDeduction = variableItems.filter(item => item.treatment === 'deduction').reduce((sum, item) => sum + Number(item.amount || 0), 0);
      row.reimbursementTotal = variableItems.filter(item => item.treatment === 'reimbursement').reduce((sum, item) => sum + Number(item.amount || 0), 0);
      row.grossIncome += row.variableIncome;
      row.totalDeductions += row.variableDeduction;
      row.netSalary = Math.max(0, row.grossIncome - row.totalDeductions);
      row.totalTransfer = row.netSalary + row.reimbursementTotal;
      row.netPay = row.netSalary;
    });
    return rows;
  };

  renderPayroll = function renderPayrollV04() {
    const month = els.payrollMonth.value || monthKey();
    state.payrollRows = calculatePayroll(month);
    const totals = state.payrollRows.reduce((out, row) => ({
      present: out.present + row.present, late: out.late + row.lateMinutes, penalty: out.penalty + row.penalty, overtime: out.overtime + row.overtimeHours
    }), { present: 0, late: 0, penalty: 0, overtime: 0 });
    els.payrollPresentTotal.textContent = String(totals.present);
    els.payrollLateTotal.textContent = String(totals.late);
    els.payrollPenaltyTotal.textContent = formatCurrency(totals.penalty);
    els.payrollOvertimeTotal.textContent = totals.overtime.toFixed(1);
    els.payrollBody.innerHTML = state.payrollRows.map(row => {
      const slip = getPayslip(month, row.nik);
      const status = slip?.status || 'none';
      const statusText = status === 'published' ? 'Terbit' : status === 'draft' ? 'Draft' : 'Belum dibuat';
      const statusClass = status === 'published' ? 'published' : status === 'draft' ? 'draft' : 'pending';
      const primaryAction = status === 'published'
        ? `<button class="text-btn" data-payroll-action="preview" data-nik="${escapeHtml(row.nik)}" type="button">Buka</button><button class="text-btn danger" data-payroll-action="revoke" data-nik="${escapeHtml(row.nik)}" type="button">Tarik</button>`
        : `<button class="text-btn" data-payroll-action="generate" data-nik="${escapeHtml(row.nik)}" type="button">${status === 'draft' ? 'Perbarui' : 'Buat Draft'}</button>${status === 'draft' ? `<button class="text-btn" data-payroll-action="preview" data-nik="${escapeHtml(row.nik)}" type="button">Preview</button><button class="text-btn" data-payroll-action="publish" data-nik="${escapeHtml(row.nik)}" type="button">Terbitkan</button>` : ''}`;
      return `<tr><td>${escapeHtml(row.nik)}</td><td><strong>${escapeHtml(row.name)}</strong><small>${escapeHtml(row.position)} · ${escapeHtml(row.department)}</small></td><td>${row.present}</td><td>${row.lateMinutes} mnt</td><td>${formatCurrency(row.penalty)}</td><td>${row.overtimeHours.toFixed(1)} jam</td><td class="salary-sensitive">${formatCurrency(row.variableIncome)}</td><td class="salary-sensitive">${formatCurrency(row.reimbursementTotal)}</td><td class="salary-sensitive">${formatCurrency(row.grossIncome)}</td><td class="salary-sensitive">${formatCurrency(row.totalDeductions)}</td><td class="salary-sensitive"><strong>${formatCurrency(row.netSalary)}</strong></td><td class="salary-sensitive"><strong>${formatCurrency(row.totalTransfer)}</strong></td><td><span class="status-chip ${statusClass}">${statusText}</span></td><td><div class="action-stack">${primaryAction}</div></td></tr>`;
    }).join('');
  };

  const baseBuildPayslipSnapshot = buildPayslipSnapshot;
  buildPayslipSnapshot = function buildPayslipSnapshotV04(row, month, status = 'draft', existing = null) {
    const snapshot = baseBuildPayslipSnapshot(row, month, status, existing);
    snapshot.variableEarnings = (row.variableItems || []).filter(item => item.treatment === 'earning').map(item => ({ id: item.id, label: componentLabel(item), project: item.project || '', amount: Number(item.amount || 0) }));
    snapshot.variableDeductions = (row.variableItems || []).filter(item => item.treatment === 'deduction').map(item => ({ id: item.id, label: componentLabel(item), project: item.project || '', amount: Number(item.amount || 0) }));
    snapshot.reimbursements = (row.variableItems || []).filter(item => item.treatment === 'reimbursement').map(item => ({ id: item.id, label: componentLabel(item), project: item.project || '', amount: Number(item.amount || 0) }));
    snapshot.earnings.variableIncome = Number(row.variableIncome || 0);
    snapshot.deductions.variableDeduction = Number(row.variableDeduction || 0);
    snapshot.grossIncome = Number(row.grossIncome || 0);
    snapshot.totalDeductions = Number(row.totalDeductions || 0);
    snapshot.netSalary = Number(row.netSalary ?? row.netPay ?? 0);
    snapshot.reimbursementTotal = Number(row.reimbursementTotal || 0);
    snapshot.totalTransfer = Number(row.totalTransfer ?? snapshot.netSalary + snapshot.reimbursementTotal);
    snapshot.netPay = snapshot.netSalary;
    return snapshot;
  };

  function variablePayslipLines(items = []) {
    return items.map(item => payslipLine(`${item.label}${item.project ? ` · ${item.project}` : ''}`, item.amount)).join('');
  }

  payslipDocumentHtml = function payslipDocumentHtmlV04(slip) {
    const earnings = slip.earnings || {};
    const deductions = slip.deductions || {};
    const variableEarnings = slip.variableEarnings || [];
    const variableDeductions = slip.variableDeductions || [];
    const reimbursements = slip.reimbursements || [];
    const netSalary = Number(slip.netSalary ?? slip.netPay ?? 0);
    const reimbursementTotal = Number(slip.reimbursementTotal || reimbursements.reduce((sum, item) => sum + Number(item.amount || 0), 0));
    const totalTransfer = Number(slip.totalTransfer ?? netSalary + reimbursementTotal);
    return `<article class="payslip-sheet">
      <header class="payslip-head"><img src="assets/mpa-logo.png" alt="PT Multi Power Abadi" /><div class="payslip-head-copy"><strong>SLIP GAJI KARYAWAN</strong><span>${escapeHtml(formatPeriodLabel(slip.period))}</span><span>${escapeHtml(slip.documentNumber)}</span></div></header>
      <section class="payslip-meta"><div><small>Nama</small><strong>${escapeHtml(slip.name)}</strong></div><div><small>NIK</small><strong>${escapeHtml(slip.nik)}</strong></div><div><small>Jabatan</small><strong>${escapeHtml(slip.position)}</strong></div><div><small>Bank/Rekening</small><strong>${escapeHtml(slip.bankName || '-')} · ${escapeHtml(maskAccount(slip.bankAccount))}</strong></div></section>
      <section class="payslip-columns">
        <div class="payslip-column"><h4>Penghasilan</h4>${payslipLine('Gaji Pokok', earnings.baseSalary)}${payslipLine('Tunjangan Jabatan', earnings.positionAllowance)}${payslipLine('Tunjangan Tetap/Lain', earnings.fixedAllowance)}${payslipLine('Makan & Transport', earnings.mealTransport)}${payslipLine(`Lembur (${Number(slip.attendance?.overtimeHours || 0).toFixed(1)} jam)`, earnings.overtimeValue)}${payslipLine('Pendapatan Tetap Lain', earnings.otherIncome)}${variablePayslipLines(variableEarnings)}${payslipLine('Total Penghasilan Bruto', slip.grossIncome, 'total')}</div>
        <div class="payslip-column"><h4>Potongan</h4>${payslipLine('BPJS Kesehatan', deductions.bpjsHealth)}${payslipLine('BPJS Ketenagakerjaan', deductions.bpjsEmployment)}${payslipLine('PPh 21', deductions.pph21)}${payslipLine(`Denda Telat (${Number(slip.attendance?.lateMinutes || 0)} menit)`, deductions.latePenalty)}${payslipLine('Potongan Kasbon', deductions.loanDeduction)}${payslipLine('Potongan Tetap Lain', deductions.otherDeduction)}${variablePayslipLines(variableDeductions)}${payslipLine('Total Potongan', slip.totalDeductions, 'total')}</div>
      </section>
      <section class="payslip-subtotal"><span>GAJI BERSIH</span><strong>${formatCurrency(netSalary)}</strong></section>
      ${reimbursements.length ? `<section class="payslip-reimbursement"><h4>Reimbursement (di luar penghasilan bruto)</h4>${variablePayslipLines(reimbursements)}${payslipLine('Total Reimbursement', reimbursementTotal, 'total')}</section>` : ''}
      <section class="payslip-total"><span>${reimbursementTotal ? 'TOTAL DITRANSFER' : 'TAKE HOME PAY'}</span><strong>${formatCurrency(totalTransfer)}</strong></section>
      <footer class="payslip-foot"><span>Hadir ${Number(slip.attendance?.present || 0)} hari · Cuti ${Number(slip.attendance?.leave || 0)} · Izin ${Number(slip.attendance?.permission || 0)} · Sakit ${Number(slip.attendance?.sick || 0)}</span><span>Terbit: ${escapeHtml(slip.publishedAt ? formatDateTime(slip.publishedAt) : 'Draft')}</span></footer>
    </article>`;
  };

  exportCurrentPayslipExcel = function exportCurrentPayslipExcelV04() {
    const slip = currentPreviewPayslip();
    if (!slip) return toast('Tidak ada slip yang dipilih.');
    const rows = [
      ['PT Multi Power Abadi', 'Slip Gaji Karyawan'], ['Periode', formatPeriodLabel(slip.period)], ['Nomor Dokumen', slip.documentNumber],
      ['NIK', slip.nik], ['Nama', slip.name], ['Jabatan', slip.position], ['Departemen', slip.department], ['Bank', slip.bankName], ['Rekening', maskAccount(slip.bankAccount)], [],
      ['PENGHASILAN', 'PROYEK/KETERANGAN', 'NILAI'], ['Gaji Pokok', '', slip.earnings.baseSalary], ['Tunjangan Jabatan', '', slip.earnings.positionAllowance], ['Tunjangan Tetap/Lain', '', slip.earnings.fixedAllowance], ['Makan & Transport', '', slip.earnings.mealTransport], ['Lembur', '', slip.earnings.overtimeValue], ['Pendapatan Tetap Lain', '', slip.earnings.otherIncome]
    ];
    (slip.variableEarnings || []).forEach(item => rows.push([item.label, item.project || '', item.amount]));
    rows.push(['Total Penghasilan Bruto', '', slip.grossIncome], [], ['POTONGAN', 'PROYEK/KETERANGAN', 'NILAI'], ['BPJS Kesehatan', '', slip.deductions.bpjsHealth], ['BPJS Ketenagakerjaan', '', slip.deductions.bpjsEmployment], ['PPh 21', '', slip.deductions.pph21], ['Denda Keterlambatan', '', slip.deductions.latePenalty], ['Potongan Kasbon', '', slip.deductions.loanDeduction], ['Potongan Tetap Lain', '', slip.deductions.otherDeduction]);
    (slip.variableDeductions || []).forEach(item => rows.push([item.label, item.project || '', item.amount]));
    rows.push(['Total Potongan', '', slip.totalDeductions], [], ['GAJI BERSIH', '', Number(slip.netSalary ?? slip.netPay ?? 0)]);
    if ((slip.reimbursements || []).length) {
      rows.push([], ['REIMBURSEMENT (DI LUAR BRUTO)', 'PROYEK/KETERANGAN', 'NILAI']);
      slip.reimbursements.forEach(item => rows.push([item.label, item.project || '', item.amount]));
      rows.push(['Total Reimbursement', '', Number(slip.reimbursementTotal || 0)]);
    }
    rows.push([], ['TOTAL DITRANSFER', '', Number(slip.totalTransfer ?? slip.netSalary ?? slip.netPay ?? 0)], ['Status', slip.status === 'published' ? 'Terbit' : 'Draft']);
    window.MPAXLSX.exportWorkbook(`Slip_Gaji_${slip.period}_${slip.nik}.xlsx`, [{ name: 'Slip Gaji', rows }]);
    addAudit(state.user?.nik || 'ADMIN', 'Export slip gaji', `${slip.period} · ${slip.nik}`, 'info');
    toast('Slip gaji Excel sedang diunduh.');
  };

  exportPayrollExcel = function exportPayrollExcelV04() {
    const month = els.payrollMonth.value || monthKey();
    const payrollRows = calculatePayroll(month);
    const summary = [['Periode','NIK','Nama','Jabatan','Departemen','Hadir','Telat (Menit)','Denda Telat','Cuti','Izin','Sakit','Lembur (Jam)','Nilai Lembur','Gaji Pokok','Tunjangan Jabatan','Tunjangan Tetap','Makan & Transport','Pendapatan Tetap Lain','Penghasilan Variabel','Bruto','BPJS Kesehatan','BPJS Ketenagakerjaan','PPh 21','Kasbon','Potongan Tetap Lain','Potongan Variabel','Total Potongan','Gaji Bersih','Reimbursement','Total Transfer','Status Slip']];
    payrollRows.forEach(row => {
      const slip = getPayslip(month, row.nik);
      summary.push([month,row.nik,row.name,row.position,row.department,row.present,row.lateMinutes,row.penalty,row.leave,row.permission,row.sick,row.overtimeHours,row.overtimeValue,row.salary.baseSalary,row.salary.positionAllowance,row.salary.fixedAllowance,row.salary.mealTransport,row.salary.otherIncome,row.variableIncome,row.grossIncome,row.salary.bpjsHealth,row.salary.bpjsEmployment,row.salary.pph21,row.salary.loanDeduction,row.salary.otherDeduction,row.variableDeduction,row.totalDeductions,row.netSalary,row.reimbursementTotal,row.totalTransfer,slip?.status === 'published' ? 'Terbit' : slip?.status === 'draft' ? 'Draft' : 'Belum dibuat']);
    });
    const components = [['ID','Periode','NIK','Nama','Proyek','Kategori','Perlakuan','Nominal','Keterangan','Status','Sumber','Trip ID','Dibuat','Disetujui']];
    state.variableComponents.filter(item => item.period === month).forEach(item => components.push([item.id,item.period,item.nik,item.name,item.project || '',componentLabel(item),treatmentLabels[item.treatment] || item.treatment,Number(item.amount || 0),item.description || '',componentStatusLabel(item.status),item.source || 'manual',item.tripId || '',item.createdAt || '',item.approvedAt || '']));
    const trips = [['ID','NIK','Nama','Proyek','Tujuan','Mulai','Selesai','Hari','Uang Muka Diminta','Uang Muka Dibayar','Estimasi Biaya','Biaya Aktual','Estimasi Tunjangan','Tunjangan Final','Reimbursement Payroll','Pengembalian UM','Potong via Payroll','Status Approval','Status Realisasi','Periode Payroll','Catatan']];
    state.requests.filter(request => request.type === 'trip' && ((request.startDate || '').startsWith(month) || request.payrollPeriod === month)).forEach(trip => trips.push([trip.id,trip.nik,trip.name,trip.project,trip.destination,trip.startDate,trip.endDate,trip.days,Number(trip.requestedAdvance || 0),Number(trip.advancePaid || 0),Number(trip.estimatedExpense || 0),Number(trip.actualExpense || 0),Number(trip.estimatedAllowance || 0),Number(trip.finalAllowance || 0),Number(trip.reimbursementPayable || 0),Number(trip.refundDue || 0),trip.deductRefundFromPayroll ? 'Ya' : 'Tidak',statusLabel(trip.status),tripSettlementLabel(trip),trip.payrollPeriod || '',trip.settlementNote || trip.reason || '']));
    const attendance = [['Tanggal','NIK','Nama','Departemen','Masuk','Pulang','Telat (Menit)','Lokasi','Jarak Masuk','Jarak Pulang','Akurasi GPS','Device ID','Time Source']];
    state.records.filter(record => record.date.startsWith(month)).sort((a,b) => a.date.localeCompare(b.date) || a.nik.localeCompare(b.nik)).forEach(record => attendance.push([record.date,record.nik,record.name,record.department,record.checkIn || '',record.checkOut || '',Number(record.lateMinutes || 0),record.officeName || '',Number(record.distanceMeters || 0),Number(record.checkOutDistanceMeters || 0),Number(record.accuracyMeters || 0),record.deviceId || '',record.timeSource || '']));
    const leave = [['ID','NIK','Nama','Jenis','Tanggal Mulai','Tanggal Selesai','Hari','Alasan','Status','Diputuskan Oleh','Catatan']];
    state.requests.filter(request => request.type === 'leave' && request.startDate.startsWith(month)).forEach(request => leave.push([request.id,request.nik,request.name,requestLabel(request),request.startDate,request.endDate,Number(request.days || 0),request.reason,statusLabel(request.status),request.decidedBy || '',request.decisionNote || '']));
    const overtime = [['ID','NIK','Nama','Tanggal','Mulai','Selesai','Jam','Uraian','Status','Nilai Lembur','Diputuskan Oleh','Catatan']];
    state.requests.filter(request => request.type === 'overtime' && request.date.startsWith(month)).forEach(request => overtime.push([request.id,request.nik,request.name,request.date,request.startTime,request.endTime,Number(request.hours || 0),request.reason,statusLabel(request.status),request.status === 'approved' ? Number(request.hours || 0) * Number(state.settings.overtimeRate || 0) : 0,request.decidedBy || '',request.decisionNote || '']));
    const published = [['Nomor Slip','Periode','NIK','Nama','Status','Bruto','Potongan','Gaji Bersih','Reimbursement','Total Transfer','Terbit Pada','Terbit Oleh']];
    state.payslips.filter(slip => slip.period === month).forEach(slip => published.push([slip.documentNumber,slip.period,slip.nik,slip.name,slip.status === 'published' ? 'Terbit' : 'Draft',slip.grossIncome,slip.totalDeductions,Number(slip.netSalary ?? slip.netPay ?? 0),Number(slip.reimbursementTotal || 0),Number(slip.totalTransfer ?? slip.netSalary ?? slip.netPay ?? 0),slip.publishedAt || '',slip.publishedBy || '']));
    const policy = [['Parameter','Nilai'],['Nama Lokasi',state.settings.officeName],['Radius GPS (m)',state.settings.radiusMeters],['Batas Akurasi GPS (m)',state.settings.maxAccuracyMeters],['Jam Kerja',`${state.settings.workStart}–${state.settings.workEnd}`],['Toleransi Telat',state.settings.lateToleranceMinutes],['Denda Aktif',state.settings.penaltyEnabled ? 'Ya' : 'Tidak'],['Denda per Menit',state.settings.penaltyPerMinute],['Tarif Lembur per Jam',state.settings.overtimeRate],['Catatan','Reimbursement dipisahkan dari penghasilan bruto dan ditambahkan pada total transfer. Hanya komponen disetujui yang masuk payroll.']];
    window.MPAXLSX.exportWorkbook(`MPA_Payroll_${month}.xlsx`, [
      { name: 'Rekap Payroll', rows: summary }, { name: 'Komponen Variabel', rows: components }, { name: 'Perjalanan Dinas', rows: trips }, { name: 'Status Slip', rows: published }, { name: 'Detail Absensi', rows: attendance }, { name: 'Cuti Izin Sakit', rows: leave }, { name: 'Lembur', rows: overtime }, { name: 'Kebijakan', rows: policy }
    ]);
    addAudit('ADMIN', 'Export payroll lengkap', month, 'info');
    toast('File Excel payroll lengkap sedang diunduh.');
  };

  function exportVariableExcel() {
    const month = els.variableMonth.value || monthKey();
    const components = [['ID','Periode','NIK','Nama','Proyek','Komponen','Perlakuan','Nominal','Keterangan','Status','Sumber','Trip ID']];
    state.variableComponents.filter(item => item.period === month).forEach(item => components.push([item.id,item.period,item.nik,item.name,item.project || '',componentLabel(item),treatmentLabels[item.treatment] || item.treatment,Number(item.amount || 0),item.description || '',componentStatusLabel(item.status),item.source || 'manual',item.tripId || '']));
    const trips = [['ID','NIK','Nama','Proyek','Tujuan','Mulai','Selesai','Uang Muka Diminta','Uang Muka Dibayar','Biaya Aktual','Tunjangan Final','Reimbursement','Pengembalian','Status Approval','Status Realisasi','Periode Payroll']];
    state.requests.filter(request => request.type === 'trip' && ((request.startDate || '').startsWith(month) || request.payrollPeriod === month)).forEach(trip => trips.push([trip.id,trip.nik,trip.name,trip.project,trip.destination,trip.startDate,trip.endDate,Number(trip.requestedAdvance || 0),Number(trip.advancePaid || 0),Number(trip.actualExpense || 0),Number(trip.finalAllowance || 0),Number(trip.reimbursementPayable || 0),Number(trip.refundDue || 0),statusLabel(trip.status),tripSettlementLabel(trip),trip.payrollPeriod || '']));
    window.MPAXLSX.exportWorkbook(`MPA_Bonus_Dinas_${month}.xlsx`, [{ name: 'Komponen Variabel', rows: components }, { name: 'Perjalanan Dinas', rows: trips }]);
    addAudit('ADMIN', 'Export bonus dan perjalanan dinas', month, 'info');
    toast('File Excel bonus dan perjalanan dinas sedang diunduh.');
  }
})();
