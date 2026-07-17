<?php
require_once 'config.php';
setJsonHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Method tidak diizinkan', null, 405);
}

$query = trim($_GET['query'] ?? '');
if (empty($query)) {
    sendResponse(false, 'Parameter query wajib diisi', null, 400);
}

$url = USDA_BASE_URL . '/foods/search?query=' . urlencode($query)
    . '&pageSize=15&api_key=' . USDA_API_KEY;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    sendResponse(false, 'Gagal mengambil data dari USDA API (kode: ' . $httpCode . ')', null, 502);
}

$usdaData = json_decode($response, true);
$foods = [];

// Ambil nutrient yang relevan: kalori, protein, karbo, lemak
foreach (($usdaData['foods'] ?? []) as $item) {
    $nutrients = ['calories' => 0, 'protein_g' => 0, 'carbs_g' => 0, 'fat_g' => 0];

    foreach (($item['foodNutrients'] ?? []) as $n) {
        $name = strtolower($n['nutrientName'] ?? '');
        $value = $n['value'] ?? 0;
        if (strpos($name, 'energy') !== false) $nutrients['calories'] = $value;
        elseif (strpos($name, 'protein') !== false) $nutrients['protein_g'] = $value;
        elseif (strpos($name, 'carbohydrate') !== false) $nutrients['carbs_g'] = $value;
        elseif ($name === 'total lipid (fat)') $nutrients['fat_g'] = $value;
    }

    $foods[] = [
        'fdc_id' => $item['fdcId'] ?? null,
        'name' => $item['description'] ?? 'Tidak diketahui',
        'portion' => '100 g',
        'calories' => $nutrients['calories'],
        'protein_g' => $nutrients['protein_g'],
        'carbs_g' => $nutrients['carbs_g'],
        'fat_g' => $nutrients['fat_g']
    ];
}

sendResponse(true, 'Berhasil mengambil data makanan', $foods);
