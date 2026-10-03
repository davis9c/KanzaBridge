<?php

// Partial include() tidak menjamin semua variabel terdefinisi, jadi
// setiap data dari luar diambil dengan operator ??.
$catalog   = $catalog ?? [];
$selected  = $selected ?? [];
$idPrefix  = $idPrefix ?? 'scope';
$showTools = (bool) ($showTools ?? false);

/**
 * Checklist endpoint yang boleh diakses sebuah API key.
 *
 * Dipakai dua kali di halaman "API Key":
 *   - modal Buat Key       (semua belum tercentang)
 *   - modal Hak Akses      (sesuai scope key yang diklik)
 *
 * Dipisah supaya isi dan markup-nya tidak ditulis dua kali. Perbedaannya
 * hanya nilai tercentang dan prefix id checkbox.
 *
 * @var array<string, array{label:string, endpoints:array<string, array<string,mixed>>}> $catalog
 * @var list<string> $selected
 * @var string $idPrefix   Prefix unik per pemakaian, supaya dua modal
 *                         di halaman yang sama tidak menghasilkan id DOM kembar.
 * @var bool $showTools    Tampilkan tombol Pilih semua / Kosongkan.
 */
?>

<?php if ($showTools): ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <span class="small text-muted">
            <span data-scope-count><?= count($selected) ?></span> endpoint diizinkan.
            Minimal satu endpoint — mengosongkan semua berarti key tidak bisa dipakai.
        </span>
        <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-outline-secondary" data-scope-toggle="all">
                Pilih semua
            </button>
            <button type="button" class="btn btn-outline-secondary" data-scope-toggle="none">
                Kosongkan
            </button>
        </div>
    </div>
<?php endif; ?>

<?php foreach ($catalog as $group): ?>
    <div class="mb-3">
        <h6 class="text-uppercase text-secondary small fw-bold mb-2">
            <?= esc($group['label']) ?>
        </h6>
        <div class="row">
            <?php foreach ($group['endpoints'] as $scope => $meta): ?>
                <?php $boxId = $idPrefix . '-' . $scope; ?>
                <div class="col-md-6">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="scopes[]"
                            value="<?= esc($scope) ?>" id="<?= esc($boxId) ?>" data-scope-checkbox
                            <?= in_array($scope, $selected, true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="<?= esc($boxId) ?>">
                            <strong><?= esc($meta['label']) ?></strong>
                            <span class="badge bg-body-tertiary border ms-1"><?= esc($meta['method']) ?></span>
                            <code class="d-block small text-muted"><?= esc($meta['path']) ?></code>
                            <span class="d-block small text-muted"><?= esc($meta['description']) ?></span>
                        </label>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>