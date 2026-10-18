# ISCVMS reliability test — step-by-step (Apache JMeter)

Use this to fill **Questionnaire-JMeter-Reliabilityfinal.pdf** against the live site:

**https://www.iscvms.com**

The PDF still says `http://127.0.0.1:8000`. On Part A, write the public URL instead. Localhost only tests one PC. `www.iscvms.com` is the campus system evaluators will judge.

Do this **outside class hours** (evening or weekend). These tests send many requests at once. A busy afternoon can slow the real site for guards and students.

---

## Before you start (safety)

1. Tell the team you are load-testing **www.iscvms.com** and pick a 1–2 hour window.
2. Use **test accounts only** (Admin, Guard, and one Student or Staff). Do not use a real student’s password in JMeter.
3. **Do not tap a real gate card in the RFID scan test.** A registered UID can write an entry/exit and can open the gate. Use a fake UID such as `TEST0001`.
4. **Do not flood registration with real people.** The site allows only **3 registration posts per minute per IP**, and **5 login posts per minute per email**. Extra attempts are rejected on purpose. That rejection is a Pass for fault tolerance, not a crash.
5. RFID limits on the server: **scan 30/minute**, **heartbeat 120/minute**. Stay under those or the error % will be rate-limit responses (HTTP 429), not a broken gate.
6. Keep the RFID token in JMeter only. Do not paste `RFID_API_TOKEN` into the questionnaire, GitHub, or a screenshot.

Pass rule used below (matches the questionnaire):

| Check | Pass |
|---|---|
| Normal load (about 10 users) | Success rate **≥ 98%** (Error % ≤ 2) and pages still open in a browser |
| Moderate load (25–50 users) | Most requests succeed, site does not crash |
| RFID heartbeat / denied scan | API answers (even `Access Denied` / card not registered). Process stays up |
| After the peak | A 10-user rerun is close to the first 10-user times |

---

## Part 1 — Install Java 17, then JMeter (once per laptop)

JMeter will not start without Java. This PC does not have Java yet.

### 1.1 Install Java 17

1. Press the Windows key, type **PowerShell**, and open **Windows PowerShell** (the normal one, not the one already inside Cursor).
2. Paste this and press Enter:

   ```powershell
   winget install --id EclipseAdoptium.Temurin.17.JDK -e
   ```

3. If Windows asks “Do you want to allow this app to make changes?”, click **Yes**.
4. Wait until the last lines say the install succeeded. A progress bar and the words `Successfully installed` are the sign it finished.
5. **Close that PowerShell window completely.** Java is not visible in a window that was already open.
6. Open a **new** PowerShell window and run:

   ```powershell
   java -version
   ```

7. You should see three lines. The first must contain **17**, for example:

   ```text
   openjdk version "17.0.16" 2025-07-15
   OpenJDK Runtime Environment Temurin-17.0.16+8 (build 17.0.16+8)
   OpenJDK 64-Bit Server VM Temurin-17.0.16+8 (build 17.0.16+8, mixed mode, sharing)
   ```

   The exact `17.0.x` number can be newer. That is still Java 17.

