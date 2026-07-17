<?php
require_once 'config.php';
setJsonHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method tidak diizinkan', null, 405);
}

$input = getJsonInput();
$name = trim($input['name'] ?? '');
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if (empty($name) || empty($email) || empty($password)) {
    sendResponse(false, 'Nama, email, dan password wajib diisi', null, 400);
}

$conn = getDbConnection();

// Cek email sudah terdaftar atau belum
$stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
$stmt->bind_param('s', $email);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    sendResponse(false, 'Email sudah terdaftar', null, 409);
}
$stmt->close();

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare('INSERT INTO users (name, email, password) VALUES (?, ?, ?)');
$stmt->bind_param('sss', $name, $email, $hashedPassword);

if ($stmt->execute()) {
    sendResponse(true, 'Registrasi berhasil', ['user_id' => $stmt->insert_id]);
} else {
    sendResponse(false, 'Registrasi gagal: ' . $stmt->error, null, 500);
}

$stmt->close();
$conn->close();
