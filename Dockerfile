# syntax=docker/dockerfile:1

# ===========================================================================
# Stage 1 — Dependency
#
# Composer dijalankan di image terpisah supaya cache hit-nya tinggi dan
# layer composer tidak ikut bobot image runtime.
# ===========================================================================
FROM composer:2.7 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./

# Image composer belum punya ext-intl, sedangkan aplikasi membutuhkannya
# untuk collation. ext-intl baru dipasang di stage 2, jadi di sini diabaikan saja.
#
# `--optimize-autoloader` TIDAK bisa membuat classmap App\, karena folder app/
# belum ada di stage ini. Composer tetap menulis aturan PSR-4-nya, jadi
# autoloader tetap berfungsi di runtime.
RUN composer install \
	--no-dev \
	--no-interaction \
	--prefer-dist \
	--optimize-autoloader \
	--ignore-platform-req=ext-intl

# ===========================================================================
# Stage 2 — Runtime (Apache + mod_php)
#
# Versi PHP 8.3 memenuhi syarat composer.lock: codeigniter4/framework v4.7.2
# demanding php ^8.2 (composer.json sendiri masih menulis ^8.1, lebih longgar).
# Jangan diturunkan ke 8.1 atau composer install akan gagal.
# ===========================================================================
FROM php:8.3-apache

# mysqli   -> koneksi MySQL untuk grup database `default` (khanzabridge)
#             dan `khanza` (sik_beta)
# mbstring -> helper dan manipulasi string bawaan CodeIgniter
# intl     -> collation & format multibita untuk data non-ASCII
RUN apt-get update \
	&& apt-get install -y --no-install-recommends libicu-dev libonig-dev \
	&& docker-php-ext-install -j"$(nproc)" intl mbstring mysqli \
	&& a2enmod rewrite \
	&& rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# ---------------------------------------------------------------------------
# Layout CodeIgniter 4:
#   public/  <- DocumentRoot, satu-satunya folder yang bisa diakses web
#   app/     <- controller, model, config  (di luar DocumentRoot)
#   vendor/  <- dependency                (di luar DocumentRoot)
#
# Sisa folder dibuat oleh docker-entrypoint.sh, bukan di sini, supaya hak
# aksesnya bisa diperbaiki ulang setiap container start.
# ---------------------------------------------------------------------------
COPY public/ ./public/
COPY app/ ./app/
COPY spark .
COPY preload.php .
COPY --from=vendor /app/vendor ./vendor

# DocumentRoot + aturan penolakan akses ke folder di luar docroot.
COPY apache-vhost.conf /etc/apache2/sites-available/000-default.conf

# Entrypoint: siapkan writable/, tunggu database, jalankan migrasi.
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh spark

# Struktur awal writable/ supaya `php spark ...` bisa jalan sebelum entrypoint
# sempat berjalan. Ownership final diurus entrypoint (butuh root).
RUN mkdir -p writable/cache writable/logs writable/session writable/uploads writable/debugbar \
	&& chown -R www-data:www-data writable

EXPOSE 80

# Container dianggap sehat saat Apache benar-benar menerima koneksi di port 80.
# `docker compose ps` lalu menampilkan status healthy, bukan sekadar running.
HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 \
	CMD php -r 'exit(@fsockopen("127.0.0.1", 80, $e, $s, 3) ? 0 : 1);'

# Path absolut supaya tidak bergantung pada resolusi PATH.
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]