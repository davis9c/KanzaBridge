<?php

/**
 * =====================================================
 * AES Encryption/Decryption Utility
 * 
 * Keterangan:
 * - Algorithm: AES-128-CBC
 * - Compatible dengan Java (PKCS5Padding)
 * - Support Format: Base64 dan Hex
 * 
 * Penggunaan:
 * - $encrypted = EnkripsiAES::encryptBase64("plaintext");
 * - $decrypted = EnkripsiAES::decryptBase64($encrypted);
 * =====================================================
 */

class EnkripsiAES
{
    // Konfigurasi Encryption
    private const ALGORITHM = "AES-128-CBC";
    private const KEY = "Bar12345Bar12345";    // 16 bytes
    private const IV = "sayangsamakhanza";     // 16 bytes
    private const OPTIONS = OPENSSL_RAW_DATA;

    /**
     * Encrypt string menggunakan Base64 encoding
     * 
     * @param string $plaintext Plain text yang akan dienkripsi
     * @return string|false Base64 encoded encrypted text
     */
    public static function encryptBase64(string $plaintext): string|false
    {
        try {
            $encrypted = openssl_encrypt(
                $plaintext,
                self::ALGORITHM,
                self::KEY,
                self::OPTIONS,
                self::IV
            );

            return $encrypted ? base64_encode($encrypted) : false;
        } catch (Exception $e) {
            error_log("Encryption error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Decrypt Base64 encoded string
     * 
     * @param string $ciphertext Base64 encoded encrypted text
     * @return string|false Decrypted plain text
     */
    public static function decryptBase64(string $ciphertext): string|false
    {
        try {
            $decoded = base64_decode($ciphertext, true);
            if ($decoded === false) {
                return false;
            }

            return openssl_decrypt(
                $decoded,
                self::ALGORITHM,
                self::KEY,
                self::OPTIONS,
                self::IV
            );
        } catch (Exception $e) {
            error_log("Decryption error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Encrypt string menggunakan Hex encoding
     * 
     * @param string $plaintext Plain text yang akan dienkripsi
     * @return string|false Hex encoded encrypted text
     */
    public static function encryptHex(string $plaintext): string|false
    {
        try {
            $encrypted = openssl_encrypt(
                $plaintext,
                self::ALGORITHM,
                self::KEY,
                self::OPTIONS,
                self::IV
            );

            return $encrypted ? bin2hex($encrypted) : false;
        } catch (Exception $e) {
            error_log("Encryption error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Decrypt Hex encoded string
     * 
     * @param string $ciphertext Hex encoded encrypted text
     * @return string|false Decrypted plain text
     */
    public static function decryptHex(string $ciphertext): string|false
    {
        try {
            $decoded = hex2bin($ciphertext);
            if ($decoded === false) {
                return false;
            }

            return openssl_decrypt(
                $decoded,
                self::ALGORITHM,
                self::KEY,
                self::OPTIONS,
                self::IV
            );
        } catch (Exception $e) {
            error_log("Decryption error: " . $e->getMessage());
            return false;
        }
    }
}

// =====================================================
// DEMO & TEST
// =====================================================

function runDemo(): void
{
    echo "<h2>AES Encryption Demo</h2>";
    echo "<hr>";

    // Data untuk testing
    $plaintext = "	.Ò½,iA–RÂøu™";

    echo "<h3>1. PLAINTEXT:</h3>";
    echo "<p><code>" . htmlspecialchars($plaintext) . "</code></p>";
    echo "<br>";

    // Encrypt Base64
    echo "<h3>2. ENCRYPT BASE64:</h3>";
    $encBase64 = EnkripsiAES::encryptBase64($plaintext);
    echo "<p><code>" . ($encBase64 ? $encBase64 : "ERROR") . "</code></p>";
    echo "<br>";

    // Encrypt Hex
    echo "<h3>3. ENCRYPT HEX:</h3>";
    $encHex = EnkripsiAES::encryptHex($plaintext);
    echo "<p><code>" . ($encHex ? $encHex : "ERROR") . "</code></p>";
    echo "<hr>";

    // Decrypt Base64
    echo "<h3>4. DECRYPT BASE64:</h3>";
    $decBase64 = EnkripsiAES::decryptBase64($encBase64 ?? '');
    echo "<p><code>" . ($decBase64 ? htmlspecialchars($decBase64) : "ERROR") . "</code></p>";
    echo "<br>";

    // Decrypt Hex
    echo "<h3>5. DECRYPT HEX:</h3>";
    $decHex = EnkripsiAES::decryptHex($encHex ?? '');
    echo "<p><code>" . ($decHex ? htmlspecialchars($decHex) : "ERROR") . "</code></p>";
    echo "<hr>";

    // Verifikasi
    echo "<h3>6. VERIFIKASI:</h3>";
    $isValid = ($decBase64 === $plaintext) && ($decHex === $plaintext);
    $status = $isValid ? "<span style='color: green; font-weight: bold;'>✓ PASS</span>" : "<span style='color: red; font-weight: bold;'>✗ FAIL</span>";
    echo "<p>Status: " . $status . "</p>";
}

// Jalankan demo
runDemo();
