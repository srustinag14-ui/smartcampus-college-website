const $ = (s, r = document) => r.querySelector(s);
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const fmt = d => new Date(d.replace(' ', 'T')).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
const today = () => new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);
const say = (el, text, ok) => { el.textContent = text; el.className = ok ? 'ok' : 'err'; };
let me = null, pub = null;

async function api(a, { body, query = '' } = {}) {
  const opt = { method: body ? 'POST' : 'GET', credentials: 'same-origin' };
  if (body instanceof FormData) opt.body = body;
  else if (body) { opt.headers = { 'Content-Type': 'application/json' }; opt.body = JSON.stringify(body); }
  const res = await fetch(`api.php?a=${a}${query}`, opt);
  const data = await res.json().catch(() => ({ error: 'The server did not answer correctly. Check that Apache and MySQL are running in XAMPP.' }));
  if (!res.ok) throw new Error(data.error || 'Something went wrong. Try again.');
  return data;
}
const table = (head, rows) => `<div class="table-wrap"><table><thead><tr>${head.map(h => `<th>${esc(h)}</th>`).join('')}</tr></thead><tbody>${
  rows.length ? rows.map(r => `<tr>${r.map(c => `<td>${c}</td>`).join('')}</tr>`).join('') : `<tr><td colspan="${head.length}" class="muted">Nothing here yet.</td></tr>`}</tbody></table></div>`;
const noticeList = list => list.length ? list.map(n => `<li><b>${esc(n.title)}</b>${esc(n.body)}<br><time>${fmt(n.posted_at)}</time></li>`).join('') : '<li class="muted">No notices right now.</li>';

// ---------- Navigation ----------
const nav = $('#nav'), burger = $('.burger');
burger.onclick = () => burger.setAttribute('aria-expanded', nav.classList.toggle('open'));
nav.addEventListener('click', e => { if (e.target.tagName === 'A') { nav.classList.remove('open'); burger.setAttribute('aria-expanded', 'false'); } });

// ---------- Public site ----------
function showNotices(cat) {
  const list = pub.announcements.filter(n => cat === 'exam' ? n.category === 'exam' : n.category !== 'exam');
  $('#notices').innerHTML = noticeList(list);
}
document.querySelectorAll('.tabs button[data-cat]').forEach(b => b.onclick = () => {
  document.querySelectorAll('.tabs button[data-cat]').forEach(x => x.classList.toggle('on', x === b));
  showNotices(b.dataset.cat);
});
function renderPublic(d) {
  pub = d; showNotices('news');
  $('#courseRows').innerHTML = d.courses.map(c => `<tr><td>${esc(c.name)}</td><td>${esc(c.level)}</td><td>${esc(c.dept)}</td><td>${esc(c.duration)}</td><td>${c.seats}</td></tr>`).join('');
  const chips = $('#deptChips');
  chips.innerHTML = '<button class="on" data-id="">All</button>' + d.departments.map(x => `<button data-id="${x.id}">${esc(x.name)}</button>`).join('');
  const faculty = id => {
    const list = d.faculty.filter(f => !id || String(f.dept_id) === id);
    $('#facultyGrid').innerHTML = list.map(f => `<article class="card"><h3>${esc(f.name)}</h3><p>${esc(f.designation)}, ${esc(f.dept)}</p><a href="mailto:${esc(f.email)}">${esc(f.email)}</a></article>`).join('') || '<p class="muted">No faculty listed.</p>';
  };
  chips.onclick = e => {
    if (e.target.tagName !== 'BUTTON') return;
    chips.querySelectorAll('button').forEach(b => b.classList.toggle('on', b === e.target));
    const x = d.departments.find(z => String(z.id) === e.target.dataset.id);
    $('#deptAbout').textContent = x ? `${x.about} Head of department: ${x.hod}.` : '';
    faculty(e.target.dataset.id);
  };
  faculty('');
  $('#eventList').innerHTML = d.events.length ? d.events.map(ev => `<article class="card"><div class="date">${fmt(ev.event_date)}</div><h3>${esc(ev.title)}</h3><p>${esc(ev.description)}</p><p class="muted">${esc(ev.venue)}</p></article>`).join('') : '<p class="muted">No upcoming events. Check back soon.</p>';
  const total = d.placements.reduce((a, r) => a + +r.students_placed, 0), top = Math.max(0, ...d.placements.map(r => +r.package_lpa));
  $('#stats').innerHTML = `<div><strong>${total}</strong>students placed</div><div><strong>${top.toFixed(1)} LPA</strong>highest package</div><div><strong>${d.placements.length}</strong>recruiters</div>`;
  $('#placeRows').innerHTML = d.placements.map(r => `<tr><td>${esc(r.company)}</td><td>${esc(r.role_title)}</td><td>${(+r.package_lpa).toFixed(2)}</td><td>${r.students_placed}</td></tr>`).join('');
}
$('#enquiryForm').onsubmit = async e => {
  e.preventDefault();
  try { await api('enquiry', { body: Object.fromEntries(new FormData(e.target)) }); say($('#enquiryMsg'), 'Thanks. The college office will reply by email.', true); e.target.reset(); }
  catch (err) { say($('#enquiryMsg'), err.message); }
};

