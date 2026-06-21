'use strict';
const BASE = (window.location.hostname === '127.0.0.1' || window.location.hostname === 'localhost') ? 'http://127.0.0.1:8000/api' : window.location.origin + '/api';
const token = localStorage.getItem('token');
const role  = localStorage.getItem('role');
if (!token || role !== 'admin') { location.replace('index.html'); }
document.getElementById('uname').textContent = localStorage.getItem('user_name') || 'Admin';

const auth = () => ({ Authorization: `Bearer ${token}`, Accept: 'application/json', 'Content-Type': 'application/json' });
const esc  = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
const $    = id => document.getElementById(id);
const val  = id => $(id)?.value?.trim() ?? '';

let toastTimer;
function toast(msg, cls='', dur=2800) {
  const el = $('toast'); el.textContent = msg;
  el.className = cls ? `show ${cls}` : 'show';
  clearTimeout(toastTimer); toastTimer = setTimeout(() => el.className = '', dur);
}

const debounceMap = {};
function debounce(fn, ms) {
  return () => { clearTimeout(debounceMap[fn]); debounceMap[fn] = setTimeout(fn, ms); };
}

function showModal(id)  { const m = $(id); m.style.display='flex'; }
function closeModal(id) { const m = $(id); m.style.display='none'; }

function setBtn(id, loading) {
  const b = $(id);
  if (!b) return;
  b.disabled = loading;
  b.textContent = loading ? '…' : b.dataset.label || b.textContent;
}

function showErr(id, msg) {
  const el = $(id); if (!el) return;
  el.textContent = msg; el.style.display = msg ? 'block' : 'none';
}

function pillHtml(s) {
  const map = { NEW:'sp-new', IN_PROGRESS:'sp-ip', QC_VALIDATED:'sp-sub',
    RELEASED:'sp-val', PENDING:'sp-rej', COMPLETED:'sp-cmp' };
  let label = s || '—';
  if (label === 'NEW') label = 'BARU';
  else if (label === 'IN_PROGRESS') label = 'IN PROGRESS';
  else if (label === 'QC_VALIDATED') label = 'VALIDASI QC';
  else if (label === 'RELEASED') label = 'RILIS';
  else if (label === 'COMPLETED') label = 'SELESAI';
  else if (label === 'PENDING') label = 'RE-INSPEKSI';
  return `<span class="status-pill ${map[s]||''}">${esc(label)}</span>`;
}

function roleBadge(r) {
  const cls = { admin:'badge-admin-r', qc:'badge-qc', operator:'badge-operator' };
  return `<span class="badge ${cls[r]||''}">${esc(r)}</span>`;
}

function statusBadge(s) {
  return s === 'active'
    ? `<span class="badge badge-active">Active</span>`
    : `<span class="badge badge-inactive">Inactive</span>`;
}

function pagerHtml(meta, loadFn) {
  if (!meta || meta.last_page <= 1) return '';
  return `<button onclick="${loadFn}(${meta.current_page-1})" ${meta.current_page<=1?'disabled':''}>‹ Prev</button>
    <span>Hal ${meta.current_page} / ${meta.last_page}</span>
    <button onclick="${loadFn}(${meta.current_page+1})" ${meta.current_page>=meta.last_page?'disabled':''}>Next ›</button>`;
}

function skRows(cols, n=4) {
  return Array(n).fill(`<tr>${Array(cols).fill(`<td><div class="sk" style="height:13px;width:80%;border-radius:4px"></div></td>`).join('')}</tr>`).join('');
}

// ── Tab switching ────────────────────────────────────────────
const tabLoaders = {
  users: loadUsers,
  clients: loadClients,
  machines: loadMachines,
  requests: loadRequests,
  rolls: loadRolls,
  defects: loadDefects,
  yarns: loadYarns,
  settings: loadSettings,
  gramasi: loadGramasis,
  'final-data': loadFinalData
};

document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    $(`tab-${btn.dataset.tab}`).classList.add('active');
    tabLoaders[btn.dataset.tab]?.();
  });
});

// ── Stats ────────────────────────────────────────────────────
async function loadStats() {
  try {
    const [u,c,m,r,ro,d,y,s] = await Promise.all([
      fetch(`${BASE}/admin/users?per_page=1`, {headers:auth()}).then(r=>r.json()),
      fetch(`${BASE}/admin/clients?per_page=1`, {headers:auth()}).then(r=>r.json()),
      fetch(`${BASE}/admin/machines?per_page=1`, {headers:auth()}).then(r=>r.json()),
      fetch(`${BASE}/admin/inspection-requests?per_page=1`, {headers:auth()}).then(r=>r.json()),
      fetch(`${BASE}/admin/fabric-rolls?per_page=1`, {headers:auth()}).then(r=>r.json()),
      fetch(`${BASE}/admin/defect-types`, {headers:auth()}).then(r=>r.json()),
      fetch(`${BASE}/admin/yarn-types`, {headers:auth()}).then(r=>r.json()),
      fetch(`${BASE}/admin/settings`, {headers:auth()}).then(r=>r.json()),
    ]);
    $('sUsers').textContent    = u?.data?.total ?? u?.total ?? '—';
    $('sClients').textContent  = c?.data?.total ?? c?.total ?? '—';
    $('sMachines').textContent = m?.data?.total ?? m?.total ?? '—';
    $('sRequests').textContent = r?.data?.total ?? r?.total ?? '—';
    $('sRolls').textContent    = ro?.data?.total ?? ro?.total ?? '—';
    $('sDefects').textContent  = Array.isArray(d?.data) ? d.data.length : '—';
    $('sYarns').textContent    = Array.isArray(y?.data) ? y.data.length : '—';
    $('sSettings').textContent = Array.isArray(s?.data) ? s.data.length : '—';
  } catch(_) {}
}

