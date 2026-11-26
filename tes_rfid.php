
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tes RFID</title>
</head>
<body style="font-family:Arial; padding:40px;">

    <h2>TES RFID READER</h2>

    <p>Tempelkan kartu RFID ke reader.</p>

    <input
        type="text"
        id="rfid"
        placeholder="Tempelkan kartu..."
        autofocus
        style="padding:15px;width:300px;font-size:18px;"
    >

    <h3>UID yang terbaca:</h3>
    <h2 id="hasil" style="color:green;">Belum ada kartu</h2>

    <script>
        const input = document.getElementById('rfid');
        const hasil = document.getElementById('hasil');

        input.addEventListener('input', function () {
            hasil.textContent = input.value;
        });

        input.addEventListener('keydown', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                hasil.textContent = input.value;
                input.value = '';
            }
        });

        window.onload = function () {
            input.focus();
        };
    </script>

</body>
</html>
