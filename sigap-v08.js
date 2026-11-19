'use strict';

/* SIGAP v1.8 UI patch. Loaded after app.js so backend features stay intact. */
(() => {
  const UI_VERSION = '1.10.1';
  const allRoles = ['employee','supervisor','hr_admin','payroll_finance','owner','super_admin'];
  const adminRoles = ['hr_admin','payroll_finance','super_admin'];
  const ownerRoles = ['owner','super_admin'];
  const announcementManagerRoles = ['hr_admin','owner','super_admin'];
  const originalNavigate = typeof navigate === 'function' ? navigate : null;

  const safe = async (promise, fallback) => { try { return await promise; } catch { return fallback; } };
  const roleCan = roles => !!state?.user && roles.includes(state.user.role);
  const isAdmin = () => hasPerm('employees') || hasPerm('payroll') || hasPerm('devices') || hasPerm('locations');
  const isOwnerDashboard = () => canOwnerMonitor();
  const canManageAnnouncements = () => roleCan(announcementManagerRoles);

  const MENU_PERMISSIONS = {
    dashboard:'dashboard', security:'security', access:'positions_access', announcements:'announcements_manage',
    employees:'employees', shifts:'shifts', 'attendance-report':'owner_monitor', 'attendance-admin':'attendance_admin', locations:'locations', devices:'devices', payroll:'payroll', audit:'audit',
    requests:'requests', trips:'trips', payslips:'payslips', profile:'employee_profile_self', 'employee-profiles':'employee_profile_manage'
  };
  const DEFAULT_ROLE_PERMS = {
    employee:['dashboard','security','requests','trips','payslips','announcements_view','employee_profile_self'],
    supervisor:['dashboard','security','requests','trips','payslips','announcements_view','employee_profile_self','employee_profile_team'],
    hr_admin:['dashboard','security','requests','trips','payslips','announcements_view','announcements_manage','owner_monitor','recruitment','employees','locations','devices','shifts','employee_profile_self','employee_profile_manage'],
    payroll_finance:['dashboard','security','payslips','announcements_view','owner_monitor','employees','payroll','employee_profile_self','employee_profile_payroll'],
    owner:['dashboard','requests','announcements_view','announcements_manage','owner_monitor'],
    super_admin:['*']
  };
  const userPerms = () => {
    if(!state?.user) return [];
    if(state.user.role==='super_admin') return ['*'];
    const p = state.user.menu_permissions || state.user.permissions;
    const perms = Array.isArray(p) && p.length ? [...p] : [...(DEFAULT_ROLE_PERMS[state.user.role] || ['dashboard','security'])];
    if((state.user.employee_id || state.user.role==='owner') && !perms.includes('requests')) perms.push('requests');
    return perms;
  };
  const hasPerm = perm => {
    if(!perm) return true;
    const perms = userPerms();
    return perms.includes('*') || perms.includes(perm);
  };
  const canManageAnnouncementsCustom = () => hasPerm('announcements_manage');
  const canViewAnnouncements = () => hasPerm('announcements_view') || hasPerm('announcements_manage');
  const canOwnerMonitor = () => hasPerm('owner_monitor') || state.user?.role==='super_admin';

  function sidebarItems(){
    const items = [
      ['dashboard','⌂','Dashboard','dashboard'],
      ['security','📱','Perangkat Absensi','security'],
      ['requests','🗓️','Cuti & Lembur','requests'],
      ['access','🛡️','Jabatan & Hak Akses','positions_access'],
      ['announcements','📢','Pengumuman','announcements_manage'],
      ['recruitment','🧠','Tes Masuk Karyawan','recruitment'],
      ['profile','🧑','Profil Saya','employee_profile_self'],
      ['employee-profiles','🗂️','Database Karyawan','employee_profile_manage'],
      ['employees','👥','Karyawan','employees'],
      ['shifts','🕒','Shift Kerja','shifts'],
      ['attendance-report','📊','Rekap Absensi','owner_monitor'],
      ['locations','📍','Lokasi Kerja','locations'],
      ['devices','📱','Persetujuan HP','devices'],
      ['payroll','▦','Pengaturan Payroll','payroll'],
      ['audit','🧾','Audit Log','audit'],
    ];
    return items.filter(item => hasPerm(item[3]));
  }

  window.buildNav = function buildNavV17(){
    const nav = document.querySelector('#mainNav');
    if(!nav || !state.user) return;
    const items = sidebarItems();
    const primary = items.filter(x => ['dashboard','security'].includes(x[0]));
    const admin = items.filter(x => !['dashboard','security'].includes(x[0]));
    nav.innerHTML = `
      <div class="sigap-nav-label">Menu Utama</div>
      ${primary.map(n=>`<button class="nav-btn" data-page="${n[0]}"><span>${n[1]}</span>${n[2]}</button>`).join('')}
      ${admin.length?`<div class="sigap-nav-label">Pengaturan & Administrasi</div>${admin.map(n=>`<button class="nav-btn" data-page="${n[0]}"><span>${n[1]}</span>${n[2]}</button>`).join('')}`:''}`;
    nav.onclick = e => {
      const button = e.target.closest('[data-page]');
      if(!button) return;
      navigate(button.dataset.page);
      if(typeof closeSidebar === 'function') closeSidebar();
    };
    const mobile = document.querySelector('#mobileNav');
    if(mobile){ mobile.innerHTML=''; mobile.hidden=true; }
  };

  function quickItems(){
    const candidates = [
      ['requests','🗓️','Pengajuan','Cuti, izin & lembur','requests'],
      ['trips','✈️','Perjalanan Dinas','Tugas luar & realisasi','trips'],
      ['payslips','📄','Slip Gaji','Lihat slip terbit','payslips'],
      ['announcements','📢','Pengumuman','Informasi internal','announcements_manage'],
      ['recruitment','🧠','Tes Masuk Karyawan','Kandidat & hasil tes','recruitment'],
      ['profile','🧑','Profil Saya','Lengkapi data pribadi','employee_profile_self'],
      ['employee-profiles','🗂️','Database Karyawan','Verifikasi & kelengkapan data','employee_profile_manage'],
      ['employees','👥','Karyawan','Data & akun','employees'],
      ['shifts','🕒','Shift Kerja','Jadwal per jabatan & karyawan','shifts'],
      ['attendance-report','📊','Rekap Absensi','Dasar payroll bulanan','owner_monitor'],
      ['access','🛡️','Jabatan & Akses','Menu per jabatan','positions_access'],
      ['payroll','💰','Payroll','Gaji & komponen','payroll'],
      ['devices','📱','Approval HP','Perangkat baru','devices'],
      ['locations','📍','Lokasi Kerja','GPS & radius','locations'],
      ['audit','🧾','Audit Log','Jejak aktivitas','audit'],
    ];
    return candidates.filter(q=>hasPerm(q[4]));
  }

  function recentAttendanceTable(rows){
    if(!rows?.length) return '<div class="empty">Belum ada data absensi bulan ini.</div>';
    return `<div class="sigap-recent-list">${rows.slice(0,5).map(r=>`
      <div class="sigap-recent-row">
        <div><strong>${fmtDate(r.attendance_date)}</strong><small>${esc(r.location_name||'Lokasi belum tercatat')}</small></div>
        <div class="sigap-recent-time"><span>${r.check_in_at?String(r.check_in_at).slice(11,16):'--:--'}</span><span>—</span><span>${r.check_out_at?String(r.check_out_at).slice(11,16):'--:--'}</span></div>
        <div>${statusBadge(r.status)}</div>
      </div>`).join('')}</div>`;
  }

  function ownerStatus(row){
    if(!row.check_in_at) return {key:'not_checked_in', text:'Belum masuk', cls:'pending'};
    if(row.check_out_at) return {key:'checked_out', text:'Sudah pulang', cls:'published'};
    if(Number(row.late_minutes||0)>0 || row.status==='late') return {key:'late', text:`Telat ${Number(row.late_minutes||0)} mnt`, cls:'late'};
    return {key:'present', text:'Hadir', cls:'present'};
  }

  function announcementsSection(list){
    const rows = list || [];
    const body = rows.length ? rows.slice(0,4).map(a=>`<article class="announcement-item priority-${esc(a.priority||'normal')}">
      <div><strong>${esc(a.title)}</strong><p>${esc(a.body)}</p><small>${esc(a.audience==='all'?'Semua pengguna':a.audience)} · ${fmtDateTime(a.published_at||a.created_at)}</small></div>
      <span>${esc(a.priority||'normal')}</span>
    </article>`).join('') : '<div class="empty compact-empty">Belum ada pengumuman aktif.</div>';
    return `<section class="sigap-announcements card">
      <div class="sigap-section-title"><div><h3>Pengumuman</h3><p>Informasi internal terbaru di aplikasi SIGAP.</p></div>${canManageAnnouncementsCustom()?'<button class="btn primary" type="button" data-go="announcements">Kelola</button>':''}</div>
      <div class="announcement-list">${body}</div>
    </section>`;
  }

  function ownerMonitorSection(summary){
    if(!isOwnerDashboard()) return '';
    const rows = summary.owner_attendance || [];
    const counts = summary.owner_counts || {present:0,late:0,not_checked_in:0,active:0};
    const mobileRows = rows.length ? rows.map(r=>{
      const s=ownerStatus(r);
      return `<div class="owner-mobile-row" data-status="${s.key}" data-name="${esc((r.full_name||'').toLowerCase())}">
        <div class="owner-mobile-person"><strong>${esc(r.full_name)}</strong><small class="owner-mobile-time">${r.check_in_at?String(r.check_in_at).slice(11,16):'--:--'}</small></div>
        <span class="status ${s.cls}">${s.text}</span>
      </div>`;
    }).join('') : '<div class="empty compact-empty">Belum ada karyawan aktif.</div>';

    const desktopRows = rows.length ? rows.map(r=>{
      const s=ownerStatus(r);
      return `<tr data-status="${s.key}" data-name="${esc((r.full_name||'').toLowerCase())}">
        <td><strong>${esc(r.full_name)}</strong><br><small>${esc(r.nik||'-')}</small></td>
        <td>${r.check_in_at?String(r.check_in_at).slice(11,16):'-'}</td>
        <td>${r.check_out_at?String(r.check_out_at).slice(11,16):'-'}</td>
        <td><span class="status ${s.cls}">${s.text}</span></td>
        <td>${esc(r.location_name||'-')}</td>
      </tr>`;
    }).join('') : '<tr><td colspan="5" class="empty">Belum ada karyawan aktif.</td></tr>';

    return `<section class="card owner-monitor-card sigap-owner-monitor">
      <div class="toolbar owner-monitor-head">
        <div><h3>Monitoring Kehadiran Hari Ini</h3><p class="muted">Owner/Super Admin dapat melihat siapa yang sudah absen dan belum.</p></div>
        <button id="ownerExport" class="btn secondary" type="button">Export Excel</button>
      </div>
      <div class="owner-counts owner-counts-v15">
        <button class="owner-count good active" data-owner-filter="all"><strong>${counts.active||rows.length}</strong>Semua</button>
        <button class="owner-count good" data-owner-filter="present"><strong>${counts.present||0}</strong>Hadir</button>
        <button class="owner-count late" data-owner-filter="late"><strong>${counts.late||0}</strong>Terlambat</button>
        <button class="owner-count waiting" data-owner-filter="not_checked_in"><strong>${counts.not_checked_in||0}</strong>Belum masuk</button>
      </div>
      <div class="owner-filterbar"><input id="ownerSearch" placeholder="Cari nama karyawan..."></div>
      <div class="owner-mobile-list" aria-label="Daftar kehadiran karyawan">${mobileRows}</div>
      <div class="table-wrap owner-desktop-table"><table><thead><tr><th>Karyawan</th><th>Masuk</th><th>Pulang</th><th>Status</th><th>Lokasi</th></tr></thead><tbody>${desktopRows}</tbody></table></div>
    </section>`;
  }

  function bindOwnerMonitor(summary){
    if(!isOwnerDashboard()) return;
    let filter='all', q='';
    const apply=()=>{
      document.querySelectorAll('[data-owner-filter]').forEach(b=>b.classList.toggle('active', b.dataset.ownerFilter===filter));
      document.querySelectorAll('.owner-mobile-row,.owner-desktop-table tbody tr').forEach(row=>{
        const s=row.dataset.status||'';
        const name=row.dataset.name||'';
        row.style.display=(filter==='all'||s===filter) && (!q||name.includes(q)) ? '' : 'none';
      });
    };
    document.querySelectorAll('[data-owner-filter]').forEach(btn=>btn.addEventListener('click',()=>{filter=btn.dataset.ownerFilter;apply();}));
    document.querySelector('#ownerSearch')?.addEventListener('input',e=>{q=e.target.value.trim().toLowerCase();apply();});
    document.querySelector('#ownerExport')?.addEventListener('click',()=>{
      const rows=summary.owner_attendance||[];
      if(!window.MPAXLSX){toast('Export Excel belum siap.','error');return;}
      MPAXLSX.exportWorkbook(`Monitoring_Kehadiran_${new Date().toISOString().slice(0,10)}.xlsx`,[{
        name:'Kehadiran Hari Ini',
        rows:[['NIK','Nama','Jabatan','Departemen','Masuk','Pulang','Status','Telat Menit','Lokasi'], ...rows.map(r=>[r.nik||'',r.full_name||'',r.position_name||'',r.department_name||'',r.check_in_at||'',r.check_out_at||'',ownerStatus(r).text,Number(r.late_minutes||0),r.location_name||''])]
      }]);
    });
    apply();
  }

  window.renderDashboard = async function renderDashboardV17(){
    const month = new Date().toISOString().slice(0,7);
    const [dashboard,todayResult,historyResult,locationsResult,security,announcements] = await Promise.all([
      safe(api('/dashboard'),{summary:{}}),
      safe(api('/attendance/today'),{data:null}),
      safe(api('/attendance/history?month='+month),{data:[]}),
      safe(api('/locations'),{data:[]}),
      safe(securityStatus(),{device:false,approved:false,registered:false,pin_registered:false}),
      safe(api('/announcements?active=1'),{data:[]}),
    ]);
    const summary = dashboard.summary || {};
    const today = todayResult.data || summary.today || null;
    const shift = todayResult.shift || null;
    const history = historyResult.data || [];
    const locations = locationsResult.data || [];
    const workStatus = today?.check_out_at ? 'Selesai' : today?.check_in_at ? 'Sedang Bekerja' : 'Belum Absen';
    const ready = !!security.approved;
    const content = document.querySelector('#content'); if(!content) return;
    const quick = quickItems();
    content.innerHTML = `<div class="sigap-dashboard-v08 sigap-dashboard-v15 sigap-dashboard-v17">
      <section class="sigap-clock-card">
        <div class="sigap-clock-head sigap-clock-head-no-logo"><span class="sigap-status-pill ${today?.status||'belum'}">${esc(workStatus)}</span></div>
        <div class="sigap-clock-main"><div><span class="sigap-kicker">Halo, ${esc(state.user.full_name||state.user.username)}</span><strong id="sigapLiveClock">--:--:--</strong><small>${esc(today?.location_name||locations[0]?.location_name||'Lokasi kerja belum diatur')}${shift?` · ${esc(shift.shift_name)} ${esc(shift.work_start)}–${esc(shift.work_end)}`:''}</small></div><div class="sigap-clock-times"><div><span>Masuk</span><strong>${today?.check_in_at?String(today.check_in_at).slice(11,16):'--:--'}</strong></div><div><span>Pulang</span><strong>${today?.check_out_at?String(today.check_out_at).slice(11,16):'--:--'}</strong></div></div></div>
        <div class="sigap-attendance-actions"><button id="sigapCheckIn" class="btn primary" ${today?.check_in_at?'disabled':''}>Absen Masuk</button><button id="sigapCheckOut" class="btn success" ${!today?.check_in_at||today?.check_out_at?'disabled':''}>Absen Pulang</button></div>
        ${ready?'':`<div class="sigap-readiness not-ready"><div><span>!</span><p><strong>HP belum disetujui</strong><small>Daftarkan HP dan tunggu persetujuan admin</small></p></div><button type="button" id="sigapSecurityButton">Siapkan</button></div>`}
      </section>
      ${announcementsSection(announcements.data)}
      ${ownerMonitorSection(summary)}
      <section class="sigap-quick-card"><div class="sigap-section-title"><div><h3>Layanan</h3><p>Akses cepat tanpa membuka menu samping</p></div></div><div class="sigap-quick-grid">${quick.map(q=>`<button type="button" class="sigap-quick-item" data-go="${q[0]}"><span>${q[1]}</span><strong>${q[2]}</strong><small>${q[3]}</small></button>`).join('')}</div></section>
      <section class="sigap-summary-grid"><article><span>Status hari ini</span><strong>${esc(workStatus)}</strong></article><article><span>Shift hari ini</span><strong>${shift?esc(shift.shift_name):'-'}</strong></article><article><span>Menunggu approval</span><strong>${summary.pending_requests||0}</strong></article>${isAdmin()?`<article><span>HP menunggu approval</span><strong>${summary.pending_devices||0}</strong></article><article><span>Karyawan aktif</span><strong>${summary.employees_active||summary.owner_counts?.active||0}</strong></article>`:''}</section>
      <section class="sigap-history-card"><div class="sigap-section-title"><div><h3>Riwayat Absensi Saya</h3><p>Aktivitas terbaru bulan ${esc(month)}</p></div>${isAdmin()?'<button id="sigapExportAttendance" class="btn secondary">Export Excel</button>':''}</div>${recentAttendanceTable(history)}</section>
    </div>`;
    document.querySelector('#sigapCheckIn')?.addEventListener('click',async()=>{await doAttendance('check-in');await renderDashboardV17();});
    document.querySelector('#sigapCheckOut')?.addEventListener('click',async()=>{await doAttendance('check-out');await renderDashboardV17();});
    document.querySelector('#sigapSecurityButton')?.addEventListener('click',()=>navigate('security'));
    content.onclick = e => { const go=e.target.closest('[data-go]'); if(go) navigate(go.dataset.go); };
    bindOwnerMonitor(summary);
    document.querySelector('#sigapExportAttendance')?.addEventListener('click',()=>MPAXLSX.exportWorkbook(`Absensi_${month}.xlsx`,[{name:'Absensi Saya',rows:[['Tanggal','Masuk','Pulang','Status','Telat (menit)','Lokasi','Jarak Masuk','Akurasi GPS'],...history.map(r=>[r.attendance_date,r.check_in_at||'',r.check_out_at||'',r.status,Number(r.late_minutes||0),r.location_name||'',Number(r.check_in_distance_meters||0),Number(r.check_in_accuracy_meters||0)])]}]));
    const tick=()=>{const el=document.querySelector('#sigapLiveClock');if(el)el.textContent=serverNow().toLocaleTimeString('id-ID');};
    tick(); clearInterval(window.__clock); window.__clock=setInterval(tick,1000);
    clearInterval(window.__ownerRefresh); if(isOwnerDashboard()) window.__ownerRefresh=setInterval(()=>{if(state.page==='dashboard')renderDashboardV17()},60000);
  };

  async function renderSecuritySettings(){
    const security = await safe(securityStatus(),{device:false,approved:false});
    const content=document.querySelector('#content');
    content.innerHTML=`<div class="sigap-settings-page"><section class="sigap-settings-hero"><div><span class="sigap-kicker">Pengaturan Absensi</span><h3>Perangkat Absensi</h3><p>Absensi langsung dilakukan melalui HP yang telah didaftarkan dan disetujui admin. Tidak diperlukan sidik jari atau PIN.</p></div><div class="sigap-security-score ${security.approved?'ready':''}">${security.approved?'SIAP':'BELUM'}</div></section><section class="card sigap-settings-card"><div class="sigap-setting-row"><div><strong>Status HP</strong><small>${security.device?'Perangkat sudah terdaftar':'Perangkat belum didaftarkan'}</small></div><span>${security.approved?'Disetujui':security.device?'Menunggu':'Belum'}</span></div><div class="sigap-setting-row"><div><strong>Validasi lokasi</strong><small>GPS dan geofence lokasi kerja</small></div><span>Aktif</span></div><div class="sigap-setting-row"><div><strong>Waktu absensi</strong><small>Menggunakan waktu server untuk mencegah manipulasi jam HP</small></div><span>Server</span></div><div class="notice info sigap-device-note"><strong>Keamanan perangkat:</strong> satu akun hanya dapat memakai HP yang disetujui. Saat HP baru disetujui, HP lama otomatis dicabut.</div><div class="sigap-setting-actions"><button id="sigapRegisterDevice" class="btn soft">${security.device?'Cek / Daftarkan Ulang HP':'Daftarkan HP'}</button><button id="sigapTestReadiness" class="btn warning">Uji Kesiapan Absensi</button></div></section></div>`;
    document.querySelector('#sigapRegisterDevice')?.addEventListener('click',async()=>{loading(true);try{const result=await ensureDevice();toast(result==='approved'?'HP sudah disetujui.':'HP sudah didaftarkan dan menunggu persetujuan admin.','success');await renderSecuritySettings();}catch(e){toast(e.message,'error')}finally{loading(false)}});
    document.querySelector('#sigapTestReadiness')?.addEventListener('click',async()=>{loading(true);try{const check=await attendancePreflight();let gps='';try{const pos=await getPosition();gps=` GPS terbaca dengan akurasi ${Math.round(pos.accuracy)} meter.`;}catch(e){gps=' '+e.message;}const ok=!check.blockers?.length;toast((ok?'Siap digunakan. ':'Belum siap: '+(check.blockers||[]).join(' '))+gps,ok?'success':'error');}catch(e){toast(e.message,'error')}finally{loading(false)}});
  }

  function permissionBadges(items){return `<div class="access-badges">${(items||[]).map(x=>`<span>${esc(x)}</span>`).join('')}</div>`;}

  async function renderAccessSettings(){
    const out = await api('/access/positions');
    const roles = out.roles || {};
    const catalog = out.permission_catalog || {};
    const catalogEntries = Object.entries(catalog);
    const roleOptions = selected => Object.entries(roles).map(([key,val])=>`<option value="${esc(key)}" ${key===selected?'selected':''}>${esc(val.label||key)}</option>`).join('');
    const permChecklist = (selected=[]) => {
      const set = new Set(selected || []);
      const grouped = {};
      catalogEntries.forEach(([key,val])=>{ const g=val.group||'Lainnya'; (grouped[g] ||= []).push([key,val]); });
      return Object.entries(grouped).map(([group,items])=>`<div class="access-perm-group"><strong>${esc(group)}</strong>${items.map(([key,val])=>`<label class="access-perm-check"><input type="checkbox" value="${esc(key)}" ${set.has(key)?'checked':''} ${['dashboard','security'].includes(key)?'data-base="1"':''}> <span><b>${esc(val.label||key)}</b><small>${esc(val.description||'')}</small></span></label>`).join('')}</div>`).join('');
    };
    const roleDefaults = role => (roles[role]?.permissions || ['dashboard','security']);
    const content=document.querySelector('#content');
    content.innerHTML=`<div class="sigap-access-page"><section class="sigap-settings-hero"><div><span class="sigap-kicker">Super Admin</span><h3>Jabatan & Hak Akses</h3><p>Pilih fitur/menu aktif secara custom sesuai master jabatan. Dashboard dan Perangkat Absensi tetap aktif sebagai akses dasar.</p></div><div class="sigap-security-score ready">AKSES</div></section><section class="card"><div class="toolbar"><div><h3>Master Jabatan & Menu Aktif</h3><p class="muted">Role dipakai sebagai template awal. Setelah itu fitur bisa dipilih satu per satu.</p></div><button id="newPosition" class="btn primary">+ Jabatan</button></div><div class="table-wrap access-table-wrap"><table class="mobile-card-table"><thead><tr><th>Jabatan</th><th>Departemen</th><th>Karyawan Aktif</th><th>Role Saat Ini</th><th>Template Role</th><th>Menu Aktif Custom</th><th>Aksi</th></tr></thead><tbody>${out.data?.length?out.data.map((r,i)=>`<tr data-access-row="${i}"><td><strong>${esc(r.position_name||'-')}</strong></td><td>${esc(r.department_name||'-')}</td><td>${Number(r.active_count||0)}</td><td>${esc(r.current_roles||'-')}</td><td><select class="access-role-select">${roleOptions(r.role)}</select><div class="access-row-actions"><button type="button" class="btn soft load-default-perms">Ikuti Template</button></div><label class="access-apply"><input type="checkbox" class="access-apply-check"> Terapkan role ke user jabatan ini</label></td><td class="access-permissions"><div class="access-perm-list">${permChecklist(r.permissions||roleDefaults(r.role))}</div></td><td><button class="btn primary save-access" type="button">Simpan</button></td><input type="hidden" class="access-position" value="${esc(r.position_name||'')}"><input type="hidden" class="access-department" value="${esc(r.department_name||'')}"></tr>`).join(''):'<tr><td colspan="7" class="empty">Belum ada master jabatan.</td></tr>'}</tbody></table></div></section></div>`;
    document.querySelector('#newPosition')?.addEventListener('click',()=>openModal('Tambah Master Jabatan',`<form data-form class="form-grid"><label>Nama Jabatan<input name="position_name" required></label><label>Departemen<input name="department_name" placeholder="Opsional"></label><label class="full">Role Default<select name="default_role">${roleOptions('employee')}</select></label><label class="full">Keterangan<textarea name="description" rows="3"></textarea></label><div class="full"><button class="btn primary wide">Simpan Jabatan</button></div></form>`,d=>api('/positions',{method:'POST',body:d})));
    content.querySelectorAll('[data-base="1"]').forEach(cb=>{cb.checked=true; cb.addEventListener('click',e=>{e.preventDefault(); cb.checked=true; toast('Dashboard dan Perangkat Absensi adalah akses dasar dan tidak dapat dimatikan.','info');});});
    content.querySelectorAll('.load-default-perms').forEach(btn=>btn.addEventListener('click',()=>{const row=btn.closest('[data-access-row]');const role=row.querySelector('.access-role-select').value;const defaults=new Set(roleDefaults(role));row.querySelectorAll('.access-perm-check input').forEach(cb=>{cb.checked=defaults.has(cb.value)||['dashboard','security'].includes(cb.value);});}));
    content.querySelectorAll('.access-role-select').forEach(sel=>sel.addEventListener('change',()=>{const row=sel.closest('[data-access-row]');const defaults=new Set(roleDefaults(sel.value));row.querySelectorAll('.access-perm-check input').forEach(cb=>{cb.checked=defaults.has(cb.value)||['dashboard','security'].includes(cb.value);});}));
    content.querySelectorAll('.save-access').forEach(btn=>btn.addEventListener('click',async()=>{const row=btn.closest('[data-access-row]');let permissions=[...row.querySelectorAll('.access-perm-check input:checked')].map(cb=>cb.value);permissions=[...new Set([...permissions,'dashboard','security'])];const payload={position_name:row.querySelector('.access-position').value,department_name:row.querySelector('.access-department').value,role:row.querySelector('.access-role-select').value,permissions,apply_to_users:row.querySelector('.access-apply-check').checked};loading(true);try{const saved=await api('/access/positions',{method:'POST',body:payload});toast(saved.message||'Hak akses disimpan.','success');await renderAccessSettings();}catch(e){toast(e.message,'error')}finally{loading(false)}}));
  }

  async function renderAnnouncements(){
    const res = await api(canManageAnnouncementsCustom()?'/announcements?active=0':'/announcements?active=1');
    const data=res.data||[];
    const content=document.querySelector('#content');
    const rows=data.length?data.map(a=>`<tr><td><strong>${esc(a.title)}</strong><br><small>${esc(a.body)}</small></td><td>${esc(a.audience)}</td><td><span class="status ${a.priority==='urgent'?'late':a.priority==='important'?'pending':'active'}">${esc(a.priority)}</span></td><td>${a.is_active?'Aktif':'Nonaktif'}</td><td>${fmtDateTime(a.published_at||a.created_at)}</td><td>${canManageAnnouncementsCustom()?`<button class="btn soft edit-announcement" data-id="${a.id}">Edit</button> <button class="btn danger delete-announcement" data-id="${a.id}">Delete</button>`:'-'}</td></tr>`).join(''):'<tr><td colspan="6" class="empty">Belum ada pengumuman.</td></tr>';
    content.innerHTML=`<div class="card"><div class="toolbar"><div><h3>Pengumuman</h3><p class="muted">Pengumuman akan tampil di Dashboard sesuai audiens.</p></div>${canManageAnnouncementsCustom()?'<button id="newAnnouncement" class="btn primary">+ Pengumuman</button>':''}</div><div class="table-wrap"><table class="mobile-card-table"><thead><tr><th>Isi</th><th>Audiens</th><th>Prioritas</th><th>Status</th><th>Tayang</th><th>Aksi</th></tr></thead><tbody>${rows}</tbody></table></div></div>`;
    const formHtml=(a={})=>`<form data-form class="form-grid"><label class="full">Judul<input name="title" value="${esc(a.title||'')}" required></label><label class="full">Isi Pengumuman<textarea name="body" rows="5" required>${esc(a.body||'')}</textarea></label><label>Audiens<select name="audience">${['all','employee','supervisor','hr_admin','payroll_finance','owner','super_admin'].map(x=>`<option value="${x}" ${a.audience===x?'selected':''}>${x==='all'?'Semua':x}</option>`).join('')}</select></label><label>Prioritas<select name="priority">${['normal','important','urgent'].map(x=>`<option value="${x}" ${a.priority===x?'selected':''}>${x}</option>`).join('')}</select></label><label>Tayang mulai<input type="datetime-local" name="published_at" value="${a.published_at?String(a.published_at).replace(' ','T').slice(0,16):''}"></label><label>Berakhir<input type="datetime-local" name="expires_at" value="${a.expires_at?String(a.expires_at).replace(' ','T').slice(0,16):''}"></label><label class="full access-apply"><input type="checkbox" name="is_active" value="1" ${a.is_active===0?'':'checked'}> Aktif</label><div class="full"><button class="btn primary wide">Simpan Pengumuman</button></div></form>`;
    document.querySelector('#newAnnouncement')?.addEventListener('click',()=>openModal('Tambah Pengumuman',formHtml(),d=>api('/announcements',{method:'POST',body:{...d,is_active:!!d.is_active}})));
    content.querySelectorAll('.edit-announcement').forEach(btn=>btn.addEventListener('click',()=>{const a=data.find(x=>String(x.id)===String(btn.dataset.id));if(a)openModal('Edit Pengumuman',formHtml(a),d=>api('/announcements/'+a.id,{method:'PUT',body:{...d,is_active:!!d.is_active}}));}));
    content.querySelectorAll('.delete-announcement').forEach(btn=>btn.addEventListener('click',async()=>{if(!confirm('Hapus pengumuman ini?'))return;loading(true);try{await api('/announcements/'+btn.dataset.id,{method:'DELETE',body:{}});toast('Pengumuman dihapus.','success');await renderAnnouncements();}catch(e){toast(e.message,'error')}finally{loading(false)}}));
  }

  if(originalNavigate){
    window.navigate = async function navigateV17(page){
      if(page==='attendance') page='dashboard';
      if(['security','access','announcements'].includes(page)){
        state.page=page;
        document.querySelectorAll('.nav-btn').forEach(b=>b.classList.toggle('active',b.dataset.page===page));
        if(typeof closeSidebar==='function') closeSidebar();
        const title=document.querySelector('#pageTitle'); const subtitle=document.querySelector('#pageSubtitle');
        if(page==='security'){ if(title)title.textContent='Pengaturan Absensi'; if(subtitle)subtitle.textContent='Perangkat, GPS, dan persetujuan HP'; }
        if(page==='access'){ if(title)title.textContent='Jabatan & Hak Akses'; if(subtitle)subtitle.textContent='Master jabatan dan role menu'; }
        if(page==='announcements'){ if(title)title.textContent='Pengumuman'; if(subtitle)subtitle.textContent='Informasi internal aplikasi'; }
        loading(true); try{ if(page==='security') await renderSecuritySettings(); else if(page==='access') await renderAccessSettings(); else await renderAnnouncements(); window.scrollTo({top:0,behavior:'auto'}); } catch(e){toast(e.message,'error');} finally{loading(false)}
        return;
      }
      return originalNavigate(page);
    };
  }

  function applyBranding(){
    document.title='SIGAP MPA | PT Multi Power Abadi';
    document.querySelectorAll('.topbar-brand-btn,.topbar-logo,#homeBtn').forEach(el=>el.remove());
    const tagline=document.querySelector('.sigap-login-tagline');
    if(tagline)tagline.textContent='Sistem Informasi Gaji, Absensi & Pengajuan MPA';
  }
  function activatePatch(){ applyBranding(); if(state?.user){ buildNav(); if(state.page==='dashboard'||state.page==='attendance') navigate('dashboard'); } }
  applyBranding(); window.addEventListener('load',()=>setTimeout(activatePatch,100)); window.addEventListener('pageshow',()=>setTimeout(activatePatch,100));
})();
