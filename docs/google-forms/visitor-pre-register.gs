/**
 * Visitor Pre-Registration — Google Form confirmation email (no Laravel webhook)
 *
 * Flow:
 *   Visitor submits Google Form (Email required)
 *        ↓
 *   This script reads their answers
 *        ↓
 *   Emails them a confirmation to show the guard
 *   (name, date/time, purpose, who to visit, plate, etc.)
 *
 * This does NOT create a visitor in Smart Campus VMS.
 * Guards verify using the email the visitor shows on their phone,
 * then register / assign RFID in the app as usual.
 *
 * Setup:
 * 1. Form questions must use the exact titles in FIELD_TITLES below.
 * 2. Make "Email" Required on the form.
 * 3. Form → Settings → Presentation → Confirmation message, e.g.:
 *      "Thank you! Check your email for your visit confirmation — show that email to the guard."
 * 4. Form → Extensions → Apps Script → paste this file.
 * 5. Run installFormSubmitTrigger() once (authorize MailApp when prompted).
 * 6. Optional: set VISITOR_PRE_REGISTER_GOOGLE_FORM_URL in Laravel .env so the
 *    entrance QR opens this Google Form.
 *
 * No WEBHOOK_URL / WEBHOOK_TOKEN / ngrok needed for this email-only mode.
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
  ScriptApp.getProjectTriggers().forEach(function (trigger) {
    if (trigger.getHandlerFunction() === 'onFormSubmit') {
      ScriptApp.deleteTrigger(trigger);
    }
  });

  var form = FormApp.getActiveForm();
  if (form) {
    ScriptApp.newTrigger('onFormSubmit')
      .forForm(form)
      .onFormSubmit()
      .create();
    Logger.log('Installed onFormSubmit trigger (form-bound).');
    return;
  }

  var spreadsheet = SpreadsheetApp.getActiveSpreadsheet();
  if (spreadsheet) {
    ScriptApp.newTrigger('onFormSubmit')
      .forSpreadsheet(spreadsheet)
      .onFormSubmit()
      .create();
    Logger.log('Installed onFormSubmit trigger (spreadsheet-bound).');
    return;
  }

  throw new Error(
    'Open this script from your Google Form (Extensions → Apps Script) ' +
      'or from the linked response spreadsheet, then run installFormSubmitTrigger again.'
  );
}

function onFormSubmit(e) {
  var details;
  if (e.response) {
    details = buildDetailsFromFormResponse_(e.response);
  } else if (e.namedValues) {
    details = buildDetailsFromNamedValues_(e.namedValues);
  } else {
    Logger.log('Unsupported onFormSubmit event (missing response / namedValues).');
    return;
  }

  if (!details.email) {
    Logger.log('No email on response — cannot send confirmation. Make Email required on the form.');
    return;
  }

  var fullName = [details.first_name, details.middle_name, details.last_name]
    .filter(Boolean)
    .join(' ');
  if (!fullName) {
    fullName = 'Visitor';
  }

  var submittedAt = Utilities.formatDate(
    new Date(),
    Session.getScriptTimeZone(),
    'MMM d, yyyy · h:mm a'
  );

  var visitWhen = details.expected_exit_display || details.expected_exit_at || '—';

  MailApp.sendEmail({
    to: details.email,
    subject: 'CSPC visit confirmation — ' + fullName,
    body:
      'Thank you, ' +
      fullName +
      '.\n\n' +
      'Your visit is pre-registered via the campus Google Form.\n' +
      'Show this email to the guard at the booth.\n\n' +
      '========== VISIT CONFIRMATION ==========\n' +
      'Name: ' +
      fullName +
      '\n' +
      'Submitted: ' +
      submittedAt +
      '\n' +
      'Expected exit: ' +
      visitWhen +
      '\n' +
      'Purpose of visit: ' +
      (details.purpose || '—') +
      '\n' +
      'Office / Person to visit: ' +
      (details.office_to_visit || '—') +
      '\n' +
      'Contact: ' +
      (details.contact_number || '—') +
      '\n' +
      'Plate number: ' +
      (details.plate_number || '—') +
      '\n' +
      'Vehicle: ' +
      (details.vehicle_name || '—') +
      '\n' +
      'Color: ' +
      (details.vehicle_color || '—') +
      '\n' +
      '=======================================\n\n' +
      'Status: Pre-registered (Google Form)\n' +
      'Next: Guard will verify your ID and issue a temporary RFID.\n',
  });

  Logger.log('Confirmation email sent to ' + details.email + ' for ' + fullName);
}

function buildDetailsFromFormResponse_(formResponse) {
  var byTitle = {};
  formResponse.getItemResponses().forEach(function (itemResponse) {
    byTitle[itemResponse.getItem().getTitle()] = itemResponse.getResponse();
  });

  return buildDetailsFromTitles_(byTitle);
}

function buildDetailsFromNamedValues_(namedValues) {
  var byTitle = {};
  Object.keys(namedValues).forEach(function (title) {
    var values = namedValues[title];
    byTitle[title] = values && values.length ? values[0] : '';
  });

  return buildDetailsFromTitles_(byTitle);
}

function buildDetailsFromTitles_(byTitle) {
  var exitRaw = combineDateTime_(
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
    expected_exit_at: exitRaw.iso || '',
    expected_exit_display: exitRaw.display || '',
    plate_number: String(byTitle[FIELD_TITLES.plate] || '').trim(),
    vehicle_name: String(byTitle[FIELD_TITLES.vehicleType] || '').trim(),
    vehicle_color: String(byTitle[FIELD_TITLES.vehicleColor] || '').trim(),
  };
}

/**
 * @return {{iso: string, display: string}}
 */
function combineDateTime_(dateValue, timeValue) {
  if (!dateValue) {
    return { iso: '', display: '' };
  }

  var date = new Date(dateValue);
  if (timeValue) {
    var parts = String(timeValue).match(/(\d+):(\d+)/);
    if (parts) {
      date.setHours(parseInt(parts[1], 10), parseInt(parts[2], 10), 0, 0);
    }
  }

  var tz = Session.getScriptTimeZone();
  return {
    iso: Utilities.formatDate(date, tz, "yyyy-MM-dd'T'HH:mm:ss"),
    display: Utilities.formatDate(date, tz, 'MMM d, yyyy · h:mm a'),
  };
}
