
<?php
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/duitku_config.php';

// Ambil data callback
$merchantCode = $_POST['merchantCode'] ?? '';
$amount = $_POST['amount'] ?? '';
$merchantOrderId = $_POST['merchantOrderId'] ?? '';
$resultCode = $_POST['resultCode'] ?? '';
$signature = $_POST['signature'] ?? '';

if (
    $merchantCode === '' ||
    $amount === '' ||
    $merchantOrderId === '' ||
    $resultCode === '' ||
    $signature === ''
) {
    http_response_code(400);
    exit('Parameter tidak lengkap');
}

// Validasi Merchant Code
if ($merchantCode !== $duitkuMerchantCode) {
    http_response_code(403);
    exit('Merchant Code tidak valid');
}

$signatureValid = md5(
    $merchantCode . $amount . $merchantOrderId . $duitkuApiKey
);

if (!hash_equals($signatureValid, $signature)) {
    http_response_code(403);
    exit('Signature tidak valid');
}

// Validasi ID transaksi
if (!ctype_digit($merchantOrderId)) {
    http_response_code(400);
    exit('ID transaksi tidak valid');
}

$idTransaksi = (int)$merchantOrderId;

// Hanya proses pembayaran berhasil
if ($resultCode !== '00') {
    http_response_code(200);
    exit('Callback diterima, pembayaran belum berhasil');
}

// Ambil data transaksi
$stmt = mysqli_prepare(
    $conn,
    "SELECT total_harga, status, metode_pembayaran
     FROM transaksi
     WHERE id_transaksi = ?
     LIMIT 1"
);

mysqli_stmt_bind_param($stmt, "i", $idTransaksi);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$transaksi = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$transaksi) {
    http_response_code(404);
    exit('Transaksi tidak ditemukan');
}

if ($transaksi['metode_pembayaran'] !== 'Duitku') {
    http_response_code(400);
    exit('Metode pembayaran tidak cocok');
}

// Validasi jumlah pembayaran
if ((int)round((float)$transaksi['total_harga']) !==
    (int)round((float)$amount)) {
    http_response_code(400);
    exit('Nominal pembayaran tidak cocok');
}

// Jika transaksi belum selesai, tandai selesai
if ($transaksi['status'] !== 'selesai') {

    $stmt = mysqli_prepare(
        $conn,
        "UPDATE transaksi
         SET status = 'selesai',
             metode_pembayaran = 'Duitku'
         WHERE id_transaksi = ?
         AND status IN ('menunggu', 'pending')"
    );

    mysqli_stmt_bind_param($stmt, "i", $idTransaksi);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

http_response_code(200);
echo 'SUCCESS';
?>
