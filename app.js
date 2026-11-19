'use strict';

const APP_VERSION='1.11.3';
const state = { user:null, csrf:'', page:'dashboard', data:{}, serverTime:null, serverOffsetMs:0 };
const $ = s => document.querySelector(s);
const esc = v => String(v ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
const money = v => new Intl.NumberFormat('id-ID',{style:'currency',currency:'IDR',maximumFractionDigits:0}).format(Number(v||0));
const fmtDate = v => v ? new Intl.DateTimeFormat('id-ID',{dateStyle:'medium'}).format(new Date(v+'T00:00:00')) : '-';
const fmtDateTime = v => v ? new Intl.DateTimeFormat('id-ID',{dateStyle:'medium',timeStyle:'short'}).format(new Date(String(v).replace(' ','T'))) : '-';
const roleLabel = {employee:'Karyawan',supervisor:'Supervisor',hr_admin:'HR Admin',payroll_finance:'Finance Payroll',owner:'Owner',super_admin:'Super Admin'};
function syncPlatformClasses(){
  const ua=navigator.userAgent||'';
  const iOS=/iPad|iPhone|iPod/.test(ua)||(navigator.platform==='MacIntel'&&navigator.maxTouchPoints>1);
  const standalone=window.matchMedia?.('(display-mode: standalone)').matches||navigator.standalone===true;
  document.documentElement.classList.toggle('is-ios',iOS);
  document.documentElement.classList.toggle('is-standalone',!!standalone);
}
syncPlatformClasses();
function setServerTime(value){if(!value)return;const parsed=new Date(String(value).replace(' ','T')+'+07:00');if(!Number.isNaN(parsed.getTime())){state.serverTime=value;state.serverOffsetMs=parsed.getTime()-Date.now();}}
function serverNow(){return new Date(Date.now()+state.serverOffsetMs);}

function loading(on){const el=$('#loading');if(!el)return;el.hidden=!on;}
function toast(message,type='info'){const el=$('#toast');el.textContent=message;el.style.borderLeftColor=type==='error'?'#d60812':type==='success'?'#168447':'#1769aa';el.hidden=false;clearTimeout(window.__toast);window.__toast=setTimeout(()=>el.hidden=true,4200);}
function showPantun(title,pantun,variant){
  if(!pantun)return;
  const dlg=$('#modal');
  $('#modalTitle').textContent=title;
  $('#modalBody').innerHTML=`<div class="pantun-box ${variant||''}">${esc(pantun).replace(/\n/g,'<br>')}</div><div class="full" style="margin-top:16px"><button class="btn primary wide" type="button" id="pantunCloseBtn">Siap!</button></div>`;
  dlg.showModal();
  $('#pantunCloseBtn').onclick=()=>dlg.close();
}
function statusBadge(status){return `<span class="status ${esc(status)}">${esc(status)}</span>`;}
function deviceUuid(){let id=localStorage.getItem('mpa_device_uuid');if(!id){id=(crypto.randomUUID?crypto.randomUUID():'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g,c=>{const r=Math.random()*16|0,v=c==='x'?r:(r&3|8);return v.toString(16)}));localStorage.setItem('mpa_device_uuid',id);}return id;}
function formDataObject(form){return Object.fromEntries(new FormData(form).entries());}

async function api(path,options={}){
  const headers={'Accept':'application/json',...(options.headers||{})};
  if(options.body && !(options.body instanceof FormData)){headers['Content-Type']='application/json';options.body=JSON.stringify(options.body);}
  if((options.method||'GET')!=='GET' && state.csrf) headers['X-CSRF-Token']=state.csrf;
  const res=await fetch('api'+path,{credentials:'same-origin',cache:'no-store',...options,headers});
  let data={};try{data=await res.json();}catch{}
  if(res.status===401){showLogin();throw new Error(data.message||'Sesi berakhir.');}
  if(!res.ok){const err=new Error(data.message||`Server error ${res.status}`);err.data=data;err.status=res.status;throw err;}
  return data;
}

function showLogin(){state.user=null;state.csrf='';document.body.classList.add('auth-locked');$('#appView').hidden=true;$('#loginView').hidden=false;window.scrollTo({top:0,behavior:'auto'});const pass=$('#loginPassword');if(pass)pass.value='';}
function showApp(){
  document.body.classList.remove('auth-locked');
  $('#loginView').hidden=true;$('#appView').hidden=false;
  ensureDrawerControls();
  $('#userName').textContent=state.user.full_name||state.user.username;
  $('#userRole').textContent=roleLabel[state.user.role]||state.user.role;
  const avatar=$('#avatar');
  if(avatar){
    const initial=(state.user.full_name||state.user.username||'M')[0].toUpperCase();
    avatar.title=state.user.full_name||state.user.username||'Pengguna';
    avatar.setAttribute('aria-label','Akun '+(state.user.full_name||state.user.username||initial));
    avatar.innerHTML=`<img src="icons/topbar-logo.png?v=${APP_VERSION}" alt="SIGAP">`;
    avatar.querySelector('img')?.addEventListener('error',()=>{avatar.textContent=initial;},{once:true});
  }
  buildNav();navigate('dashboard');
}

const NAV=[
  ['dashboard','⌂','Dashboard',['employee','supervisor','hr_admin','payroll_finance','owner','super_admin']],
  ['attendance','◷','Absensi',['employee','supervisor','hr_admin','payroll_finance','super_admin']],
  ['attendance-report','▥','Rekap Absensi',['hr_admin','payroll_finance','owner','super_admin']],
  ['requests','▣','Cuti & Lembur',['employee','supervisor','hr_admin','payroll_finance','owner','super_admin']],
  ['trips','✈','Perjalanan Dinas',['employee','supervisor','hr_admin','payroll_finance','super_admin']],
  ['payslips','▤','Slip Gaji',['employee','supervisor','hr_admin','payroll_finance','super_admin']],
  ['recruitment','🧠','Tes Masuk Karyawan',['hr_admin','super_admin']],
  ['profile','🧑','Profil Saya',['employee','supervisor','hr_admin','payroll_finance','super_admin']],
  ['employee-profiles','🗂','Database Karyawan',['hr_admin','super_admin']],
  ['employees','♟','Karyawan',['hr_admin','payroll_finance','super_admin']],
  ['shifts','◫','Shift Kerja',['hr_admin','super_admin']],
  ['locations','⌖','Lokasi Kerja',['hr_admin','super_admin']],
  ['devices','▣','Persetujuan HP',['hr_admin','super_admin']],
  ['payroll','▦','Payroll',['payroll_finance','super_admin']],
  ['audit','≡','Audit Log',['super_admin']],
];

const APP_ICONS={
  dashboard:'⌂',attendance:'⏱️','attendance-report':'📊','attendance-admin':'✎',requests:'🗓️',trips:'✈️',payslips:'📄',
  employees:'👥',shifts:'🕒',locations:'📍',devices:'📱',payroll:'💰',audit:'🧾',recruitment:'🧠',profile:'🧑','employee-profiles':'🗂️'
};
const APP_TONES={
  attendance:'red','attendance-report':'teal',requests:'blue',trips:'amber',payslips:'green',employees:'violet',shifts:'blue',
  locations:'teal',devices:'slate',payroll:'gold',audit:'gray',recruitment:'violet',profile:'blue','employee-profiles':'violet'
};
const DISC_TRAIT_LABEL={D:'Dominance',I:'Influence',S:'Steadiness',C:'Compliance'};
const RECRUITMENT_STATUS_LABEL={
  not_started:'Belum Mulai',iq_in_progress:'Sedang Tes IQ',iq_completed:'IQ Selesai, Belum DISC',
  disc_in_progress:'Sedang Tes DISC',completed:'Selesai',expired:'Link Kedaluwarsa',aborted:'Dibatalkan'
};
function launcherHtml(){
  return allowedNav().filter(n=>n[0]!=='dashboard').map(n=>`<button class="app-menu-tile" type="button" data-go="${n[0]}"><span class="app-menu-icon tone-${APP_TONES[n[0]]||'red'}">${APP_ICONS[n[0]]||n[1]}</span><span class="app-menu-label">${n[2]}</span></button>`).join('');
}
function enhanceMobileTables(){
  document.querySelectorAll('#content table').forEach(table=>{
    const labels=[...table.querySelectorAll('thead th')].map(th=>th.textContent.trim());
    table.classList.add('mobile-card-table');
    table.querySelectorAll('tbody tr').forEach(row=>{
      [...row.children].forEach((cell,i)=>{
        if(!cell.dataset.label)cell.dataset.label=labels[i]||'';
      });
    });
  });
}
function allowedNav(){
  return NAV.filter(n=>n[3].includes(state.user.role));
}
function preferredMobilePages(){
  const byRole={
    employee:['dashboard','attendance','requests','payslips'],
    supervisor:['dashboard','attendance','requests','trips'],
    hr_admin:['dashboard','attendance','employees','devices'],
    payroll_finance:['dashboard','attendance','payroll','payslips'],
    owner:['dashboard','requests','attendance-report'],
    super_admin:['dashboard','attendance','employees','payroll'],
  };
  return byRole[state.user.role]||['dashboard','attendance','requests','payslips'];
}
function setSidebarOpen(open){
  const sidebar=$('.sidebar');
  const backdrop=$('#navBackdrop');
  const menu=$('#menuBtn');
  sidebar?.classList.toggle('open',!!open);
  if(backdrop)backdrop.hidden=!open;
  document.body.classList.toggle('drawer-open',!!open);
  menu?.setAttribute('aria-expanded',String(!!open));
  sidebar?.setAttribute('aria-hidden',String(!open && window.innerWidth<=1024));
}
function openSidebar(){setSidebarOpen(true)}
function closeSidebar(){setSidebarOpen(false)}

function ensureDrawerControls(){
  const appView=$('#appView');
  const sidebar=$('.sidebar');
  if(!appView||!sidebar)return;

  let close=$('#sidebarClose');
  if(!close){
    close=document.createElement('button');
    close.id='sidebarClose';
    close.className='drawer-close';
    close.type='button';
    close.setAttribute('aria-label','Tutup menu');
    close.textContent='×';
    const brand=sidebar.querySelector('.brand');
    (brand||sidebar).appendChild(close);
  }

  let backdrop=$('#navBackdrop');
  if(!backdrop){
    backdrop=document.createElement('div');
    backdrop.id='navBackdrop';
    backdrop.className='nav-backdrop';
    backdrop.hidden=true;
    sidebar.insertAdjacentElement('afterend',backdrop);
  }

  close.onclick=closeSidebar;
  backdrop.onclick=closeSidebar;
  if(!close.dataset.iosTouchBound){
    close.addEventListener('touchend',e=>{e.preventDefault();closeSidebar();},{passive:false});
    close.dataset.iosTouchBound='1';
  }
  if(!backdrop.dataset.iosTouchBound){
    backdrop.addEventListener('touchend',e=>{e.preventDefault();closeSidebar();},{passive:false});
    backdrop.dataset.iosTouchBound='1';
  }

  if(!sidebar.dataset.drawerGestureBound){
    let startX=null;
    sidebar.addEventListener('touchstart',e=>{
      startX=e.touches?.[0]?.clientX ?? null;
    },{passive:true});
    sidebar.addEventListener('touchend',e=>{
      if(startX===null)return;
      const endX=e.changedTouches?.[0]?.clientX ?? startX;
      if(endX-startX < -55)closeSidebar();
      startX=null;
    },{passive:true});
    sidebar.dataset.drawerGestureBound='1';
  }
}

document.addEventListener('click',e=>{
  const sidebar=$('.sidebar');
  if(!sidebar?.classList.contains('open')||window.innerWidth>1024)return;
  const clickedInside=sidebar.contains(e.target);
  const clickedOpener=e.target.closest?.('#menuBtn,[data-more]');
  if(!clickedInside&&!clickedOpener)closeSidebar();
},true);
function buildNav(){
  const allowed=allowedNav();
  $('#mainNav').innerHTML=allowed.map(n=>`<button class="nav-btn" data-page="${n[0]}"><span>${APP_ICONS[n[0]]||n[1]}</span>${n[2]}</button>`).join('');
  $('#mainNav').onclick=e=>{const b=e.target.closest('[data-page]');if(b){navigate(b.dataset.page);closeSidebar();}};
  const mobile=$('#mobileNav');
  if(mobile){mobile.innerHTML='';mobile.hidden=true;}
}
async function navigate(page){
  state.page=page;document.querySelectorAll('.nav-btn').forEach(b=>b.classList.toggle('active',b.dataset.page===page));
  closeSidebar();
  const nav=NAV.find(n=>n[0]===page);$('#pageTitle').textContent=nav?.[2]||'SIGAP MPA';$('#pageSubtitle').textContent=serverNow().toLocaleString('id-ID',{dateStyle:'full',timeStyle:'short'});
  loading(true);try{await ({dashboard:renderDashboard,attendance:renderAttendance,'attendance-report':renderAttendanceReport,'attendance-admin':renderAttendanceAdmin,requests:renderRequests,trips:renderTrips,payslips:renderPayslips,employees:renderEmployees,shifts:renderShifts,locations:renderLocations,devices:renderDevices,payroll:renderPayroll,audit:renderAudit,recruitment:renderRecruitment,profile:renderProfile,'employee-profiles':renderEmployeeProfiles}[page]||renderDashboard)();enhanceMobileTables();window.scrollTo({top:0,behavior:'auto'});}catch(e){toast(e.message,'error');$('#content').innerHTML=`<div class="card"><h3>Terjadi kendala</h3><p>${esc(e.message)}</p></div>`;}finally{loading(false)}
}

async function renderDashboard(){
  const {summary}=await api('/dashboard');
  const t=summary.today;
  const ownerView=['owner','super_admin'].includes(state.user.role);
  const ownerRows=summary.owner_attendance||[];
  const counts=summary.owner_counts||{present:0,late:0,not_checked_in:0,active:0};
  const launcher=launcherHtml();
  const mobileMenuCard=launcher?`<div class="app-menu-card"><div class="app-section-head"><div><h3>Menu Utama</h3><p>Pilih layanan yang dibutuhkan</p></div><button class="mini-menu-btn" type="button" data-open-menu>Semua</button></div><div class="app-menu-grid">${launcher}</div></div>`:'';
  const ownerMobileList=ownerRows.length?ownerRows.map(r=>{
    const checked=!!r.check_in_at;
    const late=Number(r.late_minutes||0)>0;
    const statusClass=!checked?'pending':late?'late':'present';
    const statusText=!checked?'Belum masuk':late?`Telat ${Number(r.late_minutes)} mnt`:'Tepat waktu';
    const time=checked?String(r.check_in_at).slice(11,16):'--:--';
    return `<div class="owner-mobile-row"><div class="owner-mobile-person"><strong>${esc(r.full_name)}</strong><small>${time}</small></div><span class="status ${statusClass}">${statusText}</span></div>`;
  }).join(''):'<div class="empty compact-empty">Belum ada karyawan aktif.</div>';
  const ownerPanel=ownerView?`
    <div class="card owner-monitor-card" style="margin-top:18px">
      <div class="toolbar owner-monitor-head">
        <div><h3>Monitoring Kehadiran Hari Ini</h3><p class="muted">Ringkasan masuk karyawan berdasarkan waktu server.</p></div>
        <div class="owner-counts">
          <span class="owner-count good"><strong>${counts.present||0}</strong>Tepat waktu</span>
          <span class="owner-count late"><strong>${counts.late||0}</strong>Terlambat</span>
          <span class="owner-count waiting"><strong>${counts.not_checked_in||0}</strong>Belum masuk</span>
        </div>
      </div>
      <div class="owner-mobile-list" aria-label="Daftar kehadiran karyawan">${ownerMobileList}</div>
      <div class="table-wrap owner-desktop-table"><table><thead><tr><th>Karyawan</th><th>Jam Masuk</th><th>Status</th><th>Lokasi</th></tr></thead><tbody>
      ${ownerRows.length?ownerRows.map(r=>`<tr><td><strong>${esc(r.full_name)}</strong><br><small>${esc(r.nik)}</small></td><td>${r.check_in_at?String(r.check_in_at).slice(11,16):'-'}</td><td>${!r.check_in_at?'<span class="status pending">Belum masuk</span>':Number(r.late_minutes)>0?`<span class="status late">Telat ${Number(r.late_minutes)} menit</span>`:'<span class="status present">Tepat waktu</span>'}</td><td>${esc(r.location_name||'-')}</td></tr>`).join(''):'<tr><td colspan="4" class="empty">Belum ada data karyawan aktif.</td></tr>'}
      </tbody></table></div>
    </div>`:'';
  $('#content').innerHTML=`
  <section class="mobile-home">
    <div class="mobile-welcome-card">
      <div><span class="welcome-kicker">Selamat datang</span><strong>${esc(state.user.full_name||state.user.username)}</strong></div>
      <div class="mobile-time"><span id="mobileClock">--:--</span><small>WIB</small></div>
    </div>
    ${mobileMenuCard}
  </section>
  <div class="grid cols-4 dashboard-stats">
    <div class="card stat"><div><span>Status Hari Ini</span><strong>${t?t.check_out_at?'Selesai':t.check_in_at?'Bekerja':'Belum':'Belum'}</strong></div><div class="stat-icon">◷</div></div>
    <div class="card stat"><div><span>Jam Masuk</span><strong>${t?.check_in_at?String(t.check_in_at).slice(11,16):'--:--'}</strong></div><div class="stat-icon">→</div></div>
    <div class="card stat"><div><span>Jam Pulang</span><strong>${t?.check_out_at?String(t.check_out_at).slice(11,16):'--:--'}</strong></div><div class="stat-icon">←</div></div>
    <div class="card stat"><div><span>Menunggu Approval</span><strong>${summary.pending_requests||0}</strong></div><div class="stat-icon">✓</div></div>
  </div>
  <div class="grid cols-2 dashboard-lower" style="margin-top:18px">
    <div class="card hero desktop-hero"><h3>Selamat datang, ${esc(state.user.full_name||state.user.username)}</h3><p class="muted">Absensi menggunakan waktu server, GPS, radius lokasi, dan perangkat yang telah disetujui.</p><div class="attendance-clock" id="liveClock">--:--:--</div><div class="actions">${state.user.role!=='owner'?'<button class="btn primary" data-go="attendance">Buka Absensi</button><button class="btn soft" data-go="requests">Buat Pengajuan</button>':'<button class="btn primary" type="button" onclick="window.location.reload()">Perbarui Data</button>'}</div></div>
    <div class="card system-card"><h3>Kontrol Sistem</h3><div class="slip-row"><span>Perangkat menunggu persetujuan</span><strong>${summary.pending_devices||0}</strong></div><div class="slip-row"><span>Karyawan aktif</span><strong>${summary.employees_active||counts.active||'-'}</strong></div><div class="slip-row"><span>Sumber waktu</span><strong>Server</strong></div><p class="footnote">Absensi memakai GPS, geofence, perangkat terdaftar, dan waktu server.</p></div>
  </div>${ownerPanel}`;
  $('#content').onclick=e=>{const b=e.target.closest('[data-go]');if(b)navigate(b.dataset.go);if(e.target.closest('[data-open-menu]'))openSidebar();};
  const tick=()=>{const now=serverNow();const el=$('#liveClock');if(el)el.textContent=now.toLocaleTimeString('id-ID');const mobile=$('#mobileClock');if(mobile)mobile.textContent=now.toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit'});};tick();clearInterval(window.__clock);window.__clock=setInterval(tick,1000);
  clearInterval(window.__ownerRefresh);if(ownerView)window.__ownerRefresh=setInterval(()=>{if(state.page==='dashboard')renderDashboard()},60000);
}

function webauthnSupported(){return !!(window.PublicKeyCredential&&navigator.credentials&&window.isSecureContext);}
function b64urlToBytes(value){
  const base64=String(value).replace(/-/g,'+').replace(/_/g,'/').padEnd(Math.ceil(String(value).length/4)*4,'=');
  const binary=atob(base64);return Uint8Array.from(binary,c=>c.charCodeAt(0));
}
function bytesToB64url(value){
  const bytes=value instanceof ArrayBuffer?new Uint8Array(value):new Uint8Array(value.buffer,value.byteOffset||0,value.byteLength);
  let binary='';for(const byte of bytes)binary+=String.fromCharCode(byte);
  return btoa(binary).replace(/\+/g,'-').replace(/\//g,'_').replace(/=+$/,'');
}
function decodeCreationOptions(options){
  return {...options,challenge:b64urlToBytes(options.challenge),user:{...options.user,id:b64urlToBytes(options.user.id)},excludeCredentials:(options.excludeCredentials||[]).map(c=>({...c,id:b64urlToBytes(c.id)}))};
}
function decodeRequestOptions(options){
  return {...options,challenge:b64urlToBytes(options.challenge),allowCredentials:(options.allowCredentials||[]).map(c=>({...c,id:b64urlToBytes(c.id)}))};
}
function serializeCredential(credential,kind){
  const base={id:credential.id,type:credential.type,rawId:bytesToB64url(credential.rawId),clientExtensionResults:credential.getClientExtensionResults?.()||{}};
  if(kind==='create')return {...base,response:{clientDataJSON:bytesToB64url(credential.response.clientDataJSON),attestationObject:bytesToB64url(credential.response.attestationObject),transports:credential.response.getTransports?.()||[]}};
  return {...base,response:{clientDataJSON:bytesToB64url(credential.response.clientDataJSON),authenticatorData:bytesToB64url(credential.response.authenticatorData),signature:bytesToB64url(credential.response.signature),userHandle:credential.response.userHandle?bytesToB64url(credential.response.userHandle):null}};
}
function webauthnErrorMessage(error){
  if(error?.name==='NotAllowedError')return 'Verifikasi dibatalkan, waktunya habis, atau kunci layar belum diaktifkan.';
  if(error?.name==='InvalidStateError')return 'Sidik jari/passkey ini sudah terdaftar pada perangkat.';
  if(error?.name==='SecurityError')return 'Passkey ditolak karena konfigurasi domain/HTTPS tidak sesuai.';
  return error?.message||'Proses sidik jari/passkey gagal.';
}
async function securityStatus(){return await api('/webauthn/status?device_uuid='+encodeURIComponent(deviceUuid()));}
async function attendancePreflight(){
  const server=await api('/attendance/preflight?device_uuid='+encodeURIComponent(deviceUuid()));
  const client={secure_context:window.isSecureContext,geolocation_supported:!!navigator.geolocation,origin:location.origin};
  const blockers=[...(server.blockers||[])];
  if(!client.secure_context)blockers.push('Koneksi HTTPS belum aman.');
  if(!client.geolocation_supported)blockers.push('GPS tidak didukung browser ini.');
  return {...server,client,blockers:[...new Set(blockers)]};
}
function readinessHtml(check){
  const item=(ok,label,detail='')=>`<div class="readiness-item ${ok?'ready':'blocked'}"><span>${ok?'✓':'!'}</span><div><strong>${esc(label)}</strong>${detail?`<small>${esc(detail)}</small>`:''}</div></div>`;
  return `<div class="readiness-list">
    ${item(check.client?.secure_context,'HTTPS aman',check.client?.origin||'')}
    ${item(check.employee_linked,'Akun terhubung karyawan')}
    ${item(check.device_status==='approved','HP disetujui admin',check.device_status||'belum didaftarkan')}
    ${item((check.active_locations||0)>0,'Lokasi kerja aktif',`${check.active_locations||0} lokasi`)}
    ${item(check.client?.geolocation_supported,'GPS tersedia')}
  </div>${check.blockers?.length?`<div class="notice warning"><strong>Belum siap:</strong><ul>${check.blockers.map(x=>`<li>${esc(x)}</li>`).join('')}</ul></div>`:`<div class="notice success">HP sudah disetujui dan siap untuk absensi langsung.</div>`}`;
}
async function activatePasskey(){
  if(!webauthnSupported())throw new Error('HP/browser ini belum mendukung sidik jari atau passkey. Pastikan HTTPS, kunci layar, dan Chrome terbaru aktif.');
  try{
    const out=await api('/webauthn/register/options',{method:'POST',body:{device_uuid:deviceUuid()}});
    const credential=await navigator.credentials.create({publicKey:decodeCreationOptions(out.publicKey)});
    if(!credential)throw new Error('Credential tidak dibuat.');
    return await api('/webauthn/register/verify',{method:'POST',body:{device_uuid:deviceUuid(),...serializeCredential(credential,'create')}});
  }catch(error){throw new Error(webauthnErrorMessage(error));}
}
async function verifyPasskey(){
  if(!webauthnSupported())throw new Error('Sidik jari/passkey tidak tersedia pada HP/browser ini.');
  try{
    const out=await api('/webauthn/auth/options',{method:'POST',body:{device_uuid:deviceUuid()}});
    const credential=await navigator.credentials.get({publicKey:decodeRequestOptions(out.publicKey)});
    if(!credential)throw new Error('Verifikasi tidak selesai.');
    const verified=await api('/webauthn/auth/verify',{method:'POST',body:{device_uuid:deviceUuid(),...serializeCredential(credential,'get')}});
    return verified.biometric_token;
  }catch(error){throw new Error(webauthnErrorMessage(error));}
}


async function setAttendancePin(){
  return await new Promise((resolve,reject)=>{
    const dlg=$('#modal');
    $('#modalTitle').textContent='Atur PIN SIGAP';
    $('#modalBody').innerHTML=`<form id="pinSetupForm" class="form-grid">
      <label class="full">Password akun<input type="password" name="current_password" autocomplete="current-password" required></label>
      <label>PIN SIGAP 6 angka<input type="password" name="pin" inputmode="numeric" pattern="\\d{6}" maxlength="6" autocomplete="new-password" required></label>
      <label>Ulangi PIN<input type="password" name="pin_confirm" inputmode="numeric" pattern="\\d{6}" maxlength="6" autocomplete="new-password" required></label>
      <p class="full footnote">PIN hanya berlaku pada HP yang sudah disetujui. Jangan gunakan 123456, tanggal lahir, atau angka berulang.</p>
      <div class="full"><button class="btn primary wide" type="submit">Simpan PIN</button></div>
    </form>`;
    dlg.showModal();
    const form=$('#pinSetupForm');
    const close=()=>{if(dlg.open)dlg.close();};
    form.onsubmit=async e=>{
      e.preventDefault();loading(true);
      try{
        const d=formDataObject(form);
        const out=await api('/attendance/pin/set',{method:'POST',body:{...d,device_uuid:deviceUuid()}});
        close();toast(out.message,'success');resolve(out);
      }catch(err){toast(err.message,'error');reject(err)}
      finally{loading(false)}
    };
  });
}
async function verifyAttendancePin(){
  return await new Promise((resolve,reject)=>{
    const dlg=$('#modal');
    $('#modalTitle').textContent='Verifikasi PIN SIGAP';
    $('#modalBody').innerHTML=`<form id="pinVerifyForm" class="form-grid">
      <label class="full">Masukkan PIN 6 angka<input autofocus type="password" name="pin" inputmode="numeric" pattern="\\d{6}" maxlength="6" autocomplete="one-time-code" required></label>
      <div class="full"><button class="btn primary wide" type="submit">Verifikasi</button></div>
    </form>`;
    dlg.showModal();
    const form=$('#pinVerifyForm');
    form.onsubmit=async e=>{
      e.preventDefault();loading(true);
      try{
        const d=formDataObject(form);
        const out=await api('/attendance/pin/verify',{method:'POST',body:{device_uuid:deviceUuid(),pin:d.pin}});
        dlg.close();resolve(out.auth_token);
      }catch(err){toast(err.message,'error');reject(err)}
      finally{loading(false)}
    };
  });
}

async function ensureDevice(){
  const result=await api('/devices/register',{method:'POST',body:{device_uuid:deviceUuid(),device_label:navigator.userAgent.slice(0,150)}});
  return result.status;
}
async function getPosition(){
  if(!navigator.geolocation)throw new Error('GPS tidak didukung perangkat ini.');
  return await new Promise((resolve,reject)=>navigator.geolocation.getCurrentPosition(p=>resolve({latitude:p.coords.latitude,longitude:p.coords.longitude,accuracy:p.coords.accuracy}),e=>reject(new Error('Lokasi tidak dapat dibaca: '+e.message)),{enableHighAccuracy:true,timeout:20000,maximumAge:0}));
}
async function doAttendance(kind){
  loading(true);try{
    const status=await ensureDevice();
    if(status!=='approved')throw new Error('HP belum disetujui. Admin/HR harus membuka Persetujuan HP lalu menekan Setujui.');
    const preflight=await attendancePreflight();
    if(preflight.blockers?.length)throw new Error(preflight.blockers.join(' '));
    const pos=await getPosition();
    if(pos.accuracy>Number(preflight.max_accuracy_meters||5000))throw new Error(`Akurasi GPS ${Math.round(pos.accuracy)} m, sedangkan batas ${Math.round(preflight.max_accuracy_meters)} m. Pindah ke area terbuka.`);
    let body={...pos,device_uuid:deviceUuid()};
    let out;
    try{out=await api('/attendance/'+kind,{method:'POST',body});}
    catch(e){
      if(kind==='check-out'&&e?.data?.error==='outside_reason_required'){
        const reason=prompt(`Anda berada sekitar ${Math.round(Number(e.data.distance||0))} meter dari lokasi kerja. Jelaskan alasan check-out di luar kantor:`,'');
        if(!reason?.trim())throw new Error('Check-out dibatalkan karena alasan wajib diisi.');
        body.outside_reason=reason.trim();out=await api('/attendance/'+kind,{method:'POST',body});
      }else throw e;
    }
    toast(out.message,'success');await renderAttendance();
    if(out.pantun){
      if(kind==='check-in')showPantun(out.late_minutes>0?'Yah, Telat Nih! \u{1F605}':'Mantap, Tepat Waktu! \u{1F389}',out.pantun,out.late_minutes>0?'':'pantun-ontime');
      else showPantun('Selamat Pulang! \u{1F44B}',out.pantun,'pantun-checkout');
    }
  }catch(e){toast(e.message,'error')}finally{loading(false)}
}
async function renderAttendance(){
  const month=new Date().toISOString().slice(0,7);
  const [todayResponse,{data:history},{data:locations},security,preflight]=await Promise.all([api('/attendance/today'),api('/attendance/history?month='+month),api('/locations'),securityStatus(),attendancePreflight()]);
  const today=todayResponse.data;
  const shift=preflight.shift||todayResponse.shift||null;
  const deviceText=!security.device?'Belum didaftarkan':security.approved?'Disetujui':security.device.status==='revoked'?'Dicabut':'Menunggu persetujuan';
  $('#content').innerHTML=`<div class="grid cols-2 attendance-layout"><div class="card hero attendance-main-card"><h3>Absensi Hari Ini</h3><p>${today?statusBadge(today.status):statusBadge('belum absen')}</p><div class="attendance-clock">${today?.check_in_at?String(today.check_in_at).slice(11,16):'--:--'} <span style="font-size:18px">s.d.</span> ${today?.check_out_at?String(today.check_out_at).slice(11,16):'--:--'}</div><p class="muted">${today?.location_name||locations[0]?.location_name||'Lokasi belum diatur'}</p>
  <div class="actions"><button id="checkInBtn" class="btn primary" ${today?.check_in_at?'disabled':''}>Absen Masuk</button><button id="checkOutBtn" class="btn success" ${!today?.check_in_at||today?.check_out_at?'disabled':''}>Absen Pulang</button><button id="registerDeviceBtn" class="btn soft full-action">${security.device?'Cek / Daftarkan Ulang HP':'Daftarkan HP'}</button><button id="testAttendanceBtn" class="btn warning full-action">Uji Kesiapan Absensi</button></div></div><div class="card attendance-security-card"><h3>Kesiapan & Keamanan</h3><div class="slip-row"><span>Waktu transaksi</span><strong>Server</strong></div><div class="slip-row"><span>Status HP</span><strong>${esc(deviceText)}</strong></div><div class="slip-row"><span>Shift kerja</span><strong>${shift?esc(shift.shift_name):'-'}</strong></div><div class="slip-row"><span>Jadwal</span><strong>${shift?`${esc(shift.work_start)}–${esc(shift.work_end)}`:'-'}</strong></div><div class="slip-row"><span>Hari kerja</span><strong>${shift?esc(shiftDaysLabel(shift.work_days)):'-'}</strong></div><div class="slip-row"><span>Validasi lokasi</span><strong>GPS + geofence</strong></div><div id="readinessBox">${readinessHtml(preflight)}</div><p class="footnote">Tidak diperlukan sidik jari atau PIN. Absensi hanya dapat dilakukan dari HP yang sudah disetujui admin.</p></div></div><div class="card attendance-history-card" style="margin-top:18px"><div class="toolbar"><h3>Riwayat Bulan Ini</h3><button id="exportAttendance" class="btn secondary">Export Excel</button></div>${tableAttendance(history)}</div>`;
  $('#checkInBtn').onclick=()=>doAttendance('check-in');$('#checkOutBtn').onclick=()=>doAttendance('check-out');
  $('#registerDeviceBtn').onclick=async()=>{loading(true);try{const r=await ensureDevice();toast(r==='approved'?'HP sudah disetujui.':'HP didaftarkan dan menunggu persetujuan admin.',r==='approved'?'success':'info');await renderAttendance()}catch(e){toast(e.message,'error')}finally{loading(false)}};
  $('#testAttendanceBtn').onclick=async()=>{loading(true);try{const check=await attendancePreflight();let gpsText='';if(check.client.geolocation_supported&&check.device_status==='approved'){try{const pos=await getPosition();gpsText=`GPS terbaca dengan akurasi ${Math.round(pos.accuracy)} meter.`}catch(e){gpsText=e.message}}$('#readinessBox').innerHTML=readinessHtml(check)+(gpsText?`<p class="footnote">${esc(gpsText)}</p>`:'');if(!check.blockers.length)toast('HP siap digunakan untuk absensi langsung. '+gpsText,'success');else toast(check.blockers.join(' '),'error')}catch(e){toast(e.message,'error')}finally{loading(false)}};
  $('#exportAttendance').onclick=()=>MPAXLSX.exportWorkbook(`Absensi_${month}.xlsx`,[{name:'Absensi',rows:[['Tanggal','Masuk','Pulang','Status','Telat (menit)','Lokasi','Distan Masuk','Akurasi GPS','Metode Masuk','Metode Pulang'],...history.map(r=>[r.attendance_date,r.check_in_at||'',r.check_out_at||'',r.status,Number(r.late_minutes),r.location_name||'',Number(r.check_in_distance_meters||0),Number(r.check_in_accuracy_meters||0),r.check_in_auth_method||'',r.check_out_auth_method||''])]}]);
}
function tableAttendance(rows){return rows.length?`<div class="table-wrap"><table><thead><tr><th>Tanggal</th><th>Masuk</th><th>Pulang</th><th>Status</th><th>Telat</th><th>Lokasi</th><th>Catatan Pulang</th></tr></thead><tbody>${rows.map(r=>`<tr><td>${fmtDate(r.attendance_date)}</td><td>${r.check_in_at?String(r.check_in_at).slice(11,16):'-'}</td><td>${r.check_out_at?String(r.check_out_at).slice(11,16):'-'}</td><td>${statusBadge(r.status)}</td><td>${r.late_minutes} menit</td><td>${esc(r.location_name||'-')}</td><td>${Number(r.check_out_outside_geofence||0)?`<strong>Di luar lokasi</strong><br><small>${esc(r.check_out_reason||'-')}</small>`:'-'}</td></tr>`).join('')}</tbody></table></div>`:'<div class="empty">Belum ada data absensi.</div>'}



function reportNumber(value){
  const number=Number(value||0);
  return Number.isInteger(number)?String(number):number.toLocaleString('id-ID',{maximumFractionDigits:2});
}
function reportDayName(dayNumber){return ['','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'][Number(dayNumber)]||'-';}
function attendanceReportTotals(rows){
  const keys=['work_days','present_days','check_out_days','on_time_days','late_days','incomplete_days','leave_days','permission_days','sick_days','business_trip_days','alpha_days','overtime_hours','late_minutes'];
  return rows.reduce((totals,row)=>{keys.forEach(key=>totals[key]=(totals[key]||0)+Number(row[key]||0));return totals;},{});
}
function attendanceReportSummaryTable(rows){
  if(!rows.length)return '<div class="empty">Belum ada karyawan aktif pada periode ini.</div>';
  return `<div class="table-wrap"><table><thead><tr><th>Karyawan</th><th>Shift/Jadwal</th><th>Hari Kerja</th><th>Masuk</th><th>Pulang</th><th>Tepat Waktu</th><th>Terlambat</th><th>Belum Pulang</th><th>Cuti</th><th>Izin</th><th>Sakit</th><th>Dinas</th><th>Alfa</th><th>Lembur</th><th>Agenda Lembur</th><th>Menit Telat</th></tr></thead><tbody>${rows.map(r=>`<tr><td><strong>${esc(r.full_name)}</strong><br><small>${esc(r.nik)} · ${esc(r.department_name||'-')}</small></td><td>${esc(r.shift_name||'-')}<br><small>${esc(r.work_start||'-')}–${esc(r.work_end||'-')} · ${esc(shiftDaysLabel(r.work_day_numbers))}</small></td><td>${reportNumber(r.work_days)}</td><td>${reportNumber(r.present_days)}</td><td>${reportNumber(r.check_out_days)}</td><td>${reportNumber(r.on_time_days)}</td><td>${reportNumber(r.late_days)}</td><td>${reportNumber(r.incomplete_days)}</td><td>${reportNumber(r.leave_days)}</td><td>${reportNumber(r.permission_days)}</td><td>${reportNumber(r.sick_days)}</td><td>${reportNumber(r.business_trip_days)}</td><td><strong>${reportNumber(r.alpha_days)}</strong></td><td>${reportNumber(r.overtime_hours)} jam</td><td>${esc(r.overtime_agenda||'-')}</td><td>${reportNumber(r.late_minutes)}</td></tr>`).join('')}</tbody></table></div>`;
}
function attendanceReportDetailTable(rows){
  if(!rows.length)return '<div class="empty">Detail harian belum tersedia pada periode ini.</div>';
  return `<div class="table-wrap"><table><thead><tr><th>Tanggal</th><th>Karyawan</th><th>Shift</th><th>Status</th><th>Unit</th><th>Masuk</th><th>Pulang</th><th>Telat</th><th>Lokasi/Keterangan</th></tr></thead><tbody>${rows.map(r=>`<tr><td>${fmtDate(r.attendance_date)}<br><small>${reportDayName(r.day_number)}</small></td><td>${esc(r.full_name)}<br><small>${esc(r.nik)}</small></td><td>${esc(r.shift_name||'-')}<br><small>${esc(r.work_start||'-')}–${esc(r.work_end||'-')}</small></td><td><strong>${esc(r.status)}</strong></td><td>${reportNumber(r.unit)}</td><td>${r.check_in_at?String(r.check_in_at).slice(11,16):'-'}</td><td>${r.check_out_at?String(r.check_out_at).slice(11,16):'-'}</td><td>${reportNumber(r.late_minutes)} mnt</td><td>${esc(r.location_name||r.note||'-')}${r.location_name&&r.note?`<br><small>${esc(r.note)}</small>`:''}</td></tr>`).join('')}</tbody></table></div>`;
}
async function renderAttendanceReport(){
  const initialMonth=state.data.attendanceReportMonth||serverNow().toISOString().slice(0,7);
  const initialEmployee=state.data.attendanceReportEmployee||'';
  const query=`?month=${encodeURIComponent(initialMonth)}${initialEmployee?`&employee_id=${encodeURIComponent(initialEmployee)}`:''}`;
  const response=await api('/attendance/report'+query);
  const report=response.data||{};
  const rows=report.summary||[];
  const detail=report.detail||[];
  const overtimeDetail=report.overtime_detail||[];
  const totals=attendanceReportTotals(rows);
  const employees=report.employees||[];
  const cutoff=report.effective_end?fmtDate(report.effective_end):'Belum ada hari berjalan';
  const employeeOptions=`<option value="">Semua karyawan</option>${employees.map(e=>`<option value="${e.id}" ${String(initialEmployee)===String(e.id)?'selected':''}>${esc(e.nik)} — ${esc(e.full_name)}</option>`).join('')}`;
  $('#content').innerHTML=`<div class="card"><div class="toolbar"><div><h3>Rekap Absensi Dasar Payroll</h3><p class="muted">Periode dihitung sampai ${cutoff}. Hari kerja dan batas keterlambatan mengikuti shift efektif masing-masing karyawan.</p></div><div class="actions">${state.user.role==='super_admin'?'<button id="openAttendanceAdmin" class="btn primary">Bantu / Edit Absensi</button>':''}<button id="exportAttendanceReport" class="btn secondary">Export Excel</button></div></div><div class="form-grid" style="margin-top:14px"><label>Periode<input id="attendanceReportMonth" type="month" value="${esc(initialMonth)}"></label><label>Karyawan<select id="attendanceReportEmployee">${employeeOptions}</select></label><div style="align-self:end"><button id="applyAttendanceReport" class="btn primary wide">Tampilkan Rekap</button></div></div><div class="notice warning" style="margin-top:14px">Alfa dihitung dari hari kerja tanpa absensi, tanpa pengajuan yang disetujui, dan tanpa perjalanan dinas. Hari libur khusus dapat dimasukkan pada konfigurasi aplikasi.</div></div>
  <div class="grid cols-4 dashboard-stats" style="margin-top:18px"><div class="card"><div class="stat"><span>Hari Kerja</span><strong>${reportNumber(totals.work_days)}</strong></div></div><div class="card"><div class="stat"><span>Jumlah Masuk</span><strong>${reportNumber(totals.present_days)}</strong></div></div><div class="card"><div class="stat"><span>Total Alfa</span><strong>${reportNumber(totals.alpha_days)}</strong></div></div><div class="card"><div class="stat"><span>Jam Lembur</span><strong>${reportNumber(totals.overtime_hours)}</strong></div></div></div>
  <div class="card" style="margin-top:18px"><div class="toolbar"><div><h3>Ringkasan per Karyawan</h3><p class="muted">Digunakan untuk pemeriksaan sebelum generate payroll.</p></div></div>${attendanceReportSummaryTable(rows)}</div>
  <div class="card" style="margin-top:18px"><div class="toolbar"><div><h3>Detail Harian</h3><p class="muted">Menampilkan klasifikasi setiap hari kerja hingga tanggal batas laporan.</p></div></div>${attendanceReportDetailTable(detail)}</div>
  <div class="card" style="margin-top:18px"><div class="toolbar"><div><h3>Detail Lembur Disetujui Owner</h3><p class="muted">Jam lembur berikut otomatis menjadi dasar perhitungan payroll.</p></div></div>${overtimeDetail.length?`<div class="table-wrap"><table><thead><tr><th>Tanggal</th><th>Karyawan</th><th>Jam</th><th>Durasi</th><th>Agenda</th><th>Disetujui</th></tr></thead><tbody>${overtimeDetail.map(r=>`<tr><td>${fmtDate(r.start_date)}</td><td><strong>${esc(r.full_name)}</strong><br><small>${esc(r.nik||'')}</small></td><td>${esc((r.start_time||'').slice(0,5))}–${esc((r.end_time||'').slice(0,5))}</td><td>${reportNumber(r.hours)} jam</td><td>${esc(r.agenda||'-')}</td><td>${r.decided_at?esc(String(r.decided_at).slice(0,16)):'-'}</td></tr>`).join('')}</tbody></table></div>`:'<div class="empty">Belum ada lembur yang disetujui Owner pada periode ini.</div>'}</div>`;

  if($('#openAttendanceAdmin')) $('#openAttendanceAdmin').onclick=()=>navigate('attendance-admin');
  $('#applyAttendanceReport').onclick=async()=>{state.data.attendanceReportMonth=$('#attendanceReportMonth').value;state.data.attendanceReportEmployee=$('#attendanceReportEmployee').value;await renderAttendanceReport();enhanceMobileTables();};
  $('#attendanceReportMonth').onchange=()=>$('#applyAttendanceReport').click();
  $('#attendanceReportEmployee').onchange=()=>$('#applyAttendanceReport').click();
  $('#exportAttendanceReport').onclick=()=>{
    const summaryRows=[['Periode','NIK','Nama','Jabatan','Departemen','Shift','Sumber Shift','Jam Masuk','Jam Pulang','Hari Kerja Shift','Hari Kerja Periode','Masuk','Pulang','Tepat Waktu','Terlambat','Belum Absen Pulang','Cuti','Izin','Sakit','Dinas','Alfa','Lembur (Jam)','Agenda Lembur','Menit Terlambat'],...rows.map(r=>[report.month,r.nik,r.full_name,r.position_name,r.department_name,r.shift_name,shiftSourceLabel(r.shift_source),r.work_start,r.work_end,shiftDaysLabel(r.work_day_numbers),Number(r.work_days),Number(r.present_days),Number(r.check_out_days),Number(r.on_time_days),Number(r.late_days),Number(r.incomplete_days),Number(r.leave_days),Number(r.permission_days),Number(r.sick_days),Number(r.business_trip_days),Number(r.alpha_days),Number(r.overtime_hours),r.overtime_agenda||'',Number(r.late_minutes)])];
    const detailRows=[['Tanggal','Hari','NIK','Nama','Departemen','Shift','Jadwal Masuk','Jadwal Pulang','Status','Unit Hari','Masuk Aktual','Pulang Aktual','Telat (Menit)','Lokasi','Keterangan'],...detail.map(r=>[r.attendance_date,reportDayName(r.day_number),r.nik,r.full_name,r.department_name,r.shift_name||'',r.work_start||'',r.work_end||'',r.status,Number(r.unit),r.check_in_at||'',r.check_out_at||'',Number(r.late_minutes),r.location_name||'',r.note||''])];
    const overtimeRows=[['Tanggal Mulai','Tanggal Selesai','NIK','Nama','Jabatan','Departemen','Jam Mulai','Jam Selesai','Durasi (Jam)','Agenda Lembur','Waktu Approval Owner'],...overtimeDetail.map(r=>[r.start_date,r.end_date,r.nik||'',r.full_name||'',r.position_name||'',r.department_name||'',r.start_time||'',r.end_time||'',Number(r.hours||0),r.agenda||'',r.decided_at||''])];
    MPAXLSX.exportWorkbook(`Rekap_Absensi_${report.month}${initialEmployee?'_Karyawan':''}.xlsx`,[{name:'Rekap Karyawan',rows:summaryRows},{name:'Detail Harian',rows:detailRows},{name:'Detail Lembur',rows:overtimeRows}]);
  };
}

function openModal(title,html,onSubmit){$('#modalTitle').textContent=title;$('#modalBody').innerHTML=html;const dlg=$('#modal');dlg.showModal();const form=dlg.querySelector('form[data-form]');if(form)form.onsubmit=async e=>{e.preventDefault();loading(true);try{await onSubmit(formDataObject(form));dlg.close();toast('Data berhasil disimpan.','success');await navigate(state.page)}catch(err){toast(err.message,'error')}finally{loading(false)}}}
async function renderAttendanceAdmin(){
  if(state.user.role!=='super_admin')throw new Error('Menu ini khusus Super Admin.');
  const month=state.data.attAdminMonth||new Date().toISOString().slice(0,7);
  const emp=state.data.attAdminEmployee||'';
  const out=await api('/attendance/admin?month='+encodeURIComponent(month)+(emp?'&employee_id='+encodeURIComponent(emp):''));
  const employees=out.employees||[], rows=out.data||[];
  const employeeOptions='<option value="">Semua karyawan</option>'+employees.map(e=>`<option value="${e.id}" ${String(e.id)===String(emp)?'selected':''}>${esc(e.full_name)} · ${esc(e.nik)}</option>`).join('');
  $('#content').innerHTML=`<div class="card"><div class="toolbar"><div><h3>Bantu & Edit Absensi Karyawan</h3><p class="muted">Khusus Super Admin. Fitur ini dibuka melalui Rekap Absensi dan setiap perubahan tercatat pada Audit Log.</p></div><div class="actions"><button id="backAttendanceReport" class="btn secondary">← Kembali ke Rekap</button><button id="newAdminAttendance" class="btn primary">+ Bantu Absensi</button></div></div><div class="form-grid" style="margin-top:14px"><label>Periode<input id="attAdminMonth" type="month" value="${esc(month)}"></label><label>Karyawan<select id="attAdminEmployee">${employeeOptions}</select></label></div></div><div class="card" style="margin-top:18px"><div class="table-wrap"><table><thead><tr><th>Tanggal</th><th>Karyawan</th><th>Masuk</th><th>Pulang</th><th>Status</th><th>Catatan</th><th>Aksi</th></tr></thead><tbody>${rows.length?rows.map(r=>`<tr><td>${fmtDate(r.attendance_date)}</td><td><strong>${esc(r.full_name)}</strong><br><small>${esc(r.nik)} · ${esc(r.position_name||'-')}</small></td><td>${r.check_in_at?String(r.check_in_at).slice(11,16):'-'}</td><td>${r.check_out_at?String(r.check_out_at).slice(11,16):'-'}</td><td>${statusBadge(r.status)}</td><td>${esc(r.admin_note||r.check_out_reason||'-')}</td><td><button class="btn soft edit-admin-att" data-id="${r.id}">Edit</button></td></tr>`).join(''):'<tr><td colspan="7" class="empty">Belum ada data.</td></tr>'}</tbody></table></div></div>`;
  const openForm=(r={})=>openModal(r.id?'Edit Absensi':'Bantu Absensi',`<form data-form class="form-grid"><label>Karyawan<select name="employee_id" required>${employees.map(e=>`<option value="${e.id}" ${String(e.id)===String(r.employee_id||emp)?'selected':''}>${esc(e.full_name)} · ${esc(e.nik)}</option>`).join('')}</select></label><label>Tanggal<input type="date" name="attendance_date" value="${esc(r.attendance_date||new Date().toISOString().slice(0,10))}" required></label><label>Jam masuk<input type="time" name="check_in_time" value="${r.check_in_at?esc(String(r.check_in_at).slice(11,16)):''}"></label><label>Jam pulang<input type="time" name="check_out_time" value="${r.check_out_at?esc(String(r.check_out_at).slice(11,16)):''}"></label><label>Status<select name="status">${[['present','Hadir'],['late','Terlambat'],['late_permitted','Izin Terlambat'],['incomplete','Belum Lengkap'],['corrected','Dikoreksi']].map(x=>`<option value="${x[0]}" ${r.status===x[0]?'selected':''}>${x[1]}</option>`).join('')}</select></label><label class="full">Catatan koreksi<textarea name="admin_note" rows="3" required>${esc(r.admin_note||'')}</textarea></label><div class="full"><button class="btn primary wide">Simpan Absensi</button></div></form>`,d=>api('/attendance/admin/save',{method:'POST',body:d}));
  $('#backAttendanceReport').onclick=()=>navigate('attendance-report');
  $('#newAdminAttendance').onclick=()=>openForm();
  $('#attAdminMonth').onchange=async e=>{state.data.attAdminMonth=e.target.value;await renderAttendanceAdmin();};
  $('#attAdminEmployee').onchange=async e=>{state.data.attAdminEmployee=e.target.value;await renderAttendanceAdmin();};
  $('#content').querySelectorAll('.edit-admin-att').forEach(b=>b.onclick=()=>openForm(rows.find(r=>String(r.id)===String(b.dataset.id))||{}));
}

async function renderRequests(){
  const {data}=await api('/requests');
  const role=state.user.role;
  const employeeLinked=!!state.user.employee_id;
  const canReviewNonOvertime=['supervisor','hr_admin','super_admin'].includes(role);
  const canReviewOvertime=role==='owner';
  const showActionColumn=canReviewNonOvertime||canReviewOvertime;
  const requestTypeLabel=type=>({leave:'Cuti',permission:'Izin',sick:'Sakit',overtime:'Lembur',late_permission:'Izin Terlambat'}[type]||type);
  const canDecide=row=>row.status==='pending'&&((row.request_type==='overtime'&&canReviewOvertime)||(row.request_type!=='overtime'&&canReviewNonOvertime));
  const periodLabel=row=>{
    if(row.request_type==='late_permission')return `${fmtDate(row.start_date)}<br><small>Perkiraan masuk ${esc(String(row.start_time||'').slice(0,5))}</small>`;
    if(row.request_type==='overtime')return `${fmtDate(row.start_date)}<br><small>${esc(String(row.start_time||'').slice(0,5))}–${esc(String(row.end_time||'').slice(0,5))}</small>`;
    return `${fmtDate(row.start_date)} — ${fmtDate(row.end_date)}`;
  };
  const amountLabel=row=>row.request_type==='overtime'?`${reportNumber(row.quantity)} jam`:(row.request_type==='late_permission'?'-':`${reportNumber(row.quantity)} hari`);
  $('#content').innerHTML=`<div class="card"><div class="toolbar"><div><h3>Pengajuan Cuti, Izin, Sakit & Lembur</h3><p class="muted">Lembur dapat diajukan semua karyawan dan hanya disetujui oleh Owner. Durasi dihitung otomatis dari jam mulai dan selesai.</p></div>${employeeLinked?'<button id="newRequest" class="btn primary">+ Pengajuan</button>':''}</div><div class="table-wrap"><table><thead><tr><th>Karyawan</th><th>Jenis</th><th>Periode/Jam</th><th>Jumlah</th><th>Agenda/Alasan</th><th>Status</th><th>Keputusan</th>${showActionColumn?'<th>Aksi</th>':''}</tr></thead><tbody>${data.length?data.map(r=>`<tr><td>${esc(r.full_name)}<br><small>${esc(r.department_name||'-')}</small></td><td><strong>${requestTypeLabel(r.request_type)}</strong></td><td>${periodLabel(r)}</td><td>${amountLabel(r)}</td><td>${esc(r.reason||'-')}</td><td>${statusBadge(r.status)}</td><td>${r.decided_at?`${esc(r.decider_name||'Owner/Admin')}<br><small>${esc(String(r.decided_at).slice(0,16))}</small>`:'-'}</td>${showActionColumn?`<td>${canDecide(r)?`<button class="btn success decide" data-id="${r.id}" data-status="approved">Setujui</button> <button class="btn danger decide" data-id="${r.id}" data-status="rejected">Tolak</button>`:'-'}</td>`:''}</tr>`).join(''):`<tr><td colspan="${showActionColumn?8:7}" class="empty">Belum ada pengajuan.</td></tr>`}</tbody></table></div></div>`;
  const newButton=$('#newRequest');
  if(newButton)newButton.onclick=()=>{
    openModal('Buat Pengajuan',`<form data-form id="requestForm" class="form-grid"><label>Jenis<select id="requestType" name="request_type"><option value="overtime">Lembur</option><option value="late_permission">Izin Terlambat Masuk</option><option value="leave">Cuti</option><option value="permission">Izin</option><option value="sick">Sakit</option></select></label><label id="requestQuantityWrap">Jumlah hari<input id="requestQuantity" type="number" step="0.5" min="0.5" name="quantity" value="1"></label><label id="requestStartDateLabel">Tanggal lembur<input id="requestStartDate" type="date" name="start_date" required></label><label id="requestEndDateWrap" hidden>Tanggal selesai<input id="requestEndDate" type="date" name="end_date" required></label><label id="requestStartTimeWrap">Jam mulai<input id="requestStartTime" type="time" name="start_time" required></label><label id="requestEndTimeWrap">Jam selesai<input id="requestEndTime" type="time" name="end_time" required></label><label class="full"><span id="requestReasonLabel">Agenda lembur</span><textarea name="reason" rows="4" required placeholder="Contoh: Menyelesaikan pengiriman material proyek ..."></textarea></label><div id="overtimeCalc" class="notice info full">Durasi lembur dihitung otomatis oleh sistem berdasarkan jam mulai dan jam selesai.</div><div class="full"><button class="btn primary wide">Kirim Pengajuan</button></div></form>`,d=>api('/requests',{method:'POST',body:d}));
    const type=$('#requestType'),qtyWrap=$('#requestQuantityWrap'),qty=$('#requestQuantity'),startDate=$('#requestStartDate'),endDateWrap=$('#requestEndDateWrap'),endDate=$('#requestEndDate'),startTimeWrap=$('#requestStartTimeWrap'),endTimeWrap=$('#requestEndTimeWrap'),startTime=$('#requestStartTime'),endTime=$('#requestEndTime'),reasonLabel=$('#requestReasonLabel'),startDateLabel=$('#requestStartDateLabel'),calc=$('#overtimeCalc');
    const sync=()=>{
      const overtime=type.value==='overtime', latePermit=type.value==='late_permission', timed=overtime||latePermit;
      qtyWrap.hidden=timed;qty.disabled=timed;qty.required=!timed;
      endDateWrap.hidden=timed;
      startTimeWrap.hidden=!timed;endTimeWrap.hidden=!overtime;startTime.required=timed;endTime.required=overtime;
      startDateLabel.childNodes[0].nodeValue=overtime?'Tanggal lembur':(latePermit?'Tanggal terlambat':'Tanggal mulai');
      reasonLabel.textContent=overtime?'Agenda lembur':(latePermit?'Alasan terlambat':'Alasan');
      calc.hidden=!timed;calc.textContent=overtime?'Durasi lembur dihitung otomatis oleh sistem berdasarkan jam mulai dan jam selesai.':'Setelah disetujui, Anda tetap melakukan Absen Masuk saat tiba dan Absen Pulang seperti biasa.';
      if(timed&&startDate.value)endDate.value=startDate.value;
      if(!timed&&!endDate.value)endDate.value=startDate.value;
    };
    type.onchange=sync;startDate.onchange=()=>{if(type.value==='overtime'||!endDate.value)endDate.value=startDate.value;};sync();
  };
  $('#content').onclick=async e=>{const b=e.target.closest('.decide');if(!b)return;const note=prompt('Catatan keputusan (opsional):','')||'';loading(true);try{await api(`/requests/${b.dataset.id}/decision`,{method:'POST',body:{status:b.dataset.status,decision_note:note}});toast('Keputusan tersimpan.','success');await renderRequests();enhanceMobileTables()}catch(err){toast(err.message,'error')}finally{loading(false)}};
}

async function renderTrips(){
  const {data}=await api('/trips');const canApprove=['supervisor','hr_admin','super_admin'].includes(state.user.role);const canSettle=['payroll_finance','super_admin'].includes(state.user.role);
  $('#content').innerHTML=`<div class="card"><div class="toolbar"><div><h3>Perjalanan Dinas</h3><p class="muted">Pisahkan tunjangan, reimbursement, dan pengembalian uang muka.</p></div><button id="newTrip" class="btn primary">+ Ajukan Dinas</button></div><div class="table-wrap"><table><thead><tr><th>Karyawan/Proyek</th><th>Tujuan</th><th>Tanggal</th><th>Estimasi</th><th>Approval</th><th>Realisasi</th><th>Aksi</th></tr></thead><tbody>${data.length?data.map(r=>`<tr><td><strong>${esc(r.full_name)}</strong><br>${esc(r.project_name)}</td><td>${esc(r.destination)}</td><td>${fmtDate(r.start_date)} — ${fmtDate(r.end_date)}</td><td>UM ${money(r.requested_advance)}<br>Biaya ${money(r.estimated_expense)}</td><td>${statusBadge(r.approval_status)}</td><td>${statusBadge(r.settlement_status)}</td><td>${canApprove&&r.approval_status==='pending'?`<button class="btn success trip-decision" data-id="${r.id}" data-status="approved">Setujui</button> <button class="btn danger trip-decision" data-id="${r.id}" data-status="rejected">Tolak</button>`:''}${canSettle&&r.approval_status==='approved'&&r.settlement_status!=='settled'?` <button class="btn secondary settle" data-id="${r.id}">Realisasi</button>`:''}</td></tr>`).join(''):'<tr><td colspan="7" class="empty">Belum ada perjalanan dinas.</td></tr>'}</tbody></table></div></div>`;
  $('#newTrip').onclick=()=>openModal('Ajukan Perjalanan Dinas',`<form data-form class="form-grid"><label>Kode proyek<input name="project_code"></label><label>Nama proyek<input name="project_name" required></label><label>Tujuan<input name="destination" required></label><label>Uang muka diminta<input type="number" name="requested_advance" min="0"></label><label>Tanggal mulai<input type="date" name="start_date" required></label><label>Tanggal selesai<input type="date" name="end_date" required></label><label>Estimasi biaya<input type="number" name="estimated_expense" min="0"></label><label>Estimasi tunjangan<input type="number" name="estimated_allowance" min="0"></label><label class="full">Tujuan penugasan<textarea name="purpose" required></textarea></label><div class="full"><button class="btn primary wide">Kirim Pengajuan</button></div></form>`,d=>api('/trips',{method:'POST',body:d}));
  $('#content').onclick=async e=>{const d=e.target.closest('.trip-decision');if(d){loading(true);try{await api(`/trips/${d.dataset.id}/decision`,{method:'POST',body:{status:d.dataset.status,approval_note:''}});toast('Keputusan tersimpan.','success');await renderTrips()}catch(err){toast(err.message,'error')}finally{loading(false)}}const s=e.target.closest('.settle');if(s)openModal('Realisasi Perjalanan Dinas',`<form data-form class="form-grid"><label>Periode payroll<input type="month" name="payroll_period" required></label><label>Uang muka dibayar<input type="number" name="advance_paid" min="0"></label><label>Biaya aktual<input type="number" name="actual_expense" min="0"></label><label>Tunjangan final<input type="number" name="final_allowance" min="0"></label><label class="full"><input type="checkbox" name="deduct_refund_from_payroll" value="1"> Potong kelebihan uang muka melalui payroll</label><label class="full">Catatan<textarea name="settlement_note"></textarea></label><div class="full"><button class="btn primary wide">Simpan Realisasi</button></div></form>`,d=>api(`/trips/${s.dataset.id}/settle`,{method:'POST',body:{...d,deduct_refund_from_payroll:!!d.deduct_refund_from_payroll}}));};
}

async function renderPayslips(){
  const {data}=await api('/payslips');const isPayroll=['payroll_finance','super_admin'].includes(state.user.role);
  $('#content').innerHTML=`<div class="card"><div class="toolbar"><div><h3>Slip Gaji</h3><p class="muted">Slip terbit adalah snapshot dan tidak berubah ketika master gaji diperbarui.</p></div></div><div class="table-wrap"><table><thead><tr><th>Periode</th><th>Karyawan</th><th>No. Dokumen</th><th>Gaji Bersih</th><th>Reimbursement</th><th>Total Transfer</th><th>Status</th><th>Aksi</th></tr></thead><tbody>${data.length?data.map(r=>`<tr><td>${esc(r.period)}</td><td>${esc(r.full_name)}</td><td>${esc(r.document_number)}</td><td>${money(r.net_salary)}</td><td>${money(r.reimbursement_total)}</td><td><strong>${money(r.total_transfer)}</strong></td><td>${statusBadge(r.status)}</td><td><button class="btn soft view-slip" data-id="${r.id}">Lihat</button>${isPayroll&&r.status==='draft'?` <button class="btn success publish-slip" data-id="${r.id}">Terbitkan</button>`:''}</td></tr>`).join(''):'<tr><td colspan="8" class="empty">Belum ada slip.</td></tr>'}</tbody></table></div></div>`;
  $('#content').onclick=async e=>{const v=e.target.closest('.view-slip');if(v){loading(true);try{const {data:r}=await api(`/payslips/${v.dataset.id}/view`);showPayslip(r)}catch(err){toast(err.message,'error')}finally{loading(false)}}const p=e.target.closest('.publish-slip');if(p&&confirm('Terbitkan slip ini kepada karyawan?')){loading(true);try{await api(`/payslips/${p.dataset.id}/publish`,{method:'POST',body:{}});toast('Slip diterbitkan.','success');await renderPayslips()}catch(err){toast(err.message,'error')}finally{loading(false)}}};
}
function showPayslip(row){const s=row.snapshot||{};const a=s.attendance||{};const sal=s.salary||{};openModal('Slip Gaji '+row.period,`<div class="slip"><div class="slip-head"><img class="slip-logo" src="assets/mpa-logo.png"><div><strong>${esc(row.document_number)}</strong><br><small>${esc(row.period)}</small></div></div><h3>${esc(row.full_name)}</h3><p>${esc(row.nik)}</p><h4>Penghasilan</h4>${[['Gaji Pokok',sal.base_salary],['Tunjangan Jabatan',sal.position_allowance],['Tunjangan Tetap',sal.fixed_allowance],['Makan/Transport',sal.meal_transport],['Lembur',a.overtime_value],['Komponen Variabel',s.components?.earning]].map(x=>`<div class="slip-row"><span>${x[0]}</span><strong>${money(x[1])}</strong></div>`).join('')}<h4>Potongan</h4><div class="slip-row"><span>Total Potongan</span><strong>${money(s.total_deductions)}</strong></div><div class="slip-total"><div class="slip-row"><span>Gaji Bersih</span><strong>${money(s.net_salary)}</strong></div><div class="slip-row"><span>Reimbursement</span><strong>${money(s.reimbursement_total)}</strong></div><div class="slip-row"><span>Total Transfer</span><strong>${money(s.total_transfer)}</strong></div></div><div class="actions"><button type="button" id="printSlip" class="btn secondary">Cetak / PDF</button><button type="button" id="excelSlip" class="btn primary">Export Excel</button></div></div>`,async()=>{});setTimeout(()=>{$('#printSlip').onclick=()=>window.print();$('#excelSlip').onclick=()=>MPAXLSX.exportWorkbook(`Slip_${row.period}_${row.nik}.xlsx`,[{name:'Slip Gaji',rows:[['Komponen','Nominal'],['Gaji Pokok',Number(sal.base_salary||0)],['Tunjangan Jabatan',Number(sal.position_allowance||0)],['Tunjangan Tetap',Number(sal.fixed_allowance||0)],['Makan/Transport',Number(sal.meal_transport||0)],['Lembur',Number(a.overtime_value||0)],['Total Penghasilan',Number(s.gross_income||0)],['Total Potongan',Number(s.total_deductions||0)],['Gaji Bersih',Number(s.net_salary||0)],['Reimbursement',Number(s.reimbursement_total||0)],['Total Transfer',Number(s.total_transfer||0)]]}])},0)}

function shiftDaysLabel(days){return (days||[]).map(reportDayName).join(', ')||'-';}
function shiftSourceLabel(source){return source==='employee'?'Khusus karyawan':source==='position'?'Mengikuti jabatan':'Default sistem';}
function shiftDayRows(s={}){
  const selected=(s.work_days||[1,2,3,4,5,6]).map(Number);
  const daily=s.daily_schedule||{};
  return [1,2,3,4,5,6,7].map(day=>{
    const schedule=daily[String(day)]||daily[day]||{work_start:s.work_start||'08:00',work_end:s.work_end||'17:00'};
    const active=selected.includes(day);
    return `<div class="shift-day-row"><label class="check-row"><input type="checkbox" name="work_day_${day}" value="1" ${active?'checked':''}> <strong>${reportDayName(day)}</strong></label><label>Masuk<input type="time" name="day_${day}_start" value="${esc(schedule.work_start||'08:00')}" ${active?'':'disabled'}></label><label>Pulang<input type="time" name="day_${day}_end" value="${esc(schedule.work_end||'17:00')}" ${active?'':'disabled'}></label></div>`;
  }).join('');
}
function bindShiftDayRows(form){
  [1,2,3,4,5,6,7].forEach(day=>{
    const check=form.querySelector(`[name="work_day_${day}"]`);
    if(!check)return;
    const start=form.querySelector(`[name="day_${day}_start"]`),end=form.querySelector(`[name="day_${day}_end"]`);
    const sync=()=>{if(start)start.disabled=!check.checked;if(end)end.disabled=!check.checked;};
    check.addEventListener('change',sync);sync();
  });
}
function shiftPayload(data){
  const work_days=[1,2,3,4,5,6,7].filter(day=>data[`work_day_${day}`]);
  const daily_schedule={};
  work_days.forEach(day=>{daily_schedule[String(day)]={work_start:data[`day_${day}_start`]||data.work_start,work_end:data[`day_${day}_end`]||data.work_end};});
  return {...data,work_days,daily_schedule,is_active:data.is_active==='1'||data.is_active==='on'};
}
function shiftScheduleSummary(s){
  const daily=s.daily_schedule||{};
  const rows=(s.work_days||[]).map(day=>{const x=daily[String(day)]||{work_start:s.work_start,work_end:s.work_end};return `${reportDayName(day)} ${x.work_start}–${x.work_end}`;});
  return rows.join(', ');
}

async function renderShifts(){
  const response=await api('/shifts');
  const data=response.data||{};
  const shifts=data.shifts||[];
  const positions=data.positions||[];
  const employees=data.employees||[];
  const exceptions=data.exceptions||[];
  const canEdit=['hr_admin','super_admin'].includes(state.user.role);
  const activeShifts=shifts.filter(s=>Number(s.is_active)===1);
  const shiftOptions=(selected='')=>activeShifts.map(s=>`<option value="${s.id}" ${String(selected)===String(s.id)?'selected':''}>${esc(s.shift_code)} — ${esc(s.shift_name)} (${esc(s.work_start)}–${esc(s.work_end)})</option>`).join('');
  const positionAssigned=positions.filter(p=>p.shift_id).length;
  const employeeOverrides=employees.filter(e=>e.override_shift_id).length;
  $('#content').innerHTML=`
    <div class="grid cols-3 dashboard-stats">
      <div class="card"><div class="stat"><span>Shift Aktif</span><strong>${activeShifts.length}</strong></div></div>
      <div class="card"><div class="stat"><span>Jabatan Diatur</span><strong>${positionAssigned}</strong></div></div>
      <div class="card"><div class="stat"><span>Override Karyawan</span><strong>${employeeOverrides}</strong></div></div>
    </div>
    <div class="card" style="margin-top:18px">
      <div class="toolbar"><div><h3>Master Shift Kerja</h3><p class="muted">Atur jam masuk, jam pulang, toleransi keterlambatan, dan hari kerja.</p></div>${canEdit?'<button id="newShift" class="btn primary">+ Shift Baru</button>':''}</div>
      <div class="table-wrap"><table><thead><tr><th>Kode</th><th>Nama Shift</th><th>Jam Kerja</th><th>Hari Kerja</th><th>Toleransi</th><th>Status</th>${canEdit?'<th>Aksi</th>':''}</tr></thead><tbody>${shifts.length?shifts.map(s=>`<tr><td><strong>${esc(s.shift_code)}</strong></td><td>${esc(s.shift_name)}</td><td><small>${esc(shiftScheduleSummary(s))}</small></td><td>${esc(shiftDaysLabel(s.work_days))}</td><td>${Number(s.late_tolerance_minutes)} menit</td><td>${statusBadge(Number(s.is_active)===1?'active':'inactive')}</td>${canEdit?`<td><div class="group"><button class="btn soft edit-shift" data-id="${s.id}">Edit</button>${Number(s.is_active)===1?`<button class="btn danger delete-shift" data-id="${s.id}">Nonaktifkan</button>`:''}</div></td>`:''}</tr>`).join(''):'<tr><td colspan="7" class="empty">Belum ada shift kerja.</td></tr>'}</tbody></table></div>
    </div>
    <div class="card" style="margin-top:18px">
      <div class="toolbar"><div><h3>Shift Berdasarkan Jabatan</h3><p class="muted">Semua karyawan pada jabatan tersebut otomatis mengikuti shift ini, kecuali memiliki override pribadi.</p></div></div>
      <div class="table-wrap"><table><thead><tr><th>Jabatan</th><th>Departemen</th><th>Karyawan Aktif</th><th>Shift Jabatan</th>${canEdit?'<th>Aksi</th>':''}</tr></thead><tbody>${positions.length?positions.map(p=>`<tr><td><strong>${esc(p.position_name)}</strong></td><td>${esc(p.department_name||'-')}</td><td>${Number(p.active_count||0)}</td><td>${p.shift?`${esc(p.shift.shift_name)}<br><small>${esc(p.shift.work_start)}–${esc(p.shift.work_end)} · ${esc(shiftDaysLabel(p.shift.work_days))}</small>`:'<span class="muted">Mengikuti default sistem</span>'}</td>${canEdit?`<td><div class="group"><button class="btn soft assign-position-shift" data-position="${encodeURIComponent(p.position_name)}" data-department="${encodeURIComponent(p.department_name||'')}">${p.shift_id?'Ubah':'Atur'}</button>${p.shift_id?`<button class="btn danger remove-position-shift" data-position="${encodeURIComponent(p.position_name)}" data-department="${encodeURIComponent(p.department_name||'')}">Hapus</button>`:''}</div></td>`:''}</tr>`).join(''):'<tr><td colspan="5" class="empty">Belum ada data jabatan.</td></tr>'}</tbody></table></div>
    </div>
    <div class="card" style="margin-top:18px">
      <div class="toolbar"><div><h3>Jadwal per Karyawan</h3><p class="muted">Gunakan override hanya untuk karyawan yang jadwalnya berbeda dari jabatan.</p></div></div>
      <div class="table-wrap"><table><thead><tr><th>Karyawan</th><th>Jabatan</th><th>Jadwal Efektif</th><th>Sumber Jadwal</th>${canEdit?'<th>Aksi</th>':''}</tr></thead><tbody>${employees.length?employees.map(e=>`<tr><td><strong>${esc(e.full_name)}</strong><br><small>${esc(e.nik)}</small></td><td>${esc(e.position_name)}<br><small>${esc(e.department_name||'-')}</small></td><td>${esc(e.shift.shift_name)}<br><small>${esc(e.shift.work_start)}–${esc(e.shift.work_end)} · ${esc(shiftDaysLabel(e.shift.work_days))}</small></td><td>${esc(shiftSourceLabel(e.shift.source))}</td>${canEdit?`<td><div class="group"><button class="btn soft assign-employee-shift" data-id="${e.id}">${e.override_shift_id?'Ubah Override':'Atur Khusus'}</button>${e.override_shift_id?`<button class="btn danger remove-employee-shift" data-id="${e.id}">Ikuti Jabatan</button>`:''}</div></td>`:''}</tr>`).join(''):'<tr><td colspan="5" class="empty">Belum ada karyawan aktif.</td></tr>'}</tbody></table></div>
    </div>
    <div class="card" style="margin-top:18px">
      <div class="toolbar"><div><h3>Jadwal Khusus / Tukar Hari Kerja</h3><p class="muted">Atur hari masuk pengganti atau libur pengganti untuk karyawan pada tanggal tertentu. Jadwal utama tidak berubah.</p></div>${canEdit?'<button id="newSpecialSchedule" class="btn primary">+ Jadwal Khusus</button>':''}</div>
      <div class="table-wrap"><table><thead><tr><th>Karyawan</th><th>Tanggal</th><th>Jenis</th><th>Jam</th><th>Tanggal Pasangan</th><th>Alasan</th>${canEdit?'<th>Aksi</th>':''}</tr></thead><tbody>${exceptions.length?exceptions.map(x=>`<tr><td><strong>${esc(x.full_name)}</strong><br><small>${esc(x.nik)}</small></td><td>${fmtDate(x.work_date)}</td><td>${x.schedule_type==='work'?'<strong>Masuk Pengganti</strong>':'<strong>Libur Pengganti</strong>'}</td><td>${x.schedule_type==='work'?`${esc(x.work_start)}–${esc(x.work_end)}`:'-'}</td><td>${x.replacement_date?fmtDate(x.replacement_date):'-'}</td><td>${esc(x.reason)}</td>${canEdit?`<td><button class="btn danger delete-special-schedule" data-id="${x.id}">Hapus</button></td>`:''}</tr>`).join(''):'<tr><td colspan="7" class="empty">Belum ada jadwal khusus.</td></tr>'}</tbody></table></div>
    </div>`;

  if(!canEdit)return;
  const shiftForm=(s={})=>`<form data-form class="form-grid"><label>Kode Shift<input name="shift_code" value="${esc(s.shift_code||'')}" placeholder="REGULER" required></label><label>Nama Shift<input name="shift_name" value="${esc(s.shift_name||'')}" placeholder="Shift Reguler" required></label><label>Jam Masuk Default<input type="time" name="work_start" value="${esc(s.work_start||'08:00')}" required></label><label>Jam Pulang Default<input type="time" name="work_end" value="${esc(s.work_end||'17:00')}" required></label><label>Toleransi Terlambat (menit)<input type="number" min="0" max="180" name="late_tolerance_minutes" value="${Number(s.late_tolerance_minutes??15)}"></label><label>Status<select name="is_active"><option value="1" ${Number(s.is_active??1)===1?'selected':''}>Aktif</option><option value="0" ${Number(s.is_active??1)===0?'selected':''}>Nonaktif</option></select></label><fieldset class="full"><legend>Jadwal per Hari</legend><p class="muted">Centang hari kerja lalu atur jam masuk dan pulang masing-masing hari.</p><div class="shift-day-list">${shiftDayRows(s)}</div></fieldset><div class="full"><button class="btn primary wide">Simpan Shift</button></div></form>`;
  const openShiftModal=(title,shift,onSubmit)=>{openModal(title,shiftForm(shift),onSubmit);const form=$('#modal').querySelector('form[data-form]');if(form)bindShiftDayRows(form);};
  $('#newShift').onclick=()=>openShiftModal('Tambah Shift Kerja',{},d=>api('/shifts',{method:'POST',body:shiftPayload(d)}));
  const employeeOptions=employees.map(e=>`<option value="${e.id}">${esc(e.nik)} — ${esc(e.full_name)}</option>`).join('');
  const openSpecialSchedule=()=>openModal('Jadwal Khusus / Tukar Hari Kerja',`<form data-form class="form-grid"><label class="full">Karyawan<select name="employee_id" required>${employeeOptions}</select></label><label>Tanggal yang diubah<input type="date" name="work_date" required></label><label>Jenis jadwal<select name="schedule_type" id="specialScheduleType"><option value="work">Masuk Pengganti</option><option value="off">Libur Pengganti</option></select></label><label class="special-time">Jam masuk<input type="time" name="work_start" value="08:00"></label><label class="special-time">Jam pulang<input type="time" name="work_end" value="17:00"></label><label>Tanggal pasangan <small>(opsional)</small><input type="date" name="replacement_date"></label><label class="full">Alasan<textarea name="reason" required placeholder="Contoh: Masuk Selasa menggantikan libur Jumat"></textarea></label><div class="full notice">Untuk pertukaran hari, buat dua data: satu <strong>Masuk Pengganti</strong> dan satu <strong>Libur Pengganti</strong>.</div><div class="full"><button class="btn primary wide">Simpan Jadwal Khusus</button></div></form>`,d=>api('/shifts/special-schedule',{method:'POST',body:d}));
  $('#newSpecialSchedule').onclick=()=>{openSpecialSchedule();setTimeout(()=>{const t=$('#specialScheduleType');const sync=()=>document.querySelectorAll('.special-time').forEach(x=>x.hidden=t.value!=='work');t.onchange=sync;sync();},0)};
  $('#content').onclick=async e=>{
    const specialDelete=e.target.closest('.delete-special-schedule');
    if(specialDelete){if(!confirm('Hapus jadwal khusus ini?'))return;loading(true);try{const out=await api('/shifts/special-schedule/'+specialDelete.dataset.id,{method:'DELETE',body:{}});toast(out.message,'success');await renderShifts()}catch(err){toast(err.message,'error')}finally{loading(false)}return;}
    const edit=e.target.closest('.edit-shift');
    if(edit){const shift=shifts.find(s=>String(s.id)===String(edit.dataset.id));if(shift)openShiftModal('Edit Shift Kerja',shift,d=>api('/shifts/'+shift.id,{method:'PUT',body:shiftPayload(d)}));return;}
    const del=e.target.closest('.delete-shift');
    if(del){const shift=shifts.find(s=>String(s.id)===String(del.dataset.id));if(!shift||!confirm(`Nonaktifkan shift ${shift.shift_name}?`))return;loading(true);try{const out=await api('/shifts/'+shift.id,{method:'DELETE',body:{}});toast(out.message,'success');await renderShifts()}catch(err){toast(err.message,'error')}finally{loading(false)}return;}
    const assignPos=e.target.closest('.assign-position-shift');
    if(assignPos){const position=decodeURIComponent(assignPos.dataset.position);const department=decodeURIComponent(assignPos.dataset.department);const row=positions.find(p=>p.position_name===position&&(p.department_name||'')===department);openModal('Atur Shift Jabatan',`<form data-form class="form-grid"><div class="full notice"><strong>${esc(position)}</strong><br>${esc(department||'Tanpa departemen')}</div><label class="full">Shift<select name="shift_id" required>${shiftOptions(row?.shift_id||'')}</select></label><div class="full"><button class="btn primary wide">Terapkan ke Jabatan</button></div></form>`,d=>api('/shifts/assign-position',{method:'POST',body:{...d,position_name:position,department_name:department}}));return;}
    const removePos=e.target.closest('.remove-position-shift');
    if(removePos){const position=decodeURIComponent(removePos.dataset.position);const department=decodeURIComponent(removePos.dataset.department);if(!confirm(`Hapus pengaturan shift untuk jabatan ${position}?`))return;loading(true);try{const out=await api('/shifts/unassign-position',{method:'POST',body:{position_name:position,department_name:department}});toast(out.message,'success');await renderShifts()}catch(err){toast(err.message,'error')}finally{loading(false)}return;}
    const assignEmp=e.target.closest('.assign-employee-shift');
    if(assignEmp){const employee=employees.find(x=>String(x.id)===String(assignEmp.dataset.id));if(!employee)return;openModal('Atur Shift Khusus Karyawan',`<form data-form class="form-grid"><div class="full notice"><strong>${esc(employee.full_name)}</strong><br>${esc(employee.position_name)} · ${esc(employee.department_name||'-')}</div><label class="full">Shift khusus<select name="shift_id" required>${shiftOptions(employee.override_shift_id||employee.shift.id)}</select></label><div class="full"><button class="btn primary wide">Simpan Override</button></div></form>`,d=>api('/shifts/assign-employee',{method:'POST',body:{...d,employee_id:employee.id}}));return;}
    const removeEmp=e.target.closest('.remove-employee-shift');
    if(removeEmp){const employee=employees.find(x=>String(x.id)===String(removeEmp.dataset.id));if(!employee||!confirm(`${employee.full_name} akan kembali mengikuti shift jabatan. Lanjutkan?`))return;loading(true);try{const out=await api('/shifts/unassign-employee',{method:'POST',body:{employee_id:employee.id}});toast(out.message,'success');await renderShifts()}catch(err){toast(err.message,'error')}finally{loading(false)}}
  };
}


async function renderEmployees(){
  const {data}=await api('/employees');state.data.employees=data;
  const canEdit=['hr_admin','super_admin'].includes(state.user.role);
  const roleOptions=(value)=>[['employee','Karyawan'],['supervisor','Supervisor'],['hr_admin','HR Admin'],['payroll_finance','Finance Payroll'],['owner','Owner'],['super_admin','Super Admin']].map(([v,l])=>`<option value="${v}" ${v===value?'selected':''}>${l}</option>`).join('');
  $('#content').innerHTML=`<div class="card"><div class="toolbar"><div><h3>Master Karyawan</h3><p class="muted">Akun, role, status, dan akses karyawan dikelola dari satu menu.</p></div>${canEdit?'<button id="newEmployee" class="btn primary">+ Karyawan</button>':''}</div><div class="table-wrap"><table><thead><tr><th>NIK</th><th>Nama</th><th>Jabatan</th><th>Departemen</th><th>Username</th><th>Role</th><th>Status</th>${canEdit?'<th>Aksi</th>':''}</tr></thead><tbody>${data.map(r=>`<tr><td>${esc(r.nik)}</td><td><strong>${esc(r.full_name)}</strong></td><td>${esc(r.position_name)}</td><td>${esc(r.department_name)}</td><td>${esc(r.username||'-')}</td><td>${esc(roleLabel[r.role]||r.role||'-')}</td><td>${statusBadge(r.employment_status)}</td>${canEdit?`<td><div class="group"><button class="btn soft edit-employee" data-id="${r.id}">Edit</button><button class="btn danger delete-employee" data-id="${r.id}" ${r.employment_status!=='active'?'disabled':''}>Hapus</button></div></td>`:''}</tr>`).join('')}</tbody></table></div></div>`;
  if($('#newEmployee'))$('#newEmployee').onclick=()=>openModal('Tambah Karyawan',`<form data-form class="form-grid"><label>NIK<input name="nik" required></label><label>Nama lengkap<input name="full_name" required></label><label>Jabatan<input name="position_name" required></label><label>Departemen<input name="department_name" required></label><label>Tanggal bergabung<input type="date" name="joined_date"></label><label>Kuota cuti<input type="number" name="annual_leave_quota" value="12"></label><label>Username<input name="username" required></label><label>Password awal<input type="password" name="password" required minlength="12"></label><label class="full">Role<select name="role">${roleOptions('employee')}</select></label><div class="full"><button class="btn primary wide">Simpan Karyawan</button></div></form>`,d=>api('/employees',{method:'POST',body:d}));
  $('#content').onclick=async e=>{
    const edit=e.target.closest('.edit-employee');
    if(edit){
      const r=data.find(x=>String(x.id)===String(edit.dataset.id));if(!r)return;
      openModal('Edit Karyawan',`<form data-form class="form-grid">
        <label>NIK<input name="nik" value="${esc(r.nik)}" required></label><label>Nama lengkap<input name="full_name" value="${esc(r.full_name)}" required></label>
        <label>Jabatan<input name="position_name" value="${esc(r.position_name)}" required></label><label>Departemen<input name="department_name" value="${esc(r.department_name)}" required></label>
        <label>Tanggal bergabung<input type="date" name="joined_date" value="${esc(r.joined_date||'')}"></label><label>Kuota cuti<input type="number" name="annual_leave_quota" value="${Number(r.annual_leave_quota||12)}"></label>
        <label>Username<input name="username" value="${esc(r.username||'')}" required></label><label>Password baru <small>(kosongkan bila tidak diubah)</small><input type="password" name="password" minlength="12"></label>
        <label>Role<select name="role">${roleOptions(r.role)}</select></label>
        <label>Status karyawan<select name="employment_status"><option value="active" ${r.employment_status==='active'?'selected':''}>Aktif</option><option value="inactive" ${r.employment_status==='inactive'?'selected':''}>Nonaktif</option><option value="terminated" ${r.employment_status==='terminated'?'selected':''}>Berakhir</option></select></label>
        <label class="full">Status akun<select name="user_status"><option value="active" ${r.user_status==='active'?'selected':''}>Aktif</option><option value="locked" ${r.user_status==='locked'?'selected':''}>Terkunci</option><option value="disabled" ${r.user_status==='disabled'?'selected':''}>Dinonaktifkan</option></select></label>
        <div class="full"><button class="btn primary wide">Simpan Perubahan</button></div></form>`,d=>api('/employees/'+r.id,{method:'PUT',body:d}));
      return;
    }
    const del=e.target.closest('.delete-employee');
    if(del&&!del.disabled){
      const r=data.find(x=>String(x.id)===String(del.dataset.id));if(!r)return;
      if(!confirm(`Hapus ${r.full_name} dari daftar aktif? Riwayat absensi dan payroll tetap disimpan untuk audit.`))return;
      loading(true);try{const out=await api('/employees/'+r.id,{method:'DELETE',body:{}});toast(out.message,'success');await renderEmployees()}catch(err){toast(err.message,'error')}finally{loading(false)}
    }
  };
}

async function renderLocations(){
  const {data}=await api('/locations');
  $('#content').innerHTML=`<div class="card"><div class="toolbar"><div><h3>Lokasi & Radius Absensi</h3><p class="muted">Koordinat wajib diisi sesuai kantor atau proyek.</p></div><button id="newLocation" class="btn primary">+ Lokasi</button></div><div class="table-wrap"><table><thead><tr><th>Kode</th><th>Lokasi</th><th>Koordinat</th><th>Radius</th><th>Akurasi Maks.</th></tr></thead><tbody>${data.length?data.map(r=>`<tr><td>${esc(r.location_code)}</td><td>${esc(r.location_name)}</td><td>${r.latitude}, ${r.longitude}</td><td>${r.radius_meters} m</td><td>${r.max_accuracy_meters} m</td></tr>`).join(''):'<tr><td colspan="5" class="empty">Belum ada lokasi.</td></tr>'}</tbody></table></div></div>`;
  $('#newLocation').onclick=()=>openModal('Tambah Lokasi Kerja',`<form data-form class="form-grid"><label>Kode lokasi<input name="location_code" required></label><label>Nama lokasi<input name="location_name" required></label><label>Latitude<input type="number" step="0.0000001" name="latitude" required></label><label>Longitude<input type="number" step="0.0000001" name="longitude" required></label><label>Radius meter<input type="number" name="radius_meters" value="150"></label><label>Akurasi maksimum<input type="number" name="max_accuracy_meters" value="100"></label><div class="full"><button class="btn primary wide">Simpan Lokasi</button></div></form>`,d=>api('/locations',{method:'POST',body:d}));
}

async function renderDevices(){
  const {data}=await api('/devices');
  $('#content').innerHTML=`<div class="card"><div class="toolbar"><div><h3>Persetujuan HP</h3><p class="muted">Kelola HP yang boleh digunakan untuk absensi. Sidik jari dan PIN tidak lagi digunakan.</p></div></div><div class="table-wrap"><table><thead><tr><th>Karyawan</th><th>Perangkat</th><th>Status HP</th><th>Didaftarkan</th><th>Aksi</th></tr></thead><tbody>${data.length?data.map(r=>`<tr><td>${esc(r.full_name)}<br><small>${esc(r.nik)}</small></td><td>${esc(r.device_label||r.device_uuid)}<br><small>${esc(r.device_uuid)}</small></td><td>${statusBadge(r.status)}</td><td>${fmtDateTime(r.created_at)}</td><td><div class="device-actions">${r.status!=='approved'?`<button class="btn success approve-device" data-id="${r.id}">Setujui</button>`:''}<button class="btn soft reset-approval" data-id="${r.id}">Reset Persetujuan</button>${r.status!=='revoked'?`<button class="btn danger revoke-device" data-id="${r.id}">Cabut HP</button>`:''}<button class="btn danger delete-device" data-id="${r.id}">Delete</button></div></td></tr>`).join(''):'<tr><td colspan="5" class="empty">Belum ada perangkat.</td></tr>'}</tbody></table></div></div>`;
  $('#content').onclick=async e=>{const b=e.target.closest('button[data-id]');if(!b||b.disabled)return;const id=b.dataset.id;let endpoint='',question='',method='POST';if(b.classList.contains('approve-device')){endpoint=`/devices/${id}/approve`;question='Setujui HP ini? HP aktif lain milik karyawan yang sama akan dicabut.'}if(b.classList.contains('reset-approval')){endpoint=`/devices/${id}/reset-approval`;question='Kembalikan HP ini ke status menunggu persetujuan?'}if(b.classList.contains('revoke-device')){endpoint=`/devices/${id}/revoke`;question='Cabut akses HP ini?'}if(b.classList.contains('delete-device')){endpoint=`/devices/${id}`;method='DELETE';question='Delete data HP ini permanen dari daftar? Jika ini HP aktif, user harus daftar ulang.'}if(!endpoint||!confirm(question))return;loading(true);try{const out=await api(endpoint,{method,body:{}});toast(out.message||'Berhasil diproses.','success');await renderDevices()}catch(err){toast(err.message,'error')}finally{loading(false)}};
}

async function renderPayroll(period){
  period=period||new Date().toISOString().slice(0,7);const [emps,salaries,components,payslips]=await Promise.all([api('/employees'),api('/payroll/salaries'),api('/payroll/components?period='+period),api('/payslips?period='+period)]);state.data.employees=emps.data;
  $('#content').innerHTML=`<div class="grid cols-3"><div class="card stat"><div><span>Periode</span><input type="month" id="payrollPeriod" value="${period}" style="border:0;background:transparent;font-size:27px;font-weight:900;padding:0;margin-top:4px;font-family:inherit;color:inherit"></div><div class="stat-icon">▦</div></div><div class="card stat"><div><span>Komponen Variabel</span><strong>${components.data.length}</strong></div><div class="stat-icon">+</div></div><div class="card stat"><div><span>Slip Dibuat</span><strong>${payslips.data.length}</strong></div><div class="stat-icon">▤</div></div></div>
  <div class="card" style="margin-top:18px"><div class="toolbar"><h3>Proses Payroll</h3><div class="group"><button id="newSalary" class="btn soft">Struktur Gaji</button><button id="newComponent" class="btn soft">Komponen Bulanan</button><button id="downloadPayrollTemplate" class="btn soft">Download Template</button><button id="uploadPayrollExcel" class="btn soft">Upload Excel</button><input id="payrollExcelFile" type="file" accept=".xlsx,.csv" hidden><button id="generatePayroll" class="btn primary">Generate Payroll</button><button id="exportPayroll" class="btn secondary">Export Excel</button></div></div><div class="table-wrap"><table><thead><tr><th>Karyawan</th><th>Gaji Bersih</th><th>Reimbursement</th><th>Total Transfer</th><th>Status</th><th>Aksi</th></tr></thead><tbody>${payslips.data.length?payslips.data.map(r=>`<tr><td>${esc(r.full_name)}<br><small>${esc(r.nik)}</small></td><td>${money(r.net_salary)}</td><td>${money(r.reimbursement_total)}</td><td><strong>${money(r.total_transfer)}</strong></td><td>${statusBadge(r.status)}</td><td>${r.status==='draft'?`<button class="btn success publish-slip" data-id="${r.id}">Terbitkan</button>`:'-'}</td></tr>`).join(''):'<tr><td colspan="6" class="empty">Belum ada payroll yang digenerate.</td></tr>'}</tbody></table></div></div>
  <div class="grid cols-2" style="margin-top:18px"><div class="card"><h3>Komponen Variabel ${period}</h3><div class="table-wrap"><table><thead><tr><th>Karyawan</th><th>Komponen</th><th>Nominal</th><th>Status</th><th>Aksi</th></tr></thead><tbody>${components.data.length?components.data.map(c=>`<tr><td>${esc(c.full_name)}</td><td>${esc(c.label)}<br><small>${esc(c.project_name||'')}</small></td><td>${money(c.amount)}</td><td>${statusBadge(c.status)}</td><td>${c.status==='draft'?`<button class="btn success approve-component" data-id="${c.id}">Approve</button>`:'-'}</td></tr>`).join(''):'<tr><td colspan="5" class="empty">Belum ada komponen.</td></tr>'}</tbody></table></div></div><div class="card"><h3>Struktur Gaji</h3><div class="table-wrap"><table><thead><tr><th>Karyawan</th><th>Efektif</th><th>Gaji Pokok</th><th>Status</th><th>Aksi</th></tr></thead><tbody>${salaries.data.length?salaries.data.map(s=>`<tr><td>${esc(s.full_name)}</td><td>${fmtDate(s.effective_from)}</td><td>${money(s.base_salary)}</td><td>${statusBadge(s.status)}</td><td>${s.status==='draft'?`<button class="btn success approve-salary" data-id="${s.id}">Approve</button>`:'-'}</td></tr>`).join(''):'<tr><td colspan="5" class="empty">Belum ada struktur gaji.</td></tr>'}</tbody></table></div></div></div>`;
  $('#payrollPeriod').onchange=e=>{if(e.target.value)renderPayroll(e.target.value)};
  const employeeOptions=emps.data.map(e=>`<option value="${e.id}">${esc(e.nik)} — ${esc(e.full_name)}</option>`).join('');
  $('#newSalary').onclick=()=>openModal('Struktur Gaji Baru',`<form data-form class="form-grid"><label class="full">Karyawan<select name="employee_id">${employeeOptions}</select></label><label>Tanggal efektif<input type="date" name="effective_from" required></label><label>Gaji pokok<input type="number" name="base_salary" required></label><label>Tunjangan jabatan<input type="number" name="position_allowance"></label><label>Tunjangan tetap<input type="number" name="fixed_allowance"></label><label>Makan/transport<input type="number" name="meal_transport"></label><label>BPJS Kesehatan<input type="number" name="bpjs_health"></label><label>BPJS Ketenagakerjaan<input type="number" name="bpjs_employment"></label><label>PPh 21<input type="number" name="pph21"></label><label>Kasbon<input type="number" name="loan_deduction"></label><label>Potongan lain<input type="number" name="other_deduction"></label><label>Bank<input name="bank_name"></label><div class="full"><button class="btn primary wide">Simpan Draft</button></div></form>`,d=>api('/payroll/salaries',{method:'POST',body:d}));
  $('#newComponent').onclick=()=>openModal('Komponen Payroll Bulanan',`<form data-form class="form-grid"><input type="hidden" name="period" value="${period}"><label class="full">Karyawan<select name="employee_id">${employeeOptions}</select></label><label>Kategori<select name="category"><option value="project_bonus">Bonus Proyek</option><option value="project_incentive">Insentif Proyek</option><option value="travel_allowance">Tunjangan Dinas</option><option value="salary_adjustment">Rapel/Penyesuaian</option><option value="other_income">Pendapatan Lain</option><option value="other_deduction">Potongan Lain</option><option value="reimbursement">Reimbursement</option></select></label><label>Perlakuan<select name="treatment"><option value="earning">Penghasilan</option><option value="deduction">Potongan</option><option value="reimbursement">Reimbursement</option></select></label><label>Label<input name="label" required></label><label>Nominal<input type="number" name="amount" required></label><label>Kode proyek<input name="project_code"></label><label>Nama proyek<input name="project_name"></label><label class="full">Keterangan<textarea name="description" required></textarea></label><div class="full"><button class="btn primary wide">Simpan Draft</button></div></form>`,d=>api('/payroll/components',{method:'POST',body:d}));
  $('#downloadPayrollTemplate').onclick=()=>MPAXLSX.exportWorkbook(`Template_Slip_Gaji_${period}.xlsx`,[{name:'Upload Slip Gaji',rows:[['Periode','NIK','Nama Karyawan','Gaji Pokok','Lembur Jam','Tarif Lembur/Jam','Total Lembur','Tunjangan','Bonus','Lain-lain Tambahan','Potongan Terlambat','Potongan Absensi','Potongan Lain-lain','Reimbursement','Total Diterima','Catatan'],[period,'CONTOH001','Nama Karyawan',0,0,0,0,0,0,0,0,0,0,0,0,'Hapus baris contoh sebelum upload']]}]);
  $('#uploadPayrollExcel').onclick=()=>$('#payrollExcelFile').click();
  $('#payrollExcelFile').onchange=async e=>{const file=e.target.files?.[0];if(!file)return;const fd=new FormData();fd.append('file',file);loading(true);try{const preview=await api('/payroll/import-preview',{method:'POST',body:fd});state.data.payrollImport=preview.rows;const sm=preview.summary;openModal('Preview Upload Slip Gaji',`<div class="grid cols-3"><div class="card stat"><div><span>Total Baris</span><strong>${sm.total}</strong></div></div><div class="card stat"><div><span>Valid</span><strong>${sm.valid}</strong></div></div><div class="card stat"><div><span>Bermasalah</span><strong>${sm.invalid}</strong></div></div></div><div class="notice ${sm.invalid?'warning':'success'}" style="margin:14px 0">Total transfer valid: <strong>${money(sm.total_transfer)}</strong>. ${sm.invalid?'Perbaiki baris merah sebelum melanjutkan.':'Data siap dibuat sebagai draft atau langsung dipublish.'}</div><div class="table-wrap"><table><thead><tr><th>Baris</th><th>Karyawan</th><th>Periode</th><th>Gaji Pokok</th><th>Lembur</th><th>Total</th><th>Validasi</th></tr></thead><tbody>${preview.rows.map(r=>`<tr><td>${r.line}</td><td><strong>${esc(r.full_name)}</strong><br><small>${esc(r.nik)}</small></td><td>${esc(r.period)}</td><td>${money(r.base_salary)}</td><td>${money(r.overtime_total)}</td><td><strong>${money(r.total_transfer)}</strong></td><td>${r.valid?'<span class="status approved">valid</span>':`<span class="status rejected">${esc(r.errors.join('; '))}</span>`}${r.warnings?.length?`<br><small>${esc(r.warnings.join('; '))}</small>`:''}</td></tr>`).join('')}</tbody></table></div><div class="actions"><button type="button" id="savePayrollDraft" class="btn secondary" ${sm.invalid?'disabled':''}>Simpan Draft</button><button type="button" id="publishPayrollImport" class="btn success" ${sm.invalid?'disabled':''}>Publish ke Karyawan</button></div>`);setTimeout(()=>{const run=async publish=>{if(!confirm(publish?'Publish seluruh slip valid ke karyawan?':'Simpan seluruh slip sebagai draft?'))return;loading(true);try{const out=await api('/payroll/import-publish',{method:'POST',body:{rows:state.data.payrollImport,publish}});$('#modal').close();toast(`${out.created.length} slip berhasil ${publish?'dipublish':'dibuat sebagai draft'}.`,'success');await renderPayroll()}catch(err){toast(err.message,'error')}finally{loading(false)}};$('#savePayrollDraft').onclick=()=>run(false);$('#publishPayrollImport').onclick=()=>run(true)},0);}catch(err){toast(err.message,'error')}finally{loading(false);e.target.value=''}};
  $('#generatePayroll').onclick=async()=>{if(!confirm('Generate payroll '+period+'? Sistem membuat versi baru untuk seluruh karyawan yang memiliki struktur gaji approved.'))return;loading(true);try{const r=await api('/payroll/generate',{method:'POST',body:{period}});toast(`${r.generated.length} payroll berhasil dibuat.`,'success');await renderPayroll()}catch(e){toast(e.message,'error')}finally{loading(false)}};
  $('#exportPayroll').onclick=()=>MPAXLSX.exportWorkbook(`Payroll_${period}.xlsx`,[{name:'Payroll',rows:[['Periode','NIK','Nama','Gaji Bersih','Reimbursement','Total Transfer','Status'],...payslips.data.map(r=>[r.period,r.nik,r.full_name,Number(r.net_salary),Number(r.reimbursement_total),Number(r.total_transfer),r.status])]},{name:'Komponen Variabel',rows:[['NIK','Nama','Kategori','Label','Proyek','Perlakuan','Nominal','Status'],...components.data.map(c=>[c.nik,c.full_name,c.category,c.label,c.project_name||'',c.treatment,Number(c.amount),c.status])]}]);
  $('#content').onclick=async e=>{for(const cls of ['approve-component','approve-salary','publish-slip']){const b=e.target.closest('.'+cls);if(b){const endpoint=cls==='approve-component'?`/payroll/components/${b.dataset.id}/approve`:cls==='approve-salary'?`/payroll/salaries/${b.dataset.id}/approve`:`/payslips/${b.dataset.id}/publish`;loading(true);try{await api(endpoint,{method:'POST',body:{}});toast('Berhasil diproses.','success');await renderPayroll()}catch(err){toast(err.message,'error')}finally{loading(false)}return}}};
}

function toWaNumber(phone){
  let digits=String(phone||'').replace(/\D/g,'');
  if(!digits)return '';
  if(digits.startsWith('0'))digits='62'+digits.slice(1);
  else if(!digits.startsWith('62'))digits='62'+digits;
  return digits;
}
function waMessage(name,position,url,expiresAt){
  return `Halo ${name}, terima kasih telah melamar posisi ${position} di PT Multi Power Abadi.\n\nSilakan kerjakan tes seleksi (Tes Penalaran & Tes Kepribadian) melalui link berikut:\n${url}\n\nLink berlaku sampai ${fmtDateTime(expiresAt)}. Pastikan koneksi internet stabil selama mengerjakan tes.\n\nTerima kasih.`;
}
function waLink(phone,name,position,url,expiresAt){
  const num=toWaNumber(phone);
  return num?`https://wa.me/${num}?text=${encodeURIComponent(waMessage(name,position,url,expiresAt))}`:null;
}

async function renderRecruitment(){
  const {data}=await api('/recruitment/sessions');state.data.recruitment=data;
  $('#content').innerHTML=`<div class="card"><div class="toolbar"><div><h3>Tes Masuk Karyawan</h3><p class="muted">Kelola kandidat dan kirim link Tes Penalaran (IQ) &amp; Tes Kepribadian (DISC). Bukan tes psikometri tervalidasi klinis — gunakan sebagai salah satu bahan pertimbangan.</p></div><div class="group">${state.user.role==='super_admin'?'<button id="manageBanks" class="btn soft">Kelola Bank Soal</button>':''}<button id="newCandidate" class="btn primary">+ Kandidat Baru</button></div></div><div class="table-wrap"><table><thead><tr><th>Nama</th><th>Posisi</th><th>Tanggal</th><th>Status</th><th>Skor</th><th>Rekomendasi</th><th>Aksi</th></tr></thead><tbody>${data.length?data.map(r=>{const url=location.origin+'/test.html?t='+r.access_token;const wa=r.phone?waLink(r.phone,r.full_name,r.applied_position,url,r.expires_at):null;return `<tr><td><strong>${esc(r.full_name)}</strong></td><td>${esc(r.applied_position)}</td><td>${fmtDate((r.created_at||'').slice(0,10))}</td><td><span class="status ${esc(r.status)}">${esc(RECRUITMENT_STATUS_LABEL[r.status]||r.status)}</span></td><td>${r.iq_scaled_score!=null?`${r.iq_scaled_score} — ${esc(r.iq_category||'')}`:'-'}</td><td>${r.iq_recommendation?`<span class="status ${r.iq_recommendation==='direkomendasikan'?'approved':'rejected'}">${r.iq_recommendation==='direkomendasikan'?'Direkomendasikan':'Tidak Direkomendasikan'}</span>`:'-'}</td><td><div class="group"><button class="btn soft view-session" data-id="${r.id}">Detail</button><button class="btn soft copy-link" data-token="${r.access_token}">Copy Link</button>${wa?`<a class="btn soft" style="background:#e7f9ee;color:#128c46;text-decoration:none" href="${wa}" target="_blank" rel="noopener">WhatsApp</a>`:''}${r.status!=='completed'?`<button class="btn secondary resend-session" data-id="${r.id}">Kirim Ulang</button>`:''}</div></td></tr>`;}).join(''):'<tr><td colspan="7" class="empty">Belum ada kandidat.</td></tr>'}</tbody></table></div></div>`;

  $('#newCandidate').onclick=()=>openModal('Kandidat Baru',`<form data-form class="form-grid"><label>Nama lengkap<input name="full_name" required></label><label>Posisi dilamar<input name="applied_position" required></label><label>No. HP (untuk kirim link via WhatsApp)<input name="phone" placeholder="08xxxxxxxxxx"></label><label>Email<input type="email" name="email"></label><label class="full">Catatan<textarea name="notes"></textarea></label><div class="full"><button class="btn primary wide">Simpan &amp; Buat Link Tes</button></div></form>`,async d=>{
    const cand=await api('/recruitment/candidates',{method:'POST',body:d});
    const sess=await api(`/recruitment/candidates/${cand.id}/sessions`,{method:'POST',body:{}});
    setTimeout(()=>showRecruitmentLink(sess),150);
  });
  $('#manageBanks')?.addEventListener('click',renderBankList);

  $('#content').onclick=async e=>{
    const view=e.target.closest('.view-session');
    if(view){await openRecruitmentDetail(view.dataset.id);return;}
    const copy=e.target.closest('.copy-link');
    if(copy){
      const url=location.origin+'/test.html?t='+copy.dataset.token;
      try{await navigator.clipboard.writeText(url);toast('Link tes disalin.','success')}catch{toast(url,'info')}
      return;
    }
    const resend=e.target.closest('.resend-session');
    if(resend){
      if(!confirm('Generate ulang link tes? Link lama akan tidak berlaku.'))return;
      loading(true);try{const sess=await api(`/recruitment/sessions/${resend.dataset.id}/resend`,{method:'POST',body:{}});showRecruitmentLink(sess)}catch(err){toast(err.message,'error')}finally{loading(false)}
      return;
    }
  };
}

async function renderBankList(){
  loading(true);
  try{
    const {data}=await api('/recruitment/question-banks');
    $('#content').innerHTML=`<div class="card"><div class="toolbar"><div><h3>Bank Soal — Tes Masuk Karyawan</h3><p class="muted">Revisi teks soal/opsi, kunci jawaban IQ, dan trait DISC. Perubahan berlaku untuk sesi tes berikutnya.</p></div><button id="backToRecruitment" class="btn soft">&larr; Kembali</button></div><div class="table-wrap"><table><thead><tr><th>Bank Soal</th><th>Tipe</th><th>Durasi</th><th>Jumlah Soal</th><th>Ambang Kelulusan</th><th>Status</th><th>Aksi</th></tr></thead><tbody>${data.map(b=>`<tr><td><strong>${esc(b.title)}</strong><br><small class="muted">v${esc(b.version)}</small></td><td>${b.test_type.toUpperCase()}</td><td>${b.duration_minutes} menit</td><td>${b.question_count}</td><td>${b.pass_threshold??'-'}</td><td>${b.is_active?'<span class="status approved">Aktif</span>':'<span class="status rejected">Nonaktif</span>'}</td><td><div class="group"><button class="btn soft edit-bank-settings" data-id="${b.id}">Pengaturan</button><button class="btn soft edit-bank-questions" data-id="${b.id}">Lihat/Edit Soal</button></div></td></tr>`).join('')}</tbody></table></div></div>`;
    $('#backToRecruitment').onclick=renderRecruitment;
    $('#content').onclick=async e=>{
      const settings=e.target.closest('.edit-bank-settings');
      if(settings){
        const bank=data.find(x=>String(x.id)===String(settings.dataset.id));if(!bank)return;
        openModal('Pengaturan Bank Soal',`<form data-form class="form-grid"><label class="full">Judul<input name="title" value="${esc(bank.title)}" required></label><label>Durasi (menit)<input type="number" name="duration_minutes" value="${bank.duration_minutes}" min="1" required></label>${bank.test_type==='iq'?`<label>Ambang skor kelulusan<input type="number" name="pass_threshold" value="${bank.pass_threshold??110}"></label>`:''}<label class="full"><input type="checkbox" name="is_active" value="1" ${bank.is_active?'checked':''}> Bank soal aktif (dipakai untuk sesi tes baru)</label><div class="full"><button class="btn primary wide">Simpan</button></div></form>`);
        $('#modal form[data-form]').onsubmit=async ev=>{
          ev.preventDefault();
          const d=formDataObject(ev.target);
          loading(true);
          try{await api(`/recruitment/question-banks/${bank.id}`,{method:'PUT',body:{...d,is_active:!!d.is_active}});$('#modal').close();toast('Pengaturan bank soal disimpan.','success');await renderBankList();}
          catch(err){toast(err.message,'error');}
          finally{loading(false);}
        };
        return;
      }
      const editQ=e.target.closest('.edit-bank-questions');
      if(editQ){await renderBankQuestions(editQ.dataset.id);return;}
    };
  }catch(err){toast(err.message,'error')}finally{loading(false)}
}

async function renderBankQuestions(bankId){
  loading(true);
  try{
    const {data}=await api('/recruitment/question-banks/'+bankId);
    const bank=data.bank,questions=data.questions;
    const isIq=bank.test_type==='iq';
    $('#content').innerHTML=`<div class="card"><div class="toolbar"><div><h3>${esc(bank.title)}</h3><p class="muted">${isIq?'Klik Edit untuk mengubah teks soal, opsi jawaban, dan kunci jawaban benar.':'Klik Edit untuk mengubah teks opsi dan trait D/I/S/C tiap opsi.'}</p></div><button id="backToBankList" class="btn soft">&larr; Kembali</button></div><div class="table-wrap" style="max-height:520px;overflow:auto"><table><thead><tr><th>No</th><th>${isIq?'Soal':'Blok'}</th><th>Opsi</th><th>Aksi</th></tr></thead><tbody>${questions.map(q=>`<tr><td>${q.question_number}</td><td>${isIq?esc(q.question_text):'<em>Blok kepribadian</em>'}</td><td>${q.options.map(o=>`${o.label}. ${esc(o.text)}`).join('<br>')}</td><td><button class="btn soft edit-question" data-id="${q.id}">Edit</button></td></tr>`).join('')}</tbody></table></div></div>`;
    $('#backToBankList').onclick=renderBankList;
    $('#content').onclick=e=>{
      const btn=e.target.closest('.edit-question');
      if(btn){
        const q=questions.find(x=>String(x.id)===String(btn.dataset.id));
        if(q)openQuestionEditor(bankId,bank,q);
      }
    };
  }catch(err){toast(err.message,'error')}finally{loading(false)}
}

function openQuestionEditor(bankId,bank,question){
  const isIq=bank.test_type==='iq';
  const optionsHtml=question.options.map(o=>isIq
    ?`<div class="form-grid" style="grid-template-columns:24px 1fr 90px;align-items:center;margin-bottom:6px"><b>${o.label}</b><input name="opt_${o.label}_text" value="${esc(o.text)}" required><label style="display:flex;align-items:center;gap:4px;font-size:12px"><input type="radio" name="correct" value="${o.label}" ${o.is_correct?'checked':''}> Benar</label></div>`
    :`<div class="form-grid" style="grid-template-columns:24px 1fr 90px;align-items:center;margin-bottom:6px"><b>${o.label}</b><input name="opt_${o.label}_text" value="${esc(o.text)}" required><select name="trait_${o.label}"><option value="D" ${o.trait==='D'?'selected':''}>D</option><option value="I" ${o.trait==='I'?'selected':''}>I</option><option value="S" ${o.trait==='S'?'selected':''}>S</option><option value="C" ${o.trait==='C'?'selected':''}>C</option></select></div>`
  ).join('');
  openModal(`Edit ${isIq?'Soal':'Blok'} #${question.question_number}`,`<form data-form class="form-grid">
    ${isIq?`<label class="full">Teks Soal<textarea name="question_text" required>${esc(question.question_text)}</textarea></label>`:'<p class="muted full">Pernyataan kepribadian DISC tidak punya jawaban benar/salah — pastikan tiap blok tetap punya tepat 1 opsi per trait D, I, S, C.</p>'}
    <div class="full">${optionsHtml}</div>
    <div class="full"><button class="btn primary wide">Simpan Perubahan</button></div>
  </form>`);
  $('#modal form[data-form]').onsubmit=async ev=>{
    ev.preventDefault();
    const d=formDataObject(ev.target);
    const options=question.options.map(o=>isIq
      ?{label:o.label,text:d['opt_'+o.label+'_text'],is_correct:d.correct===o.label}
      :{label:o.label,text:d['opt_'+o.label+'_text'],trait:d['trait_'+o.label]});
    loading(true);
    try{
      await api(`/recruitment/question-banks/${bankId}/questions/${question.id}`,{method:'PUT',body:{question_text:d.question_text,options}});
      $('#modal').close();
      toast(`${isIq?'Soal':'Blok'} berhasil diperbarui.`,'success');
      await renderBankQuestions(bankId);
    }catch(err){toast(err.message,'error');}
    finally{loading(false);}
  };
}

function showRecruitmentLink(sess){
  const {test_url:url,expires_at:expiresAt,candidate_name:name,applied_position:position,phone}=sess;
  const wa=phone?waLink(phone,name,position,url,expiresAt):null;
  openModal('Link Tes Kandidat',`<div class="notice success">Bagikan link ini ke kandidat (berlaku sampai ${fmtDateTime(expiresAt)}):</div><div class="form-grid"><label class="full">Link Tes<input id="recruitmentLinkInput" readonly value="${esc(url)}"></label><div class="full"><button type="button" id="copyRecruitmentLink" class="btn primary wide">Salin Link</button></div>${wa?`<div class="full"><a href="${wa}" target="_blank" rel="noopener" class="btn wide" style="background:#25D366;color:#fff;text-decoration:none;display:inline-flex;align-items:center;justify-content:center">Kirim via WhatsApp</a></div>`:`<div class="full"><p class="muted">Nomor HP kandidat belum diisi, jadi tidak bisa kirim otomatis via WhatsApp. Salin link secara manual.</p></div>`}</div>`);
  setTimeout(()=>{$('#copyRecruitmentLink')?.addEventListener('click',async()=>{try{await navigator.clipboard.writeText(url);toast('Link disalin.','success')}catch{$('#recruitmentLinkInput')?.select();}});},0);
  navigate('recruitment');
}

async function openRecruitmentDetail(id){
  loading(true);
  try{
    const {data}=await api('/recruitment/sessions/'+id);
    const s=data.session;
    const most=data.disc_summary?.most||{},least=data.disc_summary?.least||{};
    const discMax=Math.max(1,...Object.values(most),...Object.values(least));
    const discBars=data.disc_summary?['D','I','S','C'].map(t=>`<div style="margin-bottom:10px"><strong>${DISC_TRAIT_LABEL[t]} (${t})</strong>
      <div style="display:flex;align-items:center;gap:8px;margin-top:4px"><span style="width:80px;font-size:12px" class="muted">Mirip</span><div style="flex:1;background:#eee;border-radius:4px;overflow:hidden;height:10px"><div style="width:${(most[t]||0)/discMax*100}%;background:#168447;height:100%"></div></div><small>${most[t]||0}</small></div>
      <div style="display:flex;align-items:center;gap:8px;margin-top:4px"><span style="width:80px;font-size:12px" class="muted">Tidak Mirip</span><div style="flex:1;background:#eee;border-radius:4px;overflow:hidden;height:10px"><div style="width:${(least[t]||0)/discMax*100}%;background:#d60812;height:100%"></div></div><small>${least[t]||0}</small></div>
    </div>`).join(''):'<p class="muted">Tes DISC belum diselesaikan.</p>';
    const iqTable=data.iq_answers.map(a=>`<tr><td>${a.question_number}</td><td>${esc(a.selected_option||'-')}</td><td>${esc(a.correct_option||'-')}</td><td>${a.is_correct==null?'-':(a.is_correct?'<span class="status approved">Benar</span>':'<span class="status rejected">Salah</span>')}</td></tr>`).join('');
    openModal(`Hasil Tes — ${esc(s.full_name)}`,`
      <div class="grid cols-3">
        <div class="card stat"><div><span>Posisi</span><strong>${esc(s.applied_position)}</strong></div></div>
        <div class="card stat"><div><span>Tanggal Tes</span><strong>${fmtDate((s.created_at||'').slice(0,10))}</strong></div></div>
        <div class="card stat"><div><span>Status</span><strong>${esc(RECRUITMENT_STATUS_LABEL[s.status]||s.status)}</strong></div></div>
      </div>
      <div class="notice ${s.iq_recommendation==='direkomendasikan'?'success':s.iq_recommendation?'warning':'info'}" style="margin:14px 0">
        <strong>Skor Tes Penalaran: ${s.iq_scaled_score??'-'}${s.iq_category?` — ${esc(s.iq_category)}`:''}</strong><br>
        Benar ${s.iq_raw_score??'-'} dari 60 soal. ${s.iq_recommendation?`Rekomendasi: <strong>${s.iq_recommendation==='direkomendasikan'?'Direkomendasikan':'Tidak Direkomendasikan'}</strong>`:'Tes IQ belum diselesaikan.'}
        <br><small>Bukan skor IQ tervalidasi klinis — gunakan sebagai salah satu bahan pertimbangan, bukan penentu tunggal.</small>
      </div>
      <h4>Profil DISC</h4>
      <div>${discBars}</div>
      <h4 style="margin-top:18px">Detail Jawaban Tes Penalaran (1-60)</h4>
      <div class="table-wrap" style="max-height:320px;overflow:auto"><table><thead><tr><th>No</th><th>Jawaban</th><th>Kunci</th><th>Status</th></tr></thead><tbody>${iqTable||'<tr><td colspan="4" class="empty">Belum ada jawaban.</td></tr>'}</tbody></table></div>
    `);
  }catch(err){toast(err.message,'error')}finally{loading(false)}
}

async function renderAudit(){const {data}=await api('/audit');$('#content').innerHTML=`<div class="card"><div class="toolbar"><h3>Audit Log</h3><button id="exportAudit" class="btn secondary">Export Excel</button></div><div class="table-wrap"><table><thead><tr><th>Waktu</th><th>User</th><th>Aksi</th><th>Entitas</th><th>IP</th></tr></thead><tbody>${data.map(r=>`<tr><td>${fmtDateTime(r.created_at)}</td><td>${esc(r.username||'SYSTEM')}</td><td>${esc(r.action_name)}</td><td>${esc(r.entity_type)} #${esc(r.entity_id||'')}</td><td>${esc(r.ip_address||'')}</td></tr>`).join('')}</tbody></table></div></div>`;$('#exportAudit').onclick=()=>MPAXLSX.exportWorkbook('Audit_Log_SIGAP_MPA.xlsx',[{name:'Audit Log',rows:[['Waktu','User','Aksi','Entitas','ID','IP','User Agent'],...data.map(r=>[r.created_at,r.username||'SYSTEM',r.action_name,r.entity_type,r.entity_id||'',r.ip_address||'',r.user_agent||''])]}])}


const passwordToggle=$('#passwordToggle');
passwordToggle?.addEventListener('click',()=>{
  const input=$('#loginPassword');
  if(!input)return;
  const visible=input.type==='text';
  input.type=visible?'password':'text';
  passwordToggle.setAttribute('aria-pressed',String(!visible));
  passwordToggle.setAttribute('aria-label',visible?'Tampilkan password':'Sembunyikan password');
  passwordToggle.textContent=visible?'◉':'◎';
  input.focus({preventScroll:true});
});

$('#loginForm').addEventListener('submit',async e=>{e.preventDefault();loading(true);try{const r=await api('/auth/login',{method:'POST',body:{username:$('#loginUsername').value,password:$('#loginPassword').value}});state.user=r.user;state.csrf=r.csrf;showApp()}catch(err){toast(err.message,'error')}finally{loading(false)}});
$('#logoutBtn').onclick=async()=>{loading(true);try{await api('/auth/logout',{method:'POST',body:{}})}catch{}finally{loading(false);closeSidebar();showLogin()}};
ensureDrawerControls();
const menuButton=$('#menuBtn');
menuButton?.addEventListener('click',openSidebar);
menuButton?.addEventListener('touchend',e=>{e.preventDefault();openSidebar();},{passive:false});
$('#homeBtn')?.addEventListener('click',()=>navigate('dashboard'));
$('#sidebarClose')?.addEventListener('click',closeSidebar);
$('#navBackdrop')?.addEventListener('click',closeSidebar);
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeSidebar()});
window.addEventListener('resize',()=>{syncPlatformClasses();if(window.innerWidth>1024)closeSidebar()});
const contentObserver=new MutationObserver(()=>requestAnimationFrame(enhanceMobileTables));
if($('#content'))contentObserver.observe($('#content'),{childList:true,subtree:true});

async function stabilizeAssets(){
  const currentCache='sigap-mpa-v'+APP_VERSION;
  try{
    if('caches' in window){
      const keys=await caches.keys();
      await Promise.all(keys.filter(k=>(k.startsWith('mpa-workforce-')||k.startsWith('sigap-mpa-'))&&k!==currentCache).map(k=>caches.delete(k)));
    }
  }catch{}
  try{
    if('serviceWorker' in navigator&&location.protocol==='https:'){
      const registration=await navigator.serviceWorker.register('sw.js?v='+APP_VERSION,{updateViaCache:'none'});
      await registration.update();
      if(registration.waiting)registration.waiting.postMessage({type:'SKIP_WAITING'});
    }
  }catch{}
}
// ================= Profil Saya (Database Karyawan Lengkap, self-service) =================

const PROFILE_TABS=[['identitas','Identitas'],['alamat','Alamat'],['kontak','Kontak'],['keluarga','Keluarga'],['pendidikan','Pendidikan & Kompetensi'],['kepegawaian','Kepegawaian'],['administrasi','Administrasi'],['pendukung','Pendukung'],['dokumen','Dokumen']];
const PROFILE_STATUS_CLASS={draft:'draft',menunggu_verifikasi:'pending',disetujui:'approved',ditolak:'rejected'};
const PROFILE_STATUS_LABEL={draft:'Draf',menunggu_verifikasi:'Menunggu Verifikasi',disetujui:'Terverifikasi',ditolak:'Ditolak'};
function profileStatusBadge(data){const st=data?.profile_status||'draft';return `<span class="status ${PROFILE_STATUS_CLASS[st]||'draft'}">${PROFILE_STATUS_LABEL[st]||st}</span>`;}
function profileLocked(data){return data?.profile_status==='disetujui';}
function profilePending(data){return data?.profile_status==='menunggu_verifikasi';}
function profileRejectNote(data){return data?.profile_status==='ditolak'&&data.profile_review_note?`<div class="notice warning">Ditolak HRD: ${esc(data.profile_review_note)}</div>`:'';}

async function renderProfile(tab){
  const {data}=await api('/profile/me');
  state.data.profile=data;
  if(!data.consent_accepted_at){renderProfileConsent();return;}
  tab=tab||state.data.profileTab||'identitas';
  state.data.profileTab=tab;
  renderProfileShell(data,tab);
}

function renderProfileConsent(){
  $('#content').innerHTML=`<div class="card">
    <h3>Persetujuan Pemrosesan Data Pribadi</h3>
    <p class="muted">Sebelum melengkapi profil, mohon baca dan setujui hal berikut:</p>
    <ul>
      <li>Data yang Anda isi (identitas, keluarga, dokumen, dll) digunakan untuk keperluan administrasi kepegawaian, payroll, BPJS, dan pajak di PT Multi Power Abadi.</li>
      <li>Data sensitif (NIK, No. KK, NPWP, rekening, catatan medis) disimpan dalam bentuk terenkripsi dan hanya dapat dilihat oleh HRD/Finance yang berwenang, dengan jejak audit setiap akses.</li>
      <li>Anda berhak mengajukan koreksi data melalui menu "Ajukan Perubahan Data" setelah data terverifikasi.</li>
    </ul>
    <button id="acceptConsent" class="btn primary wide">Saya Setuju &amp; Lanjutkan</button>
  </div>`;
  $('#acceptConsent').onclick=async()=>{loading(true);try{await api('/profile/me/consent',{method:'POST',body:{}});toast('Persetujuan tersimpan.','success');await renderProfile();}catch(e){toast(e.message,'error')}finally{loading(false)}};
}

function renderProfileShell(data,tab){
  const pct=data.profile_completion_percent||0;
  const tabsHtml=PROFILE_TABS.map(([code,label])=>`<button class="profile-tab${code===tab?' active':''}" data-tab="${code}">${esc(label)}</button>`).join('');
  $('#content').innerHTML=`<div class="card">
    <div class="toolbar"><div><h3>Profil Saya</h3><p class="muted">Isi &amp; simpan tiap bagian kapan saja. Data boleh belum lengkap — kirim untuk verifikasi HRD di tab terakhir (Dokumen).</p></div>
    <div class="group" style="align-items:center;gap:10px">${profileStatusBadge(data)}<div class="profile-progress"><strong>${pct}%</strong><small>lengkap</small></div></div></div>
    ${profileRejectNote(data)}
    <div id="telegramLinkBox" style="margin:14px 0"></div>
    <div class="profile-tabs">${tabsHtml}</div>
    <div id="profileTabBody"></div>
  </div>`;
  $('#content').querySelectorAll('[data-tab]').forEach(btn=>btn.onclick=()=>renderProfile(btn.dataset.tab));
  renderProfileTabBody(data,tab);
  renderTelegramLinkBox();
}
async function renderTelegramLinkBox(){
  const box=$('#telegramLinkBox');
  if(!box)return;
  try{
    const status=await api('/telegram/link/status');
    if(status.linked){
      box.innerHTML=`<div class="notice" style="background:#e1f5e8;color:#126a37">✅ Telegram sudah terhubung — Anda akan menerima notifikasi pribadi (hasil approval, pengingat absen) lewat Telegram.</div>`;
    }else{
      box.innerHTML=`<div class="notice warning">Belum terhubung ke Telegram. <button id="telegramLinkBtn" class="btn soft" type="button" style="margin-left:8px">Hubungkan Telegram</button></div>`;
      $('#telegramLinkBtn').onclick=async()=>{
        loading(true);
        try{const out=await api('/telegram/link/start',{method:'POST',body:{}});window.open(out.url,'_blank');toast('Buka Telegram dan tekan Start pada chat yang terbuka, lalu refresh halaman ini.','info')}
        catch(e){toast(e.message,'error')}finally{loading(false)}
      };
    }
  }catch(e){box.innerHTML='';}
}

async function saveProfileSection(code,payload){
  loading(true);
  try{const res=await api('/profile/me/'+code,{method:'PUT',body:payload});if(res.warnings&&res.warnings.length)toast(res.warnings.join(' '),'error');else toast('Draf tersimpan.','success');await renderProfile(code);}
  catch(e){toast(e.message,'error')}finally{loading(false)}
}
async function submitWholeProfile(){
  if(!confirm('Kirim seluruh profil untuk diverifikasi HRD? Data yang sudah diisi (meski belum lengkap) akan dikirim. Setelah dikirim, profil tidak bisa diubah sampai HRD memutuskan.'))return;
  loading(true);
  try{await api('/profile/me/submit',{method:'POST',body:{}});toast('Profil terkirim untuk verifikasi HRD.','success');await renderProfile('dokumen');}
  catch(e){toast(e.message,'error')}finally{loading(false)}
}
function profileSaveFooter(locked,pending){
  if(locked)return `<div class="notice" style="background:#e1f5e8;color:#126a37">Profil sudah terverifikasi HRD dan terkunci. Gunakan "Ajukan Perubahan Data" untuk mengubahnya.</div>`;
  if(pending)return `<div class="notice warning">Profil sedang menunggu verifikasi HRD, belum bisa diubah.</div>`;
  return `<div class="actions" style="margin-top:14px"><button type="submit" class="btn primary">Simpan</button></div>`;
}
function profileFinalSubmitFooter(data){
  if(profileLocked(data))return `<div class="notice" style="background:#e1f5e8;color:#126a37">Profil sudah terverifikasi HRD dan terkunci. Gunakan "Ajukan Perubahan Data" untuk mengubahnya.</div>`;
  if(profilePending(data))return `<div class="notice warning">Profil sedang menunggu verifikasi HRD. Anda akan diberi tahu setelah diputuskan.</div>`;
  return `<div class="actions" style="margin-top:18px"><button type="button" class="btn primary wide" id="submitWholeProfile">Kirim untuk Verifikasi</button></div>`;
}
function wireProfileSectionForm(form,code,locked,pending){
  if(locked||pending){form.querySelectorAll('input,select,textarea').forEach(el=>el.disabled=true);return;}
  form.onsubmit=e=>{
    e.preventDefault();
    const payload=formDataObject(form);
    form.querySelectorAll('input[type="checkbox"]').forEach(cb=>{payload[cb.name]=cb.checked;});
    saveProfileSection(code,payload);
  };
}

function renderProfileTabBody(data,tab){
  const body=$('#profileTabBody');
  const locked=profileLocked(data),pending=profilePending(data);
  const head=locked?`<div class="profile-section-head"><div></div><button class="btn soft" id="requestChange">Ajukan Perubahan Data</button></div>`:'';

  if(tab==='identitas'){
    body.innerHTML=head+`<form data-form class="form-grid">
      <label>Nama panggilan<input name="nickname" value="${esc(data.nickname||'')}"></label>
      <label>NIK KTP (16 digit)<input name="nik_ktp" value="${esc(data.nik_ktp||'')}" maxlength="16"></label>
      <label>No. KK (16 digit)<input name="kk_number" value="${esc(data.kk_number||'')}" maxlength="16"></label>
      <label>Tempat lahir<input name="birth_place" value="${esc(data.birth_place||'')}"></label>
      <label>Tanggal lahir<input type="date" name="birth_date" value="${esc(data.birth_date||'')}"></label>
      <label>Jenis kelamin<select name="gender"><option value="">Pilih</option><option value="L" ${data.gender==='L'?'selected':''}>Laki-laki</option><option value="P" ${data.gender==='P'?'selected':''}>Perempuan</option></select></label>
      <label>Agama<select name="religion">${['islam','kristen','katolik','hindu','buddha','khonghucu','lainnya'].map(v=>`<option value="${v}" ${data.religion===v?'selected':''}>${v[0].toUpperCase()+v.slice(1)}</option>`).join('')}</select></label>
      <label>Golongan darah<select name="blood_type">${['A','B','AB','O','unknown'].map(v=>`<option value="${v}" ${data.blood_type===v?'selected':''}>${v==='unknown'?'Tidak tahu':v}</option>`).join('')}</select></label>
      <label>Kewarganegaraan<input name="nationality" value="${esc(data.nationality||'Indonesia')}"></label>
      <label>Status perkawinan<select name="marital_status"><option value="">Pilih</option>${[['belum_kawin','Belum Kawin'],['kawin','Kawin'],['cerai_hidup','Cerai Hidup'],['cerai_mati','Cerai Mati']].map(([v,l])=>`<option value="${v}" ${data.marital_status===v?'selected':''}>${l}</option>`).join('')}</select></label>
      <label>Status dalam KK<select name="family_card_role"><option value="">Pilih</option>${[['kepala_keluarga','Kepala Keluarga'],['istri','Istri'],['anak','Anak'],['famili_lain','Famili Lain']].map(([v,l])=>`<option value="${v}" ${data.family_card_role===v?'selected':''}>${l}</option>`).join('')}</select></label>
      ${profileSaveFooter(locked,pending)}
    </form>`;
    wireProfileSectionForm(body.querySelector('form'),'identitas',locked,pending);
  }

  if(tab==='alamat'){
    const addr=data.ktp_address||{}, dom=data.domicile_address||{};
    body.innerHTML=head+`<form data-form class="form-grid">
      <div class="full"><strong>Alamat sesuai KTP</strong></div>
      <label>Jalan<input name="ktp_jalan" value="${esc(addr.jalan||'')}"></label>
      <label>RT/RW<input name="ktp_rtrw" value="${esc(addr.rtrw||'')}"></label>
      <label>Kelurahan<input name="ktp_kelurahan" value="${esc(addr.kelurahan||'')}"></label>
      <label>Kecamatan<input name="ktp_kecamatan" value="${esc(addr.kecamatan||'')}"></label>
      <label>Kota/Kabupaten<input name="ktp_kota" value="${esc(addr.kota||'')}"></label>
      <label>Provinsi<input name="ktp_provinsi" value="${esc(addr.provinsi||'')}"></label>
      <label>Kode Pos<input name="ktp_kodepos" value="${esc(addr.kodepos||'')}"></label>
      <label class="full"><input type="checkbox" name="domicile_same_as_ktp" value="1" ${data.domicile_same_as_ktp?'checked':''}> Alamat domisili sama dengan KTP</label>
      <div class="full domicile-fields" ${data.domicile_same_as_ktp?'hidden':''}>
        <div class="form-grid">
          <label>Jalan (domisili)<input name="dom_jalan" value="${esc(dom.jalan||'')}"></label>
          <label>Kota (domisili)<input name="dom_kota" value="${esc(dom.kota||'')}"></label>
        </div>
      </div>
      <label>Status tempat tinggal<select name="residence_status"><option value="">Pilih</option>${[['milik_sendiri','Milik Sendiri'],['sewa','Sewa'],['kos','Kos'],['ikut_keluarga','Ikut Keluarga']].map(([v,l])=>`<option value="${v}" ${data.residence_status===v?'selected':''}>${l}</option>`).join('')}</select></label>
      ${profileSaveFooter(locked,pending)}
    </form>`;
    const form=body.querySelector('form');
    form.querySelector('[name="domicile_same_as_ktp"]')?.addEventListener('change',e=>{form.querySelector('.domicile-fields').hidden=e.target.checked;});
    if(!locked&&!pending)form.onsubmit=e=>{e.preventDefault();const d=formDataObject(form);saveProfileSection('alamat',{
      ktp_address:{jalan:d.ktp_jalan,rtrw:d.ktp_rtrw,kelurahan:d.ktp_kelurahan,kecamatan:d.ktp_kecamatan,kota:d.ktp_kota,provinsi:d.ktp_provinsi,kodepos:d.ktp_kodepos},
      domicile_same_as_ktp:!!d.domicile_same_as_ktp,
      domicile_address:d.domicile_same_as_ktp?{}:{jalan:d.dom_jalan,kota:d.dom_kota},
      residence_status:d.residence_status,
    });};
    else form.querySelectorAll('input,select').forEach(el=>el.disabled=true);
  }

  if(tab==='kontak'){
    const contacts=data.emergency_contacts||[];
    body.innerHTML=head+`<form data-form class="form-grid">
      <label>No. HP Utama<input name="phone_primary" value="${esc(data.phone_primary||'')}" required></label>
      <label>No. HP Alternatif<input name="phone_secondary" value="${esc(data.phone_secondary||'')}" required></label>
      <label>Email Pribadi<input type="email" name="email_personal" value="${esc(data.email_personal||'')}"></label>
      <label>Email Kantor<input type="email" name="email_office" value="${esc(data.email_office||'')}"></label>
      ${profileSaveFooter(locked,pending)}
    </form>
    <div style="margin-top:22px"><div class="toolbar"><h3>Kontak Darurat (minimal 2)</h3>${!locked&&!pending?'<button id="newEmergency" class="btn soft">+ Kontak Darurat</button>':''}</div>
    ${contacts.length?contacts.map(c=>`<div class="profile-list-item"><div><strong>${esc(c.full_name)}</strong><br><small>${esc(c.relationship)} · ${esc(c.phone)}</small></div>${!locked&&!pending?`<div class="actions"><button class="btn danger" data-del-emergency="${c.id}">Hapus</button></div>`:''}</div>`).join(''):'<div class="empty">Belum ada kontak darurat.</div>'}
    </div>`;
    wireProfileSectionForm(body.querySelector('form'),'kontak',locked,pending);
    $('#newEmergency')?.addEventListener('click',()=>openModal('Tambah Kontak Darurat',`<form data-form class="form-grid"><label>Nama<input name="full_name" required></label><label>Hubungan<input name="relationship" required></label><label>No. HP<input name="phone" required></label><label class="full">Alamat<input name="address"></label><div class="full"><button class="btn primary wide">Simpan</button></div></form>`,async d=>{await api('/profile/me/emergency-contacts',{method:'POST',body:d});await renderProfile('kontak');}));
    body.querySelectorAll('[data-del-emergency]').forEach(btn=>btn.addEventListener('click',async()=>{if(!confirm('Hapus kontak darurat ini?'))return;loading(true);try{await api('/profile/me/emergency-contacts/'+btn.dataset.delEmergency,{method:'DELETE',body:{}});await renderProfile('kontak');}catch(e){toast(e.message,'error')}finally{loading(false)}}));
  }

  if(tab==='keluarga'){
    const members=data.family_members||[];
    const typeLabel={spouse:'Pasangan',child:'Anak',father:'Ayah',mother:'Ibu'};
    body.innerHTML=head+`<div class="toolbar"><h3>Data Keluarga</h3>${!locked&&!pending?'<button id="newFamily" class="btn soft">+ Anggota Keluarga</button>':''}</div>
    ${members.length?members.map(m=>`<div class="profile-list-item"><div><strong>${esc(m.full_name)}</strong> <span class="status draft">${typeLabel[m.member_type]||m.member_type}</span><br><small>${m.birth_date?fmtDate(m.birth_date):''}${m.member_type==='child'&&m.is_dependent?' · Tanggungan':''}</small></div>${!locked&&!pending?`<div class="actions"><button class="btn danger" data-del-family="${m.id}">Hapus</button></div>`:''}</div>`).join(''):'<div class="empty">Belum ada data keluarga.</div>'}
    `;
    $('#newFamily')?.addEventListener('click',()=>openModal('Tambah Anggota Keluarga',`<form data-form class="form-grid">
      <label class="full">Jenis<select name="member_type"><option value="spouse">Pasangan</option><option value="child">Anak</option><option value="father">Ayah</option><option value="mother">Ibu</option></select></label>
      <label>Nama Lengkap<input name="full_name" required></label><label>NIK<input name="nik" maxlength="16"></label>
      <label>Tempat Lahir<input name="birth_place"></label><label>Tanggal Lahir<input type="date" name="birth_date"></label>
      <label>Jenis Kelamin<select name="gender"><option value="">Pilih</option><option value="L">Laki-laki</option><option value="P">Perempuan</option></select></label>
      <label>Tanggal Menikah (pasangan)<input type="date" name="marriage_date"></label><label>Pekerjaan (pasangan)<input name="occupation"></label>
      <label>No. HP<input name="phone"></label><label>Pendidikan (anak)<input name="education_level"></label>
      <label class="full"><input type="checkbox" name="is_dependent" value="1"> Anak tanggungan</label>
      <div class="full"><button class="btn primary wide">Simpan</button></div></form>`,async d=>{await api('/profile/me/family',{method:'POST',body:{...d,is_dependent:!!d.is_dependent}});await renderProfile('keluarga');}));
    body.querySelectorAll('[data-del-family]').forEach(btn=>btn.addEventListener('click',async()=>{if(!confirm('Hapus data keluarga ini?'))return;loading(true);try{await api('/profile/me/family/'+btn.dataset.delFamily,{method:'DELETE',body:{}});await renderProfile('keluarga');}catch(e){toast(e.message,'error')}finally{loading(false)}}));
  }

  if(tab==='pendidikan'){
    const edu=data.education_history||[],certs=data.certifications||[];
    const certLabel={skk_ska:'SKK/SKA',k3_umum:'K3 Umum',ahli_k3:'Ahli K3',operator_alat_berat:'Operator Alat Berat',sim_a:'SIM A',sim_b1:'SIM B1',sim_b2:'SIM B2',sim_c:'SIM C',sim_d:'SIM D',lainnya:'Lainnya'};
    body.innerHTML=head+`<div class="toolbar"><h3>Riwayat Pendidikan</h3>${!locked&&!pending?'<button id="newEducation" class="btn soft">+ Pendidikan</button>':''}</div>
    ${edu.length?edu.map(e=>`<div class="profile-list-item"><div><strong>${esc(e.institution_name)}</strong><br><small>${esc(e.education_level)} ${esc(e.major||'')} ${e.graduation_year?'· Lulus '+e.graduation_year:''}</small></div>${!locked&&!pending?`<div class="actions"><button class="btn danger" data-del-edu="${e.id}">Hapus</button></div>`:''}</div>`).join(''):'<div class="empty">Belum ada riwayat pendidikan.</div>'}
    <div class="toolbar" style="margin-top:20px"><h3>Sertifikasi & SIM</h3>${!locked&&!pending?'<button id="newCert" class="btn soft">+ Sertifikasi</button>':''}</div>
    ${certs.length?certs.map(c=>`<div class="profile-list-item"><div><strong>${esc(c.cert_name)}</strong> <small>(${certLabel[c.cert_type]||c.cert_type})</small><br><small>${c.expiry_date?'Berlaku sampai '+fmtDate(c.expiry_date):'Tanpa tanggal kadaluarsa'}</small></div>${!locked&&!pending?`<div class="actions"><button class="btn danger" data-del-cert="${c.id}">Hapus</button></div>`:''}</div>`).join(''):'<div class="empty">Belum ada sertifikasi/SIM.</div>'}`;
    $('#newEducation')?.addEventListener('click',()=>openModal('Tambah Riwayat Pendidikan',`<form data-form class="form-grid"><label>Jenjang<select name="education_level">${['SD','SMP','SMA_SMK','D1','D2','D3','D4','S1','S2','S3'].map(v=>`<option value="${v}">${v}</option>`).join('')}</select></label><label>Institusi<input name="institution_name" required></label><label>Jurusan<input name="major"></label><label>Tahun Lulus<input type="number" name="graduation_year"></label><div class="full"><button class="btn primary wide">Simpan</button></div></form>`,async d=>{await api('/profile/me/education',{method:'POST',body:d});await renderProfile('pendidikan');}));
    $('#newCert')?.addEventListener('click',()=>openModal('Tambah Sertifikasi/SIM',`<form data-form class="form-grid"><label>Jenis<select name="cert_type">${Object.entries(certLabel).map(([v,l])=>`<option value="${v}">${l}</option>`).join('')}</select></label><label>Nama Sertifikat<input name="cert_name" required></label><label>Nomor<input name="cert_number"></label><label>Penerbit<input name="issuer"></label><label>Tanggal Terbit<input type="date" name="issued_date"></label><label>Tanggal Kadaluarsa<input type="date" name="expiry_date"></label><div class="full"><button class="btn primary wide">Simpan</button></div></form>`,async d=>{await api('/profile/me/certifications',{method:'POST',body:d});await renderProfile('pendidikan');}));
    body.querySelectorAll('[data-del-edu]').forEach(btn=>btn.addEventListener('click',async()=>{if(!confirm('Hapus riwayat pendidikan ini?'))return;loading(true);try{await api('/profile/me/education/'+btn.dataset.delEdu,{method:'DELETE',body:{}});await renderProfile('pendidikan');}catch(e){toast(e.message,'error')}finally{loading(false)}}));
    body.querySelectorAll('[data-del-cert]').forEach(btn=>btn.addEventListener('click',async()=>{if(!confirm('Hapus sertifikasi ini?'))return;loading(true);try{await api('/profile/me/certifications/'+btn.dataset.delCert,{method:'DELETE',body:{}});await renderProfile('pendidikan');}catch(e){toast(e.message,'error')}finally{loading(false)}}));
  }

  if(tab==='kepegawaian'){
    const history=data.position_history||[],training=data.training_history||[],emp=data.employment_details||{};
    const contractLabel={pkwt:'PKWT',pkwtt:'PKWTT',harian:'Harian',magang:'Magang'};
    body.innerHTML=`<div class="notice" style="background:#e7e9ec;color:#555">Bagian ini dikelola oleh HRD. Anda hanya dapat melihat data di bawah.</div>
    <div class="grid cols-3" style="margin-top:14px">
      <div class="card stat"><div><span>NIP Internal</span><strong>${esc(emp.nip_internal||'-')}</strong></div></div>
      <div class="card stat"><div><span>Status Kepegawaian</span><strong>${esc(contractLabel[emp.contract_type]||'-')}</strong></div></div>
      <div class="card stat"><div><span>Akhir Kontrak</span><strong>${emp.contract_end_date?fmtDate(emp.contract_end_date):'-'}</strong></div></div>
    </div>
    <h3 style="margin-top:18px">Riwayat Jabatan (Career Path)</h3>
    <div class="table-wrap"><table><thead><tr><th>Jabatan</th><th>Departemen</th><th>Jenis</th><th>Mulai</th><th>Selesai</th></tr></thead><tbody>${history.length?history.map(h=>`<tr><td>${esc(h.position_name)}</td><td>${esc(h.department_name)}</td><td>${esc(h.mutation_type)}</td><td>${fmtDate(h.start_date)}</td><td>${h.end_date?fmtDate(h.end_date):'<em>Saat ini</em>'}</td></tr>`).join(''):'<tr><td colspan="5" class="empty">Belum ada riwayat jabatan.</td></tr>'}</tbody></table></div>
    <h3 style="margin-top:18px">Riwayat Training</h3>
    <div class="table-wrap"><table><thead><tr><th>Training</th><th>Penyelenggara</th><th>Tanggal</th><th>Status</th></tr></thead><tbody>${training.length?training.map(t=>`<tr><td>${esc(t.training_name)}</td><td>${esc(t.provider||'-')}</td><td>${t.start_date?fmtDate(t.start_date):'-'}</td><td>${esc(t.status)}</td></tr>`).join(''):'<tr><td colspan="4" class="empty">Belum ada riwayat training.</td></tr>'}</tbody></table></div>`;
  }

  if(tab==='administrasi'){
    body.innerHTML=head+`<form data-form class="form-grid">
      <label class="full"><input type="checkbox" name="uses_nik_as_npwp" value="1" ${data.uses_nik_as_npwp?'checked':''}> Menggunakan NIK sebagai NPWP</label>
      <label>NPWP<input name="npwp" value="${esc(data.npwp||'')}"></label>
      <label>Status PTKP (otomatis)<input value="${esc(data.ptkp_status||'-')}" disabled></label>
      <label class="full"><input type="checkbox" name="ptkp_override" value="1" ${data.ptkp_override?'checked':''}> Override status PTKP (isi catatan di bawah)</label>
      <label class="full">Catatan override PTKP<input name="ptkp_override_note" value="${esc(data.ptkp_override_note||'')}"></label>
      <label>No. BPJS Kesehatan<input name="bpjs_health_no" value="${esc(data.bpjs_health_no||'')}"></label>
      <label>Faskes Tingkat 1<input name="bpjs_health_faskes" value="${esc(data.bpjs_health_faskes||'')}"></label>
      <label>No. BPJS Ketenagakerjaan<input name="bpjs_tk_no" value="${esc(data.bpjs_tk_no||'')}"></label>
      <label>Nama Bank<input name="bank_name" value="${esc(data.bank_name||'')}"></label>
      <label>No. Rekening<input name="bank_account" value="${esc(data.bank_account||'')}"></label>
      <label>Nama Pemilik Rekening<input name="bank_account_holder_name" value="${esc(data.bank_account_holder_name||'')}"></label>
      <label class="full">Catatan jika nama rekening berbeda dengan nama karyawan<input name="bank_account_holder_mismatch_note" value="${esc(data.bank_account_holder_mismatch_note||'')}"></label>
      ${profileSaveFooter(locked,pending)}
    </form>`;
    wireProfileSectionForm(body.querySelector('form'),'administrasi',locked,pending);
  }

  if(tab==='pendukung'){
    body.innerHTML=head+`<form data-form class="form-grid">
      <label>Ukuran Seragam<input name="uniform_size" value="${esc(data.uniform_size||'')}"></label>
      <label>Ukuran Sepatu Safety<input name="shoe_size" value="${esc(data.shoe_size||'')}"></label>
      <label>Ukuran Helm/APD<input name="helmet_size" value="${esc(data.helmet_size||'')}"></label>
      <label class="full">Riwayat Alergi/Kondisi Darurat <small>(hanya terlihat HRD &amp; HSE)</small><textarea name="medical_notes" rows="3">${esc(data.medical_notes&&data.medical_notes!=='(tersimpan, terenkripsi)'?data.medical_notes:'')}</textarea></label>
      ${profileSaveFooter(locked,pending)}
    </form>`;
    wireProfileSectionForm(body.querySelector('form'),'pendukung',locked,pending);
  }

  if(tab==='dokumen'){
    const docs=data.documents||[];
    const typeLabel={ktp:'KTP (Kartu Tanda Penduduk)',kk:'Kartu Keluarga (KK)',npwp:'NPWP',ijazah:'Ijazah',buku_nikah:'Buku Nikah',akta_lahir_anak:'Akta Lahir Anak',kartu_bpjs:'Kartu BPJS',buku_tabungan:'Buku Tabungan',sertifikat_kompetensi:'Sertifikat Kompetensi',sim:'SIM',lainnya:'Lainnya (sebutkan di nama file)'};
    const docStatus={menunggu_verifikasi:'pending',valid:'approved',ditolak:'rejected'};
    body.innerHTML=head+`<div class="toolbar"><h3>Dokumen</h3>${!locked&&!pending?'<button id="newDocument" class="btn soft">+ Upload Dokumen</button>':''}</div>
    ${docs.length?docs.map(d=>`<div class="profile-list-item"><div><strong>${typeLabel[d.document_type]||d.document_type}</strong><br><small>${esc(d.original_filename)} · ${fmtDateTime(d.uploaded_at)}</small>${d.status==='ditolak'&&d.rejection_note?`<br><small style="color:#9d0008">Ditolak: ${esc(d.rejection_note)}</small>`:''}</div><div><span class="status ${docStatus[d.status]||'pending'}">${d.status==='valid'?'Valid':d.status==='ditolak'?'Ditolak':'Menunggu Verifikasi'}</span><br><div class="group" style="margin-top:6px"><a class="btn soft" href="api/profile/documents/${d.id}/file" target="_blank" rel="noopener">Lihat/Unduh</a>${d.status!=='valid'&&!locked&&!pending?` <button class="btn danger" data-del-doc="${d.id}">Hapus</button>`:''}</div></div></div>`).join(''):'<div class="empty">Belum ada dokumen diupload.</div>'}
    ${profileFinalSubmitFooter(data)}`;
    $('#submitWholeProfile')?.addEventListener('click',submitWholeProfile);
    $('#newDocument')?.addEventListener('click',()=>{
      openModal('Upload Dokumen',`<form data-form class="form-grid"><label>Jenis Dokumen<select name="document_type">${Object.entries(typeLabel).map(([v,l])=>`<option value="${v}">${l}</option>`).join('')}</select></label><label>File (PDF/JPG/PNG, maks 2MB)<input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" required></label><div class="full"><button class="btn primary wide">Upload</button></div></form>`,async d=>{
        const form=$('#modal').querySelector('form');
        const fd=new FormData(form);
        await api('/profile/me/documents',{method:'POST',body:fd});
        await renderProfile('dokumen');
      });
    });
    body.querySelectorAll('[data-del-doc]').forEach(btn=>btn.addEventListener('click',async()=>{if(!confirm('Hapus dokumen ini?'))return;loading(true);try{await api('/profile/me/documents/'+btn.dataset.delDoc,{method:'DELETE',body:{}});await renderProfile('dokumen');}catch(e){toast(e.message,'error')}finally{loading(false)}}));
  }

  $('#requestChange')?.addEventListener('click',()=>openModal('Ajukan Perubahan Data',`<form data-form class="form-grid"><label>Alasan<select name="trigger_event">${[['menikah','Menikah'],['punya_anak','Punya Anak'],['pindah_alamat','Pindah Alamat'],['ganti_hp','Ganti No. HP'],['ganti_rekening','Ganti Rekening'],['lainnya','Lainnya']].map(([v,l])=>`<option value="${v}">${l}</option>`).join('')}</select></label><label class="full">Field yang ingin diubah<input name="field" placeholder="contoh: phone_primary" required></label><label class="full">Nilai baru<input name="value" required></label><div class="full"><button class="btn primary wide">Kirim Pengajuan</button></div></form>`,async d=>{
    await api('/profile/me/change-requests',{method:'POST',body:{section_code:tab,trigger_event:d.trigger_event,after_json:{[d.field]:d.value}}});
  }));
}

// ================= Database Karyawan Lengkap (HRD) =================

async function renderEmployeeProfiles(){
  const [overview,queue,dash]=await Promise.all([api('/profile/overview'),api('/profile/queue'),api('/profile/dashboard')]);
  const profiles=queue.data.profiles||[],docs=queue.data.documents||[],changes=queue.data.change_requests||[];
  const alerts=dash.data.alerts||{},stats=dash.data.stats||{};
  const sectionLabel=Object.fromEntries(PROFILE_TABS);
  const docTypeLabel={ktp:'KTP (Kartu Tanda Penduduk)',kk:'Kartu Keluarga (KK)',npwp:'NPWP',ijazah:'Ijazah',buku_nikah:'Buku Nikah',akta_lahir_anak:'Akta Lahir Anak',kartu_bpjs:'Kartu BPJS',buku_tabungan:'Buku Tabungan',sertifikat_kompetensi:'Sertifikat Kompetensi',sim:'SIM',lainnya:'Lainnya'};
  const contractLabel={pkwt:'PKWT',pkwtt:'PKWTT',harian:'Harian',magang:'Magang'};
  const statusLabel={active:'Aktif',inactive:'Nonaktif',terminated:'Berakhir'};
  $('#content').innerHTML=`
  <div class="grid cols-3">
    <div class="card stat"><div><span>Rata-rata Kelengkapan</span><strong>${overview.data.length?Math.round(overview.data.reduce((a,r)=>a+r.profile_completion_percent,0)/overview.data.length):0}%</strong></div></div>
    <div class="card stat"><div><span>Antrian Verifikasi</span><strong>${profiles.length+docs.length}</strong></div></div>
    <div class="card stat"><div><span>Pengajuan Perubahan</span><strong>${changes.length}</strong></div></div>
  </div>

  <div class="card" style="margin-top:18px"><div class="toolbar"><h3>Alert</h3></div>
    ${alerts.contracts?.length?`<div class="notice warning"><strong>${alerts.contracts.length} kontrak PKWT akan berakhir ≤60 hari:</strong> ${alerts.contracts.map(c=>`${esc(c.full_name)} (${fmtDate(c.contract_end_date)})`).join(', ')}</div>`:''}
    ${alerts.certifications?.length?`<div class="notice warning"><strong>${alerts.certifications.length} sertifikat/SIM akan kadaluarsa ≤60 hari:</strong> ${alerts.certifications.map(c=>`${esc(c.full_name)} - ${esc(c.cert_name)} (${fmtDate(c.expiry_date)})`).join(', ')}</div>`:''}
    ${alerts.birthdays?.length?`<div class="notice" style="background:#e1ecff;color:#1a4fa3"><strong>🎉 ${alerts.birthdays.length} ulang tahun bulan ini:</strong> ${alerts.birthdays.map(b=>`${esc(b.full_name)} (${fmtDate(b.birth_date).split(' ').slice(0,2).join(' ')})`).join(', ')}</div>`:''}
    ${!alerts.contracts?.length&&!alerts.certifications?.length&&!alerts.birthdays?.length?'<div class="empty">Tidak ada alert aktif saat ini.</div>':''}
  </div>

  <div class="grid cols-3" style="margin-top:18px">
    <div class="card"><h3>Status Kepegawaian</h3>${(stats.employment_status||[]).map(s=>`<div class="slip-row"><span>${esc(statusLabel[s.employment_status]||s.employment_status)}</span><strong>${s.total}</strong></div>`).join('')||'<div class="empty">-</div>'}</div>
    <div class="card"><h3>Jenis Kontrak</h3>${(stats.contract_type||[]).map(s=>`<div class="slip-row"><span>${esc(contractLabel[s.contract_type]||s.contract_type)}</span><strong>${s.total}</strong></div>`).join('')||'<div class="empty">Belum ada data.</div>'}</div>
    <div class="card"><h3>Lainnya</h3><div class="slip-row"><span>Rata-rata Masa Kerja</span><strong>${stats.average_tenure_years||0} th</strong></div><div class="slip-row"><span>Total Anak Tanggungan</span><strong>${stats.dependents_total||0}</strong></div></div>
  </div>
  ${stats.domicile_top?.length?`<div class="card" style="margin-top:18px"><h3>Sebaran Domisili (Top 10 Kota)</h3>${stats.domicile_top.map(d=>`<div class="slip-row"><span>${esc(d.kota)}</span><strong>${d.total}</strong></div>`).join('')}</div>`:''}

  <div class="card" style="margin-top:18px"><div class="toolbar"><div><h3>Kelengkapan Data per Karyawan</h3><p class="muted">Klik "Lihat Detail" untuk melihat/download dokumen &amp; kelola data kepegawaian (NIP, kontrak). Cari nama/NIK/jabatan di kotak pencarian.</p></div><div class="group"><button id="exportFullData" class="btn secondary">Export Excel Lengkap</button><button id="downloadImportTemplate" class="btn soft">Download Template Impor</button><button id="uploadImportExcel" class="btn soft">Upload Excel</button><input id="importExcelFile" type="file" accept=".xlsx,.csv" hidden></div></div>
  <input id="employeeSearch" type="search" placeholder="Cari karyawan (nama / NIK / jabatan)..." style="margin-bottom:10px;width:100%;max-width:360px">
  <div class="table-wrap"><table><thead><tr><th>NIK</th><th>Nama</th><th>Jabatan</th><th>Kelengkapan</th><th>Aksi</th></tr></thead><tbody id="employeeOverviewBody">
  ${overview.data.map(r=>`<tr data-search="${esc((r.nik+' '+r.full_name+' '+r.position_name).toLowerCase())}"><td>${esc(r.nik)}</td><td><strong>${esc(r.full_name)}</strong></td><td>${esc(r.position_name)}</td><td>${r.profile_completion_percent}%</td><td><div class="group"><button class="btn soft view-profile" data-id="${r.id}">Lihat Detail</button>${r.profile_completion_percent<100?`<button class="btn secondary remind-employee" data-id="${r.id}">Reminder</button>`:''}</div></td></tr>`).join('')}
  </tbody></table></div><div id="employeeSearchEmpty" class="empty" hidden>Tidak ada karyawan yang cocok dengan pencarian.</div></div>

  <div class="card" style="margin-top:18px"><h3>Antrian Verifikasi — Profil Karyawan</h3><p class="muted">Satu baris per karyawan. Tinjau seluruh profil lalu putuskan sekali untuk semua bagian.</p><div class="table-wrap"><table><thead><tr><th>Karyawan</th><th>Kelengkapan</th><th>Dikirim</th><th>Aksi</th></tr></thead><tbody>
  ${profiles.length?profiles.map(p=>`<tr><td><strong>${esc(p.full_name)}</strong><br><small>${esc(p.nik)}</small></td><td>${p.profile_completion_percent}%</td><td>${fmtDateTime(p.submitted_at)}</td><td><div class="group"><button class="btn soft view-profile" data-id="${p.employee_id}">Lihat Detail</button><button class="btn success approve-profile" data-id="${p.employee_id}">Setujui</button><button class="btn danger reject-profile" data-id="${p.employee_id}">Tolak</button></div></td></tr>`).join(''):'<tr><td colspan="4" class="empty">Tidak ada antrian.</td></tr>'}
  </tbody></table></div></div>
  <div class="card" style="margin-top:18px"><h3>Antrian Verifikasi — Dokumen</h3><div class="table-wrap"><table><thead><tr><th>Karyawan</th><th>Jenis Dokumen</th><th>Diupload</th><th>Aksi</th></tr></thead><tbody>
  ${docs.length?docs.map(d=>`<tr><td><strong>${esc(d.full_name)}</strong><br><small>${esc(d.nik)}</small></td><td>${docTypeLabel[d.document_type]||d.document_type}</td><td>${fmtDateTime(d.uploaded_at)}</td><td><div class="group"><a class="btn soft" href="api/profile/documents/${d.id}/file" target="_blank" rel="noopener">Lihat/Unduh</a><button class="btn success approve-doc" data-id="${d.id}">Valid</button><button class="btn danger reject-doc" data-id="${d.id}">Tolak</button></div></td></tr>`).join(''):'<tr><td colspan="4" class="empty">Tidak ada antrian.</td></tr>'}
  </tbody></table></div></div>
  <div class="card" style="margin-top:18px"><h3>Pengajuan Perubahan Data</h3><div class="table-wrap"><table><thead><tr><th>Karyawan</th><th>Bagian</th><th>Alasan</th><th>Diajukan</th><th>Aksi</th></tr></thead><tbody>
  ${changes.length?changes.map(c=>`<tr><td><strong>${esc(c.full_name)}</strong><br><small>${esc(c.nik)}</small></td><td>${esc(sectionLabel[c.section_code]||c.section_code)}</td><td>${esc(c.trigger_event)}</td><td>${fmtDateTime(c.requested_at)}</td><td><div class="group"><button class="btn success approve-change" data-id="${c.id}">Setujui</button><button class="btn danger reject-change" data-id="${c.id}">Tolak</button></div></td></tr>`).join(''):'<tr><td colspan="5" class="empty">Tidak ada pengajuan.</td></tr>'}
  </tbody></table></div></div>`;

  $('#employeeSearch').oninput=e=>{
    const q=e.target.value.trim().toLowerCase();
    let visible=0;
    $('#employeeOverviewBody').querySelectorAll('tr[data-search]').forEach(tr=>{
      const match=!q||tr.dataset.search.includes(q);
      tr.hidden=!match;
      if(match)visible++;
    });
    $('#employeeSearchEmpty').hidden=visible>0;
  };

  $('#exportFullData').onclick=async()=>{
    const reason=prompt('Alasan ekspor data karyawan lengkap (wajib, tercatat di audit log):');
    if(!reason)return;
    loading(true);
    try{
      const res=await api('/profile/export?reason='+encodeURIComponent(reason));
      MPAXLSX.exportWorkbook(`Database_Karyawan_Lengkap_${new Date().toISOString().slice(0,10)}.xlsx`,[{name:'Data Karyawan',rows:[res.headers,...res.rows]}]);
      toast('Export berhasil diunduh.','success');
    }catch(err){toast(err.message,'error')}finally{loading(false)}
  };
  $('#downloadImportTemplate').onclick=()=>MPAXLSX.exportWorkbook('Template_Impor_Karyawan.xlsx',[{name:'Impor Karyawan',rows:[['NIK','Nama Lengkap','Jabatan','Departemen','Tanggal Bergabung','Kuota Cuti','Username','Password Awal','Role'],['CONTOH001','Nama Karyawan','Staff Gudang','Operasional','2025-01-01',12,'','','Hapus baris contoh sebelum upload. Username/Password/Role opsional (kosongkan jika belum mau buat akun login).']]}]);
  $('#uploadImportExcel').onclick=()=>$('#importExcelFile').click();
  $('#importExcelFile').onchange=async e=>{
    const file=e.target.files?.[0]; if(!file)return;
    const fd=new FormData(); fd.append('file',file);
    loading(true);
    try{
      const preview=await api('/employees/import-preview',{method:'POST',body:fd});
      state.data.employeeImport=preview.rows; const sm=preview.summary;
      openModal('Preview Impor Karyawan',`<div class="grid cols-3"><div class="card stat"><div><span>Total Baris</span><strong>${sm.total}</strong></div></div><div class="card stat"><div><span>Valid</span><strong>${sm.valid}</strong></div></div><div class="card stat"><div><span>Bermasalah</span><strong>${sm.invalid}</strong></div></div></div>
      <div class="notice ${sm.invalid?'warning':'success'}" style="margin:14px 0">${sm.invalid?'Perbaiki baris bermasalah di file lalu upload ulang. Baris valid tetap bisa diimpor.':'Seluruh baris valid dan siap diimpor.'}</div>
      <div class="table-wrap"><table><thead><tr><th>Baris</th><th>NIK</th><th>Nama</th><th>Jabatan</th><th>Validasi</th></tr></thead><tbody>${preview.rows.map(r=>`<tr><td>${r.line}</td><td>${esc(r.nik)}</td><td>${esc(r.full_name)}</td><td>${esc(r.position_name)}</td><td>${r.valid?'<span class="status approved">valid</span>':`<span class="status rejected">${esc(r.errors.join('; '))}</span>`}</td></tr>`).join('')}</tbody></table></div>
      <div class="actions"><button type="button" id="commitImport" class="btn primary" ${sm.valid?'':'disabled'}>Impor ${sm.valid} Karyawan Valid</button></div>`);
      setTimeout(()=>{$('#commitImport').onclick=async()=>{
        if(!confirm(`Impor ${sm.valid} karyawan valid ke sistem?`))return;
        loading(true);
        try{const out=await api('/employees/import-commit',{method:'POST',body:{rows:state.data.employeeImport}});$('#modal').close();toast(out.message,'success');await renderEmployeeProfiles();}
        catch(err){toast(err.message,'error')}finally{loading(false)}
      };},0);
    }catch(err){toast(err.message,'error')}finally{loading(false);e.target.value='';}
  };

  $('#content').onclick=async e=>{
    const view=e.target.closest('.view-profile');
    if(view){
      const empId=view.dataset.id;
      const data=(await api('/profile/'+empId)).data;
      const emp=data.employment_details||{};
      const training=data.training_history||[];
      const docs=data.documents||[];
      const docStatusLabel={menunggu_verifikasi:'Menunggu Verifikasi',valid:'Valid',ditolak:'Ditolak'};
      const row=(label,value)=>{if(value===null||value===undefined||value==='')value='-';return `<div class="profile-list-item"><div><small>${esc(label)}</small><br><strong>${esc(value)}</strong></div></div>`;};
      const group=(title,rows)=>`<h4 style="margin:14px 0 6px">${esc(title)}</h4><div class="form-grid" style="display:block">${rows.join('')}</div>`;
      const addr=data.ktp_address||{},dom=data.domicile_address||{};
      const genderLabel={L:'Laki-laki',P:'Perempuan'};
      const maritalLabel={belum_kawin:'Belum Kawin',kawin:'Kawin',cerai_hidup:'Cerai Hidup',cerai_mati:'Cerai Mati'};
      const familyTypeLabel={spouse:'Pasangan',child:'Anak',father:'Ayah',mother:'Ibu'};
      const emergencyContacts=data.emergency_contacts||[],familyMembers=data.family_members||[],eduHistory=data.education_history||[],certs=data.certifications||[];
      openModal('Detail Profil Karyawan',`
        <div class="profile-section-head"><div>${profileStatusBadge(data)}</div><div class="muted"><small>Kelengkapan: ${data.profile_completion_percent||0}%</small></div></div>
        ${profileRejectNote(data)}
        <h3 style="margin-top:14px">Dokumen</h3>
        ${docs.length?docs.map(d=>`<div class="profile-list-item"><div><strong>${docTypeLabel[d.document_type]||d.document_type}</strong><br><small>${esc(d.original_filename)}</small></div><div style="text-align:right"><span class="status ${d.status==='valid'?'approved':d.status==='ditolak'?'rejected':'pending'}">${docStatusLabel[d.status]||d.status}</span><br><a class="btn soft" style="margin-top:6px" href="api/profile/documents/${d.id}/file" target="_blank" rel="noopener">Lihat/Unduh</a></div></div>`).join(''):'<div class="empty">Belum ada dokumen diupload.</div>'}
        <h3 style="margin-top:18px">Data Kepegawaian</h3>
        <form data-form class="form-grid">
          <label>NIP Internal <small>(otomatis jika kosong)</small><input value="${esc(emp.nip_internal||'(belum ada)')}" disabled></label>
          <label>Jenis Kontrak<select name="contract_type">${Object.entries(contractLabel).map(([v,l])=>`<option value="${v}" ${emp.contract_type===v?'selected':''}>${l}</option>`).join('')}</select></label>
          <label>Tanggal Akhir Kontrak (wajib untuk PKWT)<input type="date" name="contract_end_date" value="${esc(emp.contract_end_date||'')}"></label>
          <label>Tanggal Resign/PHK<input type="date" name="resign_date" value="${esc(emp.resign_date||'')}"></label>
          <label class="full">Alasan Resign/PHK<input name="resign_reason" value="${esc(emp.resign_reason||'')}"></label>
          <div class="full"><button class="btn primary wide">Simpan Data Kepegawaian</button></div>
        </form>
        <div class="toolbar" style="margin-top:18px"><h3>Riwayat Training</h3><button type="button" id="newTraining" class="btn soft">+ Training</button></div>
        <div id="trainingList">${training.length?training.map(t=>`<div class="profile-list-item"><div><strong>${esc(t.training_name)}</strong><br><small>${esc(t.provider||'-')} · ${t.start_date?fmtDate(t.start_date):'-'} · ${esc(t.status)}</small></div><div class="actions"><button type="button" class="btn danger del-training" data-id="${t.id}">Hapus</button></div></div>`).join(''):'<div class="empty">Belum ada riwayat training.</div>'}</div>
        <h3 style="margin-top:18px">Ringkasan Profil</h3>
        ${group('Identitas',[
          row('Nama Panggilan',data.nickname),
          row('NIK KTP',data.nik_ktp),
          row('No. KK',data.kk_number),
          row('Tempat, Tanggal Lahir',[data.birth_place,data.birth_date?fmtDate(data.birth_date):''].filter(Boolean).join(', ')),
          row('Jenis Kelamin',genderLabel[data.gender]||data.gender),
          row('Agama',data.religion),
          row('Golongan Darah',data.blood_type),
          row('Status Perkawinan',maritalLabel[data.marital_status]||data.marital_status),
        ])}
        ${group('Alamat',[
          row('Alamat KTP',[addr.jalan,addr.kelurahan,addr.kecamatan,addr.kota,addr.provinsi,addr.kodepos].filter(Boolean).join(', ')),
          row('Alamat Domisili',data.domicile_same_as_ktp?'Sama dengan KTP':[dom.jalan,dom.kota].filter(Boolean).join(', ')),
          row('Status Tempat Tinggal',data.residence_status),
        ])}
        ${group('Kontak',[
          row('No. HP Utama',data.phone_primary),
          row('No. HP Alternatif',data.phone_secondary),
          row('Email Pribadi',data.email_personal),
          row('Email Kantor',data.email_office),
        ].concat(emergencyContacts.map(c=>row('Kontak Darurat: '+c.relationship,`${c.full_name} · ${c.phone}`))))}
        ${group('Keluarga',familyMembers.length?familyMembers.map(m=>row(familyTypeLabel[m.member_type]||m.member_type,m.full_name+(m.birth_date?' · '+fmtDate(m.birth_date):''))):[row('Data Keluarga','Belum ada data')])}
        ${(()=>{const eduRows=eduHistory.map(e=>row('Pendidikan',`${e.education_level} - ${e.institution_name}${e.graduation_year?' (lulus '+e.graduation_year+')':''}`)).concat(certs.map(c=>row('Sertifikasi/SIM',`${c.cert_name}${c.expiry_date?' (berlaku s.d. '+fmtDate(c.expiry_date)+')':''}`)));return group('Pendidikan & Kompetensi',eduRows.length?eduRows:[row('Pendidikan & Kompetensi','Belum ada data')]);})()}
        ${group('Administrasi',[
          row('NPWP',data.uses_nik_as_npwp?'Menggunakan NIK sebagai NPWP':data.npwp),
          row('Status PTKP',data.ptkp_status),
          row('No. BPJS Kesehatan',data.bpjs_health_no),
          row('No. BPJS Ketenagakerjaan',data.bpjs_tk_no),
          row('Bank',[data.bank_name,data.bank_account].filter(Boolean).join(' - ')),
          row('Nama Pemilik Rekening',data.bank_account_holder_name),
        ])}
        ${group('Pendukung',[
          row('Ukuran Seragam',data.uniform_size),
          row('Ukuran Sepatu Safety',data.shoe_size),
          row('Ukuran Helm/APD',data.helmet_size),
        ])}`,
        async d=>{await api('/profile/'+empId+'/employment',{method:'PUT',body:d});}
      );
      setTimeout(()=>{
        document.querySelector('#newTraining')?.addEventListener('click',()=>{
          openModal('Tambah Riwayat Training',`<form data-form class="form-grid">
            <label class="full">Nama Training<input name="training_name" required></label>
            <label>Penyelenggara<input name="provider"></label>
            <label>Kategori<select name="category"><option value="internal">Internal</option><option value="eksternal">Eksternal</option></select></label>
            <label>Tanggal Mulai<input type="date" name="start_date"></label>
            <label>Tanggal Selesai<input type="date" name="end_date"></label>
            <label>Durasi (jam)<input type="number" name="duration_hours"></label>
            <label>Lokasi<input name="location"></label>
            <label>Status<select name="status"><option value="selesai">Selesai</option><option value="berjalan">Berjalan</option><option value="dibatalkan">Dibatalkan</option></select></label>
            <label class="full">Catatan Hasil<input name="result_note"></label>
            <div class="full"><button class="btn primary wide">Simpan Training</button></div></form>`,
            async d=>{await api('/profile/'+empId+'/training',{method:'POST',body:d});});
        });
        document.querySelectorAll('.del-training').forEach(btn=>btn.addEventListener('click',async()=>{
          if(!confirm('Hapus riwayat training ini?'))return;
          loading(true);
          try{await api('/profile/'+empId+'/training/'+btn.dataset.id,{method:'DELETE',body:{}});toast('Training dihapus.','success');await renderEmployeeProfiles();}
          catch(err){toast(err.message,'error')}finally{loading(false)}
        }));
      },0);
      return;
    }
    const remind=e.target.closest('.remind-employee');
    if(remind){
      loading(true);
      try{
        const out=await api('/profile/'+remind.dataset.id+'/remind',{method:'POST',body:{channel:'in_app'}});
        if(out.phone_primary){
          const num=toWaNumber(out.phone_primary);
          const text=`Halo ${out.full_name}, mohon segera lengkapi profil Anda di SIGAP (menu Profil Saya) agar data kepegawaian tetap up to date. Terima kasih.`;
          if(num)window.open(`https://wa.me/${num}?text=${encodeURIComponent(text)}`,'_blank');
        }
        toast('Reminder tercatat untuk '+out.full_name+'.','success');
      }catch(err){toast(err.message,'error')}finally{loading(false)}
      return;
    }
    const approveProfile=e.target.closest('.approve-profile'),rejectProfile=e.target.closest('.reject-profile');
    if(approveProfile||rejectProfile){
      const btn=approveProfile||rejectProfile;const status=approveProfile?'disetujui':'ditolak';
      let note='';if(rejectProfile){note=prompt('Catatan penolakan (wajib):')||'';if(!note)return;}
      loading(true);try{await api(`/profile/${btn.dataset.id}/decision`,{method:'POST',body:{status,review_note:note}});toast('Keputusan tersimpan.','success');await renderEmployeeProfiles();}catch(err){toast(err.message,'error')}finally{loading(false)}return;
    }
    const approveDoc=e.target.closest('.approve-doc'),rejectDoc=e.target.closest('.reject-doc');
    if(approveDoc||rejectDoc){
      const btn=approveDoc||rejectDoc;const status=approveDoc?'valid':'ditolak';
      let note='';if(rejectDoc){note=prompt('Catatan penolakan (wajib):')||'';if(!note)return;}
      loading(true);try{await api(`/profile/documents/${btn.dataset.id}/decision`,{method:'POST',body:{status,rejection_note:note}});toast('Keputusan tersimpan.','success');await renderEmployeeProfiles();}catch(err){toast(err.message,'error')}finally{loading(false)}return;
    }
    const approveChange=e.target.closest('.approve-change'),rejectChange=e.target.closest('.reject-change');
    if(approveChange||rejectChange){
      const btn=approveChange||rejectChange;const status=approveChange?'disetujui':'ditolak';
      let note='';if(rejectChange){note=prompt('Catatan penolakan (wajib):')||'';if(!note)return;}
      loading(true);try{await api(`/profile/change-requests/${btn.dataset.id}/decision`,{method:'POST',body:{status,review_note:note}});toast('Keputusan tersimpan.','success');await renderEmployeeProfiles();}catch(err){toast(err.message,'error')}finally{loading(false)}return;
    }
  };
}

(async function init(){
  loading(false);
  $('#sslNotice').hidden=location.protocol==='https:';
  await stabilizeAssets();
  try{
    const h=await api('/health?build='+encodeURIComponent(APP_VERSION)+'&t='+Date.now());
    setServerTime(h.server_time);
    $('#serverBadge').textContent=h.database?'Database online · v'+APP_VERSION:'Database error';
    if(!h.installed){showLogin();return;}
    const me=await api('/auth/me?t='+Date.now());
    setServerTime(me.server_time);
    state.user=me.user;
    state.csrf=me.csrf;
    showApp();
  }catch(e){
    showLogin();
  }finally{
    loading(false);
  }
})();

window.addEventListener('pageshow',()=>{syncPlatformClasses();loading(false);if(state.user&&state.page==='dashboard'&&!$('#content')?.children.length)navigate('dashboard')});
window.addEventListener('unhandledrejection',()=>loading(false));
window.addEventListener('error',()=>loading(false));

$('#modalClose').onclick=()=>$('#modal').close();
