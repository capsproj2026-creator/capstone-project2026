# Fill the JMeter questionnaire, part by part

Site: **https://www.iscvms.com**

Open JMeter from `C:\JMeter\apache-jmeter-5.6.3\bin\jmeter.bat`. Leave the black window open.

Before every run: only **one** Thread Group is enabled (right-click the others → **Disable**). Click the **broom** icon so old numbers are erased. Press the **green triangle**. Wait until the number at the top right stops. Click **Summary Report**. Screenshot that table. Then copy the numbers into the row below.

Summary Report → form:

| JMeter | Write on the form |
|---|---|
| Average | Avg Response (ms) |
| Min and Max | Min / Max (ms), like `420 / 2100` |
| Throughput | Throughput, and add `/sec` |
| Error % | Error % |

Run the Part B rows in this order so the heavy test does not spoil the others: **Row 1, Row 3, Row 4, Row 5, Row 6, Row 7, Row 2, Part C #8, Row 8, Part C #10.**

---

## Part A — fill this before any test

| Box | What to write |
|---|---|
| Tester / Respondent Name | Your name, or leave blank |
| Role being tested | Tick **Researcher** |
| Date of Testing | Today’s date |
| Target URL | Cross out `http://127.0.0.1:8000`. Write `https://www.iscvms.com` |
| Apache JMeter Version | **Help → About Apache JMeter** |
| Machine Specs | Windows **About your PC**: processor and installed RAM |
| Network Condition | Tick **Other** and write `Internet to www.iscvms.com`. On CSPC Wi-Fi, tick **LAN** and write `campus Wi-Fi` |
| Test Plan Name / File | `ISCVMS-Reliability.jmx` |
| Modules Targeted | Tick **Login**, **Registration**, **Portals**, **RFID API**, **Reports** |

One-time JMeter setup, if the plan is still empty:

1. **File → Save Test Plan as** → `C:\Users\Adams\Documents\ISCVMS-Reliability.jmx`
2. Right-click **Test Plan → Add → Config Element → HTTP Request Defaults**. Protocol `https`. Server `www.iscvms.com`. Port empty.
3. Right-click **Test Plan → Add → Config Element → HTTP Cookie Manager**. Do not change it.
4. Right-click **Test Plan → Add → Listener → Summary Report**.
5. Add **View Results Tree** the same way. Disable it after the login-submit test works.
6. Press **Ctrl+S** after each group you add.

---

## Part B

### Row 1 — Login (normal load)

Also fills **Part C #1** and **Part D #1**.

1. Right-click **Test Plan → Add → Threads (Users) → Thread Group**. Name: `01 Login normal`.
2. Threads `10`. Ramp-up `10`. Loop count `5`.
3. Right-click the group → **Add → Sampler → HTTP Request**. Name `GET /login`. Method **GET**. Path `/login`.
4. Run. Samples must be **50** (10 × 5).
5. In Chrome open `https://www.iscvms.com/login`. It must still load.
6. Screenshot the Summary Report as `B1-login-10.png`.

Write on row 1:

| Threads | Ramp-up | Loops | Avg | Min/Max | Throughput | Error % | Interpretation |
|---|---|---|---|---|---|---|---|
| 10 | 10 | 5 | from Average | Min / Max | Throughput/sec | Error % | `Stable` if Error % is 2 or less. Otherwise `Degraded` |

**Part C #1:** Pass if Error % is **2% or less** and the Chrome login page opened. Actual Result example: `Error 0.0%, avg 842 ms, 10 users`.

**Part D #1:** write that same average ms. Pass if it is under **3000 ms**. If it is slower, tick Fail and still write the number.

### Row 2 — Login (moderate/stress)

Do this **after rows 3–7**. Also fills **Part C #2**, **Part D #5**, and **Part D #6**.

1. Disable every other group. Duplicate `01 Login normal`. Name the copy `02 Login moderate`.
2. Threads `30`. Ramp-up `30`. Loop count `3`. Path stays `/login`.
3. Click the broom. Run. Samples must be **90**.
4. Open `https://www.iscvms.com/login` in Chrome again.
5. Screenshot `B2-login-30.png`.

Write on row 2: threads `30`, ramp-up `30`, loops `3`, then Average, Min/Max, Throughput, Error %. Interpretation: `Most requests succeeded, site still open` or `Site failed at 30 users`.

**Part C #2:** Pass if Error % is about **5% or less** and Chrome still opens the page. A 502, 503, or a page that will not load is Fail.

**Part D #5:** write this Error %. Pass if it stays low.

**Part D #6**, only if row 2 looked healthy:

