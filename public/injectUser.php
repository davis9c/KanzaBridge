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
// RESET USER
// =============================
$conn->query("TRUNCATE TABLE user");

// =============================
// PREPARE INSERT
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
// HITUNG TOTAL DATA
// =============================
$total = 0;

function countData($conn, $query)
{
    $res = $conn->query($query);
    return $res ? (int)$res->fetch_row()[0] : 0;
}

$total += countData($conn, "SELECT COUNT(*) FROM pegawai");
$total += countData($conn, "
    SELECT COUNT(*)
    FROM petugas p
    INNER JOIN pegawai g ON p.nip = g.nik
");
$total += countData($conn, "
    SELECT COUNT(*)
    FROM dokter d
    INNER JOIN pegawai g ON d.kd_dokter = g.nik
");

// =============================
// LOADING BAR
// =============================
$progress = 0;
$barWidth = 40;

function showProgress($done, $total, $width = 40)
{
    if ($total === 0) return;

    $percent = floor(($done / $total) * 100);
    $filled  = floor(($done / $total) * $width);

    $bar = str_repeat("█", $filled) . str_repeat("░", $width - $filled);

    echo "\r[$bar] {$percent}% ($done/$total)";
    flush();
}

// =============================
// INJECT FUNCTION + PROGRESS
// =============================
function injectUser($conn, $stmt, $query, $field, &$progress, $total)
{
    $res = $conn->query($query);
    while ($res && $row = $res->fetch_assoc()) {
        $stmt->bind_param("s", $row[$field]);
        $stmt->execute();

        $progress++;
        showProgress($progress, $total);
    }
}

// =============================
// PROSES INJECT
// =============================
echo "Injecting user data...\n";

injectUser(
    $conn,
    $stmt,
    "SELECT nik FROM pegawai",
    "nik",
    $progress,
    $total
);

injectUser(
    $conn,
    $stmt,
    "SELECT p.nip
     FROM petugas p
     INNER JOIN pegawai g ON p.nip = g.nik",
    "nip",
    $progress,
    $total
);

injectUser(
    $conn,
    $stmt,
    "SELECT d.kd_dokter
     FROM dokter d
     INNER JOIN pegawai g ON d.kd_dokter = g.nik",
    "kd_dokter",
    $progress,
    $total
);

// =============================
$stmt->close();
$conn->close();

echo "\n✔ Inject user selesai\n";
