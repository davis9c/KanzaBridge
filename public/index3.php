<?php

/**
 * =====================================================
 * AES Encrypt / Decrypt (SAFE VERSION)
 * Java Compatible - AES-128-CBC / PKCS5Padding
 * =====================================================
 */

class EnkripsiAES
{
    public static $key = "Bar12345Bar12345";   // 16 bytes
    public static $iv  = "sayangsamakhanza";   // 16 bytes

    /* ================= BASE64 ================= */
    public static function encryptBase64($value)
    {
        $raw = openssl_encrypt(
            $value,
            "AES-128-CBC",
            self::$key,
            OPENSSL_RAW_DATA,
            self::$iv
        );
        return base64_encode($raw);
    }

    public static function decryptBase64($cipher)
    {
        return openssl_decrypt(
            base64_decode($cipher),
            "AES-128-CBC",
            self::$key,
            OPENSSL_RAW_DATA,
            self::$iv
        );
    }

    /* ================= HEX ================= */
    public static function encryptHex($value)
    {
        $raw = openssl_encrypt(
            $value,
            "AES-128-CBC",
            self::$key,
            OPENSSL_RAW_DATA,
            self::$iv
        );
        return bin2hex($raw);
    }

    public static function decryptHex($cipher)
    {
        return openssl_decrypt(
            hex2bin($cipher),
            "AES-128-CBC",
            self::$key,
            OPENSSL_RAW_DATA,
            self::$iv
        );
    }
}

/* =========================
   PROSES FORM
   ========================= */
$input  = '';
$output = '';
$mode   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = $_POST['text'] ?? '';
    $mode  = $_POST['mode'] ?? '';

    switch ($mode) {
        case 'encrypt_base64':
            $output = EnkripsiAES::encryptBase64($input);
            break;

        case 'decrypt_base64':
            $output = EnkripsiAES::decryptBase64($input);
            break;

        case 'encrypt_hex':
            $output = EnkripsiAES::encryptHex($input);
            break;

        case 'decrypt_hex':
            $output = EnkripsiAES::decryptHex($input);
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>AES Encrypt / Decrypt (SAFE)</title>
    <style>
        body {
            font-family: Arial;
            background: #f2f2f2;
        }

        .box {
            width: 760px;
            margin: 30px auto;
            background: #fff;
            padding: 20px;
            border-radius: 6px;
            box-shadow: 0 0 10px rgba(0, 0, 0, .1);
        }

        textarea {
            width: 100%;
            height: 120px;
            font-family: monospace;
            padding: 10px;
        }

        button {
            padding: 10px 15px;
            margin: 5px;
            cursor: pointer;
        }

        .info,
        .result {
            background: #eee;
            padding: 10px;
            margin-top: 15px;
            font-family: monospace;
            word-break: break-all;
        }

        .warn {
            background: #ffe0e0;
            padding: 10px;
            margin-top: 15px;
            font-family: monospace;
        }
    </style>
</head>

<body>

    <div class="box">
        <h2>AES Encrypt / Decrypt (Java & Khanza Compatible)</h2>

        <div class="info">
            <b>Algorithm :</b> AES-128-CBC (PKCS5Padding)<br>
            <b>Key :</b> <?= EnkripsiAES::$key ?><br>
            <b>IV :</b> <?= EnkripsiAES::$iv ?><br>
            <b>Input yang didukung :</b> Base64 / HEX
        </div>

        <div class="warn">
            ⚠️ <b>PENTING:</b><br>
            Jangan masukkan cipher seperti <code>$ó5ìžHÉïËvg‘ÑInc</code><br>
            Itu <b>RAW BINARY</b> dan <u>SUDAH RUSAK</u> jika lewat form / browser.
        </div>

        <form method="post">
            <label>Input Text / Cipher</label>
            <textarea name="text"><?= htmlspecialchars($input) ?></textarea>

            <br>
            <button name="mode" value="encrypt_base64">Encrypt (Base64)</button>
            <button name="mode" value="decrypt_base64">Decrypt (Base64)</button>
            <button name="mode" value="encrypt_hex">Encrypt (HEX)</button>
            <button name="mode" value="decrypt_hex">Decrypt (HEX)</button>
        </form>

        <?php if ($output !== ''): ?>
            <h3>Hasil</h3>
            <div class="result"><?= htmlspecialchars($output) ?></div>
        <?php endif; ?>

        <div class="info">
            <b>Contoh pemakaian langsung (PHP):</b><br><br>
            <code>
                $base64 = "JPG8e4zvY0kS0kQk...";<br>
                echo EnkripsiAES::decryptBase64($base64);
            </code>
        </div>
    </div>

</body>

</html>