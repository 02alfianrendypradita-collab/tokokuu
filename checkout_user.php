
<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['id_user'])) {
    header("Location: login_pembeli.php");
    exit;
}

if (($_SESSION['role'] ?? '') !== 'pembeli') {
    header("Location: login_pembeli.php");
    exit;
}

$id_user = (int) $_SESSION['id_user'];
$nama = $_SESSION['nama'] ?? 'Pembeli';

// Ambil keranjang
$stmt = mysqli_prepare($conn, "
    SELECT k.jumlah, p.nama_produk, p.harga
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
    $subtotal = (float)$row['harga'] * (int)$row['jumlah'];
    $row['subtotal'] = $subtotal;
    $total += $subtotal;
    $items[] = $row;
}

function rupiah($angka) {
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Checkout - TokoKu</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
* {
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Segoe UI',sans-serif;
}

body {
    background:#f6f8f4;
    color:#344638;
}

.sidebar {
    width:250px;
    height:100vh;
    position:fixed;
    background:linear-gradient(180deg,#405b46,#304735);
    padding:28px 18px;
    color:white;
}

.brand {
    display:flex;
    align-items:center;
    gap:12px;
    padding:0 10px 30px;
    border-bottom:1px solid #ffffff30;
}

.brand-icon {
    width:43px;
    height:43px;
    background:#829b83;
    border-radius:13px;
    display:flex;
    justify-content:center;
    align-items:center;
    font-size:23px;
}

.brand h2 {
    font-size:22px;
}

.brand p {
    font-size:11px;
    color:#cbd9ca;
}

.menu-title {
    margin:30px 12px 12px;
    font-size:11px;
    color:#b8c9b8;
}

.menu a {
    display:flex;
    align-items:center;
    gap:13px;
    padding:14px;
    margin-bottom:7px;
    color:#dce8dc;
    text-decoration:none;
    border-radius:12px;
    font-size:14px;
}

.menu a:hover,
.menu a.active {
    background:#ffffff25;
    color:white;
}

.menu a i {
    font-size:19px;
}

.logout {
    position:absolute;
    bottom:25px;
    left:18px;
    right:18px;
}

.logout a {
    display:block;
    padding:14px;
    color:#ffe1dc;
    background:#ffffff15;
    text-decoration:none;
    border-radius:10px;
}

.main {
    margin-left:250px;
    padding:35px;
}

.topbar {
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:30px;
}

.topbar h2 {
    color:#405b46;
}

.topbar p {
    color:#899087;
    font-size:13px;
    margin-top:5px;
}

.profile {
    font-weight:bold;
    color:#405b46;
}

.checkout-container {
    display:grid;
    grid-template-columns:1fr 380px;
    gap:25px;
    align-items:start;
}

.card {
    background:white;
    padding:25px;
    border-radius:18px;
    box-shadow:0 5px 20px #405b4610;
}

.card h3 {
    margin-bottom:20px;
    color:#405b46;
}

.item {
    display:flex;
    justify-content:space-between;
    gap:15px;
    padding:15px 0;
    border-bottom:1px solid #edf0eb;
    font-size:13px;
}

.item small {
    display:block;
    color:#899087;
    margin-top:5px;
}

.item strong {
    white-space:nowrap;
}

.total {
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding-top:20px;
    margin-top:10px;
}

.total strong {
    font-size:22px;
    color:#405b46;
}

.payment-option {
    border:1.5px solid #e5ebe3;
    border-radius:14px;
    padding:18px;
    margin-bottom:15px;
    cursor:pointer;
    transition:.25s;
}

.payment-option:hover {
    border-color:#829b83;
    background:#f9fbf8;
}

.payment-option.selected {
    border-color:#405b46;
    background:#edf4ec;
}

.payment-option label {
    display:flex;
    align-items:center;
    gap:12px;
    cursor:pointer;
}

.payment-option input {
    accent-color:#405b46;
}

.payment-icon {
    font-size:25px;
}

.payment-option strong {
    display:block;
    font-size:14px;
}

.payment-option small {
    display:block;
    color:#899087;
    margin-top:5px;
    font-size:12px;
}

.qr-box {
    display:none;
    text-align:center;
    background:#f7f8f4;
    padding:20px;
    border-radius:15px;
    margin-top:20px;
}

.qr-box.show {
    display:block;
}

.qr-box img {
    width:100%;
    max-width:280px;
    border-radius:12px;
    background:white;
    padding:10px;
}

.qr-box h4 {
    margin:15px 0 8px;
    color:#405b46;
}

.qr-box p {
    font-size:12px;
    color:#899087;
    line-height:1.7;
}

.btn {
    display:block;
    width:100%;
    text-align:center;
    padding:14px;
    border:none;
    border-radius:12px;
    font-weight:700;
    cursor:pointer;
    text-decoration:none;
    margin-top:15px;
    font-size:14px;
}

.btn-primary {
    background:#405b46;
    color:white;
}

.btn-primary:hover {
    background:#304735;
}

.btn-back {
    background:#edf3eb;
    color:#405b46;
}

.info {
    font-size:12px;
    line-height:1.7;
    color:#899087;
    margin-top:15px;
}

.empty {
    text-align:center;
    padding:50px;
}

@media(max-width:1000px) {
    .checkout-container {
        grid-template-columns:1fr;
    }
}

@media(max-width:850px) {
    .sidebar {
        width:75px;
        padding:20px 10px;
    }

    .brand {
        justify-content:center;
        padding:0 0 25px;
    }

    .brand-text,
    .menu-title,
    .menu a span,
    .logout span {
        display:none;
    }

    .menu a,
    .logout a {
        text-align:center;
        justify-content:center;
        padding:14px 5px;
    }

    .main {
        margin-left:75px;
        padding:20px;
    }
}

@media(max-width:550px) {
    .main {
        padding:15px 10px;
    }

    .card {
        padding:17px;
    }

    .topbar h2 {
        font-size:18px;
    }
}
</style>
</head>

<body>

<div class="sidebar">

    <div class="brand">
        <div class="brand-icon">
            <i class="bi bi-bag-heart-fill"></i>
        </div>

        <div class="brand-text">
            <h2>TokoKu</h2>
            <p>Customer Panel</p>
        </div>
    </div>

    <div class="menu-title">MENU UTAMA</div>

    <div class="menu">
        <a href="dashboard.php">
            <i class="bi bi-grid"></i>
            <span>Dashboard</span>
        </a>

        <a href="dashboard.php#produk">
            <i class="bi bi-shop"></i>
            <span>Produk</span>
        </a>

        <a href="keranjang.php">
            <i class="bi bi-cart3"></i>
            <span>Keranjang</span>
        </a>

        <a href="dashboard.php#riwayat">
            <i class="bi bi-clock-history"></i>
            <span>Riwayat Pembelian</span>
        </a>
    </div>

    <div class="logout">
        <a href="logout.php">
            <i class="bi bi-box-arrow-left"></i>
            <span> Logout</span>
        </a>
    </div>

</div>

<div class="main">

    <div class="topbar">
        <div>
            <h2>Checkout</h2>
            <p>Periksa pesanan dan pilih metode pembayaran.</p>
        </div>

        <div class="profile">
            <i class="bi bi-person-circle"></i>
            <?= htmlspecialchars($nama, ENT_QUOTES, 'UTF-8') ?>
        </div>
    </div>

    <?php if (count($items) > 0): ?>

    <div class="checkout-container">

        <!-- RINGKASAN -->
        <div class="card">

            <h3><i class="bi bi-bag-check"></i> Ringkasan Pesanan</h3>

            <?php foreach($items as $item): ?>

                <div class="item">
                    <div>
                        <strong>
                            <?= htmlspecialchars($item['nama_produk'], ENT_QUOTES, 'UTF-8') ?>
                        </strong>

                        <small>
                            <?= (int)$item['jumlah'] ?> x
                            <?= rupiah($item['harga']) ?>
                        </small>
                    </div>

                    <strong><?= rupiah($item['subtotal']) ?></strong>
                </div>

            <?php endforeach; ?>

            <div class="total">
                <span>Total Pembayaran</span>
                <strong><?= rupiah($total) ?></strong>
            </div>

            <a href="keranjang.php" class="btn btn-back">
                <i class="bi bi-arrow-left"></i>
                Kembali ke Keranjang
            </a>

        </div>

        <!-- PEMBAYARAN -->
        <div class="card">

            <h3><i class="bi bi-credit-card"></i> Metode Pembayaran</h3>

            <div class="payment-option selected" id="optionQris">
                <label>
                    <input type="radio" name="payment" value="qris" checked>
                    <div class="payment-icon">▦</div>
                    <div>
                        <strong>QRIS / Barcode</strong>
                        <small>Bayar menggunakan scan QR</small>
                    </div>
                </label>
            </div>

            <div class="payment-option" id="optionCash">
                <label>
                    <input type="radio" name="payment" value="cash">
                    <div class="payment-icon">💵</div>
                    <div>
                        <strong>Bayar Tunai</strong>
                        <small>Bayar langsung kepada petugas</small>
                    </div>
                </label>
            </div>

            <div class="qr-box show" id="qrBox">

                <h4>Scan QRIS TokoKu</h4>

                <img
                    src="qris_tokoku.png"
                    alt="QRIS TokoKu"
                    onerror="this.style.display='none'; document.getElementById('qrMissing').style.display='block';"
                >

                <div id="qrMissing" style="display:none">
                    <div style="font-size:50px;margin:20px">▦</div>
                    <p>File QRIS toko belum tersedia.</p>
                </div>

                <p style="margin-top:15px">
                    Silakan scan QRIS menggunakan aplikasi
                    pembayaran yang mendukung QRIS.
                </p>

                <div style="margin-top:15px;font-weight:bold;color:#405b46">
                    <?= rupiah($total) ?>
                </div>

                <p style="margin-top:12px">
                    Pastikan nominal pembayaran sesuai dengan total pesanan.
                </p>

            </div>

            <div id="cashInfo" class="qr-box">
                <h4>Bayar Tunai</h4>
                <p>
                    Silakan tunjukkan pesanan Anda kepada petugas
                    dan lakukan pembayaran secara langsung.
                </p>
                <div style="margin-top:15px;font-weight:bold;color:#405b46">
                    <?= rupiah($total) ?>
                </div>
            </div>

            <button type="button" class="btn btn-primary" id="confirmButton">
                <i class="bi bi-check-circle"></i>
                Konfirmasi Pilihan
            </button>

            <p class="info">
                Pilihan metode pembayaran ini belum menyimpan transaksi
                atau memverifikasi pembayaran. Konfirmasi pembayaran
                perlu diproses melalui sistem transaksi TokoKu.
            </p>

        </div>

    </div>

    <?php else: ?>

        <div class="card empty">
            <i class="bi bi-cart-x" style="font-size:50px"></i>
            <h3 style="margin:15px 0">Keranjang Kosong</h3>
            <p>Tambahkan produk sebelum melakukan checkout.</p>

            <a href="dashboard.php#produk" class="btn btn-primary">
                Mulai Belanja
            </a>
        </div>

    <?php endif; ?>

</div>

<script>
const optionQris = document.getElementById('optionQris');
const optionCash = document.getElementById('optionCash');
const qrBox = document.getElementById('qrBox');
const cashInfo = document.getElementById('cashInfo');
const confirmButton = document.getElementById('confirmButton');

const radios = document.querySelectorAll('input[name="payment"]');

function updatePayment() {
    const payment = document.querySelector('input[name="payment"]:checked').value;

    optionQris.classList.toggle('selected', payment === 'qris');
    optionCash.classList.toggle('selected', payment === 'cash');

    qrBox.classList.toggle('show', payment === 'qris');
    cashInfo.classList.toggle('show', payment === 'cash');

    if (payment === 'qris') {
        confirmButton.innerHTML = '<i class="bi bi-check-circle"></i> Saya Mengerti, Lanjut';
    } else {
        confirmButton.innerHTML = '<i class="bi bi-check-circle"></i> Pilih Pembayaran Tunai';
    }
}

radios.forEach(radio => {
    radio.addEventListener('change', updatePayment);
});

confirmButton.addEventListener('click', function() {
    const payment = document.querySelector('input[name="payment"]:checked').value;

    if (payment === 'qris') {
        alert('Silakan scan QRIS TokoKu. Pembayaran belum diverifikasi atau dicatat sebagai transaksi.');
    } else {
        alert('Anda memilih pembayaran tunai. Silakan lanjutkan pembayaran kepada petugas.');
    }
});
</script>

</body>
</html>