// ── Users ────────────────────────────────────────────────────
let editUserId = null;
async function loadUsers(page=1) {
  $('tbUsers').innerHTML = skRows(5);
  const search = val('searchUsers'), role_ = val('filterRole');
  const url = `${BASE}/admin/users?page=${page}&search=${encodeURIComponent(search)}&role=${role_}`;
  try {
    const res = await fetch(url, {headers:auth()});
    if (res.status===401) { localStorage.clear(); location.replace('index.html'); return; }
    const j = await res.json();
    const list = j?.data?.data ?? j?.data ?? [];
    $('tbUsers').innerHTML = list.length ? list.map(u => `
      <tr>
        <td class="tc">${esc(u.name)}</td>
        <td class="tm">${esc(u.email)}</td>
        <td>${roleBadge(u.role)}</td>
        <td>${statusBadge(u.status)}</td>
        <td><div class="tbl-actions">
          <button class="btn-edit" onclick="openUserModal(${JSON.stringify(u).replace(/"/g,'&quot;')})">Edit</button>
          <button class="btn-del" onclick="confirmDelete('user',${u.id},'${esc(u.name)}')">Hapus</button>
        </div></td>
      </tr>`).join('') : `<tr><td colspan="5" class="state-box"><div class="si">👥</div><h3>Tidak ada user</h3></td></tr>`;
    $('pgUsers').innerHTML = pagerHtml(j?.data, 'loadUsers');
  } catch(_) { $('tbUsers').innerHTML = `<tr><td colspan="5" style="text-align:center;color:var(--danger);padding:2rem">Gagal memuat data</td></tr>`; }
}

function openUserModal(u=null) {
  editUserId = u?.id ?? null;
  $('muTitle').textContent = u ? 'Edit User' : 'Tambah User';
  $('muName').value  = u?.name  ?? '';
  $('muEmail').value = u?.email ?? '';
  $('muPw').value    = '';
  $('muRole').value  = u?.role   ?? 'qc';
  $('muStatus').value= u?.status ?? 'active';
  $('muPwHint').style.display = u ? 'inline' : 'none';
  showErr('muErr','');
  $('muSave').dataset.label = 'Simpan';
  showModal('modalUser');
}

async function saveUser() {
  const body = { name: val('muName'), email: val('muEmail'), role: val('muRole'), status: val('muStatus') };
  const pw = val('muPw'); if (pw) body.password = pw;
  if (!body.name || !body.email) { showErr('muErr','Nama dan email wajib diisi.'); return; }
  setBtn('muSave', true);
  try {
    const url    = editUserId ? `${BASE}/admin/users/${editUserId}` : `${BASE}/admin/users`;
    const method = editUserId ? 'PUT' : 'POST';
    const res = await fetch(url, {method, headers:auth(), body: JSON.stringify(body)});
    const j   = await res.json();
    if (!res.ok) { showErr('muErr', j?.message || 'Gagal menyimpan.'); return; }
    closeModal('modalUser'); toast(editUserId ? 'User diperbarui.' : 'User ditambahkan.', 'ok');
    loadUsers(); loadStats();
  } catch(_) { showErr('muErr','Terjadi kesalahan.'); }
  finally { setBtn('muSave', false); }
}

// ── Clients ──────────────────────────────────────────────────
let editClientId = null;
async function loadClients(page=1) {
  $('tbClients').innerHTML = skRows(6);
  const url = `${BASE}/admin/clients?page=${page}&search=${encodeURIComponent(val('searchClients'))}`;
  try {
    const res = await fetch(url, {headers:auth()});
    const j = await res.json();
    const list = j?.data?.data ?? j?.data ?? [];
    $('tbClients').innerHTML = list.length ? list.map(c => `
      <tr>
        <td>${esc(c.client_name)}</td>
        <td class="tm">${esc(c.contact_person||'—')}</td>
        <td>${c.inspection_requests_count ?? 0}</td>
        <td><div class="tbl-actions">
          <button class="btn-edit" onclick="openClientModal(${JSON.stringify(c).replace(/"/g,'&quot;')})">Edit</button>
          <button class="btn-del" onclick="confirmDelete('client',${c.id},'${esc(c.client_name)}')">Hapus</button>
        </div></td>
      </tr>`).join('') : `<tr><td colspan="4" class="state-box"><div class="si">🏢</div><h3>Tidak ada customer</h3></td></tr>`;
    $('pgClients').innerHTML = pagerHtml(j?.data, 'loadClients');
  } catch(_) {}
}

function openClientModal(c=null) {
  editClientId = c?.id ?? null;
  $('mcTitle').textContent  = c ? 'Edit Customer' : 'Tambah Customer';
  $('mcName').value         = c?.client_name    ?? '';
  $('mcCompany').value      = c?.company        ?? '';
  $('mcContact').value      = c?.contact_person ?? '';
  $('mcAddress').value      = c?.address        ?? '';
  showErr('mcErr','');
  showModal('modalClient');
}

