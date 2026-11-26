
<?php
session_start();
require_once "koneksi.php";

// Proteksi login pembeli
if (!isset($_SESSION['id_user']) || ($_SESSION['role'] ?? '') !== 'pembeli') {
    header("Location: login_pembeli.php");
    exit;
}

$id_user = (int) $_SESSION['id_user'];

// Ambil data keranjang
$query = mysqli_query($conn, "
    SELECT k.id_produk, k.jumlah, p.nama_produk, p.harga, p.foto, p.stok
    FROM keranjang k
    JOIN produk p ON k.id_produk = p.id_produk
    WHERE k.id_user = $id_user
");

if (!$query) {
    die("Gagal mengambil data keranjang: " . mysqli_error($conn));
}

$items = [];
$total = 0;

while ($row = mysqli_fetch_assoc($query)) {
    $items[] = $row;
    $total += (float)$row['harga'] * (int)$row['jumlah'];
}

if (empty($items)) {
    header("Location: keranjang.php");
    exit;
}

if (empty($_SESSION['csrf_checkout_pelanggan'])) {
    $_SESSION['csrf_checkout_pelanggan'] = bin2hex(random_bytes(32));
}

$pesanCheckout = $_SESSION['pesan_checkout_pelanggan'] ?? '';
unset($_SESSION['pesan_checkout_pelanggan']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Checkout | TokoKu</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', Arial, sans-serif;
    background: #edf4ec;
    color: #405b46;
    padding: 30px 15px;
}

.container {
    max-width: 1000px;
    margin: auto;
}

.header {
    margin-bottom: 30px;
}

.header h1 {
    font-size: 30px;
    color: #405b46;
    margin-bottom: 8px;
}

.header p {
    color: #718472;
}

.card {
    background: white;
    padding: 28px;
    border-radius: 22px;
    box-shadow: 0 10px 35px rgba(64, 91, 70, .08);
    margin-bottom: 24px;
}

.card h2 {
    font-size: 20px;
    margin-bottom: 20px;
}

.item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    padding: 16px 0;
    border-bottom: 1px solid #edf1ec;
}

.item-info {
    flex: 1;
}

.item-info h3 {
    font-size: 16px;
    margin-bottom: 5px;
}

.item-info p {
    font-size: 13px;
    color: #819181;
}

.item-total {
    font-weight: 700;
    white-space: nowrap;
}

.total {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 22px;
    padding-top: 20px;
    border-top: 2px dashed #dce7dc;
}

.total strong {
    font-size: 24px;
    color: #405b46;
}

.payment-option {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 20px;
    border: 2px solid #e3ebe2;
    border-radius: 16px;
    margin-bottom: 15px;
    cursor: pointer;
    transition: .2s;
}

.payment-option:hover {
    border-color: #829b83;
    background: #f8fbf7;
}

.payment-option:has(input:checked) {
    border-color: #405b46;
    background: #edf4ec;
}

.payment-option input {
    accent-color: #405b46;
    width: 18px;
    height: 18px;
}

.payment-icon {
    width: 48px;
    height: 48px;
    background: #edf4ec;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 14px;
    font-size: 23px;
    color: #405b46;
}

.payment-text h3 {
    font-size: 16px;
    margin-bottom: 5px;
}

.payment-text p {
    font-size: 13px;
    color: #819181;
}

.qris-box {
    display: none;
    text-align: center;
    background: #f7faf6;
    border: 1px solid #dce7dc;
    border-radius: 20px;
    padding: 25px;
    margin-top: 20px;
}

.qris-box img {
    width: 100%;
    max-width: 280px;
    height: auto;
    background: white;
    padding: 10px;
    border-radius: 15px;
    margin: 15px auto;
}

.qris-box p {
    line-height: 1.7;
    font-size: 14px;
    color: #718472;
}

.cash-box {
    display: none;
    background: #f7faf6;
    padding: 20px;
    border-radius: 15px;
    margin-top: 20px;
    line-height: 1.7;
    font-size: 14px;
}

.btn {
    display: block;
    width: 100%;
    border: none;
    padding: 16px;
    border-radius: 15px;
    background: #405b46;
    color: white;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    transition: .2s;
    text-decoration: none;
    text-align: center;
}

.btn:hover {
    background: #304635;
    transform: translateY(-2px);
}

.btn-back {
    display: inline-block;
    color: #405b46;
    text-decoration: none;
    margin-bottom: 20px;
    font-weight: 600;
}

.notice {
    background: #fff7e6;
    color: #8a6825;
    padding: 15px;
    border-radius: 12px;
    margin-top: 15px;
    font-size: 13px;
    line-height: 1.7;
}

.alert {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 12px;
    background: #fff0ed;
    color: #a33b2e;
    font-size: 14px;
    line-height: 1.6;
}

