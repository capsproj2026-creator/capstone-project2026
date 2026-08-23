# Your visitor Google Form — build checklist

**I cannot edit Google Forms from code** — complete these steps in your browser while logged into the Google account that owns the form.

| Link | URL |
|------|-----|
| **Edit form** | https://docs.google.com/forms/d/e/1FAIpQLSd7Ofy6HyLvpEpiAOIRbsCfQ6x4Jw8TuEMg2SikT2jocVCxlA/edit |
| **Public link (QR)** | https://docs.google.com/forms/d/e/1FAIpQLSd7Ofy6HyLvpEpiAOIRbsCfQ6x4Jw8TuEMg2SikT2jocVCxlA/viewform |

Laravel `.env` already points the guard QR to the **public link** above.

---

## Part A — Build the form (≈10 min)

Your form is currently empty. In the **edit** link above:

### 1. Title and description

- **Title:** `Visitor Pre-Registration`
- **Description:** `Fill this before going to the guard booth. You will receive a reference code by email if you provide one.`

### 2. Delete the placeholder question

- Remove **Untitled Question** (the default multiple-choice item).

### 3. Add these questions in order

Use **exact titles** (copy-paste from the Title column):

| Title | Type | Required | Extra settings |
|-------|------|----------|----------------|
| First Name | Short answer | Yes | — |
| Middle Name | Short answer | No | — |
| Last Name | Short answer | Yes | — |
| Contact Number | Short answer | Yes | — |
| Email | Short answer | No | Turn on **Response validation → Email** if available |
| Purpose of Visit | Short answer | Yes | — |
| Office / Person to Visit | Short answer | Yes | — |
| Expected Exit Date | Date | Yes | — |
| Expected Exit Time | Time | Yes | — |
| Plate Number | Short answer | Yes | — |
| Vehicle Type | Dropdown | Yes | Options exactly: `Motorcycles` and `Automobiles` |
| Vehicle Color | Short answer | Yes | — |

### 4. Confirmation message

Settings (gear) → **Presentation** → Confirmation message:

> Thank you! If you entered an email, check your inbox for your reference code. Otherwise, go to the guard booth and give your name and plate number.

### 5. Link to Sheets

Responses tab → **Link to Sheets** → Create new spreadsheet (needed for Apps Script).

---

## Part B — Apps Script (≈10 min)

1. Open the linked spreadsheet → **Extensions → Apps Script**.
2. Replace all code with [`visitor-pre-register.gs`](visitor-pre-register.gs).
3. **Project Settings → Script properties** — add:

   | Property | Value |
   |----------|--------|
   | `WEBHOOK_URL` | See below |
   | `WEBHOOK_TOKEN` | Copy from Laravel `.env` → `VISITOR_PRE_REGISTER_WEBHOOK_TOKEN` |

   **`WEBHOOK_URL` when testing on your PC (ngrok):**

   ```
   https://YOUR-NGROK-SUBDOMAIN.ngrok-free.app/api/visitor/pre-register/google
   ```

   **`WEBHOOK_URL` when deployed:**

   ```
   https://YOUR-PUBLIC-DOMAIN/api/visitor/pre-register/google
   ```

   Google **cannot** call `http://127.0.0.1:8000`. Use ngrok or a deployed server.

4. Run function **`installFormSubmitTrigger`** once → authorize when prompted.
5. Submit a test response on the form → check **Guard → Active Visitors**.

---

## Part C — Laravel (already done)

These are set in your `.env`:

- `VISITOR_PRE_REGISTER_GOOGLE_FORM_URL` → your form view link
- `VISITOR_PRE_REGISTER_WEBHOOK_TOKEN` → shared secret for Apps Script

After any `.env` change:

```powershell
php artisan config:clear
```

---

## Part D — Test webhook locally (optional, no Google)

With Laravel running on port 8000:

```powershell
curl -X POST "http://127.0.0.1:8000/api/visitor/pre-register/google" `
  -H "Content-Type: application/json" `
  -H "X-VISITOR-PRE-REGISTER-TOKEN: <paste token from .env>" `
  -d "{\"first_name\":\"Test\",\"last_name\":\"Visitor\",\"contact_number\":\"09171234567\",\"email\":\"you@example.com\",\"purpose\":\"Meeting\",\"office_to_visit\":\"Registrar\",\"expected_exit_at\":\"2026-12-31T17:00:00\",\"plate_number\":\"TST1234\",\"vehicle_name\":\"Automobiles\",\"vehicle_color\":\"White\"}"
```

You should get JSON with `confirmation_code` and `success_url`.

---

## Part E — Print QR

1. Log in as **guard** → **Register Visitor**.
2. QR at the top should show your Google Form URL.
3. **Download QR (SVG)** and print for the entrance.

---

## ngrok quick start (local + Google webhook)

```powershell
# Terminal 1
php artisan serve --host=0.0.0.0 --port=8000

# Terminal 2 (install ngrok once from https://ngrok.com)
ngrok http 8000
```

Copy the **https** Forwarding URL → set `APP_URL` in `.env` → use same host in Apps Script `WEBHOOK_URL` → `php artisan config:clear`.
