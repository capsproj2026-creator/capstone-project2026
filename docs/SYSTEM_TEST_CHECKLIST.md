# ISCVMS Full System Test Checklist

Use this to double-check the whole system on **https://www.iscvms.com** before the defense or turnover.
Tick each box only after you see the expected result. Write anything that fails in the **Issue Log** at the bottom.

| Tester | Date | Device / Browser | Result |
|---|---|---|---|
| | | | ☐ Pass ☐ Fail |

---

## 0. Before you start

- [ ] One test account for each role is ready: **Admin**, **Guard**, **Student**, **Staff** (and email inboxes you can open)
- [ ] One extra test account that you can safely register, approve, decline, cite, and suspend
- [ ] At least one RFID card that is **assigned** and one that is **not registered**
- [ ] A test plate number linked to the test Student/Staff account
- [ ] A phone available for the mobile layout checks
- [ ] Afterwards: remove test citations, endorsements, overnight requests, and stalled reports you created

---

## 1. Public pages and security

- [ ] Home page loads at `https://www.iscvms.com/` (and `https://iscvms.com` redirects to `www`)
- [ ] Privacy, Terms, Campus Policy, and Accessibility pages open from the footer
- [ ] Every page uses HTTPS (padlock shown, no mixed-content warnings)
- [ ] Opening `/admin`, `/guard`, or `/user` while signed out redirects to the login page
- [ ] After logging out, the browser Back button does **not** show the previous signed-in page
- [ ] Five or more wrong passwords in a row get throttled (a "too many attempts" message)

## 2. Accounts and registration

**Register**
- [ ] Register page opens; the form validates required fields
- [ ] An email address that is already used shows "already registered" while typing
- [ ] Campus ID scan fills in the name and ID number
- [ ] Driver's license scan fills in the license details
- [ ] OR/CR scan fills in the plate number
- [ ] Submitting creates a **Pending** registration and shows a success message

**Email verification**
- [ ] Verification email arrives; the link verifies the account
- [ ] "Resend verification email" works and is throttled if clicked repeatedly

**Login**
- [ ] Login works for Admin, Guard, Student, and Staff, and each lands on its own dashboard
- [ ] Google sign-in works for `@my.cspc.edu.ph` accounts and is refused for other domains
- [ ] A pending or declined account cannot reach the portal and sees the correct message
- [ ] Logout works from every portal

**Password and profile**
- [ ] Forgot password sends a reset email; the reset link sets a new password and old one stops working
- [ ] A declined user can open "Fix registration," resubmit, and becomes pending again
- [ ] Profile page: name, photo, and password changes save correctly

## 3. Admin portal

**Dashboard**
- [ ] Cards (total users, active, suspended, visitors, entries today) show believable numbers
- [ ] Charts load (user distribution, violations by location, entries over time)

**Registrations**
- [ ] Filter cards (All / Pending / Approved / Declined) and search work
- [ ] View an applicant's uploaded documents (ID, license, OR/CR)
- [ ] **Approve** a test registration: status becomes Approved and the user is notified
- [ ] **Decline** with remarks: the user sees the remarks and can resubmit

**Users, plates, and guards**
- [ ] User Management lists users; search and the user detail page work
- [ ] Registered Plates lists plates; "Rebuild" finishes without errors
- [ ] Create Guard makes a guard account that can log in

**RFID Assignment**
- [ ] Tapping an unregistered card on the desk reader fills in the UID automatically
- [ ] Assigning a UID to a user grants gate access; a duplicate UID is rejected

**Visitors**
- [ ] Registered Visitors shows visitors currently inside, and it updates live
- [ ] Visitor History shows past visits with entry and exit times

**Parking**
- [ ] Parking overview shows each area with occupied and available counts
- [ ] Zone Access: changing allowed roles and visibility saves
- [ ] Layout: add and remove a test area and slot; an AI-monitored area **cannot** be deleted
- [ ] Slot status changes (available, occupied, reserved) save and show on the guard and user pages

**Violations and offense ladder**
- [ ] Violations page lists citations; search, status, type, and risk filters work
- [ ] Evidence photos open from a citation
- [ ] The "Details" popup shows strikes and the offense status
- [ ] Each person shows the correct ladder status (1st Offense warning, 2nd/3rd awaiting review, suspended, revoked)
- [ ] **Offense Endorsements** page: the Awaiting GSU, Awaiting VPAF, and Decided tabs work (full test in section 9)

**Overnight parking and stalled vehicles**
- [ ] **Overnight Parking**: the overnight check list and the employee request queue load (full test in section 9)
- [ ] **Stalled Vehicles** page loads and lets admin log a report (full test in section 9)

