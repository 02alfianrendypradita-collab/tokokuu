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

// =====================================
// API PENCARIAN BARANG DENGAN BARCODE
// =====================================

if (isset($_GET['aksi']) && $_GET['aksi'] === 'cari') {

    header('Content-Type: application/json; charset=utf-8');

    $barcode = trim($_GET['barcode'] ?? '');

    if ($barcode === '') {
        echo json_encode([
            'success' => false,
            'message' => 'Barcode kosong.'
        ]);
        exit;
    }

    $stmt = mysqli_prepare(
        $conn,
        "SELECT id_produk, nama_produk, barcode, harga, stok
         FROM produk
         WHERE barcode = ? AND status = 'aktif'
         LIMIT 1"
    );

    mysqli_stmt_bind_param($stmt, "s", $barcode);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $produk = mysqli_fetch_assoc($result);

    if (!$produk) {
        echo json_encode([
            'success' => false,
            'message' => 'Produk tidak ditemukan.'
        ]);
        exit;
    }

    if ((int)$produk['stok'] <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Stok barang habis.'
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'produk' => $produk
    ]);

    exit;
}

// =====================================
// PROSES SIMPAN TRANSAKSI
// =====================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $keranjang = json_decode(
        $_POST['keranjang_json'] ?? '[]',
        true
    );

    $metode = $_POST['metode_pembayaran'] ?? 'Cash';
    $id_rekening = (int)($_POST['id_rekening'] ?? 0);
    $nama_bank = null;
    $nomor_rekening = null;

    if (!in_array($metode, ['Cash', 'Transfer', 'QRIS', 'Duitku'], true)) {
        die("Metode pembayaran tidak valid.");
    }

    // Jika transfer, pastikan rekening yang dipilih benar-benar aktif
    if ($metode === 'Transfer') {
        if ($id_rekening <= 0) {
            die("Silakan pilih rekening bank untuk pembayaran transfer.");
        }
        $stmtBank = mysqli_prepare($conn, "SELECT nama_bank, nomor_rekening FROM rekening_bank WHERE id_rekening = ? AND status = 'aktif' LIMIT 1");
        if (!$stmtBank) { throw new Exception("Query rekening bank gagal disiapkan. Pastikan tabel rekening_bank tersedia."); }
        mysqli_stmt_bind_param($stmtBank, "i", $id_rekening);
        mysqli_stmt_execute($stmtBank);
        $bankResult = mysqli_stmt_get_result($stmtBank);
        $bankDipilih = mysqli_fetch_assoc($bankResult);
        if (!$bankDipilih) { die("Rekening bank tidak ditemukan atau tidak aktif."); }
        $nama_bank = $bankDipilih['nama_bank'];
        $nomor_rekening = $bankDipilih['nomor_rekening'];
    } else {
        $id_rekening = null;
    }

    if (!is_array($keranjang) || count($keranjang) === 0) {
        die("Keranjang masih kosong.");
    }

    mysqli_begin_transaction($conn);

    try {

        $total_harga = 0;
        $daftar_produk = [];

        // Ambil ulang harga dan stok dari database
        foreach ($keranjang as $item) {

            $id_produk = (int)($item['id'] ?? 0);
            $jumlah = (int)($item['jumlah'] ?? 0);

            if ($id_produk <= 0 || $jumlah <= 0) {
                throw new Exception("Data barang tidak valid.");
            }

            $stmt = mysqli_prepare(
                $conn,
                "SELECT id_produk, nama_produk, harga, stok
                 FROM produk
                 WHERE id_produk = ? AND status = 'aktif'
                 FOR UPDATE"
            );

            mysqli_stmt_bind_param($stmt, "i", $id_produk);
            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);
            $produk = mysqli_fetch_assoc($result);

            if (!$produk) {
                throw new Exception("Produk tidak ditemukan.");
            }

            if ($jumlah > (int)$produk['stok']) {
                throw new Exception(
                    "Stok {$produk['nama_produk']} tidak mencukupi."
                );
            }

            $subtotal = (float)$produk['harga'] * $jumlah;

            $total_harga += $subtotal;

            $daftar_produk[] = [
                'id_produk' => $id_produk,
                'jumlah' => $jumlah,
                'harga' => (float)$produk['harga'],
                'subtotal' => $subtotal
            ];
        }

        // Simpan transaksi; Cash selesai, Transfer/QRIS menunggu konfirmasi
        $statusTransaksi = ($metode === 'Cash') ? 'selesai' : 'pending';
        $id_user = (int)$_SESSION['id_user'];

        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO transaksi
            (id_user, tanggal_transaksi, total_harga,
             alamat_pengiriman, metode_pembayaran, status,
             id_rekening, nama_bank, nomor_rekening)
            VALUES (?, NOW(), ?, 'Penjualan langsung', ?, ?, ?, ?, ?)"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "idssiss",
            $id_user,
            $total_harga,
            $metode,
            $statusTransaksi,
            $id_rekening,
            $nama_bank,
            $nomor_rekening
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Gagal menyimpan transaksi.");
        }

        $id_transaksi = mysqli_insert_id($conn);

        // Simpan detail dan kurangi stok
        foreach ($daftar_produk as $item) {

            $id_produk = $item['id_produk'];
            $jumlah = $item['jumlah'];
            $harga = $item['harga'];
            $subtotal = $item['subtotal'];

            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO detail_transaksi
                (id_transaksi, id_produk, jumlah, harga, subtotal)
                VALUES (?, ?, ?, ?, ?)"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "iiidd",
                $id_transaksi,
                $id_produk,
                $jumlah,
                $harga,
                $subtotal
            );

            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Gagal menyimpan detail transaksi.");
            }

            $stmt = mysqli_prepare(
                $conn,
                "UPDATE produk
                 SET stok = stok - ?
                 WHERE id_produk = ? AND stok >= ?"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "iii",
                $jumlah,
                $id_produk,
                $jumlah
            );

            if (!mysqli_stmt_execute($stmt) || mysqli_stmt_affected_rows($stmt) !== 1) {
                throw new Exception("Gagal memperbarui stok barang.");
            }
        }

        // Request invoice Duitku Sandbox
        $paymentUrlDuitku = '';
        if ($metode === 'Duitku') {
            require_once __DIR__ . '/duitku_config.php';
            if (empty($duitkuMerchantCode) || empty($duitkuApiKey) || empty($duitkuApiUrl) || empty($duitkuBaseUrl)) {
                throw new Exception('Konfigurasi Duitku belum lengkap.');
            }
            $orderIdDuitku = (string)$id_transaksi;
            $payload = [
                'merchantCode' => $duitkuMerchantCode,
                'paymentAmount' => (int)round($total_harga),
                'merchantOrderId' => $orderIdDuitku,
                'productDetails' => 'Transaksi TokoKu #' . $id_transaksi,
                'customerVaName' => substr((string)($_SESSION['nama'] ?? 'Kasir'), 0, 20),
                'email' => 'tokoku@example.com',
                'callbackUrl' => rtrim($duitkuBaseUrl, '/') . '/duitku_callback.php',
                'returnUrl' => rtrim($duitkuBaseUrl, '/') . '/transaksi_kasir.php?hasil_duitku=1&id=' . $id_transaksi,
                'expiryPeriod' => 60
            ];
            $payload['signature'] = hash_hmac('sha256', $duitkuMerchantCode . $orderIdDuitku . $payload['paymentAmount'], $duitkuApiKey);
            $ch = curl_init(rtrim($duitkuApiUrl, '/'));
            curl_setopt_array($ch, [
                CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
                CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30
            ]);
            $responseDuitku = curl_exec($ch);
            $curlError = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($responseDuitku === false || $curlError) throw new Exception('Gagal menghubungi Duitku: ' . $curlError);
            $dataDuitku = json_decode($responseDuitku, true);
            if ($httpCode < 200 || $httpCode >= 300 || empty($dataDuitku['paymentUrl'])) {
                throw new Exception('Duitku gagal membuat invoice (HTTP ' . $httpCode . '). Detail: ' . (is_array($dataDuitku) ? ($dataDuitku['statusMessage'] ?? $dataDuitku['message'] ?? $dataDuitku['statusCode'] ?? json_encode($dataDuitku)) : substr((string)$responseDuitku, 0, 500)));
            }
            $paymentUrlDuitku = $dataDuitku['paymentUrl'];
        }

        mysqli_commit($conn);

        if ($metode === 'Duitku') {
            header('Location: ' . $paymentUrlDuitku);
            exit;
        }

        if ($metode === 'Cash') {
            header("Location: transaksi_kasir.php?sukses=" . $id_transaksi . "&metode=" . urlencode($metode));
        } else {
            header("Location: transaksi_kasir.php?pending=" . $id_transaksi . "&metode=" . urlencode($metode));
        }
        exit;

    } catch (Throwable $e) {

        mysqli_rollback($conn);

        $error = $e->getMessage();
    }
}

