
<?php
require_once 'koneksi.php';

$email = 'kasir@tokoku.com';
$password = '12345678';

echo "<h2>PEMERIKSAAN AKUN KASIR TOKOKU</h2>";

$sql = "SELECT * FROM users WHERE email = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("ERROR QUERY: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

if (!$user) {
    die("AKUN TIDAK DITEMUKAN!");
}

echo "Nama: " . htmlspecialchars($user['nama']) . "<br>";
echo "Email: " . htmlspecialchars($user['email']) . "<br>";
echo "Role: " . htmlspecialchars($user['role']) . "<br>";

echo "<hr>";

if (password_verify($password, $user['password'])) {
    echo "<h3 style='color:green'>PASSWORD BENAR</h3>";
} else {
    echo "<h3 style='color:red'>PASSWORD TIDAK COCOK</h3>";
}

if (strtolower(trim($user['role'])) === 'kasir') {
    echo "<h3 style='color:green'>ROLE KASIR BENAR</h3>";
} else {
    echo "<h3 style='color:red'>ROLE BUKAN KASIR</h3>";
}

mysqli_stmt_close($stmt);
?>
