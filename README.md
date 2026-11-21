# SmartCampus: Integrated College Website and Student Management System

Stack: HTML, CSS, JavaScript (frontend), MySQL (database), run on XAMPP (Apache + MySQL).
XAMPP's PHP runs one small file, api.php, because browser JavaScript cannot connect to MySQL directly.

## How to run (Windows)
1. Install XAMPP from https://www.apachefriends.org
2. Unzip this project and copy the folder `smartcampus` into `C:\xampp\htdocs\`
   (macOS: /Applications/XAMPP/htdocs/, Linux: /opt/lampp/htdocs/)
3. Open the XAMPP Control Panel and click Start next to Apache and next to MySQL.
4. Open http://localhost/phpmyadmin, click the Import tab, choose `smartcampus/schema.sql`, click Import.
   This creates the database `smartcampus` with sample courses, timetable, notices, events and placements.
5. Open http://localhost/smartcampus/setup.php once. It creates the demo users and sample attendance and marks.
   Then delete setup.php.
6. Open http://localhost/smartcampus/

Do not open index.html by double-clicking it. It must be opened through http://localhost so PHP runs.

## Demo logins (password for all: Welcome@123)
- Admin:   admin@kit.edu
- Faculty: meera.n@kit.edu (Data Structures, DBMS), rohan.s@kit.edu (Operating Systems, Discrete Maths)
- Student: ananya@kit.edu, rahul@kit.edu, sneha@kit.edu

## Troubleshooting
- "Check that Apache and MySQL are running": start both in the XAMPP Control Panel.
- Port 80 busy: change Apache's port in XAMPP (Config > httpd.conf) and use http://localhost:PORT/smartcampus/
- If you set a MySQL password, put it in db.php.
- Uploads fail: make sure the `uploads` folder is inside `smartcampus` and writable.

## Files
index.html, style.css, app.js (frontend) | api.php, db.php, setup.php (server) | schema.sql (database) | uploads/ (study materials)