// ---------- Login / logout ----------
const dlg = $('#loginDlg');
$('#portalBtn').onclick = async () => {
  if (!me) return dlg.showModal();
  await api('logout', { body: {} }); me = null; leaveDash();
};
$('.close').onclick = () => dlg.close();
$('#loginForm').onsubmit = async e => {
  e.preventDefault();
  try { me = await api('login', { body: Object.fromEntries(new FormData(e.target)) }); e.target.reset(); say($('#loginMsg'), ''); dlg.close(); enterDash(); }
  catch (err) { say($('#loginMsg'), err.message); }
};
function leaveDash() { document.body.classList.remove('in-dash'); $('#site').hidden = false; $('#dash').hidden = true; $('#portalBtn').textContent = 'Log in'; window.scrollTo(0, 0); }
function enterDash() {
  document.body.classList.add('in-dash'); $('#site').hidden = true; $('#dash').hidden = false; $('#portalBtn').textContent = 'Log out';
  $('#dashTitle').textContent = `Hello, ${me.name}`; window.scrollTo(0, 0);
  ({ student: studentView, faculty: facultyView, admin: adminView })[me.role]();
}
const guard = async fn => { try { await fn(); } catch (e) { if (/log in/i.test(e.message)) { me = null; leaveDash(); } else $('#dashBody').innerHTML = `<p class="err">${esc(e.message)}</p>`; } };

// ---------- Student ----------
const studentView = () => guard(async () => {
  const d = await api('student'), p = d.profile;
  $('#dashBody').innerHTML = `<div class="grid"><article class="card"><h3>${esc(p.name)}</h3><p>Roll no. ${esc(p.roll_no)}</p><p>${esc(p.course)}, semester ${p.semester}</p><p class="muted">${esc(p.dept)}<br>${esc(p.email)}</p></article></div>
  <h3>Attendance</h3>${table(['Subject', 'Present', 'Total', 'Percent'], d.attendance.map(a => { const pc = Math.round(100 * a.present / a.total); return [esc(a.subject), a.present, a.total, pc < 75 ? `<span class="low">${pc}% (below 75%)</span>` : pc + '%']; }))}
  <h3>Results</h3>${table(['Subject', 'Exam', 'Marks'], d.results.map(r => [esc(r.subject), esc(r.exam), `${+r.marks} / ${r.max_marks}`]))}
  <h3>Timetable</h3>${table(['Day', 'Time', 'Subject'], d.timetable.map(t => [t.day, esc(t.time_slot), esc(t.subject)]))}
  <h3>Study materials</h3><ul class="notices mat">${d.materials.map(m => `<li><a href="${esc(m.file_path)}" target="_blank" rel="noopener">${esc(m.title)}</a><br><small>${esc(m.subject)}, ${fmt(m.created_at)}</small></li>`).join('') || '<li class="muted">No materials uploaded yet.</li>'}</ul>
  <h3>Announcements</h3><ul class="notices">${noticeList(pub.announcements)}</ul>`;
});

