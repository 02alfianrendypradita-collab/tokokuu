
<?php
require_once 'duitku_config.php';

echo "<h2>Pemeriksaan Konfigurasi Duitku</h2>";

if (isset($duitkuMerchantCode) && !empty($duitkuMerchantCode)) {
    echo "Merchant Code: TERISI<br>";
} else {
    echo "Merchant Code: KOSONG<br>";
}

if (isset($duitkuApiKey) && !empty($duitkuApiKey)) {
    echo "API Key: TERISI<br>";
} else {
    echo "API Key: KOSONG<br>";
}

if (isset($duitkuApiUrl) && !empty($duitkuApiUrl)) {
    echo "API URL: TERISI<br>";
} else {
    echo "API URL: KOSONG<br>";
}

if (isset($duitkuBaseUrl) && !empty($duitkuBaseUrl)) {
    echo "Base URL: TERISI<br>";
} else {
    echo "Base URL: KOSONG<br>";
}

echo "<hr>";
echo "Mode: " . ($duitkuMode ?? 'Tidak ditemukan');
?>