async function saveClient() {
  const body = { 
    client_name: val('mcName'), 
    company: val('mcCompany'),
    contact_person: val('mcContact'), 
    address: val('mcAddress')||null 
  };
  if (!body.client_name) { showErr('mcErr','Nama customer wajib diisi.'); return; }
  if (!body.company)     { showErr('mcErr','Nama perusahaan wajib diisi.'); return; }
  setBtn('mcSave', true);
  try {
    const url    = editClientId ? `${BASE}/admin/clients/${editClientId}` : `${BASE}/admin/clients`;
    const method = editClientId ? 'PUT' : 'POST';
    const res = await fetch(url, {method, headers:auth(), body: JSON.stringify(body)});
    const j   = await res.json();
    if (!res.ok) { showErr('mcErr', j?.message || 'Gagal menyimpan.'); return; }
    closeModal('modalClient'); toast(editClientId ? 'Customer diperbarui.' : 'Customer ditambahkan.', 'ok');
    loadClients(); loadStats();
  } catch(_) { showErr('mcErr','Terjadi kesalahan.'); }
  finally { setBtn('mcSave', false); }
}

// ── Machines ─────────────────────────────────────────────────
let editMachineId = null;
async function loadMachines(page=1) {
  $('tbMachines').innerHTML = skRows(2);
  const url = `${BASE}/admin/machines?page=${page}&search=${encodeURIComponent(val('searchMachines'))}`;
  try {
    const res = await fetch(url, {headers:auth()});
    const j = await res.json();
    const list = j?.data?.data ?? j?.data ?? [];
    $('tbMachines').innerHTML = list.length ? list.map(m => `
      <tr>
        <td class="tc">${esc(m.machine_name)}</td>
        <td><div class="tbl-actions">
          <button class="btn-edit" onclick="openMachineModal(${JSON.stringify(m).replace(/"/g,'&quot;')})">Edit</button>
          <button class="btn-del" onclick="confirmDelete('machine',${m.id},'${esc(m.machine_name)}')">Hapus</button>
        </div></td>
      </tr>`).join('') : `<tr><td colspan="2" class="state-box"><div class="si">🏭</div><h3>Tidak ada mesin</h3></td></tr>`;
    $('pgMachines').innerHTML = pagerHtml(j?.data, 'loadMachines');
  } catch(_) {}
}

function openMachineModal(m=null) {
  editMachineId = m?.id ?? null;
  $('mmTitle').textContent = m ? 'Edit Mesin' : 'Tambah Mesin';
  $('mmName').value = m?.machine_name ?? '';
  showErr('mmErr','');
  showModal('modalMachine');
}

async function saveMachine() {
  const body = { machine_name: val('mmName') };
  if (!body.machine_name) { showErr('mmErr','Nama mesin wajib diisi.'); return; }
  setBtn('mmSave', true);
  try {
    const url    = editMachineId ? `${BASE}/admin/machines/${editMachineId}` : `${BASE}/admin/machines`;
    const method = editMachineId ? 'PUT' : 'POST';
    const res = await fetch(url, {method, headers:auth(), body: JSON.stringify(body)});
    const j   = await res.json();
    if (!res.ok) { showErr('mmErr', j?.message || 'Gagal menyimpan.'); return; }
    closeModal('modalMachine'); toast(editMachineId ? 'Mesin diperbarui.' : 'Mesin ditambahkan.', 'ok');
    loadMachines(); loadStats();
  } catch(_) { showErr('mmErr','Terjadi kesalahan.'); }
  finally { setBtn('mmSave', false); }
}

// ── Inspection Requests ──────────────────────────────────────
let editRequestId = null;
async function loadRequests(page=1) {
  $('tbRequests').innerHTML = skRows(5);
  const url = `${BASE}/admin/inspection-requests?page=${page}&search=${encodeURIComponent(val('searchReq'))}&status=${val('filterReqStatus')}`;
  try {
    const res = await fetch(url, {headers:auth()});
    const j = await res.json();
    const list = j?.data?.data ?? j?.data ?? [];
    $('tbRequests').innerHTML = list.length ? list.map(r => `
      <tr>
        <td class="tc" style="font-family:monospace">${esc(r.opk||'—')}</td>
        <td>${esc(r.client?.client_name||'—')}</td>
        <td>${esc(r.yarn_type?.yarn_name||'—')}</td>
        <td>${esc(r.setting?.setting_name||'—')}</td>
        <td class="tc" style="font-weight:600">${esc(r.gramasi||'—')}</td>
        <td>${pillHtml(r.status?.value ?? r.status)}</td>
        <td style="text-align:center">${r.fabric_rolls_count ?? '—'}</td>
        <td class="tm">${r.request_date ? new Date(r.request_date).toLocaleDateString('id-ID') : '—'}</td>
        <td>
          <button class="btn-edit" onclick="openRequestModal(${JSON.stringify(r).replace(/"/g,'&quot;')})">Edit</button>
        </td>
      </tr>`).join('') : `<tr><td colspan="9" class="state-box"><div class="si">📋</div><h3>Belum ada order</h3><p>Klik "+ Buat Order" untuk memulai</p></td></tr>`;
    $('pgRequests').innerHTML = pagerHtml(j?.data, 'loadRequests');
  } catch(_) {}
}

