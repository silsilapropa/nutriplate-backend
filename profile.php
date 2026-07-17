<?php
require_once 'config.php';
setJsonHeaders();

$conn = getDbConnection();
$method = $_SERVER['REQUEST_METHOD'];

// Hitung kebutuhan kalori harian (rumus Mifflin-St Jeor)
function hitungKaloriHarian($weight, $height, $age, $gender, $activityLevel, $dietGoal) {
    if ($gender === 'male') {
        $bmr = (10 * $weight) + (6.25 * $height) - (5 * $age) + 5;
    } else {
        $bmr = (10 * $weight) + (6.25 * $height) - (5 * $age) - 161;
    }

    $activityMultiplier = [
        'sedentary' => 1.2,
        'light' => 1.375,
        'moderate' => 1.55,
        'active' => 1.725,
        'very_active' => 1.9
    ];

    $tdee = $bmr * ($activityMultiplier[$activityLevel] ?? 1.55);

    if ($dietGoal === 'lose_weight') {
        $tdee -= 500;
    } elseif ($dietGoal === 'gain_muscle') {
        $tdee += 300;
    }

    return (int) round($tdee);
}

if ($method === 'GET') {
    $userId = $_GET['user_id'] ?? null;
    if (!$userId) sendResponse(false, 'user_id wajib diisi', null, 400);

    $stmt = $conn->prepare('SELECT * FROM profiles WHERE user_id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        sendResponse(false, 'Profil belum diisi', null, 404);
    }

    sendResponse(true, 'Berhasil mengambil profil', $result->fetch_assoc());

} elseif ($method === 'POST' || $method === 'PUT') {
    $input = getJsonInput();
    $userId = $input['user_id'] ?? null;
    $weight = $input['weight_kg'] ?? null;
    $height = $input['height_cm'] ?? null;
    $age = $input['age'] ?? null;
    $gender = $input['gender'] ?? null;
    $activityLevel = $input['activity_level'] ?? 'moderate';
    $dietGoal = $input['diet_goal'] ?? 'maintain';

    if (!$userId || !$weight || !$height || !$age || !$gender) {
        sendResponse(false, 'Data profil tidak lengkap', null, 400);
    }

    $calorieTarget = hitungKaloriHarian($weight, $height, $age, $gender, $activityLevel, $dietGoal);

    // Cek apakah profil sudah ada -> UPDATE, kalau belum -> INSERT
    $stmt = $conn->prepare('SELECT id FROM profiles WHERE user_id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;

    if ($exists) {
        $stmt = $conn->prepare('UPDATE profiles SET weight_kg=?, height_cm=?, age=?, gender=?, activity_level=?, diet_goal=?, daily_calorie_target=? WHERE user_id=?');
        $stmt->bind_param('ddisssii', $weight, $height, $age, $gender, $activityLevel, $dietGoal, $calorieTarget, $userId);
    } else {
        $stmt = $conn->prepare('INSERT INTO profiles (user_id, weight_kg, height_cm, age, gender, activity_level, diet_goal, daily_calorie_target) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('iddisssi', $userId, $weight, $height, $age, $gender, $activityLevel, $dietGoal, $calorieTarget);
    }

    if ($stmt->execute()) {
        sendResponse(true, 'Profil berhasil disimpan', ['daily_calorie_target' => $calorieTarget]);
    } else {
        sendResponse(false, 'Gagal menyimpan profil: ' . $stmt->error, null, 500);
    }
} else {
    sendResponse(false, 'Method tidak diizinkan', null, 405);
}

$conn->close();
