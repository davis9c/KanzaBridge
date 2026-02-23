<?php

namespace App\Helpers;

/**
 * Extension Check Helper
 * 
 * Membantu diagnosa dan verifikasi extension PHP yang diperlukan
 */
class ExtensionChecker
{
    /**
     * Check if math extension is available for Hashids
     */
    public static function hasMathExtension(): bool
    {
        return extension_loaded('bcmath') || extension_loaded('gmp');
    }

    /**
     * Get available math extension
     * 
     * @return string|null 'bcmath', 'gmp', or null
     */
    public static function getMathExtension(): ?string
    {
        if (extension_loaded('bcmath')) {
            return 'bcmath';
        }
        if (extension_loaded('gmp')) {
            return 'gmp';
        }
        return null;
    }

    /**
     * Get detailed extension information
     * 
     * @return array
     */
    public static function getExtensionInfo(): array
    {
        $extensions = [
            'bcmath' => extension_loaded('bcmath'),
            'gmp' => extension_loaded('gmp'),
        ];

        $mathExt = self::getMathExtension();

        return [
            'available' => self::hasMathExtension(),
            'active' => $mathExt,
            'extensions' => $extensions,
            'php_version' => PHP_VERSION,
            'php_sapi' => php_sapi_name(),
            'loaded_extensions' => get_loaded_extensions(),
        ];
    }

    /**
     * Check if Hashids can function properly
     * 
     * @return array with status and message
     */
    public static function checkHashidSupport(): array
    {
        $hasMath = self::hasMathExtension();
        $mathExt = self::getMathExtension();

        return [
            'supported' => $hasMath,
            'extension' => $mathExt,
            'message' => $hasMath
                ? "Hashids support available ({$mathExt})"
                : 'Missing math extension for Hashids. Install either bcmath or gmp.',
            'php_sapi' => php_sapi_name(),
        ];
    }
}