async function openRequestModal(r=null) {
  editRequestId = r?.id ?? null;
  showErr('mrErr','');
  $('mrTitle').textContent = r ? 'Edit Order' : 'Buat Order';
  $('mrSave').textContent = r ? 'Simpan Perubahan' : 'Buat Order';

  if (r) {
    $('mrTotalRow').style.display = 'none';
  } else {
    $('mrTotalRow').style.display = '';
  }

  $('mrClient').innerHTML  = '<option value="">Memuat…</option>';
  $('mrYarnType').innerHTML = '<option value="">Memuat…</option>';
  $('mrSetting').innerHTML = '<option value="">Memuat…</option>';
  $('mrGramasi').innerHTML = '<option value="">Memuat…</option>';
  $('mrTotal').value = '';
  $('mrOpk').value = r?.opk ?? '';
  $('mrNotes').value = r?.notes ?? '';
  showModal('modalRequest');

  try {
    const [clients, yarns, settings, gramasis] = await Promise.all([
      fetch(`${BASE}/admin/clients?per_page=100`, {headers:auth()}).then(r=>r.json()),
      fetch(`${BASE}/admin/yarn-types`, {headers:auth()}).then(r=>r.json()),
      fetch(`${BASE}/admin/settings`, {headers:auth()}).then(r=>r.json()),
      fetch(`${BASE}/admin/gramasis`, {headers:auth()}).then(r=>r.json())
    ]);
    const cl = clients?.data?.data ?? clients?.data ?? [];
    $('mrClient').innerHTML  = `<option value="">— Pilih Customer —</option>` + cl.map(c=>`<option value="${c.id}">${esc(c.client_name)}</option>`).join('');
    if (r?.client_id) $('mrClient').value = r.client_id;
    
    const yl = yarns?.data ?? yarns ?? [];
    $('mrYarnType').innerHTML = `<option value="">— Pilih Jenis Benang —</option>` + yl.map(y=>`<option value="${y.id}">${esc(y.yarn_name)}</option>`).join('');
    if (r?.yarn_type_id) $('mrYarnType').value = r.yarn_type_id;

    const sl = settings?.data ?? settings ?? [];
    $('mrSetting').innerHTML = `<option value="">— Pilih Setting —</option>` + sl.map(s=>`<option value="${s.id}">${esc(s.setting_name)}</option>`).join('');
    if (r?.setting_id) $('mrSetting').value = r.setting_id;

    const gl = gramasis?.data ?? gramasis ?? [];
    $('mrGramasi').innerHTML = `<option value="">— Pilih Gramasi —</option>` + gl.map(g=>`<option value="${esc(g.range)}">${esc(g.range)}</option>`).join('');
    if (r?.gramasi) $('mrGramasi').value = r.gramasi;
  } catch(_) { showErr('mrErr','Gagal memuat dropdown data.'); }
}

async function saveRequest() {
  const clientId = val('mrClient'), total = parseInt(val('mrTotal'), 10), opk = val('mrOpk'), yarnTypeId = val('mrYarnType'), settingId = val('mrSetting'), gramasi = val('mrGramasi');
  if (!clientId) { showErr('mrErr','Pilih customer terlebih dahulu.'); return; }
  if (!opk) { showErr('mrErr','OPK wajib diisi.'); return; }
  if (!editRequestId && (!total || total < 1)) { showErr('mrErr','Jumlah roll minimal 1.'); return; }

  const body = { 
    client_id: clientId, 
    yarn_type_id: yarnTypeId || null,
    setting_id: settingId || null,
    gramasi: gramasi || null,
    opk, 
    notes: val('mrNotes')||null 
  };
  
  if (!editRequestId) {
    body.total_roll = total;
  }

  setBtn('mrSave', true);
  try {
    const url = editRequestId ? `${BASE}/admin/inspection-requests/${editRequestId}` : `${BASE}/admin/inspection-requests`;
    const method = editRequestId ? 'PUT' : 'POST';
    const res = await fetch(url, {method, headers:auth(), body:JSON.stringify(body)});
    const j   = await res.json();
    if (!res.ok) { showErr('mrErr', j?.message || 'Gagal menyimpan order.'); return; }
    closeModal('modalRequest');
    toast(editRequestId ? 'Order diperbarui!' : `Order ${j?.data?.request_code} dibuat dengan ${total} roll!`, 'ok', 4000);
    loadRequests(); loadStats();
  } catch(_) { showErr('mrErr','Terjadi kesalahan server.'); }
  finally { setBtn('mrSave', false); }
}