1. Duplicate the group. Name it `02b Login stress`.
2. Threads `50`. Ramp-up `60`. Loops `2`.
3. Broom, run, screenshot `D6-login-50.png`.
4. Actual Result: `30 users avg ___ ms; 50 users avg ___ ms, error ___%`.
5. Pass if you wrote the point where it got worse and the site still answers. Fail only if the site stays down.

### Row 3 — Registration submit

Also fills **Part C #4**. Do not create real accounts.

**Page load**

1. Disable other groups. Duplicate row 1. Name it `03 Register page`.
2. Threads `10`, ramp-up `10`, loops `3`. Change the sampler to name `GET /register`, path `/register`.
3. Broom, run. Samples **30**. Screenshot `B3-register-page.png`.
4. Write row 3 from this Summary Report. Threads `10`, ramp-up `10`, loops `3`. Scenario name: `Registration page`.

**One bad submit**

1. New Thread Group `03b Register reject`. Threads `1`, ramp-up `1`, loops `1`.
2. HTTP Request `GET /register csrf`, GET, path `/register`.
3. Right-click that request → **Add → Post Processors → Regular Expression Extractor**.
   - Name of created variable: `csrf`
   - Regular expression: `name="_token" value="([^"]+)"`
   - Template: `$1$`
   - Match No.: `1`
   - Default value: `CSRF_NOT_FOUND`
4. HTTP Request `POST /register invalid`, POST, path `/register`. Open **Parameters** (not Body Data):

| Name | Value |
|---|---|
| `_token` | `${csrf}` |
| `email` | `not-an-email` |

5. Run. In View Results Tree, the response should be the form again or a validation error, not a new account.
6. Refresh `https://www.iscvms.com/register` in Chrome.

**Part C #4:** Pass if that post was rejected and `/register` still opens. Actual Result: `1 invalid POST rejected; register page still available`.

### Row 4 — Admin/User dashboard

Also fills **Part D #2** and **Part E #8**.

1. New Thread Group `04 Admin dashboard`. Threads `1`, ramp-up `1`, loops `1`.
2. `GET /login csrf` plus the same CSRF extractor as row 3.
3. `POST /login valid`, POST, path `/login`, Parameters:

| Name | Value |
|---|---|
| `_token` | `${csrf}` |
| `email` | admin test email |
| `password` | admin test password |

4. `GET /admin`, GET, path `/admin`.
5. Run. View Results Tree → `GET /admin` → Response data must be the admin dashboard, not the login form.
6. Screenshot the Summary Report row for `GET /admin` as `B4-admin-dashboard.png`.

Write row 4 from the **GET /admin** line only. Threads `1`, ramp-up `1`, loops `1`.

**Part D #2:** that average ms. Pass if the dashboard is usable. A practical line on this public site is under **3000 ms**. Write the real number either way.

Then two more 1-user runs, same steps, for the other portals:

- Guard email and password, last path `/guard`. Screenshot `B4-guard.png`.
- Student or staff email and password, last path `/user`. Screenshot `B4-user.png`.

**Part E #8** uses these three. All three dashboards must load.

### Row 5 — RFID API scan simulation

Also fills **Part C #5**, **Part C #7**, and **Part D #4**. Card number must be `TEST0001`. Do not use a real gate card.

1. Copy `RFID_API_TOKEN` from the server `.env`. Do not write it on the form.
2. Right-click **Test Plan → Add → Config Element → User Defined Variables**. Add name `RFID_TOKEN`, value = the token.
3. Thread Group `05 RFID scan`. Threads `5`, ramp-up `10`, loops `6`.
4. HTTP Request `POST /api/rfid/scan`, POST, path `/api/rfid/scan`. **Body Data**:

```json
{"uid":"TEST0001","gate_id":"GATE-IN-1","direction":"Entry"}
```

5. Right-click **this sampler** → **Add → Config Element → HTTP Header Manager**:

| Name | Value |
|---|---|
| Content-Type | `application/json` |
| Accept | `application/json` |
| X-RFID-TOKEN | `${RFID_TOKEN}` |

6. Run once. View Results Tree should show **404** and a “card not registered” message. If it shows **401**, the token is wrong. Fix it and do not record that run.
7. Right-click the sampler → **Add → Assertions → Response Assertion**. Field to test: **Response code**. Pattern: **Equals**. Add pattern `404`. Check **Ignore Status**.
8. Broom, run again. Screenshot `B5-rfid-scan.png`.

Write row 5: threads `5`, ramp-up `10`, loops `6`, then the Summary Report numbers. Error % should be near 0 after Ignore Status. Interpretation: `Unknown card denied, API stayed up`.

**Part C #5:** Pass if every sample returned JSON and the website still opens.

