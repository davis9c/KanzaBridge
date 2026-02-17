<?php

/**
 * =====================================================
 * FILE : crypto_form.php (single file)
 * =====================================================
 */

/* =========================
   AES-256-CBC
   ========================= */
function encrypt_decrypt($string, $action)
{
    $secret_key     = 'Bar12345Bar12345';
    $secret_iv      = 'sayangsamakhanza';
    $encrypt_method = "AES-256-CBC";

    $key = hash('sha256', $secret_key);
    $iv  = substr(hash('sha256', $secret_iv), 0, 16);

    if ($action === "e") {
        return base64_encode(
            openssl_encrypt($string, $encrypt_method, $key, 0, $iv)
        );
    }

    if ($action === "d") {
        return openssl_decrypt(
            base64_decode($string),
            $encrypt_method,
            $key,
            0,
            $iv
        );
    }
    return '';
}

/* =========================
   PROSES FORM
   ========================= */
$result = '';
$input  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input  = $_POST['text'] ?? '';
    $action = $_POST['action'] ?? '';

    if ($action === 'encrypt') {
        $result = encrypt_decrypt($input, 'e');
    }

    if ($action === 'decrypt') {
        $result = encrypt_decrypt($input, 'd');
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Encrypt / Decrypt AES</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
        }

        .box {
            width: 600px;
            margin: 40px auto;
            background: #fff;
            padding: 20px;
            border-radius: 6px;
            box-shadow: 0 0 10px rgba(0, 0, 0, .1);
        }

        textarea {
            width: 100%;
            height: 120px;
            padding: 10px;
            font-family: monospace;
        }

        button {
            padding: 10px 20px;
            margin-top: 10px;
            cursor: pointer;
        }

        .result {
            background: #eee;
            padding: 10px;
            margin-top: 15px;
            word-break: break-all;
            font-family: monospace;
        }
    </style>
</head>

<body>

    <div class="box">
        <h2>Encrypt / Decrypt (AES-256-CBC)</h2>

        <form method="post">
            <label>Input Text</label><br>
            <textarea name="text"><?= htmlspecialchars($input) ?></textarea><br>

            <button type="submit" name="action" value="encrypt">Encrypt</button>
            <button type="submit" name="action" value="decrypt">Decrypt</button>
        </form>

        <?php if ($result !== ''): ?>
            <h3>Hasil</h3>
            <div class="result"><?= htmlspecialchars($result) ?></div>
        <?php endif; ?>
    </div>

</body>

</html>