// ── Fabric Rolls ─────────────────────────────────────────────
async function loadRolls(page=1) {
  $('tbRolls').innerHTML = skRows(8);
  const url = `${BASE}/admin/fabric-rolls?page=${page}&search=${encodeURIComponent(val('searchRolls'))}&opk=${encodeURIComponent(val('searchRollsOpk')||'')}&status=${val('filterRollStatus')}`;
  try {
    const res = await fetch(url, {headers:auth()});
    const j = await res.json();
    const list = j?.data?.data ?? j?.data ?? [];
    $('tbRolls').innerHTML = list.length ? list.map(r => {
      const insp = r.display_inspection ?? r.latest_inspection ?? r.inspection;
      const detailBtn = insp?.id
        ? `<button class="btn-edit" onclick="location.href='qc-detail.html?id=${insp.id}'">Detail</button>`
        : '—';
      
      // Calculate Netto (Berat Akhir)
      let beratAkhir = '—';
      if (insp && insp.weight_kg != null) {
        const bruto = parseFloat(insp.weight_kg);
        const p1 = parseFloat(insp.potongan_1_kg || 0);
        const p2 = parseFloat(insp.potongan_2_kg || 0);
        beratAkhir = (bruto - (p1 + p2)).toFixed(2) + ' kg';
      }

      return `<tr>
        <td class="tc" style="font-family:monospace">${esc(r.roll_code)}</td>
        <td class="tm">${esc(r.inspection_request?.opk||'—')}</td>
        <td class="tm">${esc(r.inspection_request?.client?.client_name||'—')}</td>
        <td class="tm">${esc(r.machine?.machine_name||'—')}</td>
        <td>${pillHtml(r.status)}</td>
        <td class="tm">${esc(insp?.operator?.name||'—')}</td>
        <td style="text-align:center;font-weight:700">${insp?.score != null ? insp.score : '—'}</td>
        <td style="text-align:center;font-weight:700;color:var(--accent)">${beratAkhir}</td>
        <td>${detailBtn}</td>
      </tr>`;
    }).join('') : `<tr><td colspan="9" class="state-box"><div class="si">🧵</div><h3>Tidak ada roll</h3></td></tr>`;
    $('pgRolls').innerHTML = pagerHtml(j?.data, 'loadRolls');
  } catch(_) {}
}

// ── Defect Types ─────────────────────────────────────────────
let editDefectId = null;
async function loadDefects() {
  $('tbDefects').innerHTML = skRows(4);
  try {
    const res = await fetch(`${BASE}/admin/defect-types`, {headers:auth()});
    const j   = await res.json();
    const list = j?.data ?? [];
    $('tbDefects').innerHTML = list.length ? list.map(d => `
      <tr>
        <td class="tc">${esc(d.defect_name)}</td>
        <td class="tm">${esc(d.category||'—')}</td>
        <td style="font-weight:700;color:var(--accent)">${d.default_point ?? d.point ?? '—'}</td>
        <td><div class="tbl-actions">
          <button class="btn-edit" onclick="openDefectModal(${JSON.stringify(d).replace(/"/g,'&quot;')})">Edit</button>
          <button class="btn-del" onclick="confirmDelete('defect',${d.id},'${esc(d.defect_name)}')">Hapus</button>
        </div></td>
      </tr>`).join('') : `<tr><td colspan="4" class="state-box"><div class="si">🔖</div><h3>Tidak ada defect type</h3></td></tr>`;
  } catch(_) {}
}

function openDefectModal(d=null) {
  editDefectId = d?.id ?? null;
  $('mdTitle').textContent = d ? 'Edit Defect Type' : 'Tambah Defect Type';
  $('mdName').value = d?.defect_name ?? '';
  $('mdCat').value  = d?.category    ?? '';
  $('mdPt').value   = d?.default_point ?? d?.point ?? '';
  showErr('mdErr','');
  showModal('modalDefect');
}

async function saveDefect() {
  const body = { defect_name: val('mdName'), category: val('mdCat')||null, default_point: parseInt(val('mdPt'))||1 };
  if (!body.defect_name) { showErr('mdErr','Nama cacat wajib diisi.'); return; }
  setBtn('mdSave', true);
  try {
    const url    = editDefectId ? `${BASE}/admin/defect-types/${editDefectId}` : `${BASE}/admin/defect-types`;
    const method = editDefectId ? 'PUT' : 'POST';
    const res = await fetch(url, {method, headers:auth(), body:JSON.stringify(body)});
    const j   = await res.json();
    if (!res.ok) { showErr('mdErr', j?.message || 'Gagal menyimpan.'); return; }
    closeModal('modalDefect'); toast('Defect type disimpan.','ok');
    loadDefects(); loadStats();
  } catch(_) { showErr('mdErr','Terjadi kesalahan.'); }
  finally { setBtn('mdSave', false); }
}

// ── Yarn Types ───────────────────────────────────────────────
let editYarnId = null;
let allYarns   = [];

async function loadYarns() {
  $('tbYarns').innerHTML = skRows(2);
  try {
    const res = await fetch(`${BASE}/admin/yarn-types`, {headers:auth()});
    const j   = await res.json();
    allYarns = j?.data ?? [];
    renderYarns();
  } catch(_) {
    $('tbYarns').innerHTML = `<tr><td colspan="2" style="text-align:center;color:var(--danger);padding:2rem">Gagal memuat data</td></tr>`;
  }
}

function renderYarns() {
  const q = val('searchYarns').toLowerCase();
  const list = q ? allYarns.filter(y =>
    y.yarn_name?.toLowerCase().includes(q)
  ) : allYarns;

  $('tbYarns').innerHTML = list.length ? list.map(y => `
    <tr>
      <td style="font-weight:600">${esc(y.yarn_name)}</td>
      <td><div class="tbl-actions">
        <button class="btn-edit" onclick="openYarnModal(${JSON.stringify(y).replace(/"/g,'&quot;')})">Edit</button>
        <button class="btn-del" onclick="confirmDelete('yarn',${y.id},'${esc(y.yarn_name)}')">Hapus</button>
      </div></td>
    </tr>`).join('')
  : `<tr><td colspan="2" class="state-box"><div class="si">🧶</div><h3>Belum ada jenis benang</h3><p>Klik "+ Tambah Jenis Benang" untuk memulai</p></td></tr>`;
}

