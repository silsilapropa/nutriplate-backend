<?php
require_once 'config.php';
setJsonHeaders();

$conn = getDbConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // SELECT: ambil log makanan (bisa difilter per tanggal)
    $userId = $_GET['user_id'] ?? null;
    $date = $_GET['date'] ?? null; // format: YYYY-MM-DD

    if (!$userId) sendResponse(false, 'user_id wajib diisi', null, 400);

    if ($date) {
        $stmt = $conn->prepare('SELECT * FROM food_logs WHERE user_id = ? AND log_date = ? ORDER BY created_at DESC');
        $stmt->bind_param('is', $userId, $date);
    } else {
        $stmt = $conn->prepare('SELECT * FROM food_logs WHERE user_id = ? ORDER BY log_date DESC, created_at DESC');
        $stmt->bind_param('i', $userId);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $logs = [];
    $totalCalories = 0;
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
        $totalCalories += (float) $row['calories'];
    }

    sendResponse(true, 'Berhasil mengambil log makanan', [
        'logs' => $logs,
        'total_calories' => $totalCalories
    ]);

} elseif ($method === 'POST') {
    // INSERT: tambah makanan ke log harian
    $input = getJsonInput();
    $userId = $input['user_id'] ?? null;
    $foodName = trim($input['food_name'] ?? '');
    $calories = $input['calories'] ?? null;
    $protein = $input['protein_g'] ?? 0;
    $carbs = $input['carbs_g'] ?? 0;
    $fat = $input['fat_g'] ?? 0;
    $portion = $input['portion'] ?? '100 g';
    $mealType = $input['meal_type'] ?? 'snack';
    $logDate = $input['log_date'] ?? date('Y-m-d');

    if (!$userId || empty($foodName) || $calories === null) {
        sendResponse(false, 'Data makanan tidak lengkap', null, 400);
    }

    $stmt = $conn->prepare('INSERT INTO food_logs (user_id, food_name, calories, protein_g, carbs_g, fat_g, food_portion, meal_type, log_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('isddddsss', $userId, $foodName, $calories, $protein, $carbs, $fat, $portion, $mealType, $logDate);

    if ($stmt->execute()) {
        sendResponse(true, 'Makanan berhasil ditambahkan ke log', ['id' => $stmt->insert_id]);
    } else {
        sendResponse(false, 'Gagal menambahkan log: ' . $stmt->error, null, 500);
    }

} elseif ($method === 'PUT') {
    // UPDATE: ubah entri log yang sudah ada
    $input = getJsonInput();
    $id = $input['id'] ?? null;
    $foodName = $input['food_name'] ?? null;
    $calories = $input['calories'] ?? null;
    $protein = $input['protein_g'] ?? 0;
    $carbs = $input['carbs_g'] ?? 0;
    $fat = $input['fat_g'] ?? 0;
    $portion = $input['portion'] ?? '100 g';
    $mealType = $input['meal_type'] ?? 'snack';

    if (!$id || !$foodName || $calories === null) {
        sendResponse(false, 'Data tidak lengkap untuk update', null, 400);
    }

    $stmt = $conn->prepare('UPDATE food_logs SET food_name=?, calories=?, protein_g=?, carbs_g=?, fat_g=?, food_portion=?, meal_type=? WHERE id=?');
    $stmt->bind_param('sddddssi', $foodName, $calories, $protein, $carbs, $fat, $portion, $mealType, $id);

    if ($stmt->execute()) {
        sendResponse(true, 'Log berhasil diperbarui');
    } else {
        sendResponse(false, 'Gagal memperbarui log: ' . $stmt->error, null, 500);
    }

} elseif ($method === 'DELETE') {
    // DELETE: hapus entri log
    $id = $_GET['id'] ?? null;
    if (!$id) sendResponse(false, 'id wajib diisi', null, 400);

    $stmt = $conn->prepare('DELETE FROM food_logs WHERE id = ?');
    $stmt->bind_param('i', $id);

    if ($stmt->execute()) {
        sendResponse(true, 'Log berhasil dihapus');
    } else {
        sendResponse(false, 'Gagal menghapus log: ' . $stmt->error, null, 500);
    }

} else {
    sendResponse(false, 'Method tidak diizinkan', null, 405);
}

$conn->close();
