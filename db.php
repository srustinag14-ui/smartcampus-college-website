<?php
// XAMPP defaults: user "root", empty password. Change these if you set a MySQL password.
$pdo = new PDO('mysql:host=localhost;dbname=smartcampus;charset=utf8mb4', 'root', '', [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