function openYarnModal(y=null) {
  editYarnId = y?.id ?? null;
  $('myTitle').textContent   = y ? 'Edit Jenis Benang' : 'Tambah Jenis Benang';
  $('myName').value          = y?.yarn_name    ?? '';
  showErr('myErr','');
  $('mySave').dataset.label  = 'Simpan';
  showModal('modalYarn');
}

async function saveYarn() {
  const body = {
    yarn_name: val('myName'),
  };
  if (!body.yarn_name) { showErr('myErr','Nama benang wajib diisi.'); return; }
  setBtn('mySave', true);
  try {
    const url    = editYarnId ? `${BASE}/admin/yarn-types/${editYarnId}` : `${BASE}/admin/yarn-types`;
    const method = editYarnId ? 'PUT' : 'POST';
    const res = await fetch(url, {method, headers:auth(), body:JSON.stringify(body)});
    const j   = await res.json();
    if (!res.ok) { showErr('myErr', j?.message || 'Gagal menyimpan.'); return; }
    closeModal('modalYarn');
    toast(editYarnId ? 'Jenis benang diperbarui.' : 'Jenis benang ditambahkan.', 'ok');
    loadYarns(); loadStats();
  } catch(_) { showErr('myErr','Terjadi kesalahan.'); }
  finally { setBtn('mySave', false); }
}

// ── Master Settings ──────────────────────────────────────────
let editSettingId = null;
let allSettings   = [];

async function loadSettings() {
  $('tbSettings').innerHTML = skRows(3);
  try {
    const res = await fetch(`${BASE}/admin/settings`, {headers:auth()});
    const j   = await res.json();
    allSettings = j?.data ?? [];
    renderSettings();
  } catch(_) {
    $('tbSettings').innerHTML = `<tr><td colspan="3" style="text-align:center;color:var(--danger);padding:2rem">Gagal memuat data</td></tr>`;
  }
}

function renderSettings() {
  const q = val('searchSettings').toLowerCase();
  const list = q ? allSettings.filter(s =>
    (s.setting_name||'').toLowerCase().includes(q) ||
    (s.description||'').toLowerCase().includes(q)
  ) : allSettings;

  $('tbSettings').innerHTML = list.length ? list.map(s => `
    <tr>
      <td style="font-weight:600">${esc(s.setting_name)}</td>
      <td>${esc(s.description||'—')}</td>
      <td><div class="tbl-actions">
        <button class="btn-edit" onclick="openSettingModal(${JSON.stringify(s).replace(/"/g,'&quot;')})">Edit</button>
        <button class="btn-del" onclick="confirmDelete('setting',${s.id},'${esc(s.setting_name)}')">Hapus</button>
      </div></td>
    </tr>`).join('') : `<tr><td colspan="3" class="state-box"><div class="si">⚙️</div><h3>Tidak ada setting</h3></td></tr>`;
}

function openSettingModal(s=null) {
  editSettingId = s?.id ?? null;
  showErr('msErr', '');
  $('msTitle').textContent = editSettingId ? 'Edit Setting' : 'Tambah Setting';
  $('msName').value = s?.setting_name ?? '';
  $('msDesc').value = s?.description ?? '';
  showModal('modalSetting');
}

async function saveSetting() {
  const body = {
    setting_name: val('msName'),
    description:  val('msDesc') || null,
  };
  if (!body.setting_name) { showErr('msErr','Nama setting wajib diisi.'); return; }
  setBtn('msSave', true);
  try {
    const url    = editSettingId ? `${BASE}/admin/settings/${editSettingId}` : `${BASE}/admin/settings`;
    const method = editSettingId ? 'PUT' : 'POST';
    const res = await fetch(url, {method, headers:auth(), body:JSON.stringify(body)});
    const j   = await res.json();
    if (!res.ok) { showErr('msErr', j?.message || 'Gagal menyimpan.'); return; }
    closeModal('modalSetting');
    toast(editSettingId ? 'Setting diperbarui.' : 'Setting ditambahkan.', 'ok');
    loadSettings(); loadStats();
  } catch(_) { showErr('msErr','Terjadi kesalahan.'); }
  finally { setBtn('msSave', false); }
}

// ── Master Gramasi ───────────────────────────────────────────
let editGramasiId = null;
let allGramasis   = [];

async function loadGramasis() {
  $('tbGramasis').innerHTML = skRows(3);
  try {
    const res = await fetch(`${BASE}/admin/gramasis`, {headers:auth()});
    const j   = await res.json();
    allGramasis = j?.data ?? [];
    renderGramasis();
  } catch(_) {
    $('tbGramasis').innerHTML = `<tr><td colspan="3" style="text-align:center;color:var(--danger);padding:2rem">Gagal memuat data</td></tr>`;
  }
}

