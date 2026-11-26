
<?php
session_start();
require_once 'koneksi.php';

$pesan = '';

if (isset($_SESSION['id_user'])) {
    $role = strtolower(trim($_SESSION['role'] ?? ''));

    if (in_array($role, ['kasir', 'petugas'])) {
        header("Location: dashboard_kasir.php");
        exit;
    }

    if ($role === 'admin') {
        header("Location: dashboard_admin.php");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Ambil UID dari RFID Reader
    $rfid_uid = trim($_POST['rfid_uid'] ?? '');

    if ($rfid_uid === '') {
        $pesan = "Kartu RFID belum terbaca.";
    } else {

        // Cari UID pada database
        $sql = "SELECT id_user, nama, role, rfid_uid
                FROM users
                WHERE rfid_uid = ?
                LIMIT 1";

        $stmt = mysqli_prepare($conn, $sql);

        if (!$stmt) {
            $pesan = "Kesalahan query: " . mysqli_error($conn);
        } else {

            mysqli_stmt_bind_param($stmt, "s", $rfid_uid);
            mysqli_stmt_execute($stmt);

            mysqli_stmt_store_result($stmt);

            if (mysqli_stmt_num_rows($stmt) > 0) {

                mysqli_stmt_bind_result(
                    $stmt,
                    $id_user,
                    $nama,
                    $role,
                    $uid_database
                );

                mysqli_stmt_fetch($stmt);

                $role = strtolower(trim($role));

                if (in_array($role, ['kasir', 'petugas'])) {

                    session_regenerate_id(true);

                    $_SESSION['id_user'] = $id_user;
                    $_SESSION['user_id'] = $id_user;
                    $_SESSION['nama'] = $nama;
                    $_SESSION['role'] = $role;

                    mysqli_stmt_close($stmt);

                    header("Location: dashboard_kasir.php");
                    exit;

                } else {
                    $pesan = "UID ditemukan, tetapi role akun adalah: " . $role;
                }

            } else {
                $pesan = "UID tidak ditemukan di database: " . $rfid_uid;
            }

            mysqli_stmt_close($stmt);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Kasir RFID - TokoKu</title>

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
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #edf5ed, #d9ead9);
            padding: 20px;
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            background: white;
            padding: 40px 32px;
            border-radius: 22px;
            box-shadow: 0 15px 45px rgba(30, 80, 40, .12);
            text-align: center;
        }

        .icon {
            width: 75px;
            height: 75px;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 22px;
            background: #e8f5e9;
            font-size: 35px;
        }

        h2 {
            color: #245c32;
            margin-bottom: 10px;
        }

        .subtitle {
            color: #888;
            font-size: 14px;
            margin-bottom: 28px;
        }

        input {
            width: 100%;
            padding: 15px;
            border: 1px solid #dce7dc;
            border-radius: 12px;
            outline: none;
            font-size: 16px;
            text-align: center;
            margin-bottom: 15px;
        }

        input:focus {
            border-color: #39834b;
            box-shadow: 0 0 0 3px rgba(57,131,75,.10);
        }

        button {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 12px;
            background: #287a3d;
            color: white;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #1e6030;
        }

        .message {
            padding: 12px;
            margin-bottom: 18px;
            border-radius: 10px;
            background: #fff0f0;
            color: #bd3434;
            font-size: 13px;
            overflow-wrap: anywhere;
        }

        .back {
            display: inline-block;
            margin-top: 22px;
            color: #39834b;
            text-decoration: none;
            font-size: 13px;
        }
    </style>
</head>

<body>

<div class="login-card">

    <div class="icon">📡</div>

    <h2>Login Kasir RFID</h2>

    <p class="subtitle">
        Tempelkan kartu RFID pada reader untuk masuk
    </p>

    <?php if ($pesan !== ''): ?>
        <div class="message">
            <?= htmlspecialchars($pesan, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form method="POST" id="rfidForm">

        <input
            type="password"
            name="rfid_uid"
            id="rfid_uid"
            placeholder="Menunggu kartu RFID..."
            autocomplete="off"
            autofocus
            required
        >

        <button type="submit">
            Masuk sebagai Kasir
        </button>

    </form>

    <a href="login.php" class="back">
        Kembali ke Login Biasa
    </a>

</div>

<script>
const input = document.getElementById('rfid_uid');

window.addEventListener('load', function () {
    input.focus();
});

document.addEventListener('click', function () {
    input.focus();
});

input.addEventListener('keydown', function (event) {
    if (event.key === 'Enter') {
        event.preventDefault();
        document.getElementById('rfidForm').submit();
    }
});
</script>

</body>
</html>
