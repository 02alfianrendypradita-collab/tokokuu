<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit;
}

if (!in_array($_SESSION['role'] ?? '', ['petugas', 'kasir', 'admin'], true)) {
    header("Location: login.php");
    exit;
}

function ambilAngka($conn, $sql) {
    $result = mysqli_query($conn, $sql);
    if (!$result) return 0;

    $data = mysqli_fetch_assoc($result);
    return $data['total'] ?? 0;
}

$total_transaksi = ambilAngka(
    $conn,
    "SELECT COUNT(*) AS total FROM transaksi
     WHERE DATE(tanggal_transaksi) = CURDATE()"
);

$total_penjualan = ambilAngka(
    $conn,
    "SELECT COALESCE(SUM(total_harga),0) AS total FROM transaksi
     WHERE DATE(tanggal_transaksi) = CURDATE()
     AND status = 'selesai'"
);

$total_barang = ambilAngka(
    $conn,
    "SELECT COALESCE(SUM(dt.jumlah),0) AS total
     FROM detail_transaksi dt
     JOIN transaksi t ON dt.id_transaksi = t.id_transaksi
     WHERE DATE(t.tanggal_transaksi) = CURDATE()
     AND t.status = 'selesai'"
);

$nama_kasir = $_SESSION['nama'] ?? 'Kasir';

$query_terbaru = mysqli_query(
    $conn,
    "SELECT id_transaksi, tanggal_transaksi,
            total_harga, metode_pembayaran, status
     FROM transaksi
     WHERE id_user = " . (int)$_SESSION['id_user'] . "
     ORDER BY id_transaksi DESC
     LIMIT 5"
);
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Kasir - TokoKu</title>

<style>
* {
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Segoe UI',sans-serif;
}

body {
    background:#f7f8f4;
    color:#405b46;
}

.wrapper {
    display:flex;
    min-height:100vh;
}

.sidebar {
    width:250px;
    background:white;
    position:fixed;
    height:100vh;
    padding:28px 20px;
    display:flex;
    flex-direction:column;
    box-shadow:4px 0 25px rgba(50,75,55,.05);
}

.brand {
    font-size:27px;
    font-weight:800;
    color:#405b46;
    text-decoration:none;
    padding:0 12px;
}

