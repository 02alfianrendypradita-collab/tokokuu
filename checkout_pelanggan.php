
<?php
session_start();
require_once "koneksi.php";

// ===========================================
// PROTEKSI LOGIN PEMBELI
// ===========================================
if (!isset($_SESSION['id_user']) || ($_SESSION['role'] ?? '') !== 'pembeli') {
    header("Location: login_pembeli.php");
    exit;
}

$id_user = (int)$_SESSION['id_user'];

// ===========================================
// AMBIL KERANJANG
// ===========================================
$stmt = mysqli_prepare($conn, "
    SELECT k.id_produk, k.jumlah,
           p.nama_produk, p.harga, p.foto, p.stok
    FROM keranjang k
    JOIN produk p ON k.id_produk = p.id_produk
    WHERE k.id_user = ?
");

mysqli_stmt_bind_param($stmt, "i", $id_user);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$items = [];
$total = 0;

while ($row = mysqli_fetch_assoc($result)) {

    $items[] = $row;

    $total += (float)$row['harga'] * (int)$row['jumlah'];
}

if (empty($items)) {
    header("Location: keranjang.php");
    exit;
}

// ===========================================
// CSRF
// ===========================================
if (empty($_SESSION['csrf_checkout_pelanggan'])) {
    $_SESSION['csrf_checkout_pelanggan'] = bin2hex(random_bytes(32));
}

$pesanCheckout = $_SESSION['pesan_checkout_pelanggan'] ?? '';
unset($_SESSION['pesan_checkout_pelanggan']);

function rupiah($angka) {
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}
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
    margin-bottom: 8px;
}

.header p {
    color: #718472;
}

.card {
    background: white;
    padding: 28px;
    border-radius: 22px;
    box-shadow: 0 10px 35px rgba(64,91,70,.08);
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
}

.payment-text h3 {
    font-size: 16px;
    margin-bottom: 5px;
}

.payment-text p {
    font-size: 13px;
    color: #819181;
}

.payment-box {
    display: none;
    background: #f7faf6;
    border: 1px solid #dce7dc;
    border-radius: 18px;
    padding: 22px;
    margin: 20px 0;
    line-height: 1.7;
}

.payment-box h3 {
    margin-bottom: 10px;
}

textarea {
    width: 100%;
    padding: 15px;
    border: 2px solid #e3ebe2;
    border-radius: 15px;
    font-family: inherit;
    font-size: 14px;
    resize: vertical;
    outline: none;
    transition: .2s;
}

textarea:focus {
    border-color: #405b46;
}

