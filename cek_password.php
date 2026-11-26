
<?php

$hash = '$2y$10$h1vA5fUrVNqFY.4ba3a1keQfa6rw07e6PyTITzrSAGV';

// Masukkan password yang ingin diperiksa
$password = 'admin123';

if (password_verify($password, $hash)) {
    echo "Password BENAR!";
} else {
    echo "Password SALAH!";
}

?>