// ---------- Faculty ----------
const facultyView = () => guard(async () => {
  const subs = await api('fac_subjects');
  if (!subs.length) { $('#dashBody').innerHTML = '<p class="muted">No subjects are assigned to you yet. Ask the administrator to assign subjects.</p>'; return; }
  const opts = subs.map(s => `<option value="${s.id}">${esc(s.name)} (${esc(s.course)}, sem ${s.semester})</option>`).join('');
  $('#dashBody').innerHTML = `<h3>Attendance and marks</h3>
  <div class="row"><label>Subject<select id="fSub">${opts}</select></label><label>Date<input type="date" id="fDate" value="${today()}"></label>
  <label>Exam<input id="fExam" value="Internal 1"></label><label>Max marks<input type="number" id="fMax" value="50" min="1"></label><button class="btn small" id="fLoad">Load students</button></div>
  <div id="fClass"></div>
  <h3>Upload study material</h3>
  <form id="matForm" class="form wide"><label>Subject<select name="subject_id">${opts}</select></label><label>Title<input name="title" required maxlength="160"></label>
  <label>File (PDF, Word, PowerPoint, text or zip, up to 5 MB)<input type="file" name="file" required></label><button class="btn small">Upload</button><p id="matMsg" role="status"></p></form>
  <h3>Publish academic information</h3>
  <form id="annForm" class="form"><label>Title<input name="title" required maxlength="160"></label><label>Details<textarea name="body" rows="3"></textarea></label><button class="btn small">Publish</button><p id="annMsg" role="status"></p></form>`;

  $('#fLoad').onclick = () => guard(async () => {
    const rows = await api('fac_students', { query: `&subject_id=${$('#fSub').value}&date=${$('#fDate').value}&exam=${encodeURIComponent($('#fExam').value)}` });
    $('#fClass').innerHTML = table(['Roll no.', 'Name', 'Attendance', 'Marks'], rows.map(r => [esc(r.roll_no), esc(r.name),
      `<label><input type="radio" name="att${r.id}" value="P" ${r.status !== 'A' ? 'checked' : ''}> Present</label><label><input type="radio" name="att${r.id}" value="A" ${r.status === 'A' ? 'checked' : ''}> Absent</label>`,
      `<input type="number" step="0.5" min="0" data-id="${r.id}" value="${r.marks ?? ''}">`]))
      + `<p><button class="btn small" id="saveAtt">Save attendance</button> <button class="btn ghost small" id="saveMarks">Save marks</button></p><p id="fMsg" role="status"></p>`;
    const ids = rows.map(r => r.id);
    $('#saveAtt').onclick = async () => { try {
      await api('mark_attendance', { body: { subject_id: $('#fSub').value, date: $('#fDate').value, records: ids.map(id => ({ student_id: id, status: document.querySelector(`input[name=att${id}]:checked`).value })) } });
      say($('#fMsg'), 'Attendance saved.', true); } catch (e) { say($('#fMsg'), e.message); } };
    $('#saveMarks').onclick = async () => { try {
      await api('enter_marks', { body: { subject_id: $('#fSub').value, exam: $('#fExam').value, max_marks: $('#fMax').value, records: [...document.querySelectorAll('#fClass input[data-id]')].filter(i => i.value !== '').map(i => ({ student_id: i.dataset.id, marks: i.value })) } });
      say($('#fMsg'), 'Marks saved.', true); } catch (e) { say($('#fMsg'), e.message); } };
  });
  $('#matForm').onsubmit = async e => { e.preventDefault(); try { await api('upload_material', { body: new FormData(e.target) }); say($('#matMsg'), 'Uploaded. Students can now download it.', true); e.target.reset(); } catch (er) { say($('#matMsg'), er.message); } };
  $('#annForm').onsubmit = async e => { e.preventDefault(); try { await api('announce', { body: Object.fromEntries(new FormData(e.target)) }); say($('#annMsg'), 'Published.', true); e.target.reset(); } catch (er) { say($('#annMsg'), er.message); } };
});

