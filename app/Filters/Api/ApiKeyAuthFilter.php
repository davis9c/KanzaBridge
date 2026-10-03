<?php

namespace App\Filters\Api;

use App\Libraries\Api\ApiKeyService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Autentikasi API V2 berbasis API key.
 *
 * Dipasang per route sebagai `apikey:<scope>`, misalnya:
 *
 *     $routes->get('v2/dokter', 'Api\V2\Dokter::index', ['filter' => 'apikey:dokter.read']);
 *
 * Scope ditulis sebagai argumen filter (bukan dicocokkan dari path)
 * supaya route dan tabel permission tidak mungkin berbeda: yang memblokir
 * ada di route itu sendiri.
 *
 * Urutan pemeriksaan sengaja dari yang paling murah ke yang paling
 * mahal, supaya request bermasalah tidak menyentuh database.
 */
class ApiKeyAuthFilter implements FilterInterface
{
    private ApiKeyService $keys;

    public function __construct()
    {
        $this->keys = new ApiKeyService();
    }

    /**
     * @param list<string>|null $arguments Argumen filter; elemen pertama = scope wajib.
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $plainKey = $this->keys->extractFromRequest($request);

        if ($plainKey === '') {
            return $this->error(
                'API key tidak ditemukan. Kirim API key pada header X-API-Key.',
                401
            );
        }

        $found = $this->keys->findByPlainKey($plainKey);

        if ($found['ok'] !== true) {
            return $this->error(
                'API key tidak valid.',
                401,
                $this->reasonMessage($found['failure'])
            );
        }

        $key = (array) $found['key'];

        $rate = $this->keys->hitRateLimit($key);

        if ($rate['allowed'] !== true) {
            return service('response')
                ->setStatusCode(429)
                ->setHeader('Retry-After', (string) $rate['retryAfter'])
                ->setJSON([
                    'status'  => 429,
                    'message' => 'Terlalu banyak permintaan. Batas key ini adalah '
                        . $rate['limit'] . ' request per menit.',
                ]);
        }

        $scopes = $this->keys->scopesFor((int) $key['id']);
        $need   = $this->requiredScope($arguments);

        if ($need !== null && ! in_array($need, $scopes, true)) {
            return $this->error(
                'API key ini tidak memiliki akses ke endpoint tersebut.',
                403,
                null,
                ['required_scope' => $need]
            );
        }

        $this->keys->touchUsage((int) $key['id']);

        // Disuntik ke request supaya controller bisa membacanya tanpa
        // perlu autentikasi ulang.
        $request->apiKey = [
            'key_id'         => (int) $key['id'],
            'application_id' => (int) $key['application_id'],
            'label'          => (string) $key['label'],
            'key_prefix'     => (string) $key['key_prefix'],
            'scopes'         => $scopes,
        ];

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Biar integrator bisa membedakan limit key-nya sendiri,
        // header ini ikut dikembalikan di setiap respons sukses.
        $apiKey = $request->apiKey ?? null;

        if (is_array($apiKey)) {
            $response->setHeader('X-Api-Client', (string) $apiKey['application_id']);
        }

        return $response;
    }

    /**
     * Scope wajib dari argumen filter.
     *
     * Route tanpa scope berarti "hanya butuh key yang hidup" — dipakai
     * oleh endpoint meta.
     *
     * @param  mixed $arguments
     */
    private function requiredScope($arguments): ?string
    {
        if (! is_array($arguments)) {
            return null;
        }

        foreach ($arguments as $argument) {
            if (! is_string($argument)) {
                continue;
            }

            $argument = trim($argument);

            if ($argument !== '') {
                return $argument;
            }
        }

        return null;
    }

    /**
     * Alasan kegagalan hanya untuk log server, tidak untuk klien.
     *
     * @param array<string,mixed> $extra
     */
    private function error(string $message, int $status, ?string $reason = null, array $extra = []): ResponseInterface
    {
        if ($reason !== null) {
            log_message('warning', 'API V2 ditolak: {reason}', ['reason' => $reason]);
        }

        return service('response')
            ->setStatusCode($status)
            ->setJSON(array_merge(['status' => $status, 'message' => $message], $extra));
    }

    private function reasonMessage(?string $failure): ?string
    {
        return match ($failure) {
            ApiKeyService::FAILURE_REVOKED      => 'api_key_revoked',
            ApiKeyService::FAILURE_EXPIRED      => 'api_key_expired',
            ApiKeyService::FAILURE_RATE_LIMITED => 'api_key_rate_limited',
            default                             => 'api_key_unknown',
        };
    }
}