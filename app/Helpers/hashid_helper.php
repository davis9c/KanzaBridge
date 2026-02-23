<?php

use Hashids\Hashids;

function hashids(): Hashids
{
    static $hashids;

    if (! $hashids) {
        $hashids = new Hashids(
            //env('HASHIDS_SALT', 'xproject-secret'),
            env('HASHIDS_SALT'),
            8
        );
    }

    return $hashids;
}

function hashid_encode(int $id): string
{
    return hashids()->encode($id);
}

function hashid_decode(string $hash): ?int
{
    $decoded = hashids()->decode($hash);
    return $decoded[0] ?? null;
}
