<?php
// Run ONCE in the browser after importing schema.sql, then delete this file.
require __DIR__ . '/db.php';
$pw = password_hash('Welcome@123', PASSWORD_DEFAULT);
$ins = $pdo->prepare('INSERT IGNORE INTO users (name,email,password_hash,role,roll_no,course_id,semester,dept_id,designation) VALUES (?,?,?,?,?,?,?,?,?)');
foreach ([
  ['Office Admin','admin@kit.edu','admin',null,null,null,null,'Administrator'],
  ['Dr. Meera Narayan','meera.n@kit.edu','faculty',null,null,null,1,'Professor & HoD'],
  ['Prof. Rohan Shetty','rohan.s@kit.edu','faculty',null,null,null,1,'Associate Professor'],
  ['Dr. Arjun Rao','arjun.r@kit.edu','faculty',null,null,null,2,'Professor & HoD'],
  ['Dr. Suresh Gowda','suresh.g@kit.edu','faculty',null,null,null,3,'Professor & HoD'],
  ['Dr. Kavitha Iyer','kavitha.i@kit.edu','faculty',null,null,null,4,'Professor & HoD'],
  ['Ananya Kulkarni','ananya@kit.edu','student','1KT23CS001',1,3,1,null],
  ['Rahul Hegde','rahul@kit.edu','student','1KT23CS002',1,3,1,null],
  ['Sneha Rao','sneha@kit.edu','student','1KT23CS003',1,3,1,null],
] as [$n,$e,$r,$roll,$c,$s,$d,$des]) $ins->execute([$n,$e,$pw,$r,$roll,$c,$s,$d,$des]);

$fid = fn($e) => $pdo->query("SELECT id FROM users WHERE email='$e'")->fetchColumn();
$pdo->exec('UPDATE subjects SET faculty_id=' . $fid('meera.n@kit.edu') . ' WHERE id IN (1,2)');
$pdo->exec('UPDATE subjects SET faculty_id=' . $fid('rohan.s@kit.edu') . ' WHERE id IN (3,4)');

// Sample attendance (last 8 days) and internal marks
$students = $pdo->query("SELECT id FROM users WHERE role='student'")->fetchAll(PDO::FETCH_COLUMN);
$a = $pdo->prepare('INSERT IGNORE INTO attendance (student_id,subject_id,att_date,status) VALUES (?,?,?,?)');
$m = $pdo->prepare('INSERT IGNORE INTO results (student_id,subject_id,exam,marks,max_marks) VALUES (?,?,?,?,50)');
foreach ($students as $st) foreach ([1,2,3,4] as $sub) {
  for ($d = 1; $d <= 8; $d++) $a->execute([$st, $sub, date('Y-m-d', strtotime("-$d day")), ($st + $sub + $d) % 5 ? 'P' : 'A']);
  $m->execute([$st, $sub, 'Internal 1', 30 + (($st * 7 + $sub * 3) % 19)]);
}
echo 'Setup complete. Demo password for all users: Welcome@123. Now delete setup.php and open <a href="index.html">the site</a>.';