// Ambil daftar rekening bank aktif untuk pilihan pembayaran
$rekening_bank = [];
$qBank = mysqli_query($conn, "SELECT id_rekening, nama_bank, nomor_rekening, nama_pemilik FROM rekening_bank WHERE status = 'aktif' ORDER BY nama_bank ASC");
if ($qBank) { while ($rb = mysqli_fetch_assoc($qBank)) { $rekening_bank[] = $rb; } }

$nama_kasir = $_SESSION['nama'] ?? 'Kasir';
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">

<title>Transaksi Kasir - TokoKu</title>

<script src="https://unpkg.com/html5-qrcode"></script>

<style>
* {
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Segoe UI',sans-serif;
}

body {
    background:#f7f8f4;
    color:#405b46;
}

.container {
    max-width:1150px;
    margin:auto;
    padding:30px;
}

.header {
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:25px;
    gap:15px;
}

.header a {
    text-decoration:none;
    color:#829b83;
    font-size:13px;
    font-weight:700;
}

.header h1 {
    font-size:25px;
    margin-top:12px;
}

.header p {
    color:#99a196;
    font-size:13px;
    margin-top:5px;
}

.kasir {
    background:white;
    padding:12px 18px;
    border-radius:12px;
    font-size:12px;
    box-shadow:0 4px 15px rgba(50,75,55,.04);
}

