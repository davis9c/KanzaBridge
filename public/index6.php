<?php
$host = "localhost";
$user = "root";
$pass = "11";
$db   = "sik";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// =============================
// RESET TABEL USER
// =============================
$conn->query("TRUNCATE TABLE user");

// =============================
// PREPARE INSERT USER
// =============================
$sqlInsert = "
    INSERT IGNORE INTO user (id_user, password)
    VALUES (
        AES_ENCRYPT(?, 'nur'),
        AES_ENCRYPT('123', 'windi')
    )
";

$stmt = $conn->prepare($sqlInsert);

// =============================
// PENAMPUNG DATA TIDAK VALID
// =============================
$petugasTidakAda = [];
$dokterTidakAda  = [];
$pegawaiTanpaRole = [];



// =============================
// FUNGSI INSERT DATA
// =============================
function insertData($conn, $stmt, $query, $field)
{
    $result = $conn->query($query);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $value = $row[$field];
            $stmt->bind_param("s", $value);
            $stmt->execute();
        }
    }
}

// =============================
// MIGRASI DATA VALID
// =============================

// pegawai (master)
insertData(
    $conn,
    $stmt,
    "SELECT nik FROM pegawai",
    "nik"
);

// petugas valid
insertData(
    $conn,
    $stmt,
    "SELECT p.nip
     FROM petugas p
     INNER JOIN pegawai g ON p.nip = g.nik",
    "nip"
);

// dokter valid
insertData(
    $conn,
    $stmt,
    "SELECT d.kd_dokter
     FROM dokter d
     INNER JOIN pegawai g ON d.kd_dokter = g.nik",
    "kd_dokter"
);

// =============================
// AMBIL DATA TIDAK ADA DI PEGAWAI
// =============================

// petugas tidak punya pegawai
$qPetugasInvalid = $conn->query("
    SELECT p.nip, p.nama
    FROM petugas p
    LEFT JOIN pegawai g ON p.nip = g.nik
    WHERE g.nik IS NULL
");

while ($row = $qPetugasInvalid->fetch_assoc()) {
    $petugasTidakAda[] = [
        'nip'  => $row['nip'],
        'nama' => $row['nama']
    ];
}


// dokter tidak punya pegawai
$qDokterInvalid = $conn->query("
    SELECT d.kd_dokter, d.nm_dokter
    FROM dokter d
    LEFT JOIN pegawai g ON d.kd_dokter = g.nik
    WHERE g.nik IS NULL
");

while ($row = $qDokterInvalid->fetch_assoc()) {
    $dokterTidakAda[] = [
        'kd_dokter' => $row['kd_dokter'],
        'nama'      => $row['nm_dokter']
    ];
}

// pegawai tidak ada di petugas dan dokter
$qPegawaiTanpaRole = $conn->query("
    SELECT g.nik, g.nama
    FROM pegawai g
    LEFT JOIN petugas p ON g.nik = p.nip
    LEFT JOIN dokter d ON g.nik = d.kd_dokter
    WHERE p.nip IS NULL
      AND d.kd_dokter IS NULL
");

while ($row = $qPegawaiTanpaRole->fetch_assoc()) {
    $pegawaiTanpaRole[] = [
        'nik'  => $row['nik'],
        'nama' => $row['nama']
    ];
}


// =============================
$stmt->close();
$conn->close();

// =============================
// TAMPILKAN LAPORAN
// =============================
echo "=== MIGRASI SELESAI ===\n\n";

echo "Petugas tanpa pegawai (" . count($petugasTidakAda) . "):\n";
$i = 1;
foreach ($petugasTidakAda as $p) {
    echo "{$i} - NIP  : {$p['nip']}\t| {$p['nama']}\n";
    $i++;
    //echo "  Nama : {$p['nama']}\n";
}
$i = 1;
echo "\nDokter tanpa pegawai (" . count($dokterTidakAda) . "):\n";
foreach ($dokterTidakAda as $d) {
    echo "{$i} - Kode Dokter : {$d['kd_dokter']}\t| {$d['nama']}\n";
    $i++;
    //echo "  Nama : {$d['nama']}\n";
}
$i = 1;
echo "\nPegawai tanpa petugas & dokter (" . count($pegawaiTanpaRole) . "):\n";
foreach ($pegawaiTanpaRole as $g) {
    echo "{$i} - NIK  : {$g['nik']}\t | {$g['nama']}\n";
    $i++;
}
