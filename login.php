<?php
require_once 'config.php';
setJsonHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method tidak diizinkan', null, 405);
}

$input = getJsonInput();
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if (empty($email) || empty($password)) {
    sendResponse(false, 'Email dan password wajib diisi', null, 400);
}

$conn = getDbConnection();

$stmt = $conn->prepare('SELECT id, name, email, password FROM users WHERE email = ?');
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    sendResponse(false, 'Email tidak ditemukan', null, 404);
}

$user = $result->fetch_assoc();

if (!password_verify($password, $user['password'])) {
    sendResponse(false, 'Password salah', null, 401);
}

sendResponse(true, 'Login berhasil', [
    'user_id' => $user['id'],
    'name' => $user['name'],
    'email' => $user['email']
]);

$stmt->close();
$conn->close();
