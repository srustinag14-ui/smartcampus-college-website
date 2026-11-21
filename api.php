<?php
// Thin API: the browser's JavaScript cannot talk to MySQL directly, so XAMPP's PHP does it here.
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();
header('Content-Type: application/json; charset=utf-8');

function out($d, $code = 200) { http_response_code($code); echo json_encode($d); exit; }
set_exception_handler(fn($e) => out(['error' => 'Could not complete this. Check the values you entered, and that MySQL is running in XAMPP.'], 500));

require __DIR__ . '/db.php';
function q($sql, $p = []) { global $pdo; $s = $pdo->prepare($sql); $s->execute($p); return $s; }
function rows($sql, $p = []) { return q($sql, $p)->fetchAll(); }
function need(...$roles) {
  if (empty($_SESSION['u'])) out(['error' => 'Please log in first'], 401);
  if ($roles && !in_array($_SESSION['u']['role'], $roles)) out(['error' => 'You do not have access to this'], 403);
  return $_SESSION['u'];
}
function owns($sid, $uid) { return (bool) q('SELECT 1 FROM subjects WHERE id=? AND faculty_id=?', [$sid, $uid])->fetch(); }
function classStudents($sid) {
  return rows("SELECT u.id,u.name,u.roll_no FROM users u JOIN subjects s ON s.course_id=u.course_id AND s.semester=u.semester
               WHERE s.id=? AND u.role='student' ORDER BY u.roll_no", [$sid]);
}

$a = $_GET['a'] ?? '';
$in = json_decode(file_get_contents('php://input'), true) ?: [];

// Tables the admin module may manage, with the columns it may write
$T = [
  'departments' => ['code', 'name', 'about', 'hod'],
  'courses' => ['dept_id', 'name', 'level', 'duration', 'seats'],
  'subjects' => ['course_id', 'name', 'semester', 'faculty_id'],
  'timetable' => ['course_id', 'semester', 'day', 'time_slot', 'subject_id'],
  'events' => ['title', 'description', 'event_date', 'venue'],
  'placements' => ['company', 'role_title', 'package_lpa', 'students_placed', 'year'],
  'announcements' => ['title', 'body', 'category'],
];
$USER_COLS = ['name', 'email', 'password', 'role', 'roll_no', 'course_id', 'semester', 'dept_id', 'designation'];

switch ($a) {
  // ---------- Visitors ----------
  case 'public':
    out([
      'announcements' => rows('SELECT id,title,body,category,posted_at FROM announcements ORDER BY posted_at DESC LIMIT 30'),
      'events' => rows('SELECT * FROM events WHERE event_date>=CURDATE() ORDER BY event_date'),
      'departments' => rows('SELECT * FROM departments'),
      'courses' => rows('SELECT c.*,d.code dept FROM courses c JOIN departments d ON d.id=c.dept_id ORDER BY c.level DESC,c.name'),
      'faculty' => rows("SELECT u.id,u.name,u.designation,u.dept_id,d.code dept,u.email FROM users u JOIN departments d ON d.id=u.dept_id WHERE u.role='faculty' ORDER BY u.name"),
      'placements' => rows('SELECT * FROM placements ORDER BY package_lpa DESC'),
    ]);

  case 'enquiry':
    $n = trim($in['name'] ?? ''); $e = trim($in['email'] ?? ''); $m = trim($in['message'] ?? '');
    if (!$n || !filter_var($e, FILTER_VALIDATE_EMAIL) || !$m) out(['error' => 'Enter your name, a valid email and a message'], 400);
    q('INSERT INTO enquiries (name,email,message) VALUES (?,?,?)', [substr($n, 0, 100), substr($e, 0, 120), substr($m, 0, 2000)]);
    out(['ok' => 1]);

  // ---------- Session ----------
  case 'login':
    $u = q('SELECT * FROM users WHERE email=?', [$in['email'] ?? ''])->fetch();
    if (!$u || !password_verify($in['password'] ?? '', $u['password_hash'])) out(['error' => 'Email or password is incorrect'], 401);
    session_regenerate_id(true);
    $_SESSION['u'] = ['id' => (int) $u['id'], 'name' => $u['name'], 'role' => $u['role']];
    out($_SESSION['u']);
  case 'me': out($_SESSION['u'] ?? null);
  case 'logout': session_destroy(); out(['ok' => 1]);

  // ---------- Student ----------
  case 'student':
    $u = need('student');
    $p = q('SELECT u.name,u.email,u.roll_no,u.semester,u.course_id,c.name course,d.name dept FROM users u
            JOIN courses c ON c.id=u.course_id JOIN departments d ON d.id=c.dept_id WHERE u.id=?', [$u['id']])->fetch();
    out([
      'profile' => $p,
      'attendance' => rows('SELECT s.name subject,SUM(a.status="P") present,COUNT(*) total FROM attendance a JOIN subjects s ON s.id=a.subject_id
                            WHERE a.student_id=? GROUP BY s.id,s.name', [$u['id']]),
      'results' => rows('SELECT s.name subject,r.exam,r.marks,r.max_marks FROM results r JOIN subjects s ON s.id=r.subject_id
                         WHERE r.student_id=? ORDER BY r.exam,s.name', [$u['id']]),
      'timetable' => rows("SELECT t.day,t.time_slot,s.name subject FROM timetable t JOIN subjects s ON s.id=t.subject_id
                           WHERE t.course_id=? AND t.semester=? ORDER BY FIELD(t.day,'Mon','Tue','Wed','Thu','Fri','Sat'),t.time_slot", [$p['course_id'], $p['semester']]),
      'materials' => rows('SELECT m.title,m.file_path,m.created_at,s.name subject FROM materials m JOIN subjects s ON s.id=m.subject_id
                           WHERE s.course_id=? AND s.semester=? ORDER BY m.created_at DESC', [$p['course_id'], $p['semester']]),
    ]);

  // ---------- Faculty ----------
  case 'fac_subjects':
    $u = need('faculty');
    out(rows('SELECT s.id,s.name,s.semester,c.name course FROM subjects s JOIN courses c ON c.id=s.course_id WHERE s.faculty_id=? ORDER BY c.name,s.semester', [$u['id']]));

  case 'fac_students':
    $u = need('faculty'); $sid = (int) ($_GET['subject_id'] ?? 0);
    if (!owns($sid, $u['id'])) out(['error' => 'This subject is not assigned to you'], 403);
    $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'] ?? '') ? $_GET['date'] : date('Y-m-d');
    out(rows('SELECT u.id,u.name,u.roll_no,a.status,r.marks FROM users u JOIN subjects s ON s.course_id=u.course_id AND s.semester=u.semester
              LEFT JOIN attendance a ON a.student_id=u.id AND a.subject_id=s.id AND a.att_date=?
              LEFT JOIN results r ON r.student_id=u.id AND r.subject_id=s.id AND r.exam=?
              WHERE s.id=? AND u.role="student" ORDER BY u.roll_no', [$date, $_GET['exam'] ?? 'Internal 1', $sid]));

  case 'mark_attendance':
    $u = need('faculty'); $sid = (int) ($in['subject_id'] ?? 0);
    if (!owns($sid, $u['id']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $in['date'] ?? '')) out(['error' => 'Choose one of your subjects and a date'], 400);
    $ok = array_column(classStudents($sid), 'id');
    $st = $pdo->prepare('INSERT INTO attendance (student_id,subject_id,att_date,status) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status)');
    foreach ($in['records'] ?? [] as $r)
      if (in_array($r['student_id'] ?? 0, $ok) && in_array($r['status'] ?? '', ['P', 'A'])) $st->execute([$r['student_id'], $sid, $in['date'], $r['status']]);
    out(['ok' => 1]);

  case 'enter_marks':
    $u = need('faculty'); $sid = (int) ($in['subject_id'] ?? 0);
    $exam = trim($in['exam'] ?? ''); $max = max(1, min(1000, (int) ($in['max_marks'] ?? 100)));
    if (!owns($sid, $u['id']) || !$exam) out(['error' => 'Choose one of your subjects and name the exam'], 400);
    $ok = array_column(classStudents($sid), 'id');
    $st = $pdo->prepare('INSERT INTO results (student_id,subject_id,exam,marks,max_marks) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE marks=VALUES(marks),max_marks=VALUES(max_marks)');
    foreach ($in['records'] ?? [] as $r) {
      if (!in_array($r['student_id'] ?? 0, $ok) || !is_numeric($r['marks'] ?? '')) continue;
      if ($r['marks'] < 0 || $r['marks'] > $max) out(['error' => "Marks must be between 0 and $max"], 400);
      $st->execute([$r['student_id'], $sid, substr($exam, 0, 40), $r['marks'], $max]);
    }
    out(['ok' => 1]);

  case 'upload_material':
    $u = need('faculty'); $sid = (int) ($_POST['subject_id'] ?? 0); $f = $_FILES['file'] ?? null;
    if (!owns($sid, $u['id']) || trim($_POST['title'] ?? '') === '' || !$f || $f['error']) out(['error' => 'Choose a subject, add a title and attach a file'], 400);
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'txt', 'zip']) || $f['size'] > 5 * 1024 * 1024)
      out(['error' => 'Upload a PDF, Word, PowerPoint, text or zip file under 5 MB'], 400);
    $name = bin2hex(random_bytes(8)) . '.' . $ext;
    move_uploaded_file($f['tmp_name'], __DIR__ . '/uploads/' . $name);
    q('INSERT INTO materials (subject_id,title,file_path,uploaded_by) VALUES (?,?,?,?)', [$sid, substr(trim($_POST['title']), 0, 160), 'uploads/' . $name, $u['id']]);
    out(['ok' => 1]);

  case 'announce': // faculty publish academic information; admin may use any category
    $u = need('faculty', 'admin');
    $cat = $u['role'] === 'admin' && in_array($in['category'] ?? '', ['general', 'academic', 'exam']) ? $in['category'] : 'academic';
    if (trim($in['title'] ?? '') === '') out(['error' => 'Add a title'], 400);
    q('INSERT INTO announcements (title,body,category,author_id) VALUES (?,?,?,?)', [substr($in['title'], 0, 160), $in['body'] ?? '', $cat, $u['id']]);
    out(['ok' => 1]);

  // ---------- Admin ----------
  case 'list':
    need('admin'); $t = $_GET['t'] ?? '';
    if ($t === 'users') out(rows('SELECT id,name,email,role,roll_no,course_id,semester,dept_id,designation FROM users ORDER BY role,id'));
    if ($t === 'enquiries') out(rows('SELECT * FROM enquiries ORDER BY id DESC LIMIT 100'));
    if (!isset($T[$t])) out(['error' => 'Unknown table'], 400);
    out(rows("SELECT * FROM `$t` ORDER BY id DESC LIMIT 200"));

  case 'save':
    need('admin'); $t = $in['t'] ?? ''; $d = $in['data'] ?? [];
    $cols = $t === 'users' ? $USER_COLS : ($T[$t] ?? null);
    if (!$cols) out(['error' => 'Unknown table'], 400);
    if (trim($d[$cols[0]] ?? '') === '') out(['error' => 'Fill in the first field: ' . $cols[0]], 400);
    if ($t === 'users') {
      if (!in_array($d['role'] ?? '', ['student', 'faculty']) || strlen($d['password'] ?? '') < 8 || !filter_var($d['email'] ?? '', FILTER_VALIDATE_EMAIL))
        out(['error' => 'Choose a role, enter a valid email and a password of at least 8 characters'], 400);
      $d['password_hash'] = password_hash($d['password'], PASSWORD_DEFAULT);
      $cols = array_values(array_diff($cols, ['password'])); $cols[] = 'password_hash';
    }
    $vals = array_map(fn($c) => ($d[$c] ?? '') === '' ? null : $d[$c], $cols);
    q("INSERT INTO `$t` (" . implode(',', array_map(fn($c) => "`$c`", $cols)) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', $vals);
    out(['ok' => 1]);

  case 'del':
    need('admin'); $t = $in['t'] ?? ''; $id = (int) ($in['id'] ?? 0);
    if ($t === 'users') q("DELETE FROM users WHERE id=? AND role<>'admin'", [$id]);
    elseif ($t === 'enquiries' || isset($T[$t])) q("DELETE FROM `$t` WHERE id=?", [$id]);
    else out(['error' => 'Unknown table'], 400);
    out(['ok' => 1]);

  default: out(['error' => 'Unknown request'], 404);
}
