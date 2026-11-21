DROP DATABASE IF EXISTS smartcampus;
CREATE DATABASE smartcampus CHARACTER SET utf8mb4;
USE smartcampus;

CREATE TABLE departments (id INT AUTO_INCREMENT PRIMARY KEY, code VARCHAR(10) NOT NULL, name VARCHAR(100) NOT NULL, about TEXT, hod VARCHAR(100));
CREATE TABLE courses (id INT AUTO_INCREMENT PRIMARY KEY, dept_id INT NOT NULL, name VARCHAR(120) NOT NULL,
  level ENUM('Undergraduate','Postgraduate') NOT NULL, duration VARCHAR(20), seats INT, FOREIGN KEY (dept_id) REFERENCES departments(id));
CREATE TABLE users (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, email VARCHAR(120) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL, role ENUM('student','faculty','admin') NOT NULL,
  roll_no VARCHAR(20), course_id INT NULL, semester TINYINT NULL, dept_id INT NULL, designation VARCHAR(60),
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL, FOREIGN KEY (dept_id) REFERENCES departments(id) ON DELETE SET NULL);
CREATE TABLE subjects (id INT AUTO_INCREMENT PRIMARY KEY, course_id INT NOT NULL, name VARCHAR(100) NOT NULL, semester TINYINT NOT NULL,
  faculty_id INT NULL, FOREIGN KEY (course_id) REFERENCES courses(id), FOREIGN KEY (faculty_id) REFERENCES users(id) ON DELETE SET NULL);
CREATE TABLE timetable (id INT AUTO_INCREMENT PRIMARY KEY, course_id INT NOT NULL, semester TINYINT NOT NULL,
  day ENUM('Mon','Tue','Wed','Thu','Fri','Sat') NOT NULL, time_slot VARCHAR(20) NOT NULL, subject_id INT NOT NULL,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE, FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE);
CREATE TABLE attendance (id INT AUTO_INCREMENT PRIMARY KEY, student_id INT NOT NULL, subject_id INT NOT NULL, att_date DATE NOT NULL,
  status ENUM('P','A') NOT NULL, UNIQUE (student_id, subject_id, att_date),
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE);
CREATE TABLE results (id INT AUTO_INCREMENT PRIMARY KEY, student_id INT NOT NULL, subject_id INT NOT NULL, exam VARCHAR(40) NOT NULL,
  marks DECIMAL(5,1) NOT NULL, max_marks INT NOT NULL, UNIQUE (student_id, subject_id, exam),
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE);
CREATE TABLE materials (id INT AUTO_INCREMENT PRIMARY KEY, subject_id INT NOT NULL, title VARCHAR(160) NOT NULL, file_path VARCHAR(200) NOT NULL,
  uploaded_by INT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE, FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL);
CREATE TABLE announcements (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(160) NOT NULL, body TEXT,
  category ENUM('general','academic','exam') NOT NULL DEFAULT 'general', author_id INT NULL, posted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL);
CREATE TABLE events (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(160) NOT NULL, description TEXT, event_date DATE NOT NULL, venue VARCHAR(100));
CREATE TABLE placements (id INT AUTO_INCREMENT PRIMARY KEY, company VARCHAR(80) NOT NULL, role_title VARCHAR(80),
  package_lpa DECIMAL(5,2), students_placed INT, year SMALLINT);
CREATE TABLE enquiries (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, email VARCHAR(120) NOT NULL, message TEXT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP);

INSERT INTO departments (code,name,about,hod) VALUES
('CSE','Computer Science & Engineering','Software, data and systems with a project in every semester.','Dr. Meera Narayan'),
('ECE','Electronics & Communication','Embedded systems, VLSI and wireless communication labs.','Dr. Arjun Rao'),
('MECH','Mechanical Engineering','Design, manufacturing and thermal engineering with an industry-run workshop.','Dr. Suresh Gowda'),
('MBA','Management Studies','Business fundamentals, analytics and a compulsory internship.','Dr. Kavitha Iyer');
INSERT INTO courses (dept_id,name,level,duration,seats) VALUES
(1,'B.E. Computer Science & Engineering','Undergraduate','4 years',120),(1,'M.Tech Data Science','Postgraduate','2 years',24),
(2,'B.E. Electronics & Communication','Undergraduate','4 years',90),(3,'B.E. Mechanical Engineering','Undergraduate','4 years',60),
(4,'MBA General','Postgraduate','2 years',60);
INSERT INTO subjects (course_id,name,semester) VALUES
(1,'Data Structures',3),(1,'Database Management Systems',3),(1,'Operating Systems',3),(1,'Discrete Mathematics',3);
INSERT INTO timetable (course_id,semester,day,time_slot,subject_id) VALUES
(1,3,'Mon','09:00-10:00',1),(1,3,'Mon','10:00-11:00',2),(1,3,'Tue','09:00-10:00',3),(1,3,'Tue','10:00-11:00',4),
(1,3,'Wed','09:00-10:00',2),(1,3,'Thu','09:00-10:00',1),(1,3,'Fri','09:00-10:00',3);
INSERT INTO announcements (title,body,category,posted_at) VALUES
('Admissions 2027 open','Online applications for all B.E., M.Tech and MBA programs are open. See the admission steps on this page.','general',NOW()),
('Scholarship applications','Merit and need-based scholarship forms are available at the accounts office.','general',DATE_SUB(NOW(),INTERVAL 2 DAY)),
('Library hours extended','The central library stays open until 9 PM on weekdays during examination weeks.','general',DATE_SUB(NOW(),INTERVAL 4 DAY)),
('Semester-end exam timetable released','Theory exams begin in three weeks. Download hall tickets from your student login.','exam',NOW()),
('Revaluation applications','Revaluation requests for the previous semester close in five days.','exam',DATE_SUB(NOW(),INTERVAL 1 DAY)),
('Lab practical schedule','Practical exams for CSE and ECE are held in the week before theory exams.','exam',DATE_SUB(NOW(),INTERVAL 3 DAY));
INSERT INTO events (title,description,event_date,venue) VALUES
('Code Sprint','A 24-hour hackathon open to all departments.',DATE_ADD(CURDATE(),INTERVAL 12 DAY),'Main Auditorium'),
('Open Day for Applicants','Campus tour, lab visits and a Q&A with department heads.',DATE_ADD(CURDATE(),INTERVAL 20 DAY),'Admin Block Lawn'),
('Guest Lecture: Careers in Chip Design','Industry engineers talk about VLSI roles and skills.',DATE_ADD(CURDATE(),INTERVAL 27 DAY),'ECE Seminar Hall'),
('Annual Sports Meet','Inter-department athletics, cricket and volleyball.',DATE_ADD(CURDATE(),INTERVAL 40 DAY),'College Grounds');
INSERT INTO placements (company,role_title,package_lpa,students_placed,year) VALUES
('Deloitte','Analyst',11.50,6,2026),('Bosch','Embedded Engineer',9.20,12,2026),('Tata Elxsi','Design Engineer',8.40,9,2026),
('Accenture','Associate Software Engineer',7.00,28,2026),('Infosys','Systems Engineer',6.50,42,2026),('Wipro','Project Engineer',5.80,35,2026);
