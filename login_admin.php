
<?php
session_start();

if (isset($_SESSION['id_user'])) {
    if (($_SESSION['role'] ?? '') === 'admin') {
        header("Location: dashboard_admin.php");
        exit;
    }

    header("Location: dashboard.php");
    exit;
}

// Token keamanan
if (empty($_SESSION['csrf_rfid'])) {
    $_SESSION['csrf_rfid'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login RFID Admin - TokoKu</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            background: linear-gradient(135deg, #edf2e9, #faf8f3, #f4e6da);
        }

        .login-card {
            width: 100%;
            max-width: 430px;
            padding: 42px;
            background: rgba(255,255,255,.96);
            border-radius: 26px;
            box-shadow: 0 20px 60px rgba(64,91,70,.13);
            animation: muncul .6s ease;
        }

        @keyframes muncul {
            from {
                opacity: 0;
                transform: translateY(25px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo {
            width: 75px;
            height: 75px;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 22px;
            background: linear-gradient(135deg, #829b83, #405b46);
            color: white;
            font-size: 30px;
            font-weight: bold;
        }

        .header {
            text-align: center;
            margin-bottom: 28px;
        }

        .header h2 {
            color: #405b46;
            font-size: 27px;
            margin-bottom: 8px;
        }

        .header p {
            color: #899087;
            font-size: 14px;
        }

        .admin-label {
            display: inline-block;
            margin-top: 14px;
            padding: 8px 15px;
            border-radius: 30px;
            background: #edf3eb;
            color: #405b46;
            font-size: 12px;
            font-weight: 700;
        }

        .rfid-icon {
            width: 105px;
            height: 105px;
            margin: 0 auto 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #edf3eb;
            border-radius: 50%;
            font-size: 48px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                box-shadow: 0 0 0 0 rgba(64,91,70,.18);
            }
            50% {
                box-shadow: 0 0 0 15px rgba(64,91,70,0);
            }
        }

        .instruction {
            text-align: center;
            color: #405b46;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 18px;
        }

        .rfid-input {
            width: 100%;
            padding: 16px;
            border: 1px solid #dce8da;
            border-radius: 13px;
            outline: none;
            background: #fafbf8;
            font-size: 15px;
            text-align: center;
            letter-spacing: 2px;
            transition: .3s;
        }

        .rfid-input:focus {
            border-color: #829b83;
            box-shadow: 0 0 0 4px rgba(130,155,131,.13);
            background: white;
        }

        .btn-login {
            width: 100%;
            padding: 16px;
            margin-top: 17px;
            border: none;
            border-radius: 13px;
            background: linear-gradient(135deg, #829b83, #405b46);
            color: white;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: .3s;
        }

        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 22px rgba(64,91,70,.22);
        }

        .error {
            padding: 13px;
            margin-bottom: 18px;
            border-radius: 11px;
            background: #fff0ed;
            color: #c45d4d;
            font-size: 13px;
            text-align: center;
        }

        .back-link {
            margin-top: 24px;
            text-align: center;
            color: #899087;
            font-size: 13px;
        }

        .back-link a {
            color: #405b46;
            font-weight: 700;
            text-decoration: none;
        }

        .footer {
            margin-top: 26px;
            text-align: center;
            color: #899087;
            font-size: 12px;
        }

        @media(max-width:480px) {
            .login-card {
                padding: 30px 23px;
            }
        }
    </style>
</head>

<body>

<div class="login-card">

    <div class="logo">T</div>

    <div class="header">
        <h2>Admin TokoKu</h2>
        <p>Selamat datang di panel administrator</p>
        <span class="admin-label">RFID ADMIN ACCESS</span>
    </div>

    <?php if (isset($_GET['error'])): ?>
        <div class="error">
            Kartu RFID tidak terdaftar atau bukan milik admin!
        </div>
    <?php endif; ?>

    <form action="proses_login_rfid.php" method="POST">

        <div class="rfid-icon">📡</div>

        <p class="instruction">
            Tempelkan Kartu RFID Anda
        </p>

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($_SESSION['csrf_rfid'], ENT_QUOTES, 'UTF-8') ?>"
        >

        <input
            type="text"
            name="rfid_uid"
            id="rfid_uid"
            class="rfid-input"
            placeholder="Menunggu kartu RFID..."
            autocomplete="off"
            autofocus
            required
        >

        <button type="submit" class="btn-login">
            Masuk ke Dashboard Admin
        </button>

    </form>

    <div class="back-link">
        Ingin belanja?
        <a href="login_pembeli.php">Login sebagai pelanggan</a>
    </div>

    <div class="footer">
        &copy; 2026 TokoKu. Admin Panel.
    </div>

</div>

<script>
    window.onload = function() {
        document.getElementById('rfid_uid').focus();
    };
</script>

</body>
</html>
