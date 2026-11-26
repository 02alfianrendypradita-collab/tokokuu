<?php
session_start();
require_once __DIR__ . '/koneksi.php';

// Halaman ini khusus admin.
if (!isset($_SESSION['id_user'])) {
    header("Location: login_admin.php");
    exit;
}
if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['csrf_member'])) {
    $_SESSION['csrf_member'] = bin2hex(random_bytes(32));
}

$pesan = '';
$jenis_pesan = 'success';

// Proses pendaftaran / perubahan membership.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_member'], $token)) {
        $pesan = 'Permintaan tidak valid. Silakan muat ulang halaman.';
        $jenis_pesan = 'danger';
    } else {
        $aksi = $_POST['aksi'] ?? '';

        if ($aksi === 'simpan') {
            $id_user = (int)($_POST['id_user'] ?? 0);
            $rfid_uid = trim($_POST['rfid_uid'] ?? '');

            if ($id_user <= 0 || $rfid_uid === '') {
                $pesan = 'Pilih pelanggan dan masukkan nomor RFID.';
                $jenis_pesan = 'danger';
            } else {
                // Pastikan akun yang dipilih benar-benar pelanggan.
                $cek_user = mysqli_prepare($conn, "SELECT id_user FROM users WHERE id_user = ? AND role = 'pembeli' LIMIT 1");
                mysqli_stmt_bind_param($cek_user, "i", $id_user);
                mysqli_stmt_execute($cek_user);
                $hasil_user = mysqli_stmt_get_result($cek_user);
                $user_valid = mysqli_fetch_assoc($hasil_user);
                mysqli_stmt_close($cek_user);

                // Cegah satu kartu dipakai oleh dua akun.
                $cek_rfid = mysqli_prepare($conn, "SELECT id_user FROM users WHERE rfid_uid = ? AND id_user <> ? LIMIT 1");
                mysqli_stmt_bind_param($cek_rfid, "si", $rfid_uid, $id_user);
                mysqli_stmt_execute($cek_rfid);
                $hasil_rfid = mysqli_stmt_get_result($cek_rfid);
                $rfid_dipakai = mysqli_fetch_assoc($hasil_rfid);
                mysqli_stmt_close($cek_rfid);

                if (!$user_valid) {
                    $pesan = 'Data pelanggan tidak ditemukan.';
                    $jenis_pesan = 'danger';
                } elseif ($rfid_dipakai) {
                    $pesan = 'Nomor RFID tersebut sudah digunakan akun lain. Gunakan kartu yang berbeda.';
                    $jenis_pesan = 'danger';
                } else {
                    $update = mysqli_prepare($conn, "UPDATE users SET status_member = 'member', rfid_uid = ? WHERE id_user = ? AND role = 'pembeli'");
                    mysqli_stmt_bind_param($update, "si", $rfid_uid, $id_user);
                    if (mysqli_stmt_execute($update)) {
                        $pesan = 'Membership pelanggan berhasil disimpan.';
                    } else {
                        $pesan = 'Gagal menyimpan membership: ' . mysqli_error($conn);
                        $jenis_pesan = 'danger';
                    }
                    mysqli_stmt_close($update);
                }
            }
        } elseif ($aksi === 'nonaktifkan') {
            $id_user = (int)($_POST['id_user'] ?? 0);
            $update = mysqli_prepare($conn, "UPDATE users SET status_member = 'non_member' WHERE id_user = ? AND role = 'pembeli'");
            mysqli_stmt_bind_param($update, "i", $id_user);
            if (mysqli_stmt_execute($update) && mysqli_stmt_affected_rows($update) >= 0) {
                $pesan = 'Status membership berhasil dinonaktifkan. Nomor RFID tetap disimpan pada akun pelanggan.';
            } else {
                $pesan = 'Gagal menonaktifkan membership.';
                $jenis_pesan = 'danger';
            }
            mysqli_stmt_close($update);
        }
    }
}

