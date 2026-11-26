
<?php
require_once __DIR__ . '/duitku_config.php';

echo "<h2>Tes Koneksi Duitku Sandbox</h2>";

if (!function_exists('curl_init')) {
    exit("ERROR: PHP cURL belum aktif di XAMPP.");
}

if (empty($duitkuApiUrl)) {
    exit("ERROR: API URL belum tersedia.");
}

$ch = curl_init($duitkuApiUrl);

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode([
        'test' => true
    ]),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json'
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20
]);

$response = curl_exec($ch);
$error = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

echo "<p>HTTP Status: " . (int)$httpCode . "</p>";

if ($response === false) {
    echo "<p>Koneksi gagal.</p>";
    echo "<p>" . htmlspecialchars($error) . "</p>";
    exit;
}

echo "<h3>Respons Server Duitku:</h3>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";

echo "<p>Tes selesai. Tes ini tidak membuat invoice pembayaran.</p>";
?>
