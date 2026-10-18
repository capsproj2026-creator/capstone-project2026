# ISCVMS JMeter results — updated 18 October 2026

Apache JMeter 5.6.3. Target: `https://www.iscvms.com`. Plan: `ISCVMS-Reliability.jmx`.  
PC: AMD Ryzen 5 7520U, 7.2 GB RAM. Figures are from the Summary Report unless noted.

## Part B

| No. | Scenario | Threads | Ramp-up (s) | Loops / duration | Samples | Avg (ms) | Min / Max (ms) | Throughput | Error % |
|---|---|---:|---:|---|---:|---:|---|---|---:|
| 1 | Login page, normal load (`GET /login`) | 10 | 10 | 5 | 50 | 175 | 72 / 861 | 5.2/sec | 0.00 |
| 2 | Login page, moderate load (`GET /login`) | 30 | 30 | 3 | 90 | 148 | 71 / 781 | 3.1/sec | 0.00 |
| 3 | Registration page (`GET /register`) | 10 | 10 | 3 | 30 | 348 | 92 / 1571 | 3.1/sec | 0.00 |
| 4 | Admin login then `GET /admin` | 1 | 1 | 1 | — | — | — | — | 0.00 on the tree |
| 5 | RFID scan (`POST /api/rfid/scan`) | 5 | 10 | 6 | — | — | — | — | JMeter shows red |
| 6 | RFID heartbeat (`POST /api/rfid/heartbeat`) | 10 | 10 | 6 | 60 | 104 | 45 / 1200 | 6.3/sec | 0.00 |
| 7 | Mixed admin portal browsing | 5 | 10 | 3 | — | — | — | — | green on the tree |
| 8 | Soak, 10 minutes | 10 | 30 | 600 seconds | 23189 | 251 | 55 / 2793 | 38.6/sec | 0.00 |

Rows 4, 5, and 7 were corrected in View Results Tree after the first Summary Report. The old millisecond totals for those three rows were from the Sign In page and the rejected-token scan, so they are not reused.

- Row 4 body now contains `<title>Admin Dashboard</title>`.
- Row 5 body is now `Card Not Registered` / `card_not_registered` for `GATE-IN-1`. The red rows are the HTTP 404 denial, not a crash.
- Row 7 body now contains `<title>Admin Dashboard</title>`.
- Row 6 above is the latest heartbeat, saved with the empty-card check. Body: `{"ok":true,"gate_id":"GATE-IN-1",...}`.

## Added tests

| Test | Samples | Avg (ms) | Min / Max (ms) | Throughput | Error % | What the body showed |
|---|---:|---:|---|---|---:|---|
| Wrong passwords (`C6`) | 16 (8 GET + 8 POST) | 344 | 130 / 664 | 8.1/min | 0.00 | Login page, then **Too many login attempts** |
| Empty RFID body (`C7` scan) | 1 | 336 | 336 / 336 | 3.0/sec | 100.00 | Rejected, as expected for `{"uid":""}` |
| Heartbeat immediately after that scan | 60 | 104 | 45 / 1200 | 6.3/sec | 0.00 | `ok: true` |
| 50-user login page (`02b`) | 100 | 197 | 64 / 656 | 1.7/sec | 0.00 | Sign In page stayed available |

The 30-user login average was 148 ms at 3.1/sec. At 50 users the average was 197 ms at 1.7/sec. Error stayed 0.00%. No crash. A failure point was not reached.

After the JMeter runs, the admin record check passed. Reports, Registrations, one existing user, and System health were reviewed. Existing records were unchanged.
