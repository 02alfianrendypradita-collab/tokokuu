
<?php
require_once 'koneksi.php';

// Data akun dummy
$nama = 'Kasir Dummy';
$email = 'kasir@tokoku.com';
$password = password_hash('12345678', PASSWORD_DEFAULT);
$role = 'kasir';

// Periksa apakah akun sudah tersedia
$cek = mysqli_prepare(
    $conn,
    "SELECT id_user FROM users WHERE email = ? LIMIT 1"
);

mysqli_stmt_bind_param($cek, "s", $email);
mysqli_stmt_execute($cek);
mysqli_stmt_store_result($cek);

if (mysqli_stmt_num_rows($cek) > 0) {

    mysqli_stmt_close($cek);

    // Perbaiki akun yang sudah ada
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE users
         SET nama = ?, password = ?, role = ?
         WHERE email = ?"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "ssss",
        $nama,
        $password,
        $role,
        $email
    );

} else {

    mysqli_stmt_close($cek);

    // Buat akun jika belum ada
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
}

if (mysqli_stmt_execute($stmt)) {
    echo "<h2 style='color:green'>AKUN KASIR BERHASIL DIPERBAIKI!</h2>";
    echo "Nama: Kasir Dummy<br>";
    echo "Email: kasir@tokoku.com<br>";
    echo "Password: 12345678<br>";
    echo "Role: kasir";
} else {
    echo "GAGAL: " . mysqli_stmt_error($stmt);
}

mysqli_stmt_close($stmt);
?>