function renderGramasis() {
  const q = val('searchGramasis').toLowerCase();
  const list = q ? allGramasis.filter(g =>
    (g.range||'').toLowerCase().includes(q) ||
    (g.description||'').toLowerCase().includes(q)
  ) : allGramasis;

  $('tbGramasis').innerHTML = list.length ? list.map(g => `
    <tr>
      <td style="font-weight:600">${esc(g.range)}</td>
      <td>${esc(g.description||'—')}</td>
      <td><div class="tbl-actions">
        <button class="btn-edit" onclick="openGramasiModal(${JSON.stringify(g).replace(/"/g,'&quot;')})">Edit</button>
        <button class="btn-del" onclick="confirmDelete('gramasi',${g.id},'${esc(g.range)}')">Hapus</button>
      </div></td>
    </tr>`).join('') : `<tr><td colspan="3" class="state-box"><div class="si">⚖️</div><h3>Tidak ada range gramasi</h3></td></tr>`;
}

function openGramasiModal(g=null) {
  editGramasiId = g?.id ?? null;
  showErr('mgErr', '');
  $('mgTitle').textContent = editGramasiId ? 'Edit Gramasi' : 'Tambah Gramasi';
  $('mgRange').value = g?.range ?? '';
  $('mgDesc').value = g?.description ?? '';
  showModal('modalGramasi');
}

async function saveGramasi() {
  const body = {
    range: val('mgRange').replace(/\s+/g, ''),
    description:  val('mgDesc') || null,
  };
  if (!body.range) { showErr('mgErr','Range gramasi wajib diisi.'); return; }
  const regex = /^\d+(\.\d{1,2})?-\d+(\.\d{1,2})?$/;
  if (!regex.test(body.range)) { showErr('mgErr','Format harus min-maks (contoh: 140-145).'); return; }
  
  setBtn('mgSave', true);
  try {
    const url    = editGramasiId ? `${BASE}/admin/gramasis/${editGramasiId}` : `${BASE}/admin/gramasis`;
    const method = editGramasiId ? 'PUT' : 'POST';
    const res = await fetch(url, {method, headers:auth(), body:JSON.stringify(body)});
    const j   = await res.json();
    if (!res.ok) { showErr('mgErr', j?.message || 'Gagal menyimpan.'); return; }
    closeModal('modalGramasi');
    toast(editGramasiId ? 'Gramasi diperbarui.' : 'Gramasi ditambahkan.', 'ok');
    loadGramasis(); loadStats();
  } catch(_) { showErr('mgErr','Terjadi kesalahan.'); }
  finally { setBtn('mgSave', false); }
}

// ── Reassign operator ────────────────────────────────────────
let reassignRollId = null;

async function openReassignModal(rollId, rollCode) {
  reassignRollId = rollId;
  showErr('reassignErr', '');
  $('reassignRollLabel').textContent = `Roll: ${rollCode}`;
  $('reassignOperator').innerHTML = '<option value="">Memuat…</option>';
  showModal('modalReassign');
  try {
    const res = await fetch(`${BASE}/admin/users?role=qc&status=active&per_page=100`, {headers:auth()});
    const j = await res.json();
    const ops = j?.data?.data ?? j?.data ?? [];
    $('reassignOperator').innerHTML = '<option value="">— Pilih Operator —</option>' +
      ops.map(u => `<option value="${u.id}">${esc(u.name)}</option>`).join('');
  } catch(_) { showErr('reassignErr', 'Gagal memuat daftar operator.'); }
}

async function saveReassign() {
  const operatorId = val('reassignOperator');
  if (!operatorId) { showErr('reassignErr', 'Pilih operator.'); return; }
  if (!reassignRollId) return;
  setBtn('reassignSave', true);
  try {
    const res = await fetch(`${BASE}/admin/fabric-rolls/${reassignRollId}/reassign`, {
      method: 'PATCH', headers: auth(), body: JSON.stringify({ operator_id: parseInt(operatorId, 10) }),
    });
    const j = await res.json();
    if (!res.ok) { showErr('reassignErr', j?.message || 'Gagal mengubah operator.'); return; }
    closeModal('modalReassign');
    toast('Operator roll diperbarui.', 'ok');
    loadRolls();
  } catch(_) { showErr('reassignErr', 'Terjadi kesalahan server.'); }
  finally { setBtn('reassignSave', false); }
}

// ── Confirm Delete ───────────────────────────────────────────
const endpointMap = { user:'admin/users', client:'admin/clients', machine:'admin/machines', defect:'admin/defect-types', yarn:'admin/yarn-types', setting:'admin/settings', gramasi:'admin/gramasis' };
const reloadMap   = { user: loadUsers, client: loadClients, machine: loadMachines, defect: loadDefects, yarn: loadYarns, setting: loadSettings, gramasi: loadGramasis };

function confirmDelete(type, id, name) {
  $('confirmMsg').textContent = `Yakin ingin menghapus "${name}"? Tindakan ini tidak dapat dibatalkan.`;
  const btn = $('confirmOk');
  btn.onclick = async () => {
    btn.disabled = true; btn.textContent = '…';
    try {
      const res = await fetch(`${BASE}/${endpointMap[type]}/${id}`, {method:'DELETE', headers:auth()});
      const j   = await res.json();
      if (!res.ok) { toast(j?.message || 'Gagal menghapus.','err'); }
      else { toast('Data berhasil dihapus.','ok'); reloadMap[type]?.(); loadStats(); }
    } catch(_) { toast('Terjadi kesalahan.','err'); }
    finally { closeModal('modalConfirm'); btn.disabled=false; btn.textContent='Ya, Hapus'; }
  };
  showModal('modalConfirm');
}

