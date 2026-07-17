<?php
require_once 'config.php';
setJsonHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method tidak diizinkan', null, 405);
}

// Terima gambar dalam bentuk base64 lewat JSON body
$input = getJsonInput();
$base64Image = $input['image_base64'] ?? null;

if (empty($base64Image)) {
    sendResponse(false, 'Gambar tidak ditemukan', null, 400);
}

// Bersihkan prefix data URI kalau ada (misal "data:image/jpeg;base64,...")
if (strpos($base64Image, ',') !== false) {
    $base64Image = explode(',', $base64Image, 2)[1];
}

$prompt = "Kamu adalah asisten analisis gizi. Lihat foto makanan ini dan identifikasi SEMUA jenis makanan/lauk yang terlihat (bisa lebih dari satu, misal nasi + ayam + sayur dihitung terpisah). " .
    "Untuk setiap item, perkirakan porsi yang wajar dan kandungan gizinya. " .
    "Balas HANYA dalam format JSON array murni, TANPA teks tambahan, TANPA markdown code block, dengan struktur persis seperti ini: " .
    '[{"name":"Nama Makanan","portion":"perkiraan porsi contoh 1 piring (250 g)","calories":000,"protein_g":00,"carbs_g":00,"fat_g":00}]' .
    " Gunakan nama makanan dalam Bahasa Indonesia jika makanan tersebut adalah makanan Indonesia. Jika tidak ada makanan yang terdeteksi, balas dengan array kosong [].";

$payload = [
    'contents' => [[
        'parts' => [
            ['text' => $prompt],
            ['inline_data' => [
                'mime_type' => 'image/jpeg',
                'data' => $base64Image
            ]]
        ]
    ]],
    'generationConfig' => [
        'temperature' => 0.2
    ]
];

$geminiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . GEMINI_API_KEY;

$ch = curl_init($geminiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    sendResponse(false, 'Gagal menganalisis gambar (Gemini API kode: ' . $httpCode . '): ' . $response, null, 502);
}

$geminiData = json_decode($response, true);
$text = $geminiData['candidates'][0]['content']['parts'][0]['text'] ?? '';

// Bersihkan kalau Gemini tetap membungkus dengan markdown code block
$text = trim($text);
$text = preg_replace('/^```json\s*/i', '', $text);
$text = preg_replace('/^```\s*/', '', $text);
$text = preg_replace('/```$/', '', $text);
$text = trim($text);

$foods = json_decode($text, true);

if (!is_array($foods)) {
    sendResponse(false, 'Gagal memahami hasil analisis AI, coba foto ulang dengan pencahayaan lebih jelas', null, 500);
}

sendResponse(true, 'Berhasil menganalisis gambar makanan', $foods);
