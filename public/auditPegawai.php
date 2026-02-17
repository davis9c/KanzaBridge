<?php
$host = "localhost";
$user = "root";
$pass = "11";
$db   = "sik";
$totalStep = 3;
$currentStep = 0;

echo "Audit data sedang berjalan...\n";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

$petugasTidakAda  = [];
$dokterTidakAda   = [];
$pegawaiTanpaRole = [];
function showProgress($current, $total, $label = '', $width = 30)
{
    $percent = intval(($current / $total) * 100);
    $filled  = intval(($current / $total) * $width);

    $bar = str_repeat("█", $filled) . str_repeat("░", $width - $filled);

    echo "\r[$bar] {$percent}% {$label}";
    flush();
}
// =============================
// PETUGAS TANPA PEGAWAI
// =============================
$currentStep++;
showProgress($currentStep - 1, $totalStep, "Cek petugas...");
$q = $conn->query("
    SELECT p.nip, p.nama
    FROM petugas p
    LEFT JOIN pegawai g ON p.nip = g.nik
    WHERE g.nik IS NULL
");

while ($r = $q->fetch_assoc()) {
    $petugasTidakAda[] = $r;
}
showProgress($currentStep, $totalStep, "Cek petugas ✓\n");



// =============================
// DOKTER TANPA PEGAWAI
// =============================
$currentStep++;
showProgress($currentStep - 1, $totalStep, "Cek dokter...");
$q = $conn->query("
    SELECT d.kd_dokter, d.nm_dokter
    FROM dokter d
    LEFT JOIN pegawai g ON d.kd_dokter = g.nik
    WHERE g.nik IS NULL
");

while ($r = $q->fetch_assoc()) {
    $dokterTidakAda[] = [
        'kd_dokter' => $r['kd_dokter'],
        'nama'      => $r['nm_dokter']
    ];
}
showProgress($currentStep, $totalStep, "Cek dokter ✓\n");


// =============================
// PEGAWAI TANPA ROLE
// =============================
$currentStep++;
showProgress($currentStep - 1, $totalStep, "Cek pegawai tanpa role...");
$q = $conn->query("
    SELECT g.nik, g.nama
    FROM pegawai g
    LEFT JOIN petugas p ON g.nik = p.nip
    LEFT JOIN dokter d ON g.nik = d.kd_dokter
    WHERE p.nip IS NULL
      AND d.kd_dokter IS NULL
");

while ($r = $q->fetch_assoc()) {
    $pegawaiTanpaRole[] = $r;
}
showProgress($currentStep, $totalStep, "Cek pegawai ✓\n\n");


// =============================
// OUTPUT LAPORAN
// =============================
echo "=== AUDIT DATA USER ===\n\n";

echo "Petugas tanpa pegawai (" . count($petugasTidakAda) . ")\n";
$i = 1;
foreach ($petugasTidakAda as $p) {
    echo "$i. {$p['nip']} | {$p['nama']}\n";
    $i++;
}

echo "\nDokter tanpa pegawai (" . count($dokterTidakAda) . ")\n";
$i = 1;
foreach ($dokterTidakAda as $d) {
    echo "$i. {$d['kd_dokter']} | {$d['nama']}\n";
    $i++;
}

echo "\nPegawai tanpa petugas & dokter (" . count($pegawaiTanpaRole) . ")\n";
$i = 1;
foreach ($pegawaiTanpaRole as $g) {
    echo "$i. {$g['nik']} | {$g['nama']}\n";
    $i++;
}

$conn->close();