// ── Logout ───────────────────────────────────────────────────
$('logoutBtn').addEventListener('click', async () => {
  try { await fetch(`${BASE}/logout`, {method:'POST', headers:auth()}); } catch(_) {}
  localStorage.clear(); location.replace('index.html');
});

// ── Quick Release ───────────────────────────────────────────
let pendingReleaseId = null;

function quickRelease(inspectionId) {
  pendingReleaseId = inspectionId;
  showModal('modalConfirmRelease');
}

$('btnDoRelease').addEventListener('click', async () => {
  if (!pendingReleaseId) return;
  setBtn('btnDoRelease', true);
  try {
    const res = await fetch(`${BASE}/admin/inspections/${pendingReleaseId}/release`, {
      method: 'POST',
      headers: auth()
    });
    const data = await res.json();
    if (!res.ok) {
      toast(data.message || 'Gagal merilis hasil inspeksi.', 'err');
      return;
    }
    toast('Roll berhasil dirilis ke customer!', 'ok');
    closeModal('modalConfirmRelease');
    loadFinalData();
    loadStats();
  } catch (_) {
    toast('Network error. Periksa koneksi Anda.', 'err');
  } finally {
    setBtn('btnDoRelease', false);
    pendingReleaseId = null;
  }
});

// ── Final Data (Validasi & Rilis) ──────────────────────────
async function loadFinalData() {
  $('tbPendingRelease').innerHTML = skRows(8);
  $('tbReleased').innerHTML = skRows(9);

  try {
    const [pendingRes, releasedRes] = await Promise.all([
      fetch(`${BASE}/admin/inspections/pending-release`, {headers:auth()}).then(r=>r.json()),
      fetch(`${BASE}/admin/inspections/released`, {headers:auth()}).then(r=>r.json())
    ]);

    const pendingList = pendingRes?.data?.data ?? pendingRes?.data ?? [];
    $('tbPendingRelease').innerHTML = pendingList.length ? pendingList.map(i => {
      const req = i.roll?.inspection_request ?? i.roll?.request ?? {};
      return `<tr>
        <td class="tc" style="font-family:monospace">${esc(i.roll?.roll_code)}</td>
        <td class="tm" style="font-weight:700">${i.manual_roll_number ?? '—'}</td>
        <td>${esc(req.client?.client_name||'—')}</td>
        <td>${esc(req.opk||'—')}</td>
        <td style="font-weight:700;color:var(--accent)">Grade ${esc(i.result)}</td>
        <td class="tm">${esc(i.operator?.name||'—')}</td>
        <td class="tm">${new Date(i.created_at).toLocaleDateString('id-ID')}</td>
        <td>
          <div class="tbl-actions">
            <button class="btn-val" onclick="quickRelease(${i.id})">✓ Kirim Ke Customer?</button>
            <button class="btn-edit" onclick="location.href='qc-detail.html?id=${i.id}'">🔍 Review</button>
          </div>
        </td>
      </tr>`;
    }).join('') : `<tr><td colspan="8" class="state-box"><div class="si">⏳</div><h3>Tidak ada antrean rilis</h3></td></tr>`;

    const releasedList = releasedRes?.data?.data ?? releasedRes?.data ?? [];
    $('tbReleased').innerHTML = releasedList.length ? releasedList.map(i => {
      const req = i.roll?.inspection_request ?? i.roll?.request ?? {};
      return `<tr>
        <td class="tc" style="font-family:monospace">${esc(i.roll?.roll_code)}</td>
        <td class="tm" style="font-weight:700">${i.manual_roll_number ?? '—'}</td>
        <td>${esc(req.client?.client_name||'—')}</td>
        <td>${esc(req.opk||'—')}</td>
        <td style="font-weight:700;color:var(--success)">Grade ${esc(i.result)}</td>
        <td class="tm">${esc(i.operator?.name||'—')}</td>
        <td class="tm">${esc(i.validator?.name||'—')}</td>
        <td class="tm">${new Date(i.updated_at).toLocaleDateString('id-ID')}</td>
        <td>
          <button class="btn-edit" onclick="location.href='qc-detail.html?id=${i.id}'">Detail</button>
        </td>
      </tr>`;
    }).join('') : `<tr><td colspan="9" class="state-box"><div class="si">✅</div><h3>Belum ada data rilis</h3></td></tr>`;

  } catch(_) {
    $('tbPendingRelease').innerHTML = '<tr><td colspan="8" style="text-align:center;color:var(--danger)">Gagal memuat data</td></tr>';
    $('tbReleased').innerHTML = '<tr><td colspan="9" style="text-align:center;color:var(--danger)">Gagal memuat data</td></tr>';
  }
}

// ── Init ─────────────────────────────────────────────────────
loadStats();
const hash = window.location.hash.replace('#', '');
if (hash && tabLoaders[hash]) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  const btn = document.querySelector(`.tab-btn[data-tab="${hash}"]`);
  if (btn) btn.classList.add('active');
  const panel = $(`tab-${hash}`);
  if (panel) panel.classList.add('active');
  tabLoaders[hash]();
} else {
  loadUsers();
}
