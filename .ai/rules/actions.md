---
paths:
  - 'resources/js/actions/**'
---

# Actions

## Wayfinder generate wajib --with-form
Jalankan `php artisan wayfinder:generate --with-form` (bukan tanpa flag). Tanpa --with-form, file di resources/js/actions tidak berisi method `.form` sehingga Inertia <Form {...Controller.action.form()}> gagal typecheck di semua halaman.
