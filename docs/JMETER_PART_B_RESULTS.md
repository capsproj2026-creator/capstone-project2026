# ISCVMS JMeter Part B results

Recorded from Apache JMeter 5.6.3 Summary Report on 18 October 2026.  
Target: `https://www.iscvms.com`  
Plan file: `ISCVMS-Reliability.jmx`  
PC: AMD Ryzen 5 7520U, 7.2 GB RAM

Numbers below are the Summary Report **TOTAL** row unless a row says otherwise.

| No. | Scenario | Threads | Ramp-up (s) | Loops / duration | Samples | Avg (ms) | Min / Max (ms) | Throughput | Error % |
|---|---|---:|---:|---|---:|---:|---|---|---:|
| 1 | Login page, normal load (`GET /login`) | 10 | 10 | 5 | 50 | 175 | 72 / 861 | 5.2/sec | 0.00 |
| 2 | Login page, moderate load (`GET /login`) | 30 | 30 | 3 | 90 | 148 | 71 / 781 | 3.1/sec | 0.00 |
| 3 | Registration page (`GET /register`) | 10 | 10 | 3 | 30 | 348 | 92 / 1571 | 3.1/sec | 0.00 |
| 4 | Admin login then `GET /admin` | 1 | 1 | 1 | 3 | 562 on `GET /admin` | 562 / 562 | 1.8/sec | 0.00 |
| 5 | RFID scan (`POST /api/rfid/scan`) | 5 | 10 | 6 | 30 | 186 | 65 / 1634 | 3.6/sec | 100.00 |
| 6 | RFID heartbeat (`POST /api/rfid/heartbeat`) | 10 | 10 | 6 | 60 | 123 | 47 / 393 | 6.1/sec | 0.00 |
| 7 | Mixed admin portal browsing | 5 | 10 | 3 | 115 | 171 | 124 / 470 | 9.7/sec | 0.00 |
| 8 | Soak, 10 minutes | 10 | 30 | 600 seconds | 23189 | 251 | 55 / 2793 | 38.6/sec | 0.00 |

## Notes from View Results Tree

- Row 4 and row 7 returned HTTP success, but the saved response body is the public **Sign In** page, not a confirmed admin dashboard.
- Row 5 body: `Invalid RFID API token`. The API answered and did not crash. This run used a token the server rejected.
- Row 6 body: `{"ok":true,"gate_id":"GATE-IN-1",...}`.
- Row 8 body for visitor pre-registration showed the real form (First Name, Purpose of Visit).
- No saved run for a 50-user stress test, a bad-password loop, a malformed RFID body, a recovery rerun, or an after-test records check.
