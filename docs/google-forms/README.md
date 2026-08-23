# Visitor pre-registration — Google Form

Use a **Google Form** for visitor data entry while **Smart Campus VMS** still stores official `Visitor` records, confirmation codes, and guard Active Visitors search.

## Architecture

1. QR code → Google Form URL (`VISITOR_PRE_REGISTER_GOOGLE_FORM_URL`)
2. Visitor submits the form
3. **Apps Script** (`visitor-pre-register.gs`) POSTs to Laravel
4. Laravel creates a **Waiting** visitor with confirmation code
5. Script emails the code + signed success link (if email was provided)
6. Guard searches Active Visitors as usual

## Laravel `.env`

```env
APP_URL=https://your-public-server.example   # must be reachable from Google

VISITOR_PRE_REGISTER_GOOGLE_FORM_URL=https://docs.google.com/forms/d/e/FORM_ID/viewform
VISITOR_PRE_REGISTER_WEBHOOK_TOKEN=long-random-secret-here
```

Leave `VISITOR_PRE_REGISTER_GOOGLE_FORM_URL` empty to use the built-in Laravel form instead.

**Webhook endpoint:** `POST {APP_URL}/api/visitor/pre-register/google`  
**Header:** `X-VISITOR-PRE-REGISTER-TOKEN: <VISITOR_PRE_REGISTER_WEBHOOK_TOKEN>`

## Create the Google Form

Add questions with these **exact titles** (matches the Apps Script):

| Question title | Type | Required |
|----------------|------|----------|
| First Name | Short answer | Yes |
| Middle Name | Short answer | No |
| Last Name | Short answer | Yes |
| Contact Number | Short answer | Yes |
| Email | Short answer | No (recommended for code delivery) |
| Purpose of Visit | Short answer | Yes |
| Office / Person to Visit | Short answer | Yes |
| Expected Exit Date | Date | Yes |
| Expected Exit Time | Time | Yes |
| Plate Number | Short answer | Yes |
| Vehicle Type | Dropdown: `Motorcycles`, `Automobiles` | Yes |
| Vehicle Color | Short answer | Yes |

**Form confirmation message (suggested):**

> Thank you! If you entered an email, check your inbox for your reference code. Otherwise, go to the guard booth and give your name and plate number.

## Install Apps Script

1. Open the Form → **Responses** → link to Sheets → **Extensions → Apps Script**
2. Paste [`visitor-pre-register.gs`](visitor-pre-register.gs)
3. **Project Settings → Script properties:**
   - `WEBHOOK_URL` = `https://your-public-server.example/api/visitor/pre-register/google`
   - `WEBHOOK_TOKEN` = same as Laravel `VISITOR_PRE_REGISTER_WEBHOOK_TOKEN`
4. Run **`installFormSubmitTrigger`** once and authorize
5. Submit a test response and confirm the visitor appears under **Guard → Active Visitors**

## Local development

Google’s servers cannot reach `127.0.0.1`. Use a tunnel (e.g. ngrok) and set `APP_URL` + `WEBHOOK_URL` to the public HTTPS URL, or test the webhook with `curl` locally.

## JSON payload (manual testing)

```json
{
  "first_name": "Jane",
  "last_name": "Doe",
  "contact_number": "09171234567",
  "email": "jane@example.com",
  "purpose": "Meeting",
  "office_to_visit": "Registrar",
  "expected_exit_at": "2026-08-23T17:00:00",
  "plate_number": "ABC1234",
  "vehicle_name": "Automobiles",
  "vehicle_color": "White"
}
```

Response:

```json
{
  "ok": true,
  "visitor_id": 123,
  "confirmation_code": "V-20260823-A7K3",
  "success_url": "https://.../visitor/pre-register/success?visitor=123&expires=...&signature=..."
}
```

## Fallback

The built-in Laravel form at `/visitor/pre-register` remains available when `VISITOR_PRE_REGISTER_GOOGLE_FORM_URL` is not set.
