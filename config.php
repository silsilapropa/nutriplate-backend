<?php
// ============================================
// KONFIGURASI RAILWAY - pakai internal network (lebih cepat & gratis, gak kena kuota proxy publik)
// ============================================

define('DB_HOST', 'mysql.railway.internal');
define('DB_USER', 'root');
define('DB_PASS', 'hDPMAnTuoHHGUUwHadigSvgMnkDaRbwM');
define('DB_NAME', 'railway');
define('DB_PORT', 3306);

// USDA FoodData Central API Key
define('USDA_API_KEY', '9xgOkTAwofOm7NpZhd3uaZMhXbTteUV0hH5NKLks');
define('USDA_BASE_URL', 'https://api.nal.usda.gov/fdc/v1');

// Google Gemini API Key (untuk analisis foto makanan)
define('GEMINI_API_KEY', 'GANTI_DENGAN_KEY_GEMINI_BARU_KAMU');

// Koneksi database (mysqli)
function getDbConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    if ($conn->connect_error) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Koneksi database gagal: ' . $conn->connect_error]);
        exit;
    }
    return $conn;
}

// Header standar untuk semua response API
function setJsonHeaders() {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

// Helper: ambil body JSON dari request
function getJsonInput() {
    $data = json_decode(file_get_contents('php://input'), true);
    return $data ?? [];
}

// Helper: kirim response JSON dan hentikan eksekusi
function sendResponse($success, $message, $data = null, $code = 200) {
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}
