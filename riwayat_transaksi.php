
<?php
session_start();
require_once 'koneksi.php';

// Proteksi login
if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit;
}

$id_user = (int) $_SESSION['id_user'];
$nama_kasir = $_SESSION['nama'] ?? 'Kasir';

// ================================
// STATISTIK TRANSAKSI KASIR
// ================================

$stmt = mysqli_prepare($conn, "
    SELECT COUNT(*) AS total
    FROM transaksi
    WHERE id_user = ?
    AND DATE(tanggal_transaksi) = CURDATE()
");
mysqli_stmt_bind_param($stmt, "i", $id_user);
mysqli_stmt_execute($stmt);
$data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
$total_transaksi_hari_ini = $data['total'] ?? 0;

// Pendapatan hari ini
$stmt = mysqli_prepare($conn, "
    SELECT COALESCE(SUM(total_harga),0) AS total
    FROM transaksi
    WHERE id_user = ?
    AND DATE(tanggal_transaksi) = CURDATE()
    AND status = 'selesai'
");
mysqli_stmt_bind_param($stmt, "i", $id_user);
mysqli_stmt_execute($stmt);
$data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
$total_pendapatan_hari_ini = $data['total'] ?? 0;

// Total transaksi milik kasir
$stmt = mysqli_prepare($conn, "
    SELECT COUNT(*) AS total
    FROM transaksi
    WHERE id_user = ?
");
mysqli_stmt_bind_param($stmt, "i", $id_user);
mysqli_stmt_execute($stmt);
$data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
$total_semua_transaksi = $data['total'] ?? 0;

// ================================
// DAFTAR RIWAYAT TRANSAKSI
// ================================

$stmt = mysqli_prepare($conn, "
    SELECT id_transaksi,
           tanggal_transaksi,
           total_harga,
           metode_pembayaran,
           status
    FROM transaksi
    WHERE id_user = ?
    ORDER BY id_transaksi DESC
");

mysqli_stmt_bind_param($stmt, "i", $id_user);
mysqli_stmt_execute($stmt);
$query_riwayat = mysqli_stmt_get_result($stmt);

function rupiah($angka) {
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Riwayat Transaksi - TokoKu</title>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
* {
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Inter',sans-serif;
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
    z-index:10;
}

.brand {
    font-size:27px;
    font-weight:800;
    color:#405b46;
    text-decoration:none;
    padding:0 12px;
}

.brand span {
    color:#91aa91;
}

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

.icon {
    font-size:20px;
    width:23px;
    text-align:center;
}

.sidebar-bottom {
    margin-top:auto;
    border-top:1px solid #edf0eb;
    padding-top:20px;
}

.logout {
    color:#c46c60;
}

.logout:hover {
    background:#fff0ed;
}

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

.topbar h1 {
    font-size:26px;
}

.topbar p {
    font-size:13px;
    color:#99a196;
    margin-top:5px;
}

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

.profile strong {
    font-size:13px;
}

.profile small {
    display:block;
    color:#9ba59a;
    margin-top:3px;
}

.welcome {
    padding:30px;
    color:white;
    border-radius:22px;
    background:linear-gradient(120deg,#405b46,#829b83);
    margin-bottom:28px;
    box-shadow:0 10px 25px rgba(64,91,70,.15);
}

.welcome h2 {
    font-size:24px;
    margin-bottom:10px;
}

.welcome p {
    font-size:13px;
    opacity:.85;
    line-height:1.7;
}

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

.stat-card:hover {
    transform:translateY(-5px);
}

.stat-icon {
    width:45px;
    height:45px;
    display:grid;
    place-items:center;
    border-radius:13px;
    font-size:22px;
    margin-bottom:18px;
}

.green {
    background:#edf5ec;
}

.peach {
    background:#fff0e9;
}

.yellow {
    background:#fff7e8;
}

.stat-card p {
    font-size:12px;
    color:#98a196;
    margin-bottom:8px;
}

.stat-card h3 {
    font-size:25px;
    overflow-wrap:anywhere;
}

.table-card {
    background:white;
    padding:24px;
    border-radius:18px;
    box-shadow:0 5px 20px rgba(50,75,55,.04);
}

.table-header {
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:15px;
    margin-bottom:20px;
}

.table-header h2 {
    font-size:18px;
}

.search-box {
    display:flex;
    gap:10px;
    align-items:center;
}

.search {
    padding:12px 15px;
    border:1px solid #e7ece5;
    border-radius:12px;
    outline:none;
    font-size:12px;
    width:250px;
    background:#fafbf9;
    color:#405b46;
}

.search:focus {
    border-color:#829b83;
}

.table-responsive {
    overflow-x:auto;
}

table {
    width:100%;
    min-width:850px;
    border-collapse:collapse;
}

th {
    background:#f8faf6;
    color:#9ba59a;
    font-size:11px;
    text-align:left;
    padding:15px;
    letter-spacing:.5px;
}

td {
    padding:16px 14px;
    font-size:12px;
    border-bottom:1px solid #f0f2ee;
}

tbody tr:hover td {
    background:#fcfdfb;
}

.transaction-id {
    font-weight:700;
    color:#405b46;
}

.amount {
    font-weight:700;
    color:#405b46;
}

.status {
    padding:7px 12px;
    border-radius:20px;
    font-size:10px;
    font-weight:700;
    display:inline-block;
}

.status.selesai {
    background:#e2f6e7;
    color:#238344;
}

.status.menunggu {
    background:#fff7e8;
    color:#b7791f;
}

.status.diproses {
    background:#e9f1ff;
    color:#4166a8;
}

.status.dibatalkan {
    background:#fff0ed;
    color:#c46c60;
}

.status.default {
    background:#f1f3ef;
    color:#788579;
}

.btn-detail {
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:9px 13px;
    background:#edf4ec;
    color:#405b46;
    text-decoration:none;
    border-radius:10px;
    font-size:11px;
    font-weight:700;
    transition:.3s;
}

.btn-detail:hover {
    background:#405b46;
    color:white;
}

.empty {
    text-align:center;
    color:#9ba59a;
    padding:35px;
}

.footer {
    text-align:center;
    color:#a1aaa0;
    font-size:11px;
    padding:25px 0 5px;
}

@media(max-width:1000px) {
    .stats {
        grid-template-columns:repeat(2,1fr);
    }
}

@media(max-width:750px) {
    .sidebar {
        width:75px;
        padding:22px 10px;
    }

    .brand {
        font-size:17px;
        padding:0;
        text-align:center;
    }

    .admin-label,.menu-title,
    .menu a span:not(.icon),
    .logout span:not(.icon) {
        display:none;
    }

    .menu a,.logout {
        justify-content:center;
        padding:14px 5px;
    }

    .main {
        width:calc(100% - 75px);
        margin-left:75px;
        padding:22px 15px;
    }

    .topbar h1 {
        font-size:20px;
    }

    .profile-info {
        display:none;
    }

    .table-header {
        flex-direction:column;
        align-items:flex-start;
    }

    .search-box {
        width:100%;
    }

    .search {
        width:100%;
    }
}

@media(max-width:450px) {
    .stats {
        grid-template-columns:1fr 1fr;
        gap:10px;
    }

    .stat-card {
        padding:15px;
    }

    .stat-card h3 {
        font-size:19px;
    }

    .welcome {
        padding:23px 18px;
    }

    .welcome h2 {
        font-size:19px;
    }

    .table-card {
        padding:15px;
    }
}
</style>
</head>

<body>

<div class="wrapper">

<aside class="sidebar">

    <a href="dashboard_kasir.php" class="brand">
        Toko<span>Ku.</span>
    </a>

    <div class="admin-label">CASHIER PANEL</div>

    <div class="menu-title">MENU KASIR</div>

    <nav class="menu">

        <a href="dashboard_kasir.php">
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

        <a href="riwayat_transaksi.php" class="active">
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
            <h1>Riwayat Transaksi</h1>
            <p>Kelola dan pantau transaksi penjualan Anda.</p>
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
        <h2>Riwayat Penjualan 🧾</h2>
        <p>
            Berikut adalah daftar transaksi yang telah Anda lakukan.
            Pantau penjualan dan status pembayaran dengan mudah.
        </p>
    </section>

    <h2 class="section-title">Ringkasan Transaksi</h2>

    <section class="stats">

        <div class="stat-card">
            <div class="stat-icon green">🛍️</div>
            <p>Transaksi Hari Ini</p>
            <h3><?= number_format($total_transaksi_hari_ini) ?></h3>
        </div>

        <div class="stat-card">
            <div class="stat-icon peach">🧾</div>
            <p>Semua Transaksi Saya</p>
            <h3><?= number_format($total_semua_transaksi) ?></h3>
        </div>

        <div class="stat-card">
            <div class="stat-icon yellow">💰</div>
            <p>Pendapatan Hari Ini</p>
            <h3 style="font-size:21px">
                <?= rupiah($total_pendapatan_hari_ini) ?>
            </h3>
        </div>

    </section>

    <section class="table-card">

        <div class="table-header">

            <h2>Daftar Riwayat</h2>

            <div class="search-box">
                <input
                    type="text"
                    id="searchInput"
                    class="search"
                    placeholder="Cari ID, pembayaran, status..."
                >
            </div>

        </div>

        <div class="table-responsive">

            <table id="tabelTransaksi">

                <thead>
                    <tr>
                        <th>ID TRANSAKSI</th>
                        <th>TANGGAL</th>
                        <th>PEMBAYARAN</th>
                        <th>TOTAL</th>
                        <th>STATUS</th>
                        <th>AKSI</th>
                    </tr>
                </thead>

                <tbody>

                <?php if($query_riwayat && mysqli_num_rows($query_riwayat)>0): ?>

                    <?php while($row = mysqli_fetch_assoc($query_riwayat)): ?>

                        <?php
                        $status = strtolower(trim($row['status'] ?? ''));

                        $class_status = in_array($status, [
                            'selesai',
                            'menunggu',
                            'diproses',
                            'dibatalkan'
                        ]) ? $status : 'default';
                        ?>

                        <tr>

                            <td class="transaction-id">
                                #<?= (int)$row['id_transaksi'] ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($row['tanggal_transaksi'] ?? '-') ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($row['metode_pembayaran'] ?? '-') ?>
                            </td>

                            <td class="amount">
                                <?= rupiah($row['total_harga']) ?>
                            </td>

                            <td>
                                <span class="status <?= $class_status ?>">
                                    <?= htmlspecialchars(ucfirst($status ?: 'Tidak diketahui')) ?>
                                </span>
                            </td>

                            <td>
                                <a
                                    href="nota_admin.php?id=<?= (int)$row['id_transaksi'] ?>"
                                    class="btn-detail"
                                >
                                    Lihat Nota →
                                </a>
                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="6" class="empty">
                            <div style="font-size:35px;margin-bottom:12px">🧾</div>
                            Belum ada riwayat transaksi.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

    <div class="footer">
        © 2026 TokoKu. Cashier System.
    </div>

</main>

</div>

<script>
document.getElementById('searchInput').addEventListener('keyup', function() {

    const keyword = this.value.toLowerCase();
    const rows = document.querySelectorAll('#tabelTransaksi tbody tr');

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();

        row.style.display = text.includes(keyword) ? '' : 'none';
    });

});
</script>

</body>
</html>
