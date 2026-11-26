
<?php
// ===========================================
// KONFIGURASI DUITKU TOKOKU
// ===========================================

// Sandbox = true untuk pengujian
// Production = false untuk pembayaran asli
$duitkuSandbox = true;

// Merchant Code Sandbox
$duitkuMerchantCode = 'DS38613';

// API Key Sandbox
$duitkuApiKey = 'ad06097d2e5c83f9518b0b3a474f2d57';

// Endpoint Duitku POP Sandbox
$duitkuBaseUrl = 'https://api-sandbox.duitku.com/api/merchant/createInvoice';

// URL website TokoKu
$duitkuWebsite = 'https://sterida.my.id/alfian';

// Callback
$duitkuCallbackUrl = $duitkuWebsite . '/callback_duitku_pelanggan.php';

// Return URL
$duitkuReturnUrl = $duitkuWebsite . '/dashboard_pembeli.php';
?>