**Part D #4:** write the average ms. Pass on the public site if it is under **1000 ms** and the API still answers. The form’s local target of 350 ms can be mentioned in Remarks if you are above it.

**Part C #7** is a separate 1-user group `05b RFID bad json`. Same headers. Body Data: `{"uid":""}`. Assertion for code `422` with **Ignore Status**. Pass if the response is 422 and the next heartbeat (row 6) is still 200.

### Row 6 — RFID heartbeat simulation

1. Duplicate row 5. Name it `06 RFID heartbeat`.
2. Threads `10`, ramp-up `10`, loops `6`.
3. Sampler name `POST /api/rfid/heartbeat`, path `/api/rfid/heartbeat`.
4. Body Data: `{"gate_id":"GATE-IN-1"}`
5. Delete the 404 assertion. Keep the three headers.
6. Broom, run. Code must be **200** and the body must contain `"ok": true`.
7. Screenshot `B6-heartbeat.png`.

Write row 6: threads `10`, ramp-up `10`, loops `6`, plus Average, Min/Max, Throughput, Error %. Interpretation: `Heartbeat 200`.

Add this run to **Part C #5** Remarks: `Scan denied TEST0001; heartbeat 200`.

### Row 7 — Mixed portal browsing

Also fills **Part D #3**.

1. Duplicate the working admin login group. Name it `07 Mixed portals`.
2. Threads `5`, ramp-up `10`, loops `1`.
3. After the valid login sampler, add a **Loop Controller** set to `3`.
4. Inside the loop, add a GET request for each path:

| Name | Path |
|---|---|
| Admin dashboard | `/admin` |
| Registrations | `/admin/registrations` |
| RFID assignment | `/admin/rfid` |
| Visitors | `/admin/visitors` |
| Parking | `/admin/parking` |
| Reports | `/admin/reports` |
| System health | `/admin/system-health` |

5. Broom, run. One `Admin dashboard` response must be the admin page, not the login page.
6. Screenshot `B7-mixed.png`.

Write row 7 from the **TOTAL** line. Threads `5`, ramp-up `10`, loops `3`. Interpretation: `Portals stayed available` or describe the first page that failed.

**Part D #3:** write the TOTAL throughput, for example `4.2/sec`. Pass if Error % is low and the pages returned.

### Row 8 — Soak / sustained run

Do this **after row 2 and after Part C #8**. Also fills **Part C #9** and **Part E #5**.

1. Thread Group `08 Soak`. Threads `10`. Ramp-up `30`. Check **Infinite**.
2. Check **Specify Thread lifetime**. Duration `600`. Startup delay `0`.
3. Four GET samplers: `/`, `/login`, `/register`, `/visitor/pre-register`.
4. Start it and leave it for 10 minutes. Every two minutes, refresh `https://www.iscvms.com/login` in Chrome.
5. Screenshot the TOTAL row as `B8-soak.png`.

Write row 8: threads `10`, ramp-up `30`, loops `600 seconds`, then TOTAL Average, Min/Max, Throughput, Error %. Interpretation: `Stayed up for 10 minutes` or `Failed during soak`.

**Part C #9:** Pass if Error % is **2% or less**, the login page opened during the 10 minutes, and JMeter did not crash.

---

## Part C — mark these from the runs above

| No. | Use this run | Pass when | Actual Result to write |
|---|---|---|---|
| 1 | Part B row 1 | Error % ≤ 2 and login page opens | `Error __%, avg __ ms, 10 users` |
| 2 | Part B row 2 | Error % about ≤ 5 and site still opens | `30 users, error __%, avg __ ms` |
| 3 | Extra test below | Valid login works, and a second 10-user page test matches row 1 | `Valid login OK; repeated GET /login stayed consistent` |
| 4 | Part B row 3 | Bad registration is rejected, page still opens | `Invalid POST rejected` |
| 5 | Part B rows 5 and 6 | Unknown card handled, heartbeat 200 | `Scan 404 handled; heartbeat 200, avg __ ms` |
| 6 | Extra test below | Wrong password rejected, real password works after 1 minute | `Lockout shown; valid login worked after 60s` |
| 7 | Part B row 5 bad JSON | Empty UID returns 422, heartbeat still works | `422 on bad JSON; heartbeat still 200` |
| 8 | Extra test below | 10-user rerun is close to row 1 | `Baseline __ ms; after stress __ ms` |
| 9 | Part B row 8 | 10-minute soak stays up, Error % ≤ 2 | `600s soak, error __%` |
| 10 | Extra test below | Existing admin records look unchanged | `Reports, registrations, and one user unchanged` |

### Part C #3 — repeated login