.card {
    background:white;
    border-radius:18px;
    padding:25px;
    box-shadow:0 5px 20px rgba(50,75,55,.04);
    margin-bottom:20px;
}

.card h2 {
    font-size:17px;
    margin-bottom:20px;
}

.scan-area {
    display:grid;
    grid-template-columns:1fr auto;
    gap:12px;
}

input,select {
    width:100%;
    padding:14px;
    border:1px solid #e5ebe2;
    border-radius:10px;
    outline:none;
    font-size:13px;
    color:#405b46;
}

input:focus,select:focus {
    border-color:#829b83;
}

button {
    border:0;
    padding:13px 18px;
    background:#405b46;
    color:white;
    border-radius:10px;
    font-size:13px;
    font-weight:700;
    cursor:pointer;
    transition:.3s;
}

button:hover {background:#829b83;}

.btn-scan {background:#829b83;}
.btn-delete {background:#fff0ed;color:#c46c60;padding:8px 11px;}

.camera {
    margin-top:18px;
    padding:20px;
    border:1px dashed #b8c9b5;
    border-radius:14px;
    background:#f8faf6;
    text-align:center;
}

#reader {
    max-width:420px;
    margin:15px auto;
    overflow:hidden;
    border-radius:12px;
}

.camera-actions {
    display:flex;
    justify-content:center;
    gap:10px;
    flex-wrap:wrap;
}

.info {
    font-size:12px;
    color:#99a196;
    margin-top:12px;
}

.table-responsive {overflow-x:auto;}

table {
    width:100%;
    border-collapse:collapse;
    min-width:550px;
}

th {
    background:#f8faf6;
    padding:14px;
    text-align:left;
    color:#99a196;
    font-size:11px;
}

td {
    padding:15px 14px;
    border-bottom:1px solid #f0f2ee;
    font-size:12px;
}

.qty {
    display:flex;
    align-items:center;
    gap:8px;
}

.qty button {
    padding:6px 10px;
}

.total-box {
    background:#f3f7f1;
    padding:23px;
    border-radius:15px;
    margin-top:20px;
}

.total-row {
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
}

.total-row h2 {
    font-size:25px;
    margin:0;
}

.payment {
    margin-top:22px;
}

.payment label {
    display:block;
    font-size:12px;
    font-weight:700;
    margin-bottom:9px;
}

.rekening-info {display:none;margin-top:12px;padding:15px;border-radius:12px;background:#edf5ec;border:1px solid #dce9d8;font-size:13px;line-height:1.7;}
.rekening-info strong {display:block;}
.checkout {
    width:100%;
    margin-top:20px;
    padding:16px;
    font-size:14px;
}

.empty {
    text-align:center;
    color:#a0aaa0;
    padding:35px;
}

.alert {
    padding:14px;
    border-radius:10px;
    margin-bottom:18px;
    font-size:13px;
}

.success {background:#e2f6e7;color:#238344;}
.error {background:#fff0ed;color:#c46c60;}

@media(max-width:650px) {
    .container {padding:15px;}
    .header {align-items:flex-start;}
    .header h1 {font-size:21px;}
    .card {padding:17px;}
    .scan-area {grid-template-columns:1fr;}
    .total-row {align-items:flex-start;flex-direction:column;}
}

.payment-success{position:fixed;z-index:9999;inset:0;background:rgba(15,25,15,.72);display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:24px}
.payment-success:before{content:'';position:absolute;inset:0;backdrop-filter:blur(3px)}
.payment-success>*{position:relative}
.payment-success{color:#263b2c}
.payment-success h2,.payment-success p,.payment-success button,.success-icon{max-width:420px;width:100%}
.payment-success h2{background:#fff;padding:0 28px;color:#284d34}
.payment-success p{background:#fff;padding:8px 28px}
.payment-success .success-method{padding-bottom:20px;border-radius:0 0 16px 16px}
.success-icon{width:84px;height:84px;border-radius:50%;background:#2e9d57;color:white;font-size:48px;display:flex;align-items:center;justify-content:center;margin-bottom:12px;box-shadow:0 8px 25px rgba(0,0,0,.2)}
.payment-success button{width:auto;border:0;border-radius:12px;background:#2e9d57;color:white;padding:13px 22px;margin-top:18px;font-weight:700;cursor:pointer}

</style>
</head>

<body>

<div class="container">

    <header class="header">
        <div>
            <a href="dashboard_kasir.php">← Kembali ke Dashboard</a>
            <h1>Transaksi Penjualan</h1>
            <p>Scan barcode dan layani pembelian pelanggan.</p>
        </div>

        <div class="kasir">
            👤 <?= htmlspecialchars($nama_kasir) ?>
        </div>
    </header>

    <?php if(isset($_GET['sukses'])): ?>
        <div class="payment-success" id="successPanel">
            <div class="success-icon">✓</div>
            <h2>Pembayaran Berhasil!</h2>
            <p>Transaksi #<?= htmlspecialchars($_GET['sukses']) ?> telah dikonfirmasi dan disimpan.</p>
            <p class="success-method">Metode: <?= htmlspecialchars($_GET['metode'] ?? 'Pembayaran') ?></p>
            <button type="button" onclick="tutupSukses()">Lanjutkan Transaksi</button>
        </div>
    <?php endif; ?>

    <?php if(isset($_GET['hasil_duitku'])): ?>
        <div class="alert success">Kamu kembali dari halaman Duitku. Status pembayaran akan mengikuti konfirmasi callback, bukan hanya redirect ini.</div>
    <?php endif; ?>

    <?php if(isset($_GET['pending'])): ?>
        <div class="payment-success" id="pendingPanel" style="background:#fff8e7;color:#624b16;border:1px solid #f0d58a">
            <div class="success-icon" style="background:#e0a126">!</div>
            <h2 style="color:#8a6412">Menunggu Konfirmasi Pembayaran</h2>
            <p>Transaksi #<?= htmlspecialchars($_GET['pending']) ?> belum dinyatakan lunas.</p>
            <p>Periksa uang transfer atau pembayaran QRIS terlebih dahulu.</p>
            <p class="success-method">Metode: <?= htmlspecialchars($_GET['metode'] ?? 'Pembayaran') ?></p>
            <form method="post" action="konfirmasi_pembayaran.php" onsubmit="return confirm('Pastikan pembayaran benar-benar sudah diterima. Konfirmasi transaksi ini?')">
                <input type="hidden" name="id_transaksi" value="<?= (int)$_GET['pending'] ?>">
                <button type="submit" style="background:#2e9d57">Konfirmasi Pembayaran Diterima</button>
            </form>
            <p style="font-size:12px;margin-top:12px">Jangan konfirmasi jika uang belum diterima.</p>
        </div>
    <?php endif; ?>

    <?php if(isset($error)): ?>
        <div class="alert error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <section class="card">

        <h2>▥ Scan Barcode Produk</h2>

        <div class="scan-area">

            <input type="text"
                   id="barcodeInput"
                   placeholder="Scan atau masukkan barcode produk..."
                   autocomplete="off">

            <button type="button" onclick="cariBarcode()">
                Cari Barang
            </button>

        </div>

        <div class="camera">

            <div style="font-size:30px">📷</div>

            <strong>Gunakan kamera untuk scan barcode</strong>

            <p class="info">
                Arahkan kamera ke barcode pada kemasan barang.
            </p>

            <div id="reader"></div>

            <div class="camera-actions">
                <button type="button" class="btn-scan" onclick="mulaiScan()">
                    Buka Kamera
                </button>

                <button type="button" onclick="berhentiScan()">
                    Tutup Kamera
                </button>
            </div>

        </div>

    </section>

    <section class="card">

        <h2>🛒 Keranjang Belanja</h2>

        <div class="table-responsive">

            <table>

                <thead>
                    <tr>
                        <th>PRODUK</th>
                        <th>HARGA</th>
                        <th>JUMLAH</th>
                        <th>SUBTOTAL</th>
                        <th>AKSI</th>
                    </tr>
                </thead>

                <tbody id="keranjangBody">
                    <tr>
                        <td colspan="5" class="empty">
                            Belum ada barang dalam keranjang.
                        </td>
                    </tr>
                </tbody>

            </table>

        </div>

        <div class="total-box">

            <div class="total-row">
                <div>
                    <p style="font-size:12px;color:#829b83">
                        TOTAL PEMBAYARAN
                    </p>

                    <h2 id="totalHarga">Rp 0</h2>
                </div>

                <div style="min-width:260px" class="payment">
                    <label>Metode Pembayaran</label>
                    <select id="metode">
                        <option value="Cash">Cash (Tunai)</option>
                        <option value="Transfer">Transfer Bank</option>
                        <option value="QRIS">QRIS Manual</option>
                        <option value="Duitku">Duitku (Online)</option>
                    </select>

                    <div id="pilihanRekening" style="display:none;margin-top:12px">
                        <label for="rekeningSelect">Pilih Rekening Bank</label>
                        <select id="rekeningSelect">
                            <option value="">-- Pilih rekening --</option>
                            <?php foreach ($rekening_bank as $bank): ?>
                                <option value="<?= (int)$bank['id_rekening'] ?>" data-bank="<?= htmlspecialchars($bank['nama_bank'], ENT_QUOTES) ?>" data-nomor="<?= htmlspecialchars($bank['nomor_rekening'], ENT_QUOTES) ?>" data-pemilik="<?= htmlspecialchars($bank['nama_pemilik'], ENT_QUOTES) ?>">
                                    <?= htmlspecialchars($bank['nama_bank']) ?> - <?= htmlspecialchars($bank['nomor_rekening']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="detailRekening" class="rekening-info"></div>
                        <?php if (count($rekening_bank) === 0): ?>
                            <p class="info" style="color:#c46c60">Belum ada rekening bank aktif. Tambahkan rekening di database terlebih dahulu.</p>
                        <?php endif; ?>
                    </div>
                    <div id="pilihanQRIS" style="display:none;margin-top:12px;padding:14px;border:1px solid #dce9d8;border-radius:12px;text-align:center;background:#edf5ec">
                        <strong>Scan QRIS TokoKu</strong>
                        <p style="font-size:13px;margin:8px 0">Silakan scan QR di bawah untuk melakukan pembayaran.</p>
                        <img src="qris_tokoku.png" alt="QRIS TokoKu" style="max-width:260px;width:100%;height:auto;border-radius:10px;background:white;padding:8px" onerror="this.style.display='none';document.getElementById('qrisError').style.display='block';">
                        <p id="qrisError" style="display:none;color:#c46c60">Gambar QRIS tidak ditemukan. Pastikan file qris_tokoku.png berada di folder yang sama dengan transaksi_kasir.php.</p>
                    </div>
                </div>
            </div>

            <form method="POST" id="formCheckout">

                <input type="hidden"
                       name="keranjang_json"
                       id="keranjangJson">

                <input type="hidden" name="metode_pembayaran" id="metodeInput">
                <input type="hidden" name="id_rekening" id="idRekeningInput">

                <button type="submit" class="checkout">
                    ✓ Simpan Transaksi
                </button>

            </form>

        </div>

    </section>

</div>

<script>

let keranjang = [];
let scanner = null;
let sedangScan = false;
let sedangMencari = false;

const inputBarcode = document.getElementById('barcodeInput');

inputBarcode.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        cariBarcode();
    }
});

function formatRupiah(angka) {
    return 'Rp ' + Number(angka).toLocaleString('id-ID');
}

async function cariBarcode() {

    if (sedangMencari) return;

    const barcode = inputBarcode.value.trim();

    if (!barcode) {
        alert('Masukkan barcode terlebih dahulu.');
        return;
    }

    sedangMencari = true;

    try {

        const response = await fetch(
            'transaksi_kasir.php?aksi=cari&barcode=' +
            encodeURIComponent(barcode)
        );

        const data = await response.json();

        if (!data.success) {
            alert(data.message);
            return;
        }

        tambahKeranjang(data.produk);

        inputBarcode.value = '';

    } catch(error) {
        alert('Gagal menghubungkan ke server.');
    } finally {
        sedangMencari = false;
        inputBarcode.focus();
    }
}

function tambahKeranjang(produk) {

    const id = Number(produk.id_produk);

    const ditemukan = keranjang.find(item => item.id === id);

    if (ditemukan) {

        if (ditemukan.jumlah >= Number(produk.stok)) {
            alert('Jumlah melebihi stok tersedia.');
            return;
        }

        ditemukan.jumlah++;

    } else {

        keranjang.push({
            id: id,
            nama: produk.nama_produk,
            harga: Number(produk.harga),
            stok: Number(produk.stok),
            jumlah: 1
        });

    }

    renderKeranjang();
}

function ubahJumlah(id, perubahan) {

    const item = keranjang.find(p => p.id === id);

    if (!item) return;

    const jumlahBaru = item.jumlah + perubahan;

    if (jumlahBaru <= 0) {
        hapusBarang(id);
        return;
    }

    if (jumlahBaru > item.stok) {
        alert('Stok tidak mencukupi.');
        return;
    }

    item.jumlah = jumlahBaru;

    renderKeranjang();
}

function hapusBarang(id) {

    keranjang = keranjang.filter(item => item.id !== id);

    renderKeranjang();
}

function renderKeranjang() {

    const tbody = document.getElementById('keranjangBody');

    let total = 0;

    if (keranjang.length === 0) {

        tbody.innerHTML = `
            <tr>
                <td colspan="5" class="empty">
                    Belum ada barang dalam keranjang.
                </td>
            </tr>
        `;

    } else {

        tbody.innerHTML = keranjang.map(item => {

            const subtotal = item.harga * item.jumlah;

            total += subtotal;

            return `
                <tr>
                    <td><strong>${escapeHTML(item.nama)}</strong></td>

                    <td>${formatRupiah(item.harga)}</td>

                    <td>
                        <div class="qty">
                            <button type="button"
                                onclick="ubahJumlah(${item.id},-1)">−</button>

                            ${item.jumlah}

                            <button type="button"
                                onclick="ubahJumlah(${item.id},1)">+</button>
                        </div>
                    </td>

                    <td>${formatRupiah(subtotal)}</td>

                    <td>
                        <button type="button"
                            class="btn-delete"
                            onclick="hapusBarang(${item.id})">
                            Hapus
                        </button>
                    </td>
                </tr>
            `;

        }).join('');

    }

    document.getElementById('totalHarga').textContent =
        formatRupiah(total);
}

function escapeHTML(teks) {
    return String(teks).replace(/[&<>"']/g, function(char) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[char];
    });
}

const metodeSelect = document.getElementById('metode');
const pilihanRekening = document.getElementById('pilihanRekening');
const rekeningSelect = document.getElementById('rekeningSelect');
const detailRekening = document.getElementById('detailRekening');
const pilihanQRIS = document.getElementById('pilihanQRIS');

function aturMetodePembayaran() {
    const transfer = metodeSelect.value === 'Transfer';
    const qris = metodeSelect.value === 'QRIS';
    pilihanRekening.style.display = transfer ? 'block' : 'none';
    pilihanQRIS.style.display = qris ? 'block' : 'none';
    if (!transfer) { rekeningSelect.value = ''; detailRekening.style.display = 'none'; detailRekening.innerHTML = ''; }
}

metodeSelect.addEventListener('change', aturMetodePembayaran);
rekeningSelect.addEventListener('change', function() {
    const opt = rekeningSelect.options[rekeningSelect.selectedIndex];
    if (!rekeningSelect.value) { detailRekening.style.display='none'; detailRekening.innerHTML=''; return; }
    detailRekening.innerHTML = '<strong>' + escapeHTML(opt.dataset.bank || '') + '</strong>Nomor rekening: ' + escapeHTML(opt.dataset.nomor || '') + '<br>Nama pemilik: ' + escapeHTML(opt.dataset.pemilik || '');
    detailRekening.style.display = 'block';
});
aturMetodePembayaran();

document.getElementById('formCheckout').addEventListener(
    'submit',
    function(e) {

        if (keranjang.length === 0) {
            e.preventDefault();
            alert('Keranjang masih kosong.');
            return;
        }

        if (metodeSelect.value === 'Transfer' && !rekeningSelect.value) {
            e.preventDefault();
            alert('Pilih rekening bank terlebih dahulu.');
            return;
        }

        if (!confirm('Apakah transaksi akan disimpan?')) {
            e.preventDefault();
            return;
        }

        document.getElementById('keranjangJson').value =
            JSON.stringify(keranjang.map(item => ({
                id: item.id,
                jumlah: item.jumlah
            })));

        document.getElementById('metodeInput').value = metodeSelect.value;
        document.getElementById('idRekeningInput').value =
            metodeSelect.value === 'Transfer' ? rekeningSelect.value : '';
    }
);

// ==================================
// SCAN KAMERA
// ==================================

async function mulaiScan() {

    if (sedangScan) return;

    scanner = new Html5Qrcode("reader");

    try {

        const cameras = await Html5Qrcode.getCameras();

        if (!cameras || cameras.length === 0) {
            alert('Kamera tidak ditemukan.');
            return;
        }

        const kamera = cameras.find(c =>
            /back|rear|environment/i.test(c.label)
        ) || cameras[0];

        await scanner.start(
            kamera.id,
            {
                fps: 10,
                qrbox: {width:280,height:130}
            },
            async function(teks) {

                await berhentiScan();

                inputBarcode.value = teks;

                await cariBarcode();
            }
        );

        sedangScan = true;

    } catch(error) {

        alert('Tidak dapat membuka kamera. Periksa izin kamera dan HTTPS.');
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

renderKeranjang();

</script>

<script>
function tutupSukses(){
 const panel=document.getElementById('successPanel');
 if(panel) panel.remove();
 const url=new URL(window.location.href);
 url.searchParams.delete('sukses');url.searchParams.delete('metode');
 window.history.replaceState({},'',url);
}
</script>
</body>
</html>