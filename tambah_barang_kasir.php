<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit;
}

if (!in_array($_SESSION['role'] ?? '', ['petugas', 'kasir', 'admin'], true)) {
    header("Location: login.php");
    exit;
}

$pesan = '';
$jenis_pesan = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $barcode = trim($_POST['barcode'] ?? '');
    $nama = trim($_POST['nama_produk'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $harga = (float)($_POST['harga'] ?? 0);
    $stok = (int)($_POST['stok'] ?? 0);

    if ($barcode === '' || $nama === '' || $harga <= 0 || $stok < 0) {
        $pesan = 'Lengkapi data barang dengan benar.';
        $jenis_pesan = 'error';

    } else {

        $stmt = mysqli_prepare(
            $conn,
            "SELECT id_produk FROM produk WHERE barcode = ? LIMIT 1"
        );

        mysqli_stmt_bind_param($stmt, "s", $barcode);
        mysqli_stmt_execute($stmt);
        $hasil = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($hasil) > 0) {
            $pesan = 'Barcode sudah digunakan produk lain.';
            $jenis_pesan = 'error';

        } else {

            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO produk
                (nama_produk, barcode, deskripsi, harga, stok, status)
                VALUES (?, ?, ?, ?, ?, 'aktif')"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "sssdi",
                $nama,
                $barcode,
                $deskripsi,
                $harga,
                $stok
            );

            if (mysqli_stmt_execute($stmt)) {
                $pesan = 'Barang berhasil ditambahkan!';
                $jenis_pesan = 'success';
            } else {
                $pesan = 'Gagal menyimpan barang: ' . mysqli_stmt_error($stmt);
                $jenis_pesan = 'error';
            }
        }
    }
}

$nama_kasir = $_SESSION['nama'] ?? 'Kasir';
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Tambah Barang - TokoKu</title>

<script src="https://unpkg.com/html5-qrcode"></script>

<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',sans-serif}
body{background:#f7f8f4;color:#405b46;padding:30px}
.container{max-width:850px;margin:auto}
.header{margin-bottom:25px}
.header a{color:#829b83;text-decoration:none;font-size:13px;font-weight:600}
.header h1{font-size:27px;margin:15px 0 7px}
.header p{color:#99a196;font-size:13px}
.card{background:white;border-radius:20px;padding:28px;box-shadow:0 8px 30px rgba(50,75,55,.05);margin-bottom:20px}
.card h2{font-size:17px;margin-bottom:20px}
.scan-box{background:#f7faf6;border:1px dashed #a8bca5;border-radius:15px;padding:20px;text-align:center;margin-bottom:22px}
.scan-box p{font-size:12px;color:#899689;margin:10px 0}
#reader{max-width:420px;margin:15px auto;overflow:hidden;border-radius:12px}
button,.btn{border:0;background:#405b46;color:white;padding:12px 18px;border-radius:10px;cursor:pointer;font-weight:600;font-size:13px;text-decoration:none;display:inline-block}
button:hover,.btn:hover{background:#829b83}
.stop{background:#c46c60}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}
.form-group{margin-bottom:18px}
label{display:block;font-size:12px;font-weight:700;margin-bottom:8px}
input,textarea{width:100%;padding:13px;border:1px solid #e5ebe2;border-radius:10px;outline:none;font-size:13px;color:#405b46}
input:focus,textarea:focus{border-color:#829b83;box-shadow:0 0 0 3px #edf4ec}
textarea{resize:vertical;min-height:85px}
.full{grid-column:1/-1}
.submit{width:100%;padding:15px;font-size:14px}
.alert{padding:13px 16px;border-radius:10px;margin-bottom:20px;font-size:13px}
.success{background:#e2f6e7;color:#238344}
.error{background:#fff0ed;color:#c46c60}
.actions{display:flex;justify-content:center;gap:10px;flex-wrap:wrap}
@media(max-width:600px){body{padding:15px}.card{padding:19px}.form-grid{grid-template-columns:1fr}.full{grid-column:auto}}
</style>
</head>

<body>
<div class="container">

    <div class="header">
        <a href="dashboard_kasir.php">← Kembali ke Dashboard</a>
        <h1>Tambah Barang</h1>
        <p>Scan barcode dan masukkan informasi produk baru.</p>
    </div>

    <?php if($pesan): ?>
        <div class="alert <?= $jenis_pesan ?>">
            <?= htmlspecialchars($pesan) ?>
        </div>
    <?php endif; ?>

    <div class="card">

        <h2>📷 Scan Barcode Produk</h2>

        <div class="scan-box">
            <div style="font-size:35px">▥</div>
            <strong>Scan barcode menggunakan kamera</strong>
            <p>Arahkan kamera ke barcode produk.</p>

            <div id="reader"></div>

            <div class="actions">
                <button type="button" onclick="mulaiScan()">Buka Kamera</button>
                <button type="button" class="stop" onclick="berhentiScan()">Tutup Kamera</button>
            </div>
        </div>

        <form method="POST">

            <div class="form-group">
                <label>Nomor Barcode</label>
                <input type="text" name="barcode" id="barcode"
                       placeholder="Scan atau masukkan barcode"
                       required>
            </div>

            <div class="form-grid">

                <div class="form-group full">
                    <label>Nama Barang</label>
                    <input type="text" name="nama_produk"
                           placeholder="Masukkan nama barang" required>
                </div>

                <div class="form-group">
                    <label>Harga Barang (Rp)</label>
                    <input type="number" name="harga" min="1"
                           placeholder="Contoh: 15000" required>
                </div>

                <div class="form-group">
                    <label>Stok Barang</label>
                    <input type="number" name="stok" min="0"
                           placeholder="Contoh: 100" required>
                </div>

                <div class="form-group full">
                    <label>Deskripsi Barang</label>
                    <textarea name="deskripsi"
                              placeholder="Deskripsi barang (opsional)"></textarea>
                </div>

            </div>

            <button type="submit" class="submit">+ Simpan Barang</button>

        </form>

    </div>
</div>

<script>
let scanner = null;
let sedangScan = false;

async function mulaiScan() {

    if (sedangScan) return;

    scanner = new Html5Qrcode("reader");

    try {
        const cameras = await Html5Qrcode.getCameras();

        if (!cameras || cameras.length === 0) {
            alert("Kamera tidak ditemukan.");
            return;
        }

        const kamera = cameras.find(c =>
            /back|rear|environment/i.test(c.label)
        ) || cameras[0];

        await scanner.start(
            kamera.id,
            { fps: 10, qrbox: { width: 280, height: 130 } },
            async function(teks) {

                document.getElementById('barcode').value = teks;

                await berhentiScan();

                alert("Barcode berhasil dibaca!");
            }
        );

        sedangScan = true;

    } catch(error) {
        alert("Tidak dapat membuka kamera. Pastikan izin kamera diberikan dan gunakan HTTPS atau localhost.");
    }
}

async function berhentiScan() {
    if (scanner && sedangScan) {
        try {
            await scanner.stop();
            await scanner.clear();
        } catch(e) {}

        sedangScan = false;
    }
}
</script>

</body>
</html>