8. If PowerShell says `java is not recognized`:
   - Close every PowerShell window, open a new one, and run `java -version` again.
   - If it still fails, restart the laptop once, then run `java -version` again.
   - If it still fails, open [https://adoptium.net/temurin/releases/?version=17](https://adoptium.net/temurin/releases/?version=17), download **Windows x64 JDK .msi**, run the installer, leave the default options (including “set JAVA_HOME” and “add to PATH”), then open a new PowerShell and run `java -version`.

### 1.2 Install Apache JMeter

1. In the browser, open [https://jmeter.apache.org/download_jmeter.cgi](https://jmeter.apache.org/download_jmeter.cgi).
2. Under **Binaries**, download the **zip** of Apache JMeter **5.6.3** (`apache-jmeter-5.6.3.zip`). Do not download the source zip.
3. When the download finishes, open File Explorer and go to **Downloads**.
4. Right-click `apache-jmeter-5.6.3.zip` → **Extract All**.
5. Set the folder to `C:\JMeter` and click **Extract**.
6. Confirm this file exists (double-click it only after the next step):

   `C:\JMeter\apache-jmeter-5.6.3\bin\jmeter.bat`

   If the zip extracted an extra folder, the file may be `C:\JMeter\apache-jmeter-5.6.3\apache-jmeter-5.6.3\bin\jmeter.bat`. Either path is fine. Use the one that actually contains `jmeter.bat`.

7. Double-click `jmeter.bat`. A black window stays open behind JMeter. **Leave that black window open.** Closing it closes JMeter.
8. The JMeter window title starts with **Apache JMeter**. The left side is a tree. The only item at first is **Test Plan**.
9. Click **Help → About Apache JMeter**. Write the version on Part A of the questionnaire (for example `5.6.3`). Click **Close**.

### 1.3 Write the PC facts on Part A now

1. Press the Windows key, type **About**, and open **About your PC**.
2. Copy **Processor** and **Installed RAM** onto Part A, for example `Intel Core i5, 8 GB RAM`.
3. Fill the rest of Part A:

| Field on the PDF | Write this |
|---|---|
| Tester / Respondent Name | Your name, or leave blank |
| Role being tested | Tick **Researcher** |
| Date of Testing | Today’s date |
| Target URL | `https://www.iscvms.com` (cross out `http://127.0.0.1:8000` on the form) |
| Apache JMeter Version | The version from Help → About |
| Machine Specs | Processor and RAM from About your PC |
| Network Condition | Tick **Other** and write `Internet to www.iscvms.com`. Tick **LAN** instead if you are on CSPC Wi-Fi, and add `campus Wi-Fi`. |
| Test Plan Name / File | `ISCVMS-Reliability.jmx` |
| Modules Targeted | Tick all five: **Login**, **Registration**, **Portals**, **RFID API**, **Reports** |

---

## How the JMeter window works

Learn these six actions before you build the tests. You will use them on every scenario.

1. **Left tree.** Everything you add appears under **Test Plan**. Click an item to edit it on the right.
2. **Right-click an item → Add.** That is how you create a Thread Group, an HTTP Request, a Cookie Manager, or a report.
3. **Rename.** Click the item, then change the **Name** box at the top of the right panel.
4. **Disable.** Right-click a Thread Group → **Disable**. The name turns grey. A grey group does not run. Right-click → **Enable** turns it back on. **Only one Thread Group may be enabled when you press Start.**
5. **Start and Stop.** The green triangle in the toolbar starts the test. The red square stops it. While a test runs, the top-right corner shows a number that climbs, then stops. Wait until that number stops changing before you read the report.
6. **Clear.** The broom icons on the toolbar erase old results. Click the broom **before every new scenario**. If you skip this, Scenario 2’s numbers get mixed with Scenario 1 and the questionnaire will be wrong.

**Summary Report columns, and where they go on Part B:**

| What you see in JMeter | What you write on the PDF |
|---|---|
| Label | Scenario / Sampler name |
| # Samples | You do not copy this, but it should match users × loops (and × pages, if the group has several requests) |
| Average | Avg Response (ms) |
| Min and Max | Min / Max (ms), written like `420 / 2100` |
| Error % | Error % |
| Throughput | Throughput. It is requests per second. Write the number and `/sec`. |

Click **Summary Report** in the left tree to see this table. If the table is empty, you either did not press Start, or you cleared the results too early.

---

## Part 2 — Create the test plan

In JMeter:

1. **File → Save Test Plan as**  
   `C:\Users\Adams\Documents\ISCVMS-Reliability.jmx`  
   That path is the **Test Plan Name / File** on Part A.

2. Right-click **Test Plan → Add → Threads (Users) → Thread Group**. Rename it `01 Login page normal`. You will duplicate this group for each scenario. Leave only **one** group enabled at a time (right-click → **Disable** on the others).

3. Right-click **Test Plan → Add → Config Element → HTTP Request Defaults**.
   - Protocol: `https`
   - Server name: `www.iscvms.com`
   - Port: leave empty

4. Right-click **Test Plan → Add → Config Element → HTTP Cookie Manager**. Leave defaults. Laravel login needs this.

5. Right-click **Test Plan → Add → Config Element → HTTP Header Manager**. Add one row:
   - Name: `User-Agent`
   - Value: `ISCVMS-JMeter-Reliability/1.0`

6. Right-click **Test Plan → Add → Listener → Summary Report**.  
   Also add **Aggregate Report** and **View Results Tree**.  
   After you confirm the first run works, **disable View Results Tree** (it uses a lot of RAM on long runs).

7. Right-click **Test Plan → Add → Listener → Simple Data Writer**.
   - Filename: `C:\Users\Adams\Documents\ISCVMS-jmeter-results.csv`
   - Configure → save the default fields (elapsed, response code, success, label, thread name, bytes, latency).

---

## Part 3 — Scenarios (one Thread Group each)

Build them in this order. Run **only the enabled group**. After each run, copy numbers from **Summary Report** into Part B, then **Clear All** on the listeners (broom icon) before the next scenario.

Summary Report columns you need:

| Questionnaire column | JMeter column |
|---|---|
| Avg Response (ms) | Average |
| Min / Max (ms) | Min / Max |
| Throughput | Throughput |
| Error % | Error % |

### How to duplicate a group

Right-click a Thread Group → **Duplicate**. Rename it. Change only the numbers and samplers listed below. **Disable** the previous group.

---

### Scenario 1 — Login page, normal load (Part B row 1, Part C #1, Part D #1)

**Thread Group `01 Login page normal`**

- Number of threads (users): **10**
- Ramp-up: **10** seconds
- Loop count: **5**

**Sampler:** right-click the group → **Add → Sampler → HTTP Request**

- Name: `GET /login`
- Method: **GET**
- Path: `/login`

Run (**green Start**). Wait until the top-right numbers stop.

**Record Part B row 1.**  
**Part C #1 Pass** if Error % ≤ 2 and you can still open https://www.iscvms.com/login in Chrome.  
**Part D #1:** write the average ms. Pass if the login page still feels usable (a practical campus threshold on this host is **under 3000 ms** average). If it is slower, mark Fail and write the ms — do not hide it.

Also open https://www.iscvms.com/ once by hand and note that the home page loads. Optional extra sampler in the same group: `GET /` path `/`.

---

### Scenario 2 — Login page, moderate load (Part B row 2, Part C #2, Part D #6)

Duplicate scenario 1. Rename `02 Login page moderate`.

- Threads: **30** (middle of the form’s 25–50 range; safer on the live site than 50)
- Ramp-up: **30** seconds
- Loops: **3**
- Same sampler: `GET /login`

**Part C #2 Pass** if Error % stays low (about ≤ 5%) and the site still opens in a browser afterward. A crash or a stuck 502/503 is Fail.

**Stress limit (Part D #6), only if the moderate run was healthy:** duplicate again as `02b Login stress`.

- Threads: **50**
- Ramp-up: **60**
- Loops: **2**

Write the thread count where Error % jumps or average time roughly doubles. That number is the failure point. Pass for Part D #6 means you **documented** that point and the site still answers afterward (it may be slow). Fail only if it stays down.

---

### Scenario 3 — Real login, including bad passwords (Part C #3 and #6)

This checks that repeated logins stay consistent and that bad passwords are rejected without taking the site down.

**Important:** login is limited to **5 posts per minute for the same email from your IP**. A Pass here includes HTTP **429** or the message “Too many login attempts” after the fifth try. That is the lockout working.

**Thread Group `03 Login submit`**

- Threads: **1**
- Ramp-up: **1**
- Loop count: **1**

Add these samplers **in this order** under the group:

**A. GET login (fetch CSRF token)**

- Name: `GET /login csrf`
- Method: GET
- Path: `/login`

Right-click this sampler → **Add → Post Processors → Regular Expression Extractor**

- Name of created variable: `csrf`
- Regular expression: `name="_token" value="([^"]+)"`
- Template: `$1$`
- Match No.: `1`
- Default value: `CSRF_NOT_FOUND`

**B. POST valid login**

- Name: `POST /login valid`
- Method: POST
- Path: `/login`
- Body data is **not** used. Click **Parameters** and add:

| Name | Value |
|---|---|
| `_token` | `${csrf}` |
| `email` | your Admin test email |
| `password` | your Admin test password |

**C. GET admin dashboard** (runs after the cookie from B)

- Name: `GET /admin`
- Method: GET
- Path: `/admin`

Right-click the Thread Group → **Add → Logic Controller → Loop Controller**. Set loops to **8**. Move a **copy** of sampler A and a new POST under this loop:

**D. POST invalid login** (inside the loop)

- Name: `POST /login invalid`
- Method: POST
- Path: `/login`
- Parameters: `_token` = `${csrf}`, `email` = the same admin email, `password` = `wrong-password-jmeter`

Each loop iteration must GET `/login` again first, because Laravel rotates the CSRF token. Order inside the loop: GET csrf, then POST invalid.

Run once.

**What to write**

- Part B: you can add a note row “Login submit (1 user)” with the average of `POST /login valid` and of `GET /admin`.
- **Part C #3 Pass** if `POST /login valid` and `GET /admin` succeed (HTTP 200 on the dashboard HTML, not the login page). Check View Results Tree → `GET /admin` response contains the admin dashboard, not the login form.
- **Part C #6 Pass** if invalid posts return the login page or “Too many login attempts”, and a **new** browser login with the real password still works after one minute.
- **Part D #2:** average ms of `GET /admin`. Pass if it is in the same range as the login page (usable for daily work). Write the ms either way.

Repeat `GET /admin` once with the **Guard** account (log out by using a fresh Cookie Manager: disable the Admin run, duplicate the group, change email/password, path `/guard`) and once for a Student/Staff account (path `/user`). Those three pages support **Part E item 8 (Portal Reliability)**.

---

### Scenario 4 — Registration page and a graceful reject (Part B row 3, Part C #4)

Do **not** create dozens of real accounts.

**Thread Group `04 Registration page`**

- Threads: **10**
- Ramp-up: **10**
- Loops: **3**
- Sampler: GET, path `/register`, name `GET /register`

Record Part B row 3 from this GET (label it “Registration page”). The questionnaire says “Registration submit”. Add a **second, separate** 1-user group `04b Registration reject`:

1. GET `/register` with the same CSRF extractor as scenario 3 (`csrf`).
2. POST `/register` with only:

| Name | Value |
|---|---|
| `_token` | `${csrf}` |
| `email` | `not-an-email` |

**Part C #4 Pass** if the POST is rejected with a validation error (HTTP 302 back to the form, or 422) and `/register` still opens afterward. That is “fail gracefully”.  
A successful full registration needs ID photos and creates a real pending user — skip that unless a panel member asks, and then do it **once** by hand in the browser, not with 10 threads.

---

### Scenario 5 — Portals and reports while logged in (Part B row 4 and row 7)

You already loaded `/admin` in scenario 3. For mixed browsing, reuse the valid-login samplers, then add GETs. Threads: **5**, ramp-up **10**, loops **3**. One admin login is enough; put the page GETs in a Loop Controller **after** the login samplers.

Admin pages (prefix `/admin`):

| Name | Path |
|---|---|
| Admin dashboard | `/admin` |
| Registrations | `/admin/registrations` |
| RFID assignment | `/admin/rfid` |
| Visitors | `/admin/visitors` |
| Parking | `/admin/parking` |
| Reports | `/admin/reports` |
| System health | `/admin/system-health` |

Guard (separate group, guard login, then):

| Name | Path |
|---|---|
| Guard dashboard | `/guard` |
| Access logs | `/guard/access-logs` |
| Parking | `/guard/parking` |

Student/Staff:

| Name | Path |
|---|---|
| User dashboard | `/user` |
| Entry and exit | `/user/entry-exit` |
| Violations | `/user/violations` |

**Part B row 4:** average of the dashboard samplers.  
**Part B row 7:** average and error % across this mixed run.  
**Part D #2 and #3:** write dashboard average ms and the throughput number from Summary Report.

---

### Scenario 6 — RFID scan simulation (Part B row 5, Part C #5, Part D #4)

Get the token from the server `.env` key `RFID_API_TOKEN`. In JMeter: **Test Plan → Add → Config Element → User Defined Variables**

- Name: `RFID_TOKEN`
- Value: paste the token here (local JMeter only)

**Thread Group `06 RFID scan`**

- Threads: **5**
- Ramp-up: **10**
- Loops: **6**  
  (5 × 6 = 30, which matches the scan limit of 30 per minute. Do not raise threads.)

**Sampler**

- Name: `POST /api/rfid/scan`
- Method: POST
- Path: `/api/rfid/scan`
- Body Data:

```json
{"uid":"TEST0001","gate_id":"GATE-IN-1","direction":"Entry"}
```

On this sampler → **Add → Config Element → HTTP Header Manager**:

| Name | Value |
|---|---|
| `Content-Type` | `application/json` |
| `Accept` | `application/json` |
| `X-RFID-TOKEN` | `${RFID_TOKEN}` |

`TEST0001` is not a campus card, so the API should answer **404** with “card not registered” (or similar). In JMeter that is an “error” because it is not HTTP 200.

Right-click the sampler → **Add → Assertions → Response Assertion** is optional. Easier: add **Add → Post Processors** is unnecessary. Instead add:

**Add → Assertions → JSR223 Assertion** is overkill. Use this:

Right-click sampler → **Add → Listener** is already global. To stop JMeter counting 404 as a failure:

- Right-click sampler → **Add → Assertions → Response Assertion**
- Field to test: **Response code**
- Pattern matching rules: **Equals**
- Click **Add** and type `404` **or** whatever code View Results Tree shows on the first try
- Check **Ignore Status** (this treats 404 as a completed response)

If the first trial shows **401**, the token is wrong. Fix the variable. Do not record that run.

**Part B row 5:** Error % should be near 0 **after** Ignore Status, because a denied unknown card is a successful API answer.  
**Part C #5 Pass** if every sample got a JSON body and the website still loads.  
**Part D #4:** write average ms. The questionnaire’s local target is **under 350 ms**. On public HTTPS, write the real average. Pass if it stays under **1000 ms** and the gate API is still answering. If it is over 1000 ms, mark Fail and write the number.

**Malformed request (Part C #7):** one extra sampler, 1 thread, 1 loop, same headers, body:

```json
{"uid":""}
```

Expect **422** (validation) or **401** if the token header is omitted on a second sampler with no `X-RFID-TOKEN`. Add Response Assertion for that code and **Ignore Status**. **Part C #7 Pass** if the next valid heartbeat (scenario 7) still returns 200.

---

### Scenario 7 — RFID heartbeat (Part B row 6, Part E item 7)

**Thread Group `07 RFID heartbeat`**

- Threads: **10**
- Ramp-up: **10**
- Loops: **6**  
  (about 60 requests; the limit is 120/minute)

**Sampler** `POST /api/rfid/heartbeat`

- Method: POST
- Path: `/api/rfid/heartbeat`
- Same three headers as the scan (`Content-Type`, `Accept`, `X-RFID-TOKEN`)
- Body:

```json
{"gate_id":"GATE-IN-1"}
```

Expect HTTP **200** and JSON with `"ok": true`. No Ignore Status needed if the token and `gate_id` are valid.

**Part B row 6** from this run. **Part C #5** can cite both scan and heartbeat.

---

### Scenario 8 — Soak (Part B row 8, Part C #9, Part E item 5)

Keep this shorter on the live site.

**Thread Group `08 Soak`**

- Threads: **10**
- Ramp-up: **30**
- Loop count: **Infinite** (check the box) — you will stop it yourself
- Duration is set on the Thread Group scheduler:
  - Check **Specify Thread lifetime**
  - Duration: **600** seconds (10 minutes)
  - Startup delay: **0**

Samplers (all GET, no login storm):

- `/`
- `/login`
- `/register`
- `/visitor/pre-register`

Run 10 minutes. Watch the PC in Task Manager (CPU and memory of `java.exe`) and, in another browser, refresh https://www.iscvms.com/login every couple of minutes.

**Part C #9 Pass** if Error % stays ≤ 2, memory does not climb until JMeter or the site dies, and the login page still opens at the end.  
Write duration **600 s** in Part B row 8.

---

### Scenario 9 — Recovery (Part C #8, Part E item 4)

Immediately after scenario 2 or 2b (the heavier login-page run):

1. Wait **60 seconds**.
2. Enable **only** `01 Login page normal` again (10 users, ramp-up 10, 5 loops).
3. Clear listeners. Run it.

**Part C #8 Pass** if this average is in the same ballpark as Part B row 1 (not still stuck at the stress-test time). Example: first run 800 ms, recovery run under 1500 ms. Write both numbers in Actual Result.

---

### Scenario 10 — Data integrity spot check (Part C #10, Part E item 6)

JMeter must not be creating records in the safe plan above (unknown RFID UID, invalid registration, no mass login success).

After all runs, sign in as Admin in a normal browser:

1. Open **Reports** (`/admin/reports`) and **Registrations**. Confirm there is no burst of new fake students.
2. Open an existing user and an existing access log. Names, UIDs, and plate numbers look the same as before the test.
3. Open **System health** (`/admin/system-health`). It should load.

**Part C #10 Pass** if those samples are intact. If you see a pile of `TEST0001` gate logs, note it in Remarks (unknown-card denials may be logged). That is expected for the scan test; real user rows must be unchanged.

---

## Part 4 — Fill the questionnaire

### Part A

| Field | What to write |
|---|---|
| Tester name | Your name (optional) |
| Role | Researcher |
| Date | The day you ran JMeter |
| Target URL | `https://www.iscvms.com` |
| JMeter version | From Help → About |
| Machine specs | CPU and RAM from Windows About |
| Network | Other — “Home/campus internet to public HTTPS” (or LAN if you are on CSPC Wi-Fi) |
| Test plan file | `ISCVMS-Reliability.jmx` |
| Modules | Tick **Login**, **Registration**, **Portals**, **RFID API**, **Reports** |

### Part B

One row per scenario in Part 3. Copy Average, Min, Max, Throughput, Error % from Summary Report **after that scenario only** (listeners cleared between runs).

Interpretation examples:

- Error % ≤ 2 and average stable → “Stable under this load.”
- Error % high only on invalid login after 5 tries → “Rate limit working; site stayed up.”
- Average much higher than scenario 1 → “Degraded; see stress row.”

### Part C

Mark Pass/Fail using the rules in each scenario. Put the JMeter numbers in **Actual Result** (example: `Error 0.4%, avg 842 ms, 10 users`).

### Part D

| No. | Actual Result example |
|---|---|
| 1 | Scenario 1 average ms |
| 2 | Scenario 3 or 5 dashboard average ms |
| 3 | Scenario 5 throughput (requests/sec) |
| 4 | Scenario 7 or 6 average ms |
| 5 | Scenario 2 error % |
| 6 | Thread count where time or errors jumped (scenario 2b) |

### Part E

Shade one number per row from the evidence, not from memory:

| Item | Give 5 or 4 when… | Give 2 or 1 when… |
|---|---|---|
| 1 Maturity | Scenario 1 and the recovery rerun match | The same 10-user test swings wildly |
| 2 Availability | Login and home stayed open in every run | 502/503 or the browser cannot connect |
| 3 Fault tolerance | Bad password, bad register, bad RFID JSON did not kill the site | One bad request took the site down |
| 4 Recoverability | Scenario 9 close to scenario 1 | Site still slow or down a minute later |
| 5 Stability | 10-minute soak stayed up | Timeouts or memory collapse during soak |
| 6 Data integrity | Admin spot check looked normal | User or log rows changed or duplicated |
| 7 Gate API | Heartbeat 200, unknown card handled | Heartbeat failed or the API stopped |
| 8 Portals | Admin, Guard, and User dashboards returned while logged in | A portal errored under 5 users |
| 9 Error handling | 401/404/422/429 were clear and the next request worked | Errors hung the app |
| 10 Overall | Most of Part C is Pass | Several availability or data-integrity fails |

Use **3** when a run was mixed (for example RFID was fine but login stress was slow). Use **N/A** only if you truly did not run that case.

### Part F (answer from your sheet, in your own words)

1. **Most stable:** usually heartbeat and `GET /login` at 10 users, if that is what your Error % shows. Name the scenario with the lowest error and the steadiest average.
2. **Where it degraded:** the thread count from scenario 2b, and what moved first (response time, then error %, or rate-limit messages).
3. **Risks before deployment:** anything you marked Fail. If login posts are easy to lock (5 per minute), say that is intentional protection, not a bug, unless valid users stayed locked for a long time.
4. **Limits that affected results:** your laptop CPU/RAM, Wi-Fi vs cable, testing the **public** site (HTTPS, DNS, hosting) rather than localhost, and the built-in rate limits (login 5/min, register 3/min, RFID scan 30/min, heartbeat 120/min).
5. **Recommendations:** run heavy tests off-peak; keep rate limits; watch `/admin/system-health`; do not point JMeter at a real gate UID; if averages are far above the campus threshold, profile the slowest sampler (often dashboard or reports) before adding hardware.

Sign Part F acknowledgment with your name and the test date.

---

## Part 5 — Order of operations (checklist)

Do the runs in this order so recovery and soak are meaningful.

- [ ] Java and JMeter installed; version written down
- [ ] Test plan saved; Cookie Manager, Header Manager, HTTP defaults, Summary + Aggregate reports added
- [ ] Scenario 1 — 10 users on `/login` (baseline)
- [ ] Scenario 4 — registration **page** only, then one invalid POST
- [ ] Scenario 3 — one valid login, dashboards, then invalid passwords
- [ ] Scenario 5 — mixed portal pages
- [ ] Scenario 6 — RFID scan with `TEST0001` (not a real card)
- [ ] Scenario 7 — RFID heartbeat
- [ ] Scenario 2 — 30 users on `/login`
- [ ] Scenario 2b — 50 users, only if scenario 2 was healthy
- [ ] Scenario 9 — wait 60 seconds, rerun scenario 1
- [ ] Scenario 8 — 10-minute soak
- [ ] Scenario 10 — Admin browser check of reports, registrations, one user, system health
- [ ] Part A–F filled from the Summary Report numbers
- [ ] Delete `RFID_TOKEN` from any file you will upload or commit. Keep `ISCVMS-Reliability.jmx` on your PC.

---

## If something looks wrong

| What you see | What it means |
|---|---|
| All login POSTs fail with `CSRF_NOT_FOUND` | The extractor did not see `_token`. Open View Results Tree on `GET /login` and confirm the HTML contains `name="_token"`. |
| `GET /admin` shows the login form | Cookie Manager missing, or the valid POST failed. Read the POST response. |
| RFID **401** | `X-RFID-TOKEN` does not match server `RFID_API_TOKEN`. |
| RFID or login **429** | You passed the rate limit. Lower threads/loops. For invalid login, 429 after several tries is a Pass. |
| High error % on public pages only from home Wi-Fi | Repeat once on campus or a cabled connection and note both in Part F. |
| Site will not open after a run | Stop JMeter. Wait two minutes. Try `/login` again. If it stays down, tell whoever hosts iscvms.com before you run another test. |
