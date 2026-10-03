<?php

namespace Tests\Support\Api;

use App\Libraries\Api\ApiKeyService;
use App\Models\Access\ApiApplicationModel;
use App\Models\Access\ApiKeyModel;
use App\Models\Access\ApiKeyScopeModel;
use App\Models\Access\UserModel;

/**
 * Pembuat data uji untuk fitur API key.
 *
 * Ada supaya test tidak perlu tahu detail hashing, format prefix, atau
 * urutan tabel yang harus dikosongkan.
 */
trait ApiKeyFactoryTrait
{
    private ApiApplicationModel $factoryApplications;
    private ApiKeyModel $factoryKeys;
    private ApiKeyScopeModel $factoryScopes;

    /**
     * Buat user lokal pemilik application.
     */
    protected function makeOwner(): int
    {
        $users = new UserModel();

        $users->createLocal([
            'usergate_id' => 'uuid-' . uniqid(),
            'username'    => 'pemilik-' . uniqid(),
            'email'       => uniqid() . '@example.com',
            'full_name'   => 'Pemilik Uji',

            // Default createLocal() adalah NONAKTIF. Pemilik di sini harus
            // aktif supaya merepresentasikan user sungguhan yang sudah
            // diaktifkan administrator.
            'status'      => UserModel::STATUS_ACTIVE,
        ]);

        return (int) $users->db->insertID();
    }

    /**
     * @return array{application:array<string,mixed>, key:array<string,mixed>, plain:string}
     */
    protected function makeApplication(?int $ownerId = null, string $name = 'Aplikasi Uji'): array
    {
        $ownerId ??= $this->makeOwner();

        $applications = new ApiApplicationModel();

        $applicationId = $applications->createApplication([
            'name'        => $name . ' ' . uniqid(),
            'code'        => 'uji-' . uniqid(),
            'description' => null,
            'created_by'  => $ownerId,
        ]);

        return [
            'application' => (array) $applications->find($applicationId),
            'key'         => [],
            'plain'       => '',
        ];
    }

    /**
     * Buat application + satu API key beserta scope-nya.
     *
     * @param  list<string>        $scopes    Scope yang diberikan ke key.
     * @param  array<string,mixed> $overrides Nilai kolom api_keys yang menimpa default.
     * @param  string              $name      Nama application (ikut diacak dengan uniqid).
     * @param  int|null            $ownerId   Pemilik application; null = user baru.
     * @return array{application:array<string,mixed>, key:array<string,mixed>, plain:string}
     */
    protected function makeApiKey(
        array $scopes = ['meta.read'],
        array $overrides = [],
        string $name = 'Aplikasi Uji',
        ?int $ownerId = null
    ): array {
        $made      = $this->makeApplication($ownerId, $name);
        $keys      = new ApiKeyModel();
        $scopesDb  = new ApiKeyScopeModel();
        $generated = (new ApiKeyService())->generate();

        $keyId = $keys->createKey($overrides + [
            'application_id'        => (int) $made['application']['id'],
            'label'                 => 'Key Uji',
            'key_prefix'            => $generated['prefix'],
            'key_hash'              => $generated['hash'],
            'status'                => ApiKeyModel::STATUS_ACTIVE,
            'expires_at'            => null,
            'rate_limit_per_minute' => 0,
        ]);

        $scopesDb->sync($keyId, $scopes);

        $made['key']   = (array) $keys->find($keyId);
        $made['plain'] = $generated['plain'];

        return $made;
    }
}