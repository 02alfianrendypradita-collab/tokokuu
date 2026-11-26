<?php
require_once 'koneksi.php';

$nama = 'Kasir Dummy';
$email = 'kasir@tokoku.com';
$password = password_hash('12345678', PASSWORD_DEFAULT);
$role = 'kasir';

$stmt = mysqli_prepare(
    $conn,
    "INSERT INTO users (nama, email, password, role)
     VALUES (?, ?, ?, ?)"
);

mysqli_stmt_bind_param(
    $stmt,
    "ssss",
    $nama,
    $email,
    $password,
    $role
);

if (mysqli_stmt_execute($stmt)) {
    echo "Akun dummy kasir berhasil dibuat!";
} else {
    echo "Gagal: " . mysqli_stmt_error($stmt);
}
?>