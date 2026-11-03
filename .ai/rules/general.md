---
paths:
  - .env
  - '**'
---

# General

## Dev via Apache simondc.test, bukan artisan serve
Dev sekarang pakai Apache Laragon (http://simondc.test), BUKAN php artisan serve. APP_URL=http://simondc.test. Jalankan secara terpisah: `npm run dev` (Vite, port 5173) + `php artisan reverb:start` (port 8080) + `php artisan queue:work` (dibutuhkan karena event broadcast files.changed diproses via queue database). Jangan gunakan `composer run dev` (itu memakai artisan serve + bisa dobel).

## Perintah yang harus jalan saat buka project (dev)
Jalankan 3 proses terpisah: 1) `npm run dev` (Vite :5173), 2) `php artisan reverb:start` (Reverb :8080), 3) `php artisan queue:work --tries=1 --timeout=0` (proses job broadcast FilesChanged). Apache Laragon (http://simondc.test) serve app; JANGAN pakai `composer run dev` (memakai artisan serve). Tanpa queue:work, realtime refresh tidak jalan dan harus refresh manual.

## Wayfinder generate must use --with-form
Always run `php artisan wayfinder:generate --with-form` to include .form() method helpers. Without this flag, calls like `RouteController.store.form()` or `FileController.destroy.form(id)` will fail at runtime with "is not a function".
