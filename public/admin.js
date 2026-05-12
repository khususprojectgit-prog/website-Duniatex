'use strict';
const BASE = 'http://127.0.0.1:8000/api';
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
  const map = { NEW:'sp-new', IN_PROGRESS:'sp-ip', SUBMITTED:'sp-sub',
    VALIDATED:'sp-val', PENDING:'sp-pnd', REJECTED:'sp-rej', COMPLETED:'sp-cmp' };
  return `<span class="status-pill ${map[s]||''}">${esc(s||'—')}</span>`;
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
const tabLoaders = { users: loadUsers, clients: loadClients, machines: loadMachines,
  requests: loadRequests, rolls: loadRolls, defects: loadDefects };

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
    const [u,c,m,r,ro,d] = await Promise.all([
      fetch(`${BASE}/admin/users?per_page=1`, {headers:auth()}).then(r=>r.json()),
      fetch(`${BASE}/admin/clients?per_page=1`, {headers:auth()}).then(r=>r.json()),
      fetch(`${BASE}/admin/machines?per_page=1`, {headers:auth()}).then(r=>r.json()),
      fetch(`${BASE}/admin/inspection-requests?per_page=1`, {headers:auth()}).then(r=>r.json()),
      fetch(`${BASE}/admin/fabric-rolls?per_page=1`, {headers:auth()}).then(r=>r.json()),
      fetch(`${BASE}/admin/defect-types`, {headers:auth()}).then(r=>r.json()),
    ]);
    $('sUsers').textContent    = u?.data?.total ?? u?.total ?? '—';
    $('sClients').textContent  = c?.data?.total ?? c?.total ?? '—';
    $('sMachines').textContent = m?.data?.total ?? m?.total ?? '—';
    $('sRequests').textContent = r?.data?.total ?? r?.total ?? '—';
    $('sRolls').textContent    = ro?.data?.total ?? ro?.total ?? '—';
    $('sDefects').textContent  = Array.isArray(d?.data) ? d.data.length : '—';
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
  $('muRole').value  = u?.role   ?? 'operator';
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
        <td class="tc">${esc(c.client_code||'—')}</td>
        <td>${esc(c.client_name)}</td>
        <td class="tm">${esc(c.contact_person||'—')}</td>
        <td class="tm">${esc(c.phone||'—')}</td>
        <td>${c.inspection_requests_count ?? 0}</td>
        <td><div class="tbl-actions">
          <button class="btn-edit" onclick="openClientModal(${JSON.stringify(c).replace(/"/g,'&quot;')})">Edit</button>
          <button class="btn-del" onclick="confirmDelete('client',${c.id},'${esc(c.client_name)}')">Hapus</button>
        </div></td>
      </tr>`).join('') : `<tr><td colspan="6" class="state-box"><div class="si">🏢</div><h3>Tidak ada client</h3></td></tr>`;
    $('pgClients').innerHTML = pagerHtml(j?.data, 'loadClients');
  } catch(_) {}
}

function openClientModal(c=null) {
  editClientId = c?.id ?? null;
  $('mcTitle').textContent  = c ? 'Edit Client' : 'Tambah Client';
  $('mcName').value         = c?.client_name    ?? '';
  $('mcCompany').value      = c?.company        ?? '';
  $('mcContact').value      = c?.contact_person ?? '';
  $('mcPhone').value        = c?.phone          ?? '';
  $('mcAddress').value      = c?.address        ?? '';
  showErr('mcErr','');
  showModal('modalClient');
}

async function saveClient() {
  const body = { client_name: val('mcName'), company: val('mcCompany'),
    contact_person: val('mcContact'), phone: val('mcPhone'), address: val('mcAddress')||null };
  if (!body.client_name) { showErr('mcErr','Nama client wajib diisi.'); return; }
  if (!body.company)     { showErr('mcErr','Nama perusahaan wajib diisi.'); return; }
  setBtn('mcSave', true);
  try {
    const url    = editClientId ? `${BASE}/admin/clients/${editClientId}` : `${BASE}/admin/clients`;
    const method = editClientId ? 'PUT' : 'POST';
    const res = await fetch(url, {method, headers:auth(), body: JSON.stringify(body)});
    const j   = await res.json();
    if (!res.ok) { showErr('mcErr', j?.message || 'Gagal menyimpan.'); return; }
    closeModal('modalClient'); toast(editClientId ? 'Client diperbarui.' : 'Client ditambahkan.', 'ok');
    loadClients(); loadStats();
  } catch(_) { showErr('mcErr','Terjadi kesalahan.'); }
  finally { setBtn('mcSave', false); }
}

// ── Machines ─────────────────────────────────────────────────
let editMachineId = null;
async function loadMachines(page=1) {
  $('tbMachines').innerHTML = skRows(4);
  const url = `${BASE}/admin/machines?page=${page}&search=${encodeURIComponent(val('searchMachines'))}`;
  try {
    const res = await fetch(url, {headers:auth()});
    const j = await res.json();
    const list = j?.data?.data ?? j?.data ?? [];
    $('tbMachines').innerHTML = list.length ? list.map(m => `
      <tr>
        <td class="tc">${esc(m.machine_name)}</td>
        <td class="tm">${esc(m.machine_type||'—')}</td>
        <td class="tm">${esc(m.location||'—')}</td>
        <td><div class="tbl-actions">
          <button class="btn-edit" onclick="openMachineModal(${JSON.stringify(m).replace(/"/g,'&quot;')})">Edit</button>
          <button class="btn-del" onclick="confirmDelete('machine',${m.id},'${esc(m.machine_name)}')">Hapus</button>
        </div></td>
      </tr>`).join('') : `<tr><td colspan="4" class="state-box"><div class="si">🏭</div><h3>Tidak ada mesin</h3></td></tr>`;
    $('pgMachines').innerHTML = pagerHtml(j?.data, 'loadMachines');
  } catch(_) {}
}

function openMachineModal(m=null) {
  editMachineId = m?.id ?? null;
  $('mmTitle').textContent = m ? 'Edit Mesin' : 'Tambah Mesin';
  $('mmName').value = m?.machine_name ?? '';
  $('mmType').value = m?.machine_type ?? '';
  $('mmLoc').value  = m?.location     ?? '';
  showErr('mmErr','');
  showModal('modalMachine');
}

async function saveMachine() {
  const body = { machine_name: val('mmName'), machine_type: val('mmType'), location: val('mmLoc')||null };
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
async function loadRequests(page=1) {
  $('tbRequests').innerHTML = skRows(6);
  const url = `${BASE}/admin/inspection-requests?page=${page}&search=${encodeURIComponent(val('searchReq'))}&status=${val('filterReqStatus')}`;
  try {
    const res = await fetch(url, {headers:auth()});
    const j = await res.json();
    const list = j?.data?.data ?? j?.data ?? [];
    $('tbRequests').innerHTML = list.length ? list.map(r => `
      <tr>
        <td class="tc" style="font-family:monospace">${esc(r.request_code)}</td>
        <td>${esc(r.client?.client_name||'—')}</td>
        <td>${pillHtml(r.status?.value ?? r.status)}</td>
        <td style="text-align:center">${r.fabric_rolls_count ?? '—'}</td>
        <td class="tm">${esc(r.qc?.name||'—')}</td>
        <td class="tm">${r.request_date ? new Date(r.request_date).toLocaleDateString('id-ID') : '—'}</td>
      </tr>`).join('') : `<tr><td colspan="6" class="state-box"><div class="si">📋</div><h3>Belum ada request</h3><p>Klik "+ Buat Request" untuk memulai</p></td></tr>`;
    $('pgRequests').innerHTML = pagerHtml(j?.data, 'loadRequests');
  } catch(_) {}
}

async function openRequestModal() {
  showErr('mrErr','');
  $('mrClient').innerHTML  = '<option value="">Memuat…</option>';
  $('mrQC').innerHTML      = '<option value="">Memuat…</option>';
  $('mrMachine').innerHTML = '<option value="">Memuat…</option>';
  $('mrTotal').value = ''; $('mrLength').value = ''; $('mrBatch').value = ''; $('mrNotes').value = '';
  showModal('modalRequest');
  try {
    const [clients, users, machines] = await Promise.all([
      fetch(`${BASE}/admin/clients?per_page=100`, {headers:auth()}).then(r=>r.json()),
      fetch(`${BASE}/admin/users?role=qc&per_page=100`, {headers:auth()}).then(r=>r.json()),
      fetch(`${BASE}/admin/machines?per_page=100`, {headers:auth()}).then(r=>r.json()),
    ]);
    const cl = clients?.data?.data ?? clients?.data ?? [];
    const us = users?.data?.data   ?? users?.data   ?? [];
    const mc = machines?.data?.data?? machines?.data ?? [];
    $('mrClient').innerHTML  = `<option value="">— Pilih Client —</option>` + cl.map(c=>`<option value="${c.id}">${esc(c.client_name)}</option>`).join('');
    $('mrQC').innerHTML      = `<option value="">— Opsional —</option>` + us.map(u=>`<option value="${u.id}">${esc(u.name)}</option>`).join('');
    $('mrMachine').innerHTML = `<option value="">— Opsional —</option>` + mc.map(m=>`<option value="${m.id}">${esc(m.machine_name)}</option>`).join('');
  } catch(_) { showErr('mrErr','Gagal memuat dropdown data.'); }
}

async function saveRequest() {
  const clientId = val('mrClient'), total = parseInt(val('mrTotal')), length = parseFloat(val('mrLength'));
  if (!clientId) { showErr('mrErr','Pilih client terlebih dahulu.'); return; }
  if (!total || total < 1) { showErr('mrErr','Jumlah roll minimal 1.'); return; }
  if (!length || length < 0.1) { showErr('mrErr','Panjang roll wajib diisi.'); return; }
  const body = { client_id: clientId, total_roll: total, length_meter: length,
    qc_id: val('mrQC')||null, machine_id: val('mrMachine')||null,
    batch_number: val('mrBatch')||null, notes: val('mrNotes')||null };
  setBtn('mrSave', true);
  try {
    const res = await fetch(`${BASE}/admin/inspection-requests`, {method:'POST', headers:auth(), body:JSON.stringify(body)});
    const j   = await res.json();
    if (!res.ok) { showErr('mrErr', j?.message || 'Gagal membuat request.'); return; }
    closeModal('modalRequest');
    toast(`Request ${j?.data?.request_code} dibuat dengan ${total} roll!`, 'ok', 4000);
    loadRequests(); loadStats();
  } catch(_) { showErr('mrErr','Terjadi kesalahan server.'); }
  finally { setBtn('mrSave', false); }
}

// ── Fabric Rolls ─────────────────────────────────────────────
async function loadRolls(page=1) {
  $('tbRolls').innerHTML = skRows(8);
  const url = `${BASE}/admin/fabric-rolls?page=${page}&search=${encodeURIComponent(val('searchRolls'))}&status=${val('filterRollStatus')}`;
  try {
    const res = await fetch(url, {headers:auth()});
    const j = await res.json();
    const list = j?.data?.data ?? j?.data ?? [];
    $('tbRolls').innerHTML = list.length ? list.map(r => {
      const insp = r.inspection;
      const result = insp?.result;
      const resBadge = result === 'PASS' ? `<span class="res-pass">✓ PASS</span>` : result === 'FAIL' ? `<span class="res-fail">✗ FAIL</span>` : `<span style="color:var(--muted)">—</span>`;
      return `<tr>
        <td class="tc" style="font-family:monospace">${esc(r.roll_code)}</td>
        <td class="tm">${esc(r.inspection_request?.request_code||'—')}</td>
        <td class="tm">${esc(r.inspection_request?.client?.client_name||'—')}</td>
        <td class="tm">${esc(r.machine?.machine_name||'—')}</td>
        <td>${pillHtml(r.status)}</td>
        <td class="tm">${esc(insp?.operator?.name||'—')}</td>
        <td style="text-align:center;font-weight:700">${insp?.score != null ? insp.score : '—'}</td>
        <td>${resBadge}</td>
      </tr>`;
    }).join('') : `<tr><td colspan="8" class="state-box"><div class="si">🧵</div><h3>Tidak ada roll</h3></td></tr>`;
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

// ── Confirm Delete ───────────────────────────────────────────
const endpointMap = { user:'admin/users', client:'admin/clients', machine:'admin/machines', defect:'admin/defect-types' };
const reloadMap   = { user: loadUsers, client: loadClients, machine: loadMachines, defect: loadDefects };

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

// ── Init ─────────────────────────────────────────────────────
loadStats();
loadUsers();
