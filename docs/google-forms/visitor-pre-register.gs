/**
 * Visitor pre-registration — Google Form → Smart Campus VMS webhook
 *
 * Setup:
 * 1. Create a Google Form with the question titles listed in FIELD_TITLES below.
 * 2. Form → Link to Sheets → Extensions → Apps Script → paste this file.
 * 3. Project Settings → Script properties:
 *      WEBHOOK_URL  = https://YOUR_PUBLIC_APP_URL/api/visitor/pre-register/google
 *      WEBHOOK_TOKEN = same as VISITOR_PRE_REGISTER_WEBHOOK_TOKEN in Laravel .env
 * 4. Run installFormSubmitTrigger() once (authorize when prompted).
 * 5. Set VISITOR_PRE_REGISTER_GOOGLE_FORM_URL in Laravel .env to the form's public URL.
 *
 * After submit: Laravel creates the visitor and returns a reference code.
 * If the visitor entered Email, this script emails the code + a signed success link.
 */

var FIELD_TITLES = {
  firstName: 'First Name',
  middleName: 'Middle Name',
  lastName: 'Last Name',
  contactNumber: 'Contact Number',
  email: 'Email',
  purpose: 'Purpose of Visit',
  office: 'Office / Person to Visit',
  exitDate: 'Expected Exit Date',
  exitTime: 'Expected Exit Time',
  plate: 'Plate Number',
  vehicleType: 'Vehicle Type',
  vehicleColor: 'Vehicle Color',
};

function installFormSubmitTrigger() {
  var form = FormApp.getActiveForm();
  ScriptApp.getProjectTriggers().forEach(function (trigger) {
    if (trigger.getHandlerFunction() === 'onFormSubmit') {
      ScriptApp.deleteTrigger(trigger);
    }
  });
  ScriptApp.newTrigger('onFormSubmit')
    .forForm(form)
    .onFormSubmit()
    .create();
  Logger.log('Installed onFormSubmit trigger.');
}

function onFormSubmit(e) {
  var props = PropertiesService.getScriptProperties();
  var webhookUrl = props.getProperty('WEBHOOK_URL');
  var webhookToken = props.getProperty('WEBHOOK_TOKEN');

  if (!webhookUrl || !webhookToken) {
    Logger.log('Missing WEBHOOK_URL or WEBHOOK_TOKEN script properties.');
    return;
  }

  var payload = buildPayload_(e.response);
  var options = {
    method: 'post',
    contentType: 'application/json',
    headers: { 'X-VISITOR-PRE-REGISTER-TOKEN': webhookToken },
    payload: JSON.stringify(payload),
    muteHttpExceptions: true,
  };

  var response = UrlFetchApp.fetch(webhookUrl, options);
  var status = response.getResponseCode();
  var bodyText = response.getContentText();

  if (status >= 400) {
    Logger.log('Webhook HTTP ' + status + ': ' + bodyText);
    return;
  }

  var body = JSON.parse(bodyText);
  if (!body.ok) {
    Logger.log('Webhook rejected: ' + bodyText);
    return;
  }

  if (payload.email) {
    MailApp.sendEmail({
      to: payload.email,
      subject: 'Your campus visit reference code',
      body:
        'Thank you for pre-registering.\n\n' +
        'Your reference code: ' +
        body.confirmation_code +
        '\n\nShow this code at the guard booth.\n\n' +
        'You can also open this link on your phone:\n' +
        body.success_url +
        '\n',
    });
  }
}

function buildPayload_(formResponse) {
  var byTitle = {};
  formResponse.getItemResponses().forEach(function (itemResponse) {
    byTitle[itemResponse.getItem().getTitle()] = itemResponse.getResponse();
  });

  var exitAt = combineDateTime_(
    byTitle[FIELD_TITLES.exitDate],
    byTitle[FIELD_TITLES.exitTime]
  );

  return {
    first_name: String(byTitle[FIELD_TITLES.firstName] || '').trim(),
    middle_name: String(byTitle[FIELD_TITLES.middleName] || '').trim(),
    last_name: String(byTitle[FIELD_TITLES.lastName] || '').trim(),
    contact_number: String(byTitle[FIELD_TITLES.contactNumber] || '').trim(),
    email: String(byTitle[FIELD_TITLES.email] || '').trim(),
    purpose: String(byTitle[FIELD_TITLES.purpose] || '').trim(),
    office_to_visit: String(byTitle[FIELD_TITLES.office] || '').trim(),
    expected_exit_at: exitAt,
    plate_number: String(byTitle[FIELD_TITLES.plate] || '').trim(),
    vehicle_name: String(byTitle[FIELD_TITLES.vehicleType] || '').trim(),
    vehicle_color: String(byTitle[FIELD_TITLES.vehicleColor] || '').trim(),
  };
}

function combineDateTime_(dateValue, timeValue) {
  if (!dateValue) {
    return '';
  }

  var date = new Date(dateValue);
  if (timeValue) {
    var parts = String(timeValue).match(/(\d+):(\d+)/);
    if (parts) {
      date.setHours(parseInt(parts[1], 10), parseInt(parts[2], 10), 0, 0);
    }
  }

  return Utilities.formatDate(date, Session.getScriptTimeZone(), "yyyy-MM-dd'T'HH:mm:ss");
}
