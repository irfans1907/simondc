---
paths:
  - 'app/Events/**'
---

# Events

## Broadcast events perlu queue worker (ShouldBroadcast ≠ ShouldBroadcastNow)
Events yang hanya implements ShouldBroadcast (FilesChanged/RolesChanged/UsersChanged) dikirim via job BroadcastEvent ke queue database. Realtime hanya jalan jika `php artisan queue:work` BERJALAN di samping `php artisan reverb:start`. Jika hanya reverb yang jalan, client harus refresh manual karena event menganggur di tabel jobs. Untuk broadcast sinkron tanpa queue pakai ShouldBroadcastNow.
