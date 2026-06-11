<?php

use Hashids\Hashids;

/**
 * Ambil instance Hashids yang reuse dalam satu request.
 *
 * Pastikan env('HASHIDS_SALT') terkonfigurasi di file env.
 */
function hashids(): Hashids
{
    static $hashids;

    if (! $hashids) {
        $salt = env('HASHIDS_SALT');

        if (! $salt) {
            throw new \RuntimeException('HASHIDS_SALT belum dikonfigurasi.');
        }

        $hashids = new Hashids($salt, 8);
    }

    return $hashids;
}

/**
 * Encode numeric id menjadi string hashids.
 */
function hashid_encode(int $id): string
{
    return hashids()->encode($id);
}

/**
 * Decode string hashids menjadi numeric id.
 */
function hashid_decode(string $hash): ?int
{
    $decoded = hashids()->decode($hash);
    return $decoded[0] ?? null;
}