.brand span {color:#91aa91;}

.admin-label {
    font-size:11px;
    color:#9ba59a;
    letter-spacing:2px;
    padding:0 13px;
    margin:8px 0 35px;
}

.menu-title {
    font-size:11px;
    color:#a4ada2;
    font-weight:700;
    letter-spacing:1.5px;
    padding:0 13px;
    margin-bottom:13px;
}

.menu {
    display:flex;
    flex-direction:column;
    gap:8px;
}

.menu a,.logout {
    display:flex;
    align-items:center;
    gap:13px;
    padding:14px;
    border-radius:12px;
    text-decoration:none;
    color:#788579;
    font-size:14px;
    font-weight:600;
    transition:.3s;
}

.menu a:hover,.menu a.active {
    background:#edf4ec;
    color:#405b46;
    transform:translateX(3px);
}

.icon {font-size:20px;width:23px;text-align:center;}

.sidebar-bottom {
    margin-top:auto;
    border-top:1px solid #edf0eb;
    padding-top:20px;
}

.logout {color:#c46c60;}
.logout:hover {background:#fff0ed;}

.main {
    width:calc(100% - 250px);
    margin-left:250px;
    padding:32px 38px;
}

.topbar {
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:15px;
    margin-bottom:30px;
}

.topbar h1 {font-size:26px;}
.topbar p {font-size:13px;color:#99a196;margin-top:5px;}

.profile {
    display:flex;
    align-items:center;
    gap:12px;
    background:white;
    padding:9px 15px;
    border-radius:14px;
    box-shadow:0 4px 15px rgba(50,75,55,.04);
}

.avatar {
    width:40px;
    height:40px;
    background:linear-gradient(135deg,#829b83,#405b46);
    color:white;
    border-radius:12px;
    display:grid;
    place-items:center;
    font-weight:800;
}

.profile strong {font-size:13px;}
.profile small {display:block;color:#9ba59a;}

.welcome {
    padding:30px;
    color:white;
    border-radius:22px;
    background:linear-gradient(120deg,#405b46,#829b83);
    margin-bottom:28px;
    box-shadow:0 10px 25px rgba(64,91,70,.15);
}

.welcome h2 {font-size:24px;margin-bottom:10px;}
.welcome p {font-size:13px;opacity:.85;}

.section-title {
    font-size:18px;
    margin-bottom:18px;
}

.stats {
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:18px;
    margin-bottom:30px;
}

.stat-card {
    background:white;
    border-radius:17px;
    padding:23px;
    box-shadow:0 5px 20px rgba(50,75,55,.04);
    transition:.3s;
}

.stat-card:hover {transform:translateY(-5px);}

.stat-icon {
    width:45px;
    height:45px;
    display:grid;
    place-items:center;
    border-radius:13px;
    font-size:22px;
    margin-bottom:18px;
}

.green {background:#edf5ec;}
.peach {background:#fff0e9;}
.yellow {background:#fff7e8;}

.stat-card p {font-size:12px;color:#98a196;margin-bottom:8px;}
.stat-card h3 {font-size:25px;}

.quick-menu {
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:16px;
    margin-bottom:30px;
}

.quick-card {
    padding:22px;
    background:white;
    border-radius:17px;
    text-decoration:none;
    color:#405b46;
    box-shadow:0 5px 20px rgba(50,75,55,.04);
    transition:.3s;
}

.quick-card:hover {
    transform:translateY(-5px);
    box-shadow:0 10px 25px rgba(50,75,55,.09);
}

.quick-card .icon-big {font-size:28px;margin-bottom:14px;}
.quick-card h3 {font-size:14px;margin-bottom:7px;}
.quick-card p {font-size:11px;color:#9ba59a;line-height:1.5;}

.table-card {
    background:white;
    padding:24px;
    border-radius:18px;
    box-shadow:0 5px 20px rgba(50,75,55,.04);
}

.table-header {
    display:flex;
    justify-content:space-between;
    margin-bottom:20px;
}

.table-header a {
    color:#829b83;
    text-decoration:none;
    font-size:12px;
    font-weight:700;
}

.table-responsive {overflow-x:auto;}

table {
    width:100%;
    min-width:600px;
    border-collapse:collapse;
}

th {
    background:#f8faf6;
    color:#9ba59a;
    font-size:11px;
    text-align:left;
    padding:14px;
}

td {
    padding:15px 14px;
    font-size:12px;
    border-bottom:1px solid #f0f2ee;
}

.status {
    padding:7px 12px;
    border-radius:20px;
    background:#e2f6e7;
    color:#238344;
    font-size:10px;
    font-weight:700;
}

.empty {text-align:center;color:#9ba59a;padding:30px;}

.footer {
    text-align:center;
    color:#a1aaa0;
    font-size:11px;
    padding:25px 0 5px;
}

@media(max-width:1000px) {
    .stats {grid-template-columns:repeat(2,1fr);}
    .quick-menu {grid-template-columns:repeat(2,1fr);}
}

@media(max-width:750px) {
    .sidebar {width:75px;padding:22px 10px;}
    .brand {font-size:17px;padding:0;text-align:center;}
    .admin-label,.menu-title,
    .menu a span:not(.icon),
    .logout span:not(.icon) {display:none;}
    .menu a,.logout {justify-content:center;padding:14px 5px;}
    .main {width:calc(100% - 75px);margin-left:75px;padding:22px 15px;}
    .topbar h1 {font-size:20px;}
    .profile-info {display:none;}
}

@media(max-width:450px) {
    .stats,.quick-menu {grid-template-columns:1fr 1fr;gap:10px;}
    .stat-card {padding:15px;}
    .stat-card h3 {font-size:19px;}
    .welcome {padding:23px 18px;}
    .welcome h2 {font-size:19px;}
    .table-card {padding:15px;}
}
</style>
</head>

<body>
<div class="wrapper">

<aside class="sidebar">

    <a href="dashboard_kasir.php" class="brand">Toko<span>Ku.</span></a>

    <div class="admin-label">CASHIER PANEL</div>

    <div class="menu-title">MENU KASIR</div>

    <nav class="menu">
        <a href="dashboard_kasir.php" class="active">
            <span class="icon">⌂</span>
            <span>Dashboard</span>
        </a>

        <a href="transaksi_kasir.php">
            <span class="icon">🛒</span>
            <span>Transaksi Penjualan</span>
        </a>

        <a href="tambah_barang_kasir.php">
            <span class="icon">📦</span>
            <span>Tambah Barang</span>
        </a>

        <a href="riwayat_transaksi.php">
            <span class="icon">🧾</span>
            <span>Riwayat Transaksi</span>
        </a>
    </nav>

    <div class="sidebar-bottom">
        <a href="logout.php" class="logout">
            <span class="icon">↪</span>
            <span>Keluar</span>
        </a>
    </div>

</aside>

<main class="main">

    <header class="topbar">
        <div>
            <h1>Dashboard Kasir</h1>
            <p>Selamat bekerja! Kelola transaksi penjualan TokoKu.</p>
        </div>

        <div class="profile">
            <div class="avatar">
                <?= strtoupper(substr($nama_kasir,0,1)) ?>
            </div>
            <div class="profile-info">
                <strong><?= htmlspecialchars($nama_kasir) ?></strong>
                <small>Kasir TokoKu</small>
            </div>
        </div>
    </header>

    <section class="welcome">
        <h2>Halo, <?= htmlspecialchars($nama_kasir) ?>! 👋</h2>
        <p>Selamat datang di sistem kasir TokoKu. Siap melayani pelanggan hari ini?</p>
    </section>

    <h2 class="section-title">Ringkasan Penjualan Hari Ini</h2>

    <section class="stats">

        <div class="stat-card">
            <div class="stat-icon green">🛍️</div>
            <p>Transaksi Hari Ini</p>
            <h3><?= number_format($total_transaksi) ?></h3>
        </div>

        <div class="stat-card">
            <div class="stat-icon peach">📦</div>
            <p>Barang Terjual</p>
            <h3><?= number_format($total_barang) ?></h3>
        </div>

        <div class="stat-card">
            <div class="stat-icon yellow">💰</div>
            <p>Pendapatan Hari Ini</p>
            <h3 style="font-size:21px">
                Rp <?= number_format($total_penjualan,0,',','.') ?>
            </h3>
        </div>

    </section>

    <h2 class="section-title">Menu Kasir</h2>

    <section class="quick-menu">

        <a href="transaksi_kasir.php" class="quick-card">
            <div class="icon-big">🛒</div>
            <h3>Transaksi Baru</h3>
            <p>Mulai transaksi dan scan barcode produk.</p>
        </a>

        <a href="tambah_barang_kasir.php" class="quick-card">
            <div class="icon-big">📦</div>
            <h3>Tambah Barang</h3>
            <p>Tambahkan barang baru menggunakan scanner barcode.</p>
        </a>

        <a href="nota_admin.php" class="quick-card">
            <div class="icon-big">🧾</div>
            <h3>Riwayat Transaksi</h3>
            <p>Lihat nota dan transaksi penjualan.</p>
        </a>

    </section>

    <section class="table-card">

        <div class="table-header">
            <h2>Transaksi Terbaru Saya</h2>
            <a href="nota_admin.php">Lihat Semua →</a>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID TRANSAKSI</th>
                        <th>TANGGAL</th>
                        <th>PEMBAYARAN</th>
                        <th>TOTAL</th>
                        <th>STATUS</th>
                    </tr>
                </thead>

                <tbody>
                <?php if ($query_terbaru && mysqli_num_rows($query_terbaru)>0): ?>

                    <?php while($row=mysqli_fetch_assoc($query_terbaru)): ?>
                    <tr>
                        <td>#<?= htmlspecialchars($row['id_transaksi']) ?></td>
                        <td><?= htmlspecialchars($row['tanggal_transaksi']) ?></td>
                        <td><?= htmlspecialchars($row['metode_pembayaran']) ?></td>
                        <td>Rp <?= number_format($row['total_harga'],0,',','.') ?></td>
                        <td><span class="status"><?= htmlspecialchars($row['status']) ?></span></td>
                    </tr>
                    <?php endwhile; ?>

                <?php else: ?>
                    <tr><td colspan="5" class="empty">Belum ada transaksi.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <div class="footer">© 2026 TokoKu. Cashier System.</div>

</main>
</div>
</body>
</html>