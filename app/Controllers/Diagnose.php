<?php

namespace App\Controllers;

use App\Helpers\ExtensionChecker;

class Diagnose extends BaseController
{
    /**
     * Check all extensions required by the application
     */
    public function extensions()
    {
        $info = ExtensionChecker::getExtensionInfo();

        $data = [
            'title' => 'Extension Diagnostics',
            'info' => $info,
            'hashidCheck' => ExtensionChecker::checkHashidSupport(),
        ];

        return view('diagnose/extensions', $data);
    }

    /**
     * Check Hashids specifically
     */
    public function hashid()
    {
        $check = ExtensionChecker::checkHashidSupport();
        $data = [
            'title' => 'Hashids Diagnostics',
            'check' => $check,
        ];

        // Try to use Hashids
        if ($check['supported']) {
            try {
                $testId = 123;
                $encoded = hashid_encode($testId);
                $decoded = hashid_decode($encoded);

                $data['test'] = [
                    'success' => true,
                    'original' => $testId,
                    'encoded' => $encoded,
                    'decoded' => $decoded,
                    'match' => $testId === $decoded,
                ];
            } catch (\Exception $e) {
                $data['test'] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return view('diagnose/hashid', $data);
    }

    /**
     * Output JSON response for API checks
     */
    public function checkJson()
    {
        return $this->response->setJSON([
            'hashid' => ExtensionChecker::checkHashidSupport(),
            'extensions' => ExtensionChecker::getExtensionInfo(),
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }
}