// Data pelanggan untuk pilihan form.
$q_pelanggan = mysqli_query($conn, "SELECT id_user, nama, email, status_member, rfid_uid FROM users WHERE role = 'pembeli' ORDER BY nama ASC");

// Pencarian daftar member.
$cari = trim($_GET['cari'] ?? '');
$sql = "SELECT id_user, nama, email, status_member, rfid_uid FROM users WHERE role = 'pembeli' AND status_member = 'member'";
if ($cari !== '') {
    $sql .= " AND (nama LIKE ? OR email LIKE ? OR rfid_uid LIKE ?)";
}
$sql .= " ORDER BY nama ASC";

if ($cari !== '') {
    $stmt_list = mysqli_prepare($conn, $sql);
    $keyword = '%' . $cari . '%';
    mysqli_stmt_bind_param($stmt_list, "sss", $keyword, $keyword, $keyword);
    mysqli_stmt_execute($stmt_list);
    $q_member = mysqli_stmt_get_result($stmt_list);
} else {
    $q_member = mysqli_query($conn, $sql);
    $stmt_list = null;
}

$total_member = 0;
$total_pelanggan = 0;
$q_total_member = mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'pembeli' AND status_member = 'member'");
if ($q_total_member) $total_member = (int)(mysqli_fetch_assoc($q_total_member)['total'] ?? 0);
$q_total_pelanggan = mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'pembeli'");
if ($q_total_pelanggan) $total_pelanggan = (int)(mysqli_fetch_assoc($q_total_pelanggan)['total'] ?? 0);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Data Member - TokoKu</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root{--green:#405b46;--green2:#6f8e72;--bg:#f5f7f4;--text:#253329;--muted:#7b887e;--line:#e8eee7}
*{box-sizing:border-box}body{margin:0;background:var(--bg);font-family:Inter,Segoe UI,Arial,sans-serif;color:var(--text)}
.sidebar{position:fixed;inset:0 auto 0 0;width:245px;background:#fff;border-right:1px solid var(--line);padding:25px 18px;z-index:5}
.brand{display:flex;align-items:center;gap:12px;padding:5px 8px 30px}.brand-icon{width:43px;height:43px;border-radius:14px;background:linear-gradient(135deg,var(--green),var(--green2));color:white;display:grid;place-items:center;font-size:21px}.brand h2{font-size:19px;margin:0;color:var(--green)}.brand small{color:var(--muted)}
.menu-label{font-size:10px;font-weight:800;letter-spacing:1.5px;color:#a0aaa1;padding:0 12px 12px}.menu a{display:flex;gap:13px;align-items:center;padding:13px 14px;margin:5px 0;text-decoration:none;color:#68756b;border-radius:12px;font-size:13px;font-weight:600}.menu a i{font-size:17px}.menu a:hover,.menu a.active{background:#edf3eb;color:var(--green)}
.main{margin-left:245px;padding:32px;max-width:1600px}.topbar{display:flex;justify-content:space-between;align-items:center;gap:15px;margin-bottom:27px}.topbar h1{font-size:25px;margin:0 0 6px}.topbar p{margin:0;color:var(--muted);font-size:13px}.admin{background:#fff;border:1px solid var(--line);padding:10px 15px;border-radius:14px;font-size:13px;font-weight:700}
.stats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:17px;margin-bottom:24px}.stat{background:white;border:1px solid var(--line);border-radius:17px;padding:20px;display:flex;align-items:center;gap:15px}.stat-icon{width:47px;height:47px;border-radius:14px;background:#edf3eb;color:var(--green);display:grid;place-items:center;font-size:21px}.stat strong{font-size:24px;display:block}.stat span{font-size:12px;color:var(--muted)}
.panel{background:#fff;border:1px solid var(--line);border-radius:18px;padding:23px;margin-bottom:22px;box-shadow:0 7px 25px rgba(40,65,45,.035)}.panel-head{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:18px}.panel h2{font-size:17px;margin:0 0 5px}.panel-head p{font-size:12px;color:var(--muted);margin:0}
.form-grid{display:grid;grid-template-columns:1fr 1fr auto;gap:13px;align-items:end}.field label{display:block;font-size:12px;font-weight:700;margin-bottom:8px}.field input,.field select,.search input{width:100%;padding:12px 13px;border:1px solid #dfe7df;border-radius:10px;outline:none;font-size:13px;background:#fff}.field input:focus,.field select:focus,.search input:focus{border-color:var(--green2);box-shadow:0 0 0 3px rgba(111,142,114,.12)}
.btn{border:0;border-radius:10px;padding:12px 17px;background:var(--green);color:white;font-weight:700;font-size:12px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:7px}.btn:hover{background:#304a36}.btn-danger{background:#fff0ef;color:#b33b35}.btn-danger:hover{background:#ffe1df}.btn-light{background:#edf3eb;color:var(--green)}.alert{padding:13px 15px;border-radius:11px;margin-bottom:18px;font-size:13px}.alert.success{background:#eaf5ec;color:#28643b}.alert.danger{background:#fff0ef;color:#a9322b}
.toolbar{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:15px}.search{display:flex;gap:8px;width:min(100%,430px)}.search input{background:#fbfcfb}.table-wrap{overflow-x:auto}table{width:100%;border-collapse:collapse;min-width:700px}th{text-align:left;background:#f6f8f5;color:#758177;font-size:10px;letter-spacing:.7px;text-transform:uppercase;padding:13px}td{padding:14px 13px;border-bottom:1px solid #eef1ed;font-size:12px;color:#536157}tr:last-child td{border-bottom:0}.person{display:flex;align-items:center;gap:10px}.avatar{width:35px;height:35px;border-radius:11px;background:#edf3eb;color:var(--green);display:grid;place-items:center;font-weight:800}.person strong{display:block;color:#29382d;font-size:12px}.person small{color:#8b968d;font-size:10px}.rfid{font-family:Consolas,monospace;background:#f3f5f2;padding:6px 9px;border-radius:7px}.status{display:inline-block;padding:6px 10px;border-radius:30px;background:#e8f5eb;color:#287342;font-size:10px;font-weight:800}.actions form{display:inline}.empty{text-align:center;padding:35px;color:var(--muted);font-size:13px}.hint{font-size:11px;color:var(--muted);margin-top:9px;line-height:1.5}.rfid-controls{display:flex;gap:8px;align-items:center}.scan-status{display:flex;align-items:center;gap:7px;font-size:11px;color:var(--muted);margin-top:9px}.scan-dot{width:8px;height:8px;border-radius:50%;background:#aab4aa}.scan-dot.active{background:#20a45a;box-shadow:0 0 0 4px #e4f5e9}.scan-btn{white-space:nowrap}.field input.scanned{border-color:#249653;background:#f1fbf4}@media(max-width:600px){.rfid-controls{flex-direction:column;align-items:stretch}.rfid-controls .btn{width:100%}}
@media(max-width:850px){.sidebar{width:72px;padding:20px 9px}.brand{justify-content:center;padding:4px 0 28px}.brand>div:last-child,.menu-label,.menu a span{display:none}.menu a{justify-content:center;padding:14px 5px}.main{margin-left:72px;padding:22px 16px}}
@media(max-width:600px){.stats{grid-template-columns:1fr}.form-grid{grid-template-columns:1fr}.topbar{align-items:flex-start;flex-direction:column}.panel{padding:17px}.toolbar{align-items:stretch;flex-direction:column}.search{width:100%}}
</style>
</head>
<body>
<aside class="sidebar">
    <div class="brand"><div class="brand-icon"><i class="bi bi-bag-heart-fill"></i></div><div><h2>TokoKu</h2><small>Admin Panel</small></div></div>
    <div class="menu-label">MENU ADMIN</div>
    <nav class="menu">
        <a href="dashboard_admin.php"><i class="bi bi-grid"></i><span>Dashboard</span></a>
        <a href="member.php" class="active"><i class="bi bi-award"></i><span>Data Member</span></a>
        <a href="#tambah-rfid"><i class="bi bi-upc-scan"></i><span>Tambah Kode RFID</span></a>
        <a href="logout.php" onclick="return confirm('Yakin ingin logout?')"><i class="bi bi-box-arrow-left"></i><span>Logout</span></a>
    </nav>
</aside>

<main class="main">
    <header class="topbar">
        <div><h1>Data Membership</h1><p>Kelola pelanggan member dan kartu RFID TokoKu.</p></div>
        <div class="admin"><i class="bi bi-person-circle"></i> <?= e($_SESSION['nama'] ?? 'Administrator') ?></div>
    </header>

    <?php if ($pesan !== ''): ?>
        <div class="alert <?= e($jenis_pesan) ?>"><?= e($pesan) ?></div>
    <?php endif; ?>

    <section class="stats">
        <div class="stat"><div class="stat-icon"><i class="bi bi-award-fill"></i></div><div><strong><?= number_format($total_member) ?></strong><span>Total Member Aktif</span></div></div>
        <div class="stat"><div class="stat-icon"><i class="bi bi-people-fill"></i></div><div><strong><?= number_format($total_pelanggan) ?></strong><span>Total Seluruh Pelanggan</span></div></div>
    </section>

    <section class="panel" id="tambah-rfid">
        <div class="panel-head"><div><h2><i class="bi bi-upc-scan"></i> Tambah Kode RFID / Daftarkan Membership</h2><p>Pilih akun pelanggan, lalu tempelkan kartu RFID pada reader.</p></div></div>
        <form method="POST" class="form-grid" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_member']) ?>">
            <input type="hidden" name="aksi" value="simpan">
            <div class="field">
                <label for="id_user">Pilih Pelanggan</label>
                <select name="id_user" id="id_user" required>
                    <option value="">-- Pilih pelanggan --</option>
                    <?php if ($q_pelanggan): while ($pel = mysqli_fetch_assoc($q_pelanggan)): ?>
                        <option value="<?= (int)$pel['id_user'] ?>">
                            <?= e($pel['nama']) ?> — <?= e($pel['email']) ?><?= ($pel['status_member'] ?? 'non_member') === 'member' ? ' (Member, bisa diperbarui)' : '' ?>
                        </option>
                    <?php endwhile; endif; ?>
                </select>
            </div>
            <div class="field">
                <label for="rfid_uid">Nomor Kartu RFID (UID)</label>
                <div class="rfid-controls">
                    <input type="text" name="rfid_uid" id="rfid_uid" placeholder="Klik Mulai Scan, lalu tempel kartu" required autocomplete="off">
                    <button type="button" class="btn btn-light scan-btn" id="mulaiScan">
                        <i class="bi bi-upc-scan"></i> Mulai Scan
                    </button>
                </div>
                <div class="scan-status" aria-live="polite">
                    <span class="scan-dot" id="scanDot"></span>
                    <span id="scanStatus">Reader belum aktif. Klik tombol Mulai Scan.</span>
                </div>
            </div>
            <button type="submit" class="btn"><i class="bi bi-check2-circle"></i> Simpan Member</button>
        </form>
        <p class="hint"><i class="bi bi-info-circle"></i> Untuk reader RFID USB yang terbaca seperti keyboard: klik Mulai Scan, lalu tempel kartu. UID akan masuk otomatis. Jika memakai modul RC522, diperlukan Arduino/ESP32 dan program penghubung ke komputer.</p>
    </section>

    <section class="panel">
        <div class="panel-head"><div><h2><i class="bi bi-award"></i> Daftar Member Aktif</h2><p>Daftar pelanggan yang memiliki status membership aktif.</p></div></div>
        <div class="toolbar">
            <form class="search" method="GET">
                <input type="text" name="cari" value="<?= e($cari) ?>" placeholder="Cari nama, email, atau UID RFID...">
                <button class="btn" type="submit"><i class="bi bi-search"></i> Cari</button>
                <?php if ($cari !== ''): ?><a class="btn btn-light" href="member.php">Reset</a><?php endif; ?>
            </form>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Pelanggan</th><th>Nomor RFID</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody>
                <?php if ($q_member && mysqli_num_rows($q_member) > 0): ?>
                    <?php while ($member = mysqli_fetch_assoc($q_member)): ?>
                    <tr>
                        <td><div class="person"><div class="avatar"><?= e(strtoupper(substr($member['nama'] ?? 'P', 0, 1))) ?></div><div><strong><?= e($member['nama']) ?></strong><small><?= e($member['email']) ?></small></div></div></td>
                        <td><span class="rfid"><?= e($member['rfid_uid'] ?: 'Belum ada UID') ?></span></td>
                        <td><span class="status"><i class="bi bi-check-circle-fill"></i> MEMBER AKTIF</span></td>
                        <td class="actions">
                            <form method="POST" onsubmit="return confirm('Nonaktifkan membership pelanggan ini?')">
                                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_member']) ?>">
                                <input type="hidden" name="aksi" value="nonaktifkan">
                                <input type="hidden" name="id_user" value="<?= (int)$member['id_user'] ?>">
                                <button type="submit" class="btn btn-danger"><i class="bi bi-person-dash"></i> Nonaktifkan</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="empty"><i class="bi bi-inbox" style="font-size:28px;display:block;margin-bottom:8px"></i>Belum ada member yang cocok dengan pencarian.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <p class="hint" style="text-align:center">© <?= date('Y') ?> TokoKu — Membership Management</p>
</main>
<script>
const inputRfid = document.getElementById('rfid_uid');
const tombolScan = document.getElementById('mulaiScan');
const statusScan = document.getElementById('scanStatus');
const titikScan = document.getElementById('scanDot');
let modeScan = false;

tombolScan.addEventListener('click', function () {
    modeScan = true;
    inputRfid.value = '';
    inputRfid.classList.remove('scanned');
    statusScan.textContent = 'Silakan tempelkan kartu RFID ke reader...';
    titikScan.classList.add('active');
    tombolScan.innerHTML = '<i class="bi bi-broadcast"></i> Sedang Scan';
    inputRfid.focus();
});

// Reader USB biasanya mengetikkan UID ke kolom aktif, lalu mengirim tombol Enter.
inputRfid.addEventListener('keydown', function (event) {
    if (event.key === 'Enter') {
        event.preventDefault();

        const uid = inputRfid.value.trim();
        if (uid !== '') {
            modeScan = false;
            inputRfid.value = uid;
            inputRfid.classList.add('scanned');
            statusScan.textContent = 'Kartu berhasil terbaca. UID: ' + uid;
            titikScan.classList.remove('active');
            tombolScan.innerHTML = '<i class="bi bi-arrow-repeat"></i> Scan Ulang';
        }
    }
});

inputRfid.addEventListener('input', function () {
    if (modeScan && inputRfid.value.trim() !== '') {
        statusScan.textContent = 'Membaca UID kartu...';
    }
});

// Jika UID diisi manual, tampilkan status siap disimpan.
inputRfid.addEventListener('change', function () {
    if (inputRfid.value.trim() !== '') {
        statusScan.textContent = 'UID sudah terisi. Periksa UID lalu klik Simpan Member.';
    }
});
</script>
</body>
</html>
<?php
if (isset($stmt_list) && $stmt_list instanceof mysqli_stmt) {
    mysqli_stmt_close($stmt_list);
}
?>
