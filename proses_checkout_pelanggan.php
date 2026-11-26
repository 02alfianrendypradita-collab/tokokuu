
<?php
session_start();
require_once "koneksi.php";
require_once "duitku_config.php";

// ==========================================
// 1. PROTEKSI LOGIN
// ==========================================
if (!isset($_SESSION['id_user']) || ($_SESSION['role'] ?? '') !== 'pembeli') {
    header("Location: login_pembeli.php");
    exit;
}

$id_user = (int) $_SESSION['id_user'];

// ==========================================
// 2. VALIDASI METHOD DAN CSRF
// ==========================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: checkout_pelanggan.php");
    exit;
}

$csrf = $_POST['csrf_token'] ?? '';

if (
    !isset($_SESSION['csrf_checkout_pelanggan']) ||
    !hash_equals($_SESSION['csrf_checkout_pelanggan'], $csrf)
) {
    $_SESSION['pesan_checkout_pelanggan'] = "Token keamanan tidak valid.";
    header("Location: checkout_pelanggan.php");
    exit;
}

$metode = $_POST['metode_pembayaran'] ?? '';

if (!in_array($metode, ['Duitku', 'Tunai'], true)) {
    $_SESSION['pesan_checkout_pelanggan'] = "Metode pembayaran tidak valid.";
    header("Location: checkout_pelanggan.php");
    exit;
}

// ==========================================
// 3. AMBIL DATA KERANJANG
// ==========================================
$query = mysqli_query($conn, "
    SELECT k.id_produk, k.jumlah, p.nama_produk, p.harga, p.stok
    FROM keranjang k
    JOIN produk p ON k.id_produk = p.id_produk
    WHERE k.id_user = $id_user
");

if (!$query || mysqli_num_rows($query) === 0) {
    $_SESSION['pesan_checkout_pelanggan'] = "Keranjang kosong.";
    header("Location: keranjang.php");
    exit;
}

$items = [];
$total = 0;

while ($row = mysqli_fetch_assoc($query)) {
    $jumlah = (int)$row['jumlah'];
    $harga = (int)$row['harga'];
    $stok = (int)$row['stok'];

    if ($jumlah <= 0 || $jumlah > $stok) {
        $_SESSION['pesan_checkout_pelanggan'] =
            "Stok produk " . $row['nama_produk'] . " tidak mencukupi.";
        header("Location: keranjang.php");
        exit;
    }

    $items[] = $row;
    $total += $harga * $jumlah;
}

if ($total <= 0) {
    $_SESSION['pesan_checkout_pelanggan'] = "Total transaksi tidak valid.";
    header("Location: keranjang.php");
    exit;
}

// ==========================================
// 4. AMBIL DATA PEMBELI
// ==========================================
$stmtUser = mysqli_prepare($conn, "
    SELECT nama, email
    FROM users
    WHERE id_user = ?
    LIMIT 1
");

mysqli_stmt_bind_param($stmtUser, "i", $id_user);
mysqli_stmt_execute($stmtUser);
$resultUser = mysqli_stmt_get_result($stmtUser);
$user = mysqli_fetch_assoc($resultUser);

if (!$user) {
    die("Data pembeli tidak ditemukan.");
}

$nama = $user['nama'];
$email = $user['email'];

// ==========================================
// 5. TUNAI
// ==========================================
// Pesanan tunai ditangani sebagai pesanan pending.
// Struktur penyimpanan transaksi tunai perlu disesuaikan
// dengan tabel transaksi project Anda.

if ($metode === 'Tunai') {
    $_SESSION['pesan_checkout_pelanggan'] =
        "Fitur pesanan tunai perlu disesuaikan dengan sistem kasir TokoKu.";

    header("Location: checkout_pelanggan.php");
    exit;
}

// ==========================================
// 6. PERSIAPAN DUITKU
// ==========================================
if (
    empty($duitkuMerchantCode) ||
    empty($duitkuApiKey) ||
    strpos($duitkuApiKey, 'ISI_API_KEY') !== false
) {
    die("Konfigurasi Duitku belum lengkap.");
}

$merchantOrderId = 'TK-' . date('YmdHis') . '-' . $id_user;

$productDetails = "Pembelian TokoKu " . $merchantOrderId;

$itemDetails = [];

foreach ($items as $item) {
    $itemDetails[] = [
        'name' => substr($item['nama_produk'], 0, 100),
        'price' => (int)$item['harga'],
        'quantity' => (int)$item['jumlah']
    ];
}

// ==========================================
// 7. REQUEST DUITKU
// ==========================================
$timestamp = round(microtime(true) * 1000);

// Signature API POP
$signature = hash(
    'sha256',
    $duitkuMerchantCode . $timestamp . $duitkuApiKey
);

$params = [
    'paymentAmount' => (int)$total,
    'merchantOrderId' => $merchantOrderId,
    'productDetails' => $productDetails,
    'additionalParam' => '',
    'merchantUserInfo' => (string)$id_user,
    'customerVaName' => substr($nama, 0, 20),
    'email' => $email,
    'itemDetails' => $itemDetails,
    'callbackUrl' => $duitkuCallbackUrl,
    'returnUrl' => 'https://sterida.my.id/alfian/dashboard_pembeli.php',
    'expiryPeriod' => 60
];

$jsonParams = json_encode($params);

$ch = curl_init($duitkuBaseUrl);

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $jsonParams,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'x-duitku-signature: ' . $signature,
        'x-duitku-timestamp: ' . $timestamp,
        'x-duitku-merchantcode: ' . $duitkuMerchantCode
    ],
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

curl_close($ch);

// ==========================================
// 8. PERIKSA RESPON DUITKU
// ==========================================
if ($response === false) {
    die("Koneksi Duitku gagal: " . htmlspecialchars($curlError));
}

$data = json_decode($response, true);

if (
    $httpCode >= 200 &&
    $httpCode < 300 &&
    isset($data['paymentUrl']) &&
    ($data['statusCode'] ?? '') === '00'
) {

    // Simpan ID order sementara untuk pencatatan
    // dan identifikasi callback.
    $_SESSION['order_duitku_pelanggan'] = $merchantOrderId;

    // Redirect ke halaman pembayaran Duitku
    header("Location: " . $data['paymentUrl']);
    exit;

} else {
    echo "<h2>Gagal membuat pembayaran Duitku</h2>";
    echo "<pre>";
    echo htmlspecialchars($response);
    echo "</pre>";
}
?>
