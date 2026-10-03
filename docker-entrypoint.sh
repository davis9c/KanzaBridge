#!/usr/bin/env sh
#
# Entrypoint container KanzaBridge.
#
# Dipanggil sebagai PID 1 oleh Docker. Urutannya:
#   1. siapkan direktori writable/ dan hak aksesnya
#   2. tunggu database siap (opsional, tidak memblokir)
#   3. jalankan migrasi (opsional, hanya bila RUN_MIGRATIONS=true)
#   4. serahkan kendali ke Apache
#
# Variabel environment:
#   WAIT_FOR_DB      true|false  (default: true)
#   DB_WAIT_TIMEOUT  detik        (default: 60)
#   RUN_MIGRATIONS   true|false  (default: true)
#
# Script ini POSIX sh (bukan bash) supaya aman di Debian bookworm.

set -e

APP_DIR="${APP_DIR:-/var/www/html}"
WRITABLE_DIR="$APP_DIR/writable"
RUN_AS="www-data:www-data"

WAIT_FOR_DB="${WAIT_FOR_DB:-true}"
DB_WAIT_TIMEOUT="${DB_WAIT_TIMEOUT:-60}"
RUN_MIGRATIONS="${RUN_MIGRATIONS:-true}"

log()  { printf '[entrypoint] %s\n' "$*"; }
warn() { printf '[entrypoint] PERINGATAN: %s\n' "$*" >&2; }

# ---------------------------------------------------------------------------
# 1. Direktori writable
#
# CI4 butuh cache/logs/session/debugbar bisa ditulis oleh worker Apache
# (www-data), sedangkan migrasi dijalankan sebagai root. Karena itu chown
# diulang setelah migrasi.
# ---------------------------------------------------------------------------
fix_writable_perms() {
	mkdir -p \
		"$WRITABLE_DIR/cache" \
		"$WRITABLE_DIR/logs" \
		"$WRITABLE_DIR/session" \
		"$WRITABLE_DIR/uploads" \
		"$WRITABLE_DIR/debugbar" 2>/dev/null || true

	chown -R "$RUN_AS" "$WRITABLE_DIR" 2>/dev/null \
		|| warn "Tidak bisa chown $WRITABLE_DIR (jalankan container sebagai root?)."
	chmod -R ug+rwx "$WRITABLE_DIR" 2>/dev/null || true
}

log "Menyiapkan $WRITABLE_DIR ..."
fix_writable_perms

# .env di-mount dari host (read-only). Tanpa file ini CI4 jatuh ke nilai
# default Config/*.php: baseURL dan credential database jadi kosong.
if [ ! -f "$APP_DIR/.env" ]; then
	warn "Berkas $APP_DIR/.env tidak ditemukan."
	warn "Pastikan volume './.env:/var/www/html/.env:ro' ada di docker-compose.yml."
fi

# ---------------------------------------------------------------------------
# 2. Tunggu database
#
# Host/pengguna/password dibaca memakai nama env yang PERSIS sama dengan yang
# dipakai CI4 (Config\Database + .env). Konsekuensinya, nilai override di
# docker-compose.yml ikut terpakai di sini.
#
# Keluar codes:
#   0  koneksi berhasil
#   1  host menolak koneksi
#   2  credential belum diisi, tidak ada yang perlu ditunggu
# ---------------------------------------------------------------------------
db_reachable() {
	php -r '
		$group = $argv[1] ?? "default";
		$get = static function (string $key, string $fallback = "") use ($group): string {
			$value = getenv("database." . $group . "." . $key);
			return ($value === false || $value === "") ? $fallback : $value;
		};

		mysqli_report(MYSQLI_REPORT_OFF);

		$host = $get("hostname", "localhost");
		$user = $get("username");
		$pass = $get("password");
		$name = $get("database");
		$port = (int) $get("port", "3306");

		if ($name === "") {
			fwrite(STDERR, "database." . $group . ".database kosong\n");
			exit(2);
		}

		$conn = @new mysqli($host, $user, $pass, $name, $port);
		exit($conn->connect_errno === 0 ? 0 : 1);
	' -- "$1"
}

DB_READY=false

if [ "$WAIT_FOR_DB" = "true" ]; then
	log "Menunggu database (timeout ${DB_WAIT_TIMEOUT}s)..."
	deadline=$(( $(date +%s) + DB_WAIT_TIMEOUT ))

	while : ; do
		if db_reachable default; then
			DB_READY=true
			break
		else
			rc=$?
			if [ "$rc" -eq 2 ]; then
				warn "database.default.database belum diisi, pengecekan dilewati."
				break
			fi
		fi

		if [ "$(date +%s)" -ge "$deadline" ]; then
			break
		fi

		sleep 2
	done

	if [ "$DB_READY" = "true" ]; then
		log "Database 'default' siap."
	else
		warn "Database 'default' belum terjangkau setelah ${DB_WAIT_TIMEOUT}s."
	fi

	# Database kedua (sik_beta / grup khanza) hanya dicek, tidak di-block:
	# sebagian besar endpoint tetap jalan walau SIMRS sedang mati.
	if db_reachable khanza; then
		log "Database 'khanza' siap."
	else
		rc=$?
		if [ "$rc" -eq 2 ]; then
			log "Grup database 'khanza' tidak dikonfigurasi, dilewati."
		else
			warn "Database 'khanza' belum terjangkau dari dalam container."
			warn "Endpoint yang butuh data SIMRS akan gagal sampai host DB diperbaiki."
		fi
	fi
fi

# ---------------------------------------------------------------------------
# 3. Migrasi
#
# `php spark migrate` bersifat aditif sehingga aman dijalankan ulang.
# Kegagalan tidak mematikan container: Apache tetap dinyalakan supaya
# masalahnya kelihatan lewat halaman error, bukan lewat container yang
# restart-loop.
# ---------------------------------------------------------------------------
if [ "$RUN_MIGRATIONS" = "true" ]; then
	if [ "$DB_READY" = "true" ]; then
		log "Menjalankan migrasi database..."
		if php spark migrate --all; then
			log "Migrasi selesai."
		else
			warn "Migrasi gagal, lihat pesan error di atas."
			warn "Container tetap dijalankan agar mudah didiagnosis."
		fi

		# Migrasi berjalan sebagai root, file writable/ jadi milik root.
		fix_writable_perms
	else
		warn "RUN_MIGRATIONS=true tapi database belum terjangkau, migrasi dilewati."
		warn "Jalankan manual: docker compose exec app php spark migrate"
	fi
else
	log "RUN_MIGRATIONS=false, migrasi dilewati."
fi

# ---------------------------------------------------------------------------
# 4. Apache
# ---------------------------------------------------------------------------
log "DocumentRoot: $APP_DIR/public"
log "Menjalankan Apache..."
exec apache2-foreground