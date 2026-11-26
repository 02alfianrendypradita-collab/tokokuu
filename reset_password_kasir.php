
<?php
require_once 'koneksi.php';

$email = 'kasir@tokoku.com';
$password = password_hash('12345678', PASSWORD_DEFAULT);

// Periksa apakah akun sudah ada
$stmt = mysqli_prepare(
    $conn,
    "SELECT id_user FROM users WHERE email = ? LIMIT 1"
);

mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);

$ada = mysqli_stmt_num_rows($stmt) > 0;
mysqli_stmt_close($stmt);

if ($ada) {

    // Perbarui akun yang sudah ada
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE users
         SET password = ?, role = 'kasir'
         WHERE email = ?"
    );

    mysqli_stmt_bind_param($stmt, "ss", $password, $email);

} else {

    // Buat akun baru
    $nama = 'Kasir Dummy';
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
}

if (mysqli_stmt_execute($stmt)) {
    echo "BERHASIL! Akun kasir sudah siap digunakan.";
    echo "<br>Email: kasir@tokoku.com";
    echo "<br>Password: 12345678";
} else {
    echo "GAGAL: " . mysqli_stmt_error($stmt);
}

mysqli_stmt_close($stmt);
?>
