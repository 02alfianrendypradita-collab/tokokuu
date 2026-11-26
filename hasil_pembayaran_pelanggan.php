
<?php
session_start();
require_once "koneksi.php";

// =============================================
// PROTEKSI PEMBELI
// =============================================
if (!isset($_SESSION['id_user']) || ($_SESSION['role'] ?? '') !== 'pembeli') {
    header("Location: login_pembeli.php");
    exit;
}

$id_user = (int)$_SESSION['id_user'];

// =============================================
// AMBIL ORDER ID DARI DUITKU
// =============================================
$merchantOrderId = $_GET['merchantOrderId'] ?? '';

if (!preg_match('/^TK-(\d+)$/', $merchantOrderId, $matches)) {
    $pesan = "Nomor pesanan tidak valid.";
    $transaksi = null;
} else {

    $id_transaksi = (int)$matches[1];

    // =============================================
    // AMBIL TRANSAKSI MILIK PEMBELI
    // =============================================
    $stmt = mysqli_prepare($conn, "
        SELECT id_transaksi, id_user, total_harga,
               tanggal_transaksi, status, metode_pembayaran
        FROM transaksi
        WHERE id_transaksi = ?
          AND id_user = ?
        LIMIT 1
    ");

    mysqli_stmt_bind_param(
        $stmt,
        "ii",
        $id_transaksi,
        $id_user
    );

    mysqli_stmt_execute($stmt);

    $transaksi = mysqli_fetch_assoc(
        mysqli_stmt_get_result($stmt)
    );

    if (!$transaksi) {
        $pesan = "Pesanan tidak ditemukan.";
    }
}

function rupiah($angka) {
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

$status = $transaksi['status'] ?? '';

if ($status === 'diproses') {
    $judul = "Pembayaran Berhasil";
    $deskripsi = "Pembayaran telah dikonfirmasi. Pesanan Anda sedang diproses.";
    $ikon = "bi-check-circle-fill";
} elseif ($status === 'menunggu') {
    $judul = "Menunggu Pembayaran";
    $deskripsi = "Pesanan telah dibuat. Pembayaran masih menunggu konfirmasi.";
    $ikon = "bi-clock-fill";
} elseif ($status === 'dikirim') {
    $judul = "Pesanan Dikirim";
    $deskripsi = "Pesanan Anda sedang dalam perjalanan.";
    $ikon = "bi-truck";
} elseif ($status === 'selesai') {
    $judul = "Pesanan Selesai";
    $deskripsi = "Pesanan telah selesai.";
    $ikon = "bi-check-circle-fill";
} elseif ($status === 'dibatalkan') {
    $judul = "Pesanan Dibatalkan";
    $deskripsi = "Pesanan ini telah dibatalkan.";
    $ikon = "bi-x-circle-fill";
} else {
    $judul = "Status Pesanan";
    $deskripsi = "Silakan periksa riwayat pembelian Anda.";
    $ikon = "bi-bag-check";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Hasil Pembayaran | TokoKu</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: 'Segoe UI', Arial, sans-serif;
    background: #edf4ec;
    color: #405b46;
    padding: 25px;
}

.container {
    max-width: 650px;
    margin: 40px auto;
}

.card {
    background: white;
    border-radius: 25px;
    padding: 35px;
    box-shadow: 0 10px 35px rgba(64,91,70,.08);
    text-align: center;
}

.icon {
    width: 90px;
    height: 90px;
    background: #edf4ec;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    font-size: 42px;
}

h1 {
    font-size: 28px;
    margin-bottom: 12px;
}

p {
    line-height: 1.7;
    color: #718472;
}

.detail {
    text-align: left;
    margin-top: 30px;
    padding: 20px;
    background: #f7faf6;
    border-radius: 16px;
}

.detail div {
    display: flex;
    justify-content: space-between;
    gap: 15px;
    padding: 12px 0;
    border-bottom: 1px solid #e3ebe2;
}

.detail div:last-child {
    border-bottom: none;
}

.detail span {
    color: #718472;
}

.detail strong {
    text-align: right;
    word-break: break-word;
}

.btn {
    display: block;
    width: 100%;
    padding: 16px;
    background: #405b46;
    color: white;
    border-radius: 15px;
    text-decoration: none;
    font-weight: 700;
    margin-top: 25px;
    transition: .2s;
}

.btn:hover {
    background: #304635;
}

.btn-secondary {
    background: #edf4ec;
    color: #405b46;
}

.btn-secondary:hover {
    background: #dce7dc;
}

@media(max-width:600px) {
    .card {
        padding: 25px 20px;
    }

    h1 {
        font-size: 24px;
    }

    .detail div {
        font-size: 14px;
    }
}

</style>

</head>

<body>

<div class="container">

    <div class="card">

        <?php if (!$transaksi): ?>

            <div class="icon">
                <i class="bi bi-exclamation-circle"></i>
            </div>

            <h1>Pesanan Tidak Ditemukan</h1>

            <p><?= htmlspecialchars($pesan ?? 'Pesanan tidak ditemukan.') ?></p>

            <a href="keranjang.php" class="btn">
                Kembali ke Keranjang
            </a>

        <?php else: ?>

            <div class="icon">
                <i class="bi <?= htmlspecialchars($ikon) ?>"></i>
            </div>

            <h1><?= htmlspecialchars($judul) ?></h1>

            <p><?= htmlspecialchars($deskripsi) ?></p>

            <div class="detail">

                <div>
                    <span>Nomor Pesanan</span>
                    <strong>
                        TK-<?= (int)$transaksi['id_transaksi'] ?>
                    </strong>
                </div>

                <div>
                    <span>Total Pembayaran</span>
                    <strong>
                        <?= rupiah($transaksi['total_harga']) ?>
                    </strong>
                </div>

                <div>
                    <span>Metode Pembayaran</span>
                    <strong>
                        <?= htmlspecialchars($transaksi['metode_pembayaran']) ?>
                    </strong>
                </div>

                <div>
                    <span>Status</span>
                    <strong>
                        <?= htmlspecialchars(ucfirst($transaksi['status'])) ?>
                    </strong>
                </div>

                <div>
                    <span>Tanggal</span>
                    <strong>
                        <?= htmlspecialchars($transaksi['tanggal_transaksi'] ?? '-') ?>
                    </strong>
                </div>

            </div>

            <a href="riwayat_pembelian.php" class="btn">
                <i class="bi bi-clock-history"></i>
                Lihat Riwayat Pembelian
            </a>

            <a href="dashboard_pembeli.php" class="btn btn-secondary">
                <i class="bi bi-house"></i>
                Kembali ke Dashboard
            </a>

        <?php endif; ?>

    </div>

</div>

</body>
</html>
