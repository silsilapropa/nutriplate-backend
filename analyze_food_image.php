<?php
require_once 'config.php';
setJsonHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method tidak diizinkan', null, 405);
}

$input = getJsonInput();
$base64Image = $input['image_base64'] ?? null;

if (empty($base64Image)) {
    sendResponse(false, 'Gambar tidak ditemukan', null, 400);
}

if (strpos($base64Image, ',') !== false) {
    $base64Image = explode(',', $base64Image, 2)[1];
}

$imageBinary = base64_decode($base64Image);

// ---------- STEP 1: Klasifikasi gambar makanan pakai Hugging Face (gratis) ----------
$hfModel = 'Kaludi/food-category-classification-v2.0';
$hfUrl = 'https://router.huggingface.co/hf-inference/models/' . $hfModel;

$ch = curl_init($hfUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $imageBinary);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . HUGGINGFACE_API_KEY,
    'Content-Type: image/jpeg'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
$response = curl_exec($ch);
$curlErr = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    sendResponse(false, 'Gagal mengklasifikasi gambar (Hugging Face kode: ' . $httpCode . ', curl error: ' . $curlErr . '): ' . $response, null, 502);
}

$classifications = json_decode($response, true);

if (!is_array($classifications) || empty($classifications)) {
    sendResponse(false, 'Tidak ada makanan yang terdeteksi pada gambar, coba foto ulang', null, 500);
}

usort($classifications, fn($a, $b) => $b['score'] <=> $a['score']);
$topLabel = $classifications[0]['label'] ?? null;
$confidence = $classifications[0]['score'] ?? 0;

if (!$topLabel) {
    sendResponse(false, 'Gagal membaca hasil klasifikasi', null, 500);
}

$cleanLabel = str_replace('_', ' ', $topLabel);

// ---------- STEP 2: Cari data gizi lewat USDA API berdasarkan nama makanan hasil deteksi ----------
$usdaUrl = USDA_BASE_URL . '/foods/search?query=' . urlencode($cleanLabel) . '&pageSize=3&api_key=' . USDA_API_KEY;

$ch = curl_init($usdaUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$usdaResponse = curl_exec($ch);
$usdaHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = [
    'detected_label' => $cleanLabel,
    'confidence' => round($confidence * 100, 1),
    'name' => ucwords($cleanLabel),
    'portion' => '1 porsi (~250 g)',
    'calories' => 0,
    'protein_g' => 0,
    'carbs_g' => 0,
    'fat_g' => 0
];

if ($usdaHttpCode === 200) {
    $usdaData = json_decode($usdaResponse, true);
    $firstFood = $usdaData['foods'][0] ?? null;

    if ($firstFood) {
        $nutrients = ['calories' => 0, 'protein_g' => 0, 'carbs_g' => 0, 'fat_g' => 0];
        foreach (($firstFood['foodNutrients'] ?? []) as $n) {
            $name = strtolower($n['nutrientName'] ?? '');
            $value = $n['value'] ?? 0;
            if (strpos($name, 'energy') !== false) $nutrients['calories'] = $value;
            elseif (strpos($name, 'protein') !== false) $nutrients['protein_g'] = $value;
            elseif (strpos($name, 'carbohydrate') !== false) $nutrients['carbs_g'] = $value;
            elseif ($name === 'total lipid (fat)') $nutrients['fat_g'] = $value;
        }
        $result['name'] = ucwords($cleanLabel) . ' (' . $firstFood['description'] . ')';
        $result['portion'] = '100 g (estimasi, sesuaikan dengan porsi aslimu)';
        $result['calories'] = $nutrients['calories'];
        $result['protein_g'] = $nutrients['protein_g'];
        $result['carbs_g'] = $nutrients['carbs_g'];
        $result['fat_g'] = $nutrients['fat_g'];
    }
}

sendResponse(true, 'Berhasil menganalisis gambar makanan', [$result]);