This is the valid half of row 4, plus a note that row 1 and the Part C #8 rerun did not get worse over time. Pass if `POST /login valid` and `GET /admin` succeeded and the two 10-user averages are in the same range.

### Part C #6 — invalid credentials

Do this once, after row 4’s valid login works.

1. Thread Group `C6 bad passwords`. Threads `1`, ramp-up `1`, loops `1`.
2. Add a **Loop Controller** set to `8`.
3. Inside it: `GET /login csrf` with the CSRF extractor, then `POST /login invalid`.
4. POST parameters: `_token` = `${csrf}`, the admin email, password `wrong-password-jmeter`.
5. Run. The first tries show the login page. After about the fifth, the page says **Too many login attempts**. That is a Pass.
6. Wait 60 seconds. Sign in with the real admin password in Chrome. It must work.

### Part C #8 — recovery

Do this immediately after Part B row 2 (and the 50-user run, if you did it).

1. Wait 60 seconds.
2. Disable the 30-user and 50-user groups. Enable only `01 Login normal`.
3. Broom. Run. Screenshot `C8-recovery.png`.
4. Pass if this average is close to row 1. Example: row 1 was 800 ms and this run is under 1500 ms.

### Part C #10 — data integrity

After every JMeter run has stopped:

1. In Chrome, sign in as admin.
2. Open `/admin/reports`, `/admin/registrations`, one existing user, and `/admin/system-health`.
3. Pass if names, plates, and old records look the same as before the test. A `TEST0001` denial in a gate log is expected. Write that in Remarks. A burst of new fake students is Fail.

---

## Part D — copy these numbers

| No. | Actual Result | Pass |
|---|---|---|
| 1 Response time, login | Part B row 1 average ms | Under 3000 ms |
| 2 Response time, dashboard | Part B row 4 `GET /admin` average ms | Under 3000 ms and the dashboard loaded |
| 3 Throughput | Part B row 7 TOTAL throughput | Pages returned and Error % stayed low |
| 4 RFID latency | Part B row 6 average ms | Under 1000 ms on the public site, API still answering |
| 5 Error rate | Part B row 2 Error % | About 5% or less, no broken records in Part C #10 |
| 6 Stress limit | 30-user average and 50-user average | The slowdown point is written down and the site still answers |

---

## Part E — shade one number from the evidence

5 = strongly agree, 4 = agree, 3 = mixed, 2 = disagree, 1 = strongly disagree.

| No. | Shade 5 or 4 when | Shade 2 or 1 when |
|---|---|---|
| 1 Maturity | Row 1 and Part C #8 are close | The same 10-user test swings wildly |
| 2 Availability | Login and home opened in every run | Browser could not connect, or 502/503 |
| 3 Fault tolerance | Part C #4, #6, and #7 did not kill the site | One bad request took the site down |
| 4 Recoverability | Part C #8 is close to row 1 | Still slow or down a minute later |
| 5 Stability | Row 8 stayed up for 10 minutes | Timeouts or a crash during the soak |
| 6 Data integrity | Part C #10 looked normal | A real user or log row changed |
| 7 Gate API | Row 6 was 200 and row 5 handled the unknown card | Heartbeat failed or the API stopped |
| 8 Portal reliability | Admin, Guard, and User dashboards all loaded | A portal failed |
| 9 Error handling | 401, 404, 422, and 429 were clear and the next request worked | An error left the site hung |
| 10 Overall | Most of Part C is Pass | Several availability or data checks failed |

Use **3** when a row was mixed, for example RFID was fine but 50 users were slow. Use **N/A** only if that test was not run.

---

## Part F — answer from your own sheet

1. **Which scenario was most stable?** Name the Part B row with the lowest Error % and the steadiest average.
2. **Where did it start to fail, and what failed first?** Use Part D #6. Say the thread count, and whether response time, Error %, or the “too many attempts” message moved first.
3. **Risks to fix before deployment?** List every Fail. The 5-login lockout is intentional. Call it a risk only if a valid user stayed locked for a long time.
4. **What limits affected the numbers?** Your CPU and RAM from Part A, Wi-Fi or cable, testing the public site, and the site limits: login 5 posts per minute, registration 3 per minute, RFID scan 30 per minute, heartbeat 120 per minute.
5. **What should be improved?** Run heavy tests off-peak, keep those limits, watch `/admin/system-health`, never point JMeter at a real gate card, and speed up the slowest page from Part B (often the dashboard or Reports).

Sign the acknowledgment with your name and the test date.

Remove `RFID_TOKEN` from any file you send to someone else. Keep the screenshots and `ISCVMS-Reliability.jmx` on your PC.
