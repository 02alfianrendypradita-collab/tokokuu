
<?php
require_once __DIR__ . '/duitku_config.php';

$amount = 10000;
$datetime = date('Y-m-d H:i:s');

$signature = hash_hmac(
    'sha256',
    $duitkuMerchantCode . $amount . $datetime,
    $duitkuApiKey
);

$payload = [
    'merchantcode' => $duitkuMerchantCode,
    'amount' => $amount,
    'datetime' => $datetime,
    'signature' => $signature
];

$apiUrl = 'https://sandbox.duitku.com/webapi/api/merchant/paymentmethod/getpaymentmethod';

$ch = curl_init($apiUrl);

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json'
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);
$error = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

echo "<h2>Tes Metode Pembayaran Duitku</h2>";
echo "<p>HTTP Status: " . (int)$httpCode . "</p>";

if ($response === false) {
    echo "<p>Gagal menghubungi server: " .
         htmlspecialchars($error) . "</p>";
} else {
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
}
?>