**Access logs, cameras, reports**
- [ ] Access Logs list entries and exits; filters work; new scans appear without refreshing
- [ ] Live Cameras shows the camera feed (or a clear "offline" message)
- [ ] Reports: date and type filters work
- [ ] Reports export to CSV, PDF, and Excel, and the files open with correct data

**System Health and Settings**
- [ ] System Health shows the database, AI service, RFID gate, and storage status
- [ ] Settings → General: campus info, contact email, and timezone save (timezone is Asia/Manila)
- [ ] Settings → Preferences: toggles save ("Endorse 2nd and 3rd Offenses to the GSU," "Send Violation Notifications," "Visitor Time Limits")
- [ ] Settings → Admins: create an admin; delete a staff/admin account
- [ ] Settings → Parking Rules, Stalled Vehicle rules, Campus Notices: add, edit, toggle, delete
- [ ] Settings → Violation Types: the four official types are listed; custom types cannot be added

## 4. Guard portal

**Dashboard and gate**
- [ ] Dashboard shows today's entries, exits, visitors inside, and recent alerts
- [ ] Live Gate Monitor shows each card scan as it happens (granted or denied, with the reason)
- [ ] "Open gate" button opens the boom gate (or queues the open command)
- [ ] Manual plate **entry** and **exit** work when RFID is unavailable
- [ ] Manual entry for a vehicle that is already inside is refused
- [ ] An unknown plate is refused

**Monitoring and lookup**
- [ ] User Monitor lists who is inside campus
- [ ] Plate Lookup finds the owner by full or partial plate

**Visitors**
- [ ] Register Visitor: the form saves, and a temporary RFID can be captured from the desk reader
- [ ] Returning delivery check-in works
- [ ] Visitors (inside): edit details, assign or return an RFID, and mark as exited
- [ ] Visitor History shows the completed visit

**Violations**
- [ ] Log a violation: plate, 1–4 violation types, description, and at least one photo are required
- [ ] A registered plate links to the owner and notifies them
- [ ] An unregistered plate is saved as "Unregistered Vehicle"
- [ ] A 2nd or 3rd offense shows "endorsed to the GSU" in the success message

**Parking and AI**
- [ ] Parking page shows live occupancy
- [ ] AI Parking Monitor shows detections; "correct plate" and "test scan" work; plate and vehicle crops load
- [ ] Live Cameras feed works

**Other pages**
- [ ] Notifications list loads; mark read and mark all read work
- [ ] Access Logs load
- [ ] **Stalled Vehicles** and **Overnight Check** pages load (full test in section 9)

## 5. Student and Staff portal

- [ ] Dashboard shows the user's vehicle, gate status, and recent activity
- [ ] Policy page shows the campus parking policy
- [ ] Notifications: open, mark read, and mark all read
- [ ] My Violations: citations, evidence photos, strike count, and the offense status box (e.g., "1st Offense — warning ticket")
- [ ] Entry/Exit History lists the user's own gate events only
- [ ] Parking: Acad 1 and Duran Hall photos show the painted stall numbers; availability updates
- [ ] Parking areas shown match the user's role (student vs staff zones)
- [ ] **Staff only:** "Overnight Parking" appears in the menu
- [ ] **Student:** no Overnight Parking menu item, and opening `/user/overnight-parking` gives "403 Forbidden"

## 6. Visitor and delivery (public)

- [ ] Visitor pre-registration form submits and shows the success page
- [ ] The pre-registration QR code opens the form
- [ ] A Google Form submission creates a pre-registered visitor (webhook)
- [ ] Delivery registration form submits; a returning delivery can check in
- [ ] A pre-registered visitor appears for the guard when they arrive

## 7. RFID gate hardware (ESP32)

- [ ] Assigned card, **Entry**: gate opens and the log shows "Access Granted"
- [ ] Same card, **Exit**: gate opens and the log shows the exit
- [ ] Assigned card tapped for Entry again while already inside: denied ("already inside")
- [ ] Unregistered card: denied ("card not registered") and it shows on the RFID Assignment page
- [ ] Locked account: denied
- [ ] Account with a **suspended or revoked** parking permit: **Entry denied** with the reason, **Exit allowed**
- [ ] Visitor temporary card works for entry and exit; denied after it is returned
- [ ] Emergency card opens the boom without recording an entry
- [ ] Heartbeat: an "open gate" pressed on the web reaches the ESP32 within a few seconds
- [ ] If the ESP32 loses Wi-Fi it reconnects, and System Health shows it offline and then online again

## 8. AI parking camera

- [ ] Camera feed is live; the stream recovers after a network drop
- [ ] Vehicles entering or leaving a zone update the occupancy counts
- [ ] Plate OCR reads test plates correctly (note the accuracy)
- [ ] Wrong parking (e.g., student vehicle in a staff zone) creates an automatic citation and notifies the owner and guards
- [ ] The same parked vehicle is **not** cited twice (duplicate prevention)
- [ ] An unregistered vehicle shows up as an alert for guards
- [ ] After the AI service restarts, detections and occupancy resume on their own

