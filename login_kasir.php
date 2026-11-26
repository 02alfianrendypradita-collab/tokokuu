
<?php
session_start();
require_once 'koneksi.php';

$pesan = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password_input = $_POST['password'] ?? '';

    $sql = "SELECT id_user, nama, email, password, role
            FROM users
            WHERE email = ?
            LIMIT 1";

    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        $pesan = "Query gagal: " . mysqli_error($conn);
    } else {

        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);

        if (!$user) {

            $pesan = "Email tidak ditemukan di database.";

        } else {

            // Pemeriksaan password
            $password_benar = password_verify(
                $password_input,
                $user['password']
            );

            // Pemeriksaan role
            $role_user = strtolower(trim((string)$user['role']));
            $role_benar = in_array($role_user, ['kasir', 'petugas']);

            if (!$password_benar) {

                $pesan = "Password tidak cocok.";

            } elseif (!$role_benar) {

                $pesan = "Role akun bukan kasir. Role terbaca: " . $role_user;

            } else {

                // Login berhasil
                session_regenerate_id(true);

                $_SESSION['id_user'] = $user['id_user'];
                $_SESSION['user_id'] = $user['id_user'];
                $_SESSION['nama'] = $user['nama'];
                $_SESSION['role'] = $role_user;

                mysqli_stmt_close($stmt);

                header("Location: dashboard_kasir.php");
                exit;
            }
        }

        mysqli_stmt_close($stmt);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login Kasir TokoKu</title>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: Arial, sans-serif;
}

body {
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    background: linear-gradient(135deg, #edf5ed, #d9ead9);
    padding: 20px;
}

.card {
    width: 100%;
    max-width: 420px;
    background: #fff;
    padding: 38px;
    border-radius: 22px;
    box-shadow: 0 15px 45px rgba(30,80,40,.12);
}

.icon {
    width: 70px;
    height: 70px;
    margin: 0 auto 20px;
    display: flex;
    justify-content: center;
    align-items: center;
    border-radius: 20px;
    background: #e8f5e9;
    font-size: 32px;
}

h2 {
    text-align: center;
    color: #245c32;
    margin-bottom: 10px;
}

.subtitle {
    text-align: center;
    color: #888;
    font-size: 14px;
    margin-bottom: 25px;
}

label {
    display: block;
    font-size: 13px;
    color: #45634b;
    margin-bottom: 8px;
}

input {
    width: 100%;
    padding: 14px;
    margin-bottom: 18px;
    border: 1px solid #dce7dc;
    border-radius: 11px;
    outline: none;
    font-size: 15px;
}

input:focus {
    border-color: #39834b;
    box-shadow: 0 0 0 3px rgba(57,131,75,.1);
}

button {
    width: 100%;
    padding: 15px;
    border: none;
    border-radius: 11px;
    background: #287a3d;
    color: #fff;
    font-weight: bold;
    font-size: 15px;
    cursor: pointer;
}

button:hover {
    background: #1e6030;
}

.message {
    background: #fff0f0;
    color: #bd3434;
    padding: 13px;
    border-radius: 10px;
    margin-bottom: 18px;
    font-size: 14px;
    overflow-wrap: anywhere;
}

.back {
    display: block;
    text-align: center;
    text-decoration: none;
    color: #39834b;
    margin-top: 20px;
    font-size: 13px;
}
</style>
</head>

<body>

<div class="card">

    <div class="icon">🛒</div>

    <h2>Login Kasir</h2>

    <p class="subtitle">
        Masuk ke sistem kasir TokoKu
    </p>

    <?php if ($pesan !== ''): ?>
        <div class="message">
            <?= htmlspecialchars($pesan, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <label>Email</label>
        <input
            type="email"
            name="email"
            placeholder="kasir@tokoku.com"
            required
        >

        <label>Password</label>
        <input
            type="password"
            name="password"
            placeholder="Masukkan password"
            required
        >

        <button type="submit">Masuk ke Dashboard</button>

    </form>

    <a href="login.php" class="back">
        Kembali ke Login Utama
    </a>

</div>

</body>
</html>