// ---------- Admin ----------
const AD = {
  users: ['name', 'email', 'password', 'role', 'roll_no', 'course_id', 'semester', 'dept_id', 'designation'],
  departments: ['code', 'name', 'about', 'hod'], courses: ['dept_id', 'name', 'level', 'duration', 'seats'],
  subjects: ['course_id', 'name', 'semester', 'faculty_id'], timetable: ['course_id', 'semester', 'day', 'time_slot', 'subject_id'],
  events: ['title', 'description', 'event_date', 'venue'], placements: ['company', 'role_title', 'package_lpa', 'students_placed', 'year'],
  announcements: ['title', 'body', 'category'], enquiries: []
};
const SEL = { role: ['student', 'faculty'], level: ['Undergraduate', 'Postgraduate'], category: ['general', 'academic', 'exam'], day: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] };
const field = f => {
  const label = f.replace(/_/g, ' ');
  if (SEL[f]) return `<label>${label}<select name="${f}">${SEL[f].map(o => `<option>${o}</option>`).join('')}</select></label>`;
  const type = f === 'password' ? 'password' : f === 'event_date' ? 'date' : /(_id|semester|seats|year|placed|lpa)$/.test(f) ? 'number" step="any' : 'text';
  return `<label>${label}<input name="${f}" type="${type}"></label>`;
};
const adminView = () => guard(async () => {
  $('#dashBody').innerHTML = `<div class="tabs wrap">${Object.keys(AD).map(t => `<button data-t="${t}">${t}</button>`).join('')}</div><div id="aPanel"></div>`;
  const load = async t => {
    document.querySelectorAll('#dashBody .tabs button').forEach(b => b.classList.toggle('on', b.dataset.t === t));
    const rows = await api('list', { query: '&t=' + t });
    const cols = rows[0] ? Object.keys(rows[0]) : [];
    $('#aPanel').innerHTML = (AD[t].length ? `<h3>Add to ${t}</h3><p class="muted">Use the id numbers from the departments, courses, subjects and users tables where an id is asked for.</p><form id="aForm" class="form wide">${AD[t].map(field).join('')}<button class="btn small">Add</button></form><p id="aMsg" role="status"></p>` : '')
      + `<h3>${t}</h3>` + table([...cols, ''], rows.map(r => [...cols.map(c => esc(String(r[c] ?? '').slice(0, 60))), `<button class="btn ghost small" data-del="${r.id}">Delete</button>`]));
    const f = $('#aForm');
    if (f) f.onsubmit = async e => { e.preventDefault(); try { await api('save', { body: { t, data: Object.fromEntries(new FormData(f)) } }); load(t); } catch (er) { say($('#aMsg'), er.message); } };
    $('#aPanel').querySelectorAll('[data-del]').forEach(b => b.onclick = async () => {
      if (!confirm('Delete this record? This cannot be undone.')) return;
      try { await api('del', { body: { t, id: b.dataset.del } }); load(t); } catch (er) { alert(er.message); }
    });
  };
  $('#dashBody .tabs').onclick = e => { if (e.target.dataset.t) guard(() => load(e.target.dataset.t)); };
  load('users');
});

// ---------- Start ----------
(async () => {
  try { renderPublic(await api('public')); }
  catch (e) { $('#notices').innerHTML = `<li class="err">${esc(e.message)}</li>`; }
  try { me = await api('me'); if (me) enterDash(); } catch {}
})();
