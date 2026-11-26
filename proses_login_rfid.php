
<?php
session_start();
require_once 'koneksi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login_admin.php");
    exit;
}

// Verifikasi token
$token = $_POST['csrf_token'] ?? '';

if (
    empty($_SESSION['csrf_rfid']) ||
    !hash_equals($_SESSION['csrf_rfid'], $token)
) {
    header("Location: login_admin.php?error=1");
    exit;
}

// Ambil UID RFID
$rfid_uid = strtoupper(trim($_POST['rfid_uid'] ?? ''));

// Hilangkan spasi, titik dua, dan tanda hubung
$rfid_uid = preg_replace('/[\s:-]/', '', $rfid_uid);

if ($rfid_uid === '' || strlen($rfid_uid) > 100) {
    header("Location: login_admin.php?error=1");
    exit;
}

// Cari kartu RFID yang terdaftar sebagai admin
$stmt = mysqli_prepare($conn, "
    SELECT id_user, nama, email, role
    FROM users
    WHERE rfid_uid = ?
    AND role = 'admin'
    LIMIT 1
");

if (!$stmt) {
    die("Query gagal: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmt, "s", $rfid_uid);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

// Jika kartu tidak ditemukan
if (!$user) {
    header("Location: login_admin.php?error=1");
    exit;
}

// Login berhasil
session_regenerate_id(true);

$_SESSION['id_user'] = $user['id_user'];
$_SESSION['nama'] = $user['nama'];
$_SESSION['email'] = $user['email'];
$_SESSION['role'] = 'admin';

// Hapus token login
unset($_SESSION['csrf_rfid']);

header("Location: dashboard_admin.php");
exit;
?>