## 9. CSPC parking policy workflows (Ref. No. 2026-021)

**Offense ladder** (use the test account; check "Endorse 2nd and 3rd Offenses to the GSU" is on)
- [ ] **1st citation:** the owner's notice says "warning ticket"; no endorsement is created; the gate still opens
- [ ] **2nd citation:** an endorsement appears under Admin → Offense Endorsements → **Awaiting GSU**; the gate still opens
- [ ] Admin clicks **Approve 6-month suspension**: the owner gets an in-app notice and an email; status shows "Parking permit suspended until …"
- [ ] Suspended owner: RFID Entry and manual plate Entry are **denied**, Exit is **allowed**, and portal login **still works**
- [ ] **3rd citation:** a 3rd-offense endorsement appears under Awaiting GSU
- [ ] Admin clicks **Verify and endorse to VPAF**: it moves to **Awaiting VPAF**; nothing changes for the owner yet
- [ ] Admin records **VPAF approved: revoke privileges**: status becomes "Parking privileges revoked"; the revocation email arrives
- [ ] "Do not approve" / "Do not endorse" closes the case without a sanction and notifies the owner
- [ ] Deciding the same endorsement twice is refused ("already decided")
- [ ] Removing the citations withdraws the open endorsements and lifts the sanction

**Overnight parking (10:00 p.m. – 5:00 a.m.)**
- [ ] Staff submits a request for tonight (plate filled in automatically, reason required)
- [ ] An overlapping request and a past date are refused; requests are limited to 14 nights
- [ ] Admin → Overnight Parking: approve one request and deny another; the employee is notified each time
- [ ] Staff can cancel a pending or approved request
- [ ] A vehicle that entered and has not exited appears in the **Overnight Check** as "No approval," and as "Approved" after approval
- [ ] The guard's Overnight Check shows the list but **not** the approval queue

**Stalled vehicles**
- [ ] Guard logs a stalled vehicle (plate, location, "Stalled" or "Lost or broken key"); the owner's name fills in for a registered plate
- [ ] An unregistered plate requires the owner's name to be typed
- [ ] The registered owner gets a "Stalled Vehicle Reported" notice with the grace deadline
- [ ] The report shows **Grace period** with a countdown, then **Tow required** after 12 hours, and **Tow overdue** after 15 hours
- [ ] After the grace period, opening the page sends the owner a "Tow Required" notice (once only)
- [ ] A second open report for the same plate is refused
- [ ] **Mark removed** closes the report and moves it to "Recently removed"

## 10. Notifications and email

- [ ] Email verification
- [ ] Password reset
- [ ] Registration approved and declined
- [ ] Violation recorded (in-app; email if enabled)
- [ ] 2nd offense suspension approved (in-app + email "Parking Permit Suspended")
- [ ] 3rd offense revocation approved (in-app + email "Parking Privileges Revoked")
- [ ] Overnight request approved or denied
- [ ] Stalled vehicle reported and tow required
- [ ] Turning off "Send Violation Notifications" stops the in-app violation notices
- [ ] The unread notification badge count updates when items are read

## 11. Access control between roles

- [ ] Guard opening any `/admin/...` page is blocked
- [ ] Student or Staff opening any `/admin/...` or `/guard/...` page is blocked
- [ ] Admin opening `/user/...` pages is blocked or redirected
- [ ] A user cannot open another user's violation evidence by changing the URL
- [ ] A limited admin without a permission (e.g., "clear penalties" or "manage parking") cannot see or open that page

## 12. Layout, time, and reliability

- [ ] Every page above works on a phone: the menu opens, nothing overflows sideways, buttons can be tapped
- [ ] Light and dark theme (if enabled) are readable on all pages
- [ ] All times show Philippine time (Asia/Manila), including emails and exports
- [ ] Live pages (gate monitor, access logs, visitors, parking) update without refreshing
- [ ] If the database is briefly unreachable, the site shows a friendly error page instead of a crash
- [ ] Pages load within a few seconds on campus Wi-Fi

## 13. Server and operations

- [ ] HTTPS certificate is valid and not close to expiring
- [ ] Database backup runs, and a restore has been tried at least once
- [ ] The application error log has no new errors after the test run
- [ ] Hardware API tokens (RFID, AI, Google Form webhook) are set; a request without a token is refused

---

## Issue Log

| # | Section / Item | What happened | Expected | Screenshot / Notes | Fixed? |
|---|---|---|---|---|---|
| 1 | | | | | ☐ |
| 2 | | | | | ☐ |
| 3 | | | | | ☐ |
| 4 | | | | | ☐ |
| 5 | | | | | ☐ |
