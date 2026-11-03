# Monitoring Integration Roadmap

Plan to connect the Monitoring page (currently mock `data.ts`) to real devices: Raritan EMX, NTI ENVIROMUX, CCTV (RTSP). Devices exist and are reachable from the server network.

## Checklist (user-supplied)
- IP + user/pass for Raritan EMX, NTI ENVIROMUX, and RTSP URL per camera
- go2rtc runtime on server (Docker or binary) for RTSP → WebRTC/MSE/snapshot
- Cron `* * * * * php artisan schedule:run` (scheduler currently has NO tasks; first real use is this feature)

## Key facts
- PHP has `curl`/`openssl`, **no `snmp` ext** → use HTTP JSON APIs only:
  - Raritan EMX2: JSON-RPC over HTTP(S) (`help.raritan.com/json-rpc`)
  - NTI ENVIROMUX: session cookie + `GET /json/get/appESens.json` (ext), `appISens.json` (int), `appDiginp.json`; sensor type IDs 1=temp 2=hum 9=water 10=smoke 12=motion 14=door; status 1/2/3 = normal/warning/critical
- Reverb + Echo already running; queue = database. Broadcast sensor/alarm events on **private channel `monitoring`** (authorize `view monitoring`), `useEcho` in page
- Page must **fall back to mock `data.ts`** when devices/table empty — never render blank

## Phases
1. **Fase 1 — Fondasi + Raritan PoC**: tables `monitoring_devices`, `sensors`, `sensor_readings`, `alarms` + models/factories; `config/monitoring.php` ← `.env` (`RARITAN_*`, `NTI_*`, `GO2RTC_URL`); `App\Services\Monitoring\RaritanJsonRpcClient` (unit test `Http::fake`); `Schedule::job(PollDevicesJob)->everyThirtySeconds()` in `routes/console.php` → fetch → upsert readings → `ThresholdEvaluator` → alarms → broadcast; artisan `monitoring:test {device}` and `monitoring:sync` (discover sensors); `MonitoringController` props from DB w/ mock fallback
2. **Fase 2 — NTI + realtime**: `NtiEnviroMuxClient` (cookie session, type/status mapping); `SensorUpdated`/`AlarmRaised` events via Reverb; frontend `useEcho`; alarm acknowledge route
3. **Fase 3 — CCTV**: go2rtc service + `go2rtc.yaml` (12 RTSP streams); `cameras` table + `Go2rtcClient` (status via API, snapshot `api/frame.jpeg?src=`); frontend snapshot refresh 5–10s, click → live WebRTC (`video-rtc.js`); camera-offline alarm from producer status
4. **Fase 4 — History + polish**: 24h charts from `sensor_readings` (10-min aggregates), prune readings > 90 days, polling fallback `router.reload({ only })`, cron + supervisor ops notes

## Conventions
- Every phase: Pest tests (`Http::fake`, factories), `php artisan test --compact`, `vendor/bin/pint` on touched files
- `wayfinder:generate` only when new routes affect forms/links
- Activate skills: `laravel-best-practices`, `pest-testing`, `echo-development` + `echo-react-development` (Fase 2)