.address-label {
    display: block;
    font-weight: 700;
    margin-bottom: 10px;
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
        <i class="bi bi-arrow-left"></i>
        Kembali ke Keranjang
    </a>

    <div class="header">
        <h1><i class="bi bi-bag-check"></i> Checkout</h1>
        <p>Periksa belanjaan dan pilih metode pembayaran.</p>
    </div>

    <?php if ($pesanCheckout !== ''): ?>
        <div class="alert">
            <?= htmlspecialchars($pesanCheckout, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (($_GET['hasil_duitku'] ?? '') === '1'): ?>
        <div class="notice">
            Anda telah kembali dari halaman pembayaran.
            Status pembayaran akan diperiksa melalui konfirmasi Duitku.
            Silakan periksa riwayat pembelian.
        </div>
    <?php endif; ?>

    <!-- RINGKASAN -->
    <div class="card">

        <h2><i class="bi bi-cart3"></i> Ringkasan Belanja</h2>

        <?php foreach ($items as $item): ?>

            <div class="item">

                <div class="item-info">

                    <h3>
                        <?= htmlspecialchars($item['nama_produk'], ENT_QUOTES, 'UTF-8') ?>
                    </h3>

                    <p>
                        <?= (int)$item['jumlah'] ?> x
                        <?= rupiah($item['harga']) ?>
                    </p>

                </div>

                <div class="item-total">

                    <?= rupiah(
                        (float)$item['harga'] * (int)$item['jumlah']
                    ) ?>

                </div>

            </div>

        <?php endforeach; ?>

        <div class="total">

            <span>Total Pembayaran</span>

            <strong><?= rupiah($total) ?></strong>

        </div>

    </div>

    <!-- PEMBAYARAN -->
    <div class="card">

        <h2><i class="bi bi-credit-card"></i> Checkout Pesanan</h2>

        <form action="proses_checkout_pelanggan.php" method="POST">

            <input type="hidden"
                   name="csrf_token"
                   value="<?= htmlspecialchars($_SESSION['csrf_checkout_pelanggan'], ENT_QUOTES, 'UTF-8') ?>">

            <!-- ALAMAT -->
            <div style="margin-bottom:25px;">

                <label class="address-label" for="alamat_pengiriman">
                    <i class="bi bi-geo-alt"></i>
                    Alamat Pengiriman
                </label>

                <textarea
                    id="alamat_pengiriman"
                    name="alamat_pengiriman"
                    rows="4"
                    required
                    placeholder="Masukkan alamat lengkap, nama jalan, desa, kecamatan, kota, dan kode pos..."
                ></textarea>

            </div>

            <!-- DUITKU -->
            <label class="payment-option">

                <input type="radio"
                       name="metode_pembayaran"
                       value="Duitku"
                       checked>

                <div class="payment-icon">
                    <i class="bi bi-shield-check"></i>
                </div>

                <div class="payment-text">

                    <h3>Duitku Payment Gateway</h3>

                    <p>
                        Bayar melalui Virtual Account,
                        QRIS, transfer, dan metode
                        yang tersedia di Duitku.
                    </p>

                </div>

            </label>

            <!-- TUNAI -->
            <label class="payment-option">

                <input type="radio"
                       name="metode_pembayaran"
                       value="Tunai">

                <div class="payment-icon">
                    <i class="bi bi-cash-stack"></i>
                </div>

                <div class="payment-text">

                    <h3>Bayar Tunai</h3>

                    <p>
                        Pembayaran secara tunai
                        sesuai prosedur toko.
                    </p>

                </div>

            </label>

            <!-- INFO DUITKU -->
            <div class="payment-box" id="duitkuBox">

                <h3>
                    <i class="bi bi-shield-check"></i>
                    Pembayaran Duitku
                </h3>

                <p>
                    Anda akan diarahkan ke halaman
                    pembayaran Duitku setelah pesanan dibuat.
                </p>

                <div class="notice">

                    Total pembayaran:

                    <strong><?= rupiah($total) ?></strong>

                    <br>

                    Status pesanan akan diperbarui
                    setelah pembayaran dikonfirmasi
                    melalui callback Duitku.

                </div>

            </div>

            <!-- INFO TUNAI -->
            <div class="payment-box" id="cashBox">

                <h3>
                    <i class="bi bi-cash-stack"></i>
                    Pembayaran Tunai
                </h3>

                <p>
                    Pesanan akan dibuat dengan status menunggu.
                    Silakan ikuti prosedur pembayaran tunai
                    yang berlaku di TokoKu.
                </p>

            </div>

            <button class="btn" type="submit" id="submitButton">

                <i class="bi bi-check-circle"></i>
                Lanjut ke Pembayaran Duitku

            </button>

        </form>

    </div>

</div>

<script>

const duitkuBox = document.getElementById('duitkuBox');
const cashBox = document.getElementById('cashBox');
const submitButton = document.getElementById('submitButton');

const radios = document.querySelectorAll(
    'input[name="metode_pembayaran"]'
);

function updatePayment() {

    const selected = document.querySelector(
        'input[name="metode_pembayaran"]:checked'
    );

    if (!selected) return;

    const metode = selected.value;

    duitkuBox.style.display =
        metode === 'Duitku' ? 'block' : 'none';

    cashBox.style.display =
        metode === 'Tunai' ? 'block' : 'none';

    submitButton.innerHTML = metode === 'Duitku'
        ? '<i class="bi bi-shield-check"></i> Lanjut ke Pembayaran Duitku'
        : '<i class="bi bi-cash-stack"></i> Buat Pesanan Tunai';
}

radios.forEach(function(radio) {
    radio.addEventListener('change', updatePayment);
});

updatePayment();

</script>

</body>
</html>
