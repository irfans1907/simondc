---
paths:
  - .env
  - '**'
---

# General

## Dev via Docker Compose (Laravel Sail), bukan Apache/artisan serve host
Seluruh stack jalan di Docker Compose: `laravel.test` (app :80), `vite` (:5173), `reverb` (:8081 -> 8080), `queue`, `phpmyadmin` (:8080), `mysql` (:3306). Cukup jalankan `./vendor/bin/sail up -d` sekali; serve, Vite, Reverb, dan queue otomatis hidup sebagai service. Jangan pakai `composer run dev` atau `php artisan serve` di host. Akses app di http://localhost dan phpMyAdmin di http://localhost:8080. Hentikan dengan `./vendor/bin/sail down` (JANGAN `-v` kecuali memang ingin menghapus data MySQL).

## Env Docker + Reverb
`.env` menyimpan `WWWUSER=1000`/`WWWGROUP=1000` (menghilangkan warning compose). Server-side Reverb memakai `REVERB_HOST=reverb` + `REVERB_PORT=8080`; browser memakai `VITE_REVERB_HOST=localhost` + `VITE_REVERB_PORT=8081` (host port Reverb, karena 8080 dipakai phpMyAdmin). `SESSION_DRIVER=file` dan `CACHE_STORE=file` agar dev tidak bergantung pada tabel session/cache di DB.

## Perintah harian lewat Sail
`./vendor/bin/sail artisan migrate`, `./vendor/bin/sail artisan wayfinder:generate --with-form`, `./vendor/bin/sail npm run build`, `./vendor/bin/sail composer install`, `./vendor/bin/sail logs -f`. Service `queue` wajib hidup: tanpa itu event broadcast `files.changed` tidak diproses dan realtime refresh tidak jalan.

## Wayfinder generate must use --with-form
Always run `php artisan wayfinder:generate --with-form` to include .form() method helpers. Without this flag, calls like `RouteController.store.form()` or `FileController.destroy.form(id)` will fail at runtime with "is not a function".