@media(max-width:600px) {
    .card {
        padding: 20px;
    }

    .header h1 {
        font-size: 24px;
    }

    .item {
        align-items: flex-start;
    }

    .total strong {
        font-size: 20px;
    }
}
</style>
</head>

<body>

<div class="container">

    <a href="keranjang.php" class="btn-back">
        <i class="bi bi-arrow-left"></i> Kembali ke Keranjang
    </a>

    <div class="header">
        <h1><i class="bi bi-bag-check"></i> Checkout</h1>
        <p>Periksa belanjaan dan pilih metode pembayaran.</p>
    </div>

    <?php if ($pesanCheckout !== ''): ?>
        <div class="alert"><?= htmlspecialchars($pesanCheckout, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if (($_GET['hasil_duitku'] ?? '') === '1'): ?>
        <div class="notice">
            Anda kembali dari Duitku. Status pembayaran akan diperbarui setelah
            TokoKu menerima konfirmasi resmi dari Duitku; silakan cek riwayat transaksi.
        </div>
    <?php endif; ?>

    <!-- RINGKASAN BELANJA -->
    <div class="card">
        <h2><i class="bi bi-cart3"></i> Ringkasan Belanja</h2>

        <?php foreach ($items as $item): ?>
        <div class="item">
            <div class="item-info">
                <h3><?= htmlspecialchars($item['nama_produk']) ?></h3>
                <p>
                    <?= (int)$item['jumlah'] ?> x
                    Rp <?= number_format((float)$item['harga'], 0, ',', '.') ?>
                </p>
            </div>

            <div class="item-total">
                Rp <?= number_format(
                    (float)$item['harga'] * (int)$item['jumlah'],
                    0,
                    ',',
                    '.'
                ) ?>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="total">
            <span>Total Pembayaran</span>
            <strong>Rp <?= number_format($total, 0, ',', '.') ?></strong>
        </div>
    </div>

    <!-- METODE PEMBAYARAN -->
    <div class="card">
        <h2><i class="bi bi-credit-card"></i> Metode Pembayaran</h2>

        <form action="proses_checkout_pelanggan.php" method="POST">
        <input type="hidden" name="csrf_token"
               value="<?= htmlspecialchars($_SESSION['csrf_checkout_pelanggan'], ENT_QUOTES, 'UTF-8') ?>">

        <label class="payment-option">
            <input type="radio" name="metode_pembayaran" value="Duitku" checked>

            <div class="payment-icon">
                <i class="bi bi-shield-check"></i>
            </div>

            <div class="payment-text">
                <h3>Duitku</h3>
                <p>Bayar dengan metode yang tersedia di halaman pembayaran Duitku.</p>
            </div>
        </label>

        <label class="payment-option">
            <input type="radio" name="metode_pembayaran" value="Tunai">

            <div class="payment-icon">
                <i class="bi bi-cash-stack"></i>
            </div>

            <div class="payment-text">
                <h3>Bayar Tunai</h3>
                <p>Bayar langsung secara tunai.</p>
            </div>
        </label>

        <div class="qris-box" id="duitkuBox">
            <h3><i class="bi bi-shield-check"></i> Pembayaran Duitku</h3>
            <p>
                Setelah pesanan dibuat, Anda akan diarahkan ke halaman Duitku
                untuk menyelesaikan pembayaran sebesar
                <strong>Rp <?= number_format($total, 0, ',', '.') ?></strong>.
            </p>
            <div class="notice">
                Status pesanan hanya akan berubah menjadi selesai setelah
                Duitku mengirimkan konfirmasi pembayaran yang valid.
            </div>
        </div>

        <div class="cash-box" id="cashBox">
            <i class="bi bi-cash-stack"></i>
            <strong>Pembayaran Tunai</strong>

            <p>
                Silakan serahkan uang pembayaran kepada kasir
                saat proses transaksi.
            </p>
        </div>

    </div>

        <button class="btn" type="submit" id="submitButton">
        <i class="bi bi-check-circle"></i>
        Lanjut ke Pembayaran Duitku
        </button>
        </form>

</div>

<script>
const duitkuBox = document.getElementById('duitkuBox');
const cashBox = document.getElementById('cashBox');
const submitButton = document.getElementById('submitButton');
const radios = document.querySelectorAll('input[name="metode_pembayaran"]');

function updatePayment() {
    const metode = document.querySelector('input[name="metode_pembayaran"]:checked').value;

    duitkuBox.style.display = metode === 'Duitku' ? 'block' : 'none';
    cashBox.style.display = metode === 'Tunai' ? 'block' : 'none';
    submitButton.innerHTML = metode === 'Duitku'
        ? '<i class="bi bi-check-circle"></i> Lanjut ke Pembayaran Duitku'
        : '<i class="bi bi-check-circle"></i> Buat Pesanan Tunai';
}

radios.forEach(radio => {
    radio.addEventListener('change', updatePayment);
});

updatePayment();
</script>

</body>
</html>
