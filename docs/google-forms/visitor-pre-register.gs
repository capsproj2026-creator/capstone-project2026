/**
 * Visitor Pre-Registration — Google Form confirmation email (no Laravel webhook)
 *
 * IMPORTANT — do these in order or email will NOT send:
 * 1. Open the FORM (not only the spreadsheet): Form → Extensions → Apps Script
 * 2. Delete any old script, paste THIS entire file, click Save
 * 3. Set TEST_EMAIL below to YOUR inbox (for the test only)
 * 4. Select function: installFormSubmitTrigger → Run → Allow permissions
 * 5. Select function: testSendConfirmationEmail → Run
 *    (checks that MailApp can send; look in Inbox + Spam)
 * 6. Select function: diagnoseFormTitles → Run
 *    (prints your question titles — Email title must match or be close to "Email")
 * 7. Make the Email question Required on the form
 * 8. Submit a real test response, then: Executions (left menu) → open latest run → check logs
 *
 * No WEBHOOK_URL / ngrok needed.
 */

// Used ONLY by testSendConfirmationEmail — put your real inbox here, then Run that function.
var TEST_EMAIL = 'your.email@gmail.com';

var FIELD_TITLES = {
  firstName: ['First Name', 'First name', 'Given Name'],
  middleName: ['Middle Name', 'Middle name'],
  lastName: ['Last Name', 'Last name', 'Surname', 'Family Name'],
  contactNumber: ['Contact Number', 'Contact No.', 'Contact No', 'Phone', 'Mobile Number', 'Mobile'],
  email: ['Email', 'E-mail', 'Email Address', 'E-mail Address'],
  purpose: ['Purpose of Visit', 'Purpose', 'Purpose of visit'],
  office: ['Office / Person to Visit', 'Office/Person to Visit', 'Person to Visit', 'Office to Visit'],
  exitDate: ['Expected Exit Date', 'Exit Date', 'Expected Date of Exit'],
  exitTime: ['Expected Exit Time', 'Exit Time', 'Expected Time of Exit'],
  plate: ['Plate Number', 'Plate No.', 'Plate No', 'Vehicle Plate'],
  vehicleType: ['Vehicle Type', 'Type of Vehicle'],
  vehicleColor: ['Vehicle Color', 'Color'],
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
    Logger.log('OK: form-bound onFormSubmit trigger installed for "' + form.getTitle() + '".');
    Logger.log('Next: run testSendConfirmationEmail, then submit the form once.');
    return;
  }

  var spreadsheet = SpreadsheetApp.getActiveSpreadsheet();
  if (spreadsheet) {
    ScriptApp.newTrigger('onFormSubmit')
      .forSpreadsheet(spreadsheet)
      .onFormSubmit()
      .create();
    Logger.log('OK: spreadsheet-bound onFormSubmit trigger installed.');
    Logger.log('Tip: prefer opening Apps Script from the Form itself (Extensions → Apps Script).');
    return;
  }

  throw new Error(
    'No form or spreadsheet bound. Open Apps Script from the Google Form: ' +
      'Form → Extensions → Apps Script, then run installFormSubmitTrigger again.'
  );
}

/** Sends a simple test email to TEST_EMAIL (set at the top of this file). */
function testSendConfirmationEmail() {
  var to = String(TEST_EMAIL || '').trim();
  if (!to || to === 'your.email@gmail.com') {
    throw new Error(
      'Set TEST_EMAIL at the top of Code.gs to your real email address, Save, then Run again.'
    );
  }

  MailApp.sendEmail({
    to: to,
    subject: 'CSPC test — visit confirmation mail works',
    body:
      'This is a test from the Visitor Pre-Registration Apps Script.\n\n' +
      'If you received this, MailApp is authorized.\n' +
      'Next: run installFormSubmitTrigger (if not done), then submit the real form.\n' +
      'Check Spam/Promotions if you do not see confirmation emails.\n',
  });

  Logger.log('Test email sent to: ' + to + ' — check Inbox and Spam.');
}

/** Lists form question titles so you can fix FIELD_TITLES mismatches. */
function diagnoseFormTitles() {
  var form = FormApp.getActiveForm();
  if (!form) {
    Logger.log('No active form. Open script from Form → Extensions → Apps Script.');
    return;
  }

  Logger.log('Form title: ' + form.getTitle());
  form.getItems().forEach(function (item) {
    Logger.log('Question title: [' + item.getTitle() + ']');
  });

  var sample = {};
  form.getItems().forEach(function (item) {
    sample[item.getTitle()] = '(sample)';
  });
  var parsed = buildDetailsFromTitles_(sample);
  Logger.log('Email field resolved as: [' + (parsed.email || 'NOT FOUND') + ']');
  if (!parsed.email || parsed.email === '(sample)') {
    // email key exists if title matched; value is sample placeholder
  }
  if (!findValueByAliases_(sample, FIELD_TITLES.email)) {
    Logger.log('PROBLEM: No question title matched Email aliases. Rename the question to "Email" or update FIELD_TITLES.email.');
  } else {
    Logger.log('OK: An Email-like question title was found.');
  }
}

function listTriggers() {
  var triggers = ScriptApp.getProjectTriggers();
  if (!triggers.length) {
    Logger.log('No triggers installed. Run installFormSubmitTrigger.');
    return;
  }
  triggers.forEach(function (t) {
    Logger.log('Trigger: ' + t.getHandlerFunction() + ' / ' + t.getEventType());
  });
}

function onFormSubmit(e) {
  try {
    if (!e) {
      Logger.log('onFormSubmit called with no event (do not Run this manually — submit the form).');
      return;
    }

    var details;
    if (e.response) {
      details = buildDetailsFromFormResponse_(e.response);
    } else if (e.namedValues) {
      details = buildDetailsFromNamedValues_(e.namedValues);
    } else {
      Logger.log('Unsupported event shape. Keys: ' + Object.keys(e).join(', '));
      return;
    }

    Logger.log('Parsed email=[' + details.email + '] name=[' +
      [details.first_name, details.last_name].filter(Boolean).join(' ') + ']');

    if (!details.email) {
      Logger.log('FAIL: Email empty. Make Email required and ensure the question title is "Email". Run diagnoseFormTitles.');
      return;
    }

    sendConfirmationEmail_(details);
    Logger.log('OK: Confirmation email sent to ' + details.email);
  } catch (err) {
    Logger.log('ERROR in onFormSubmit: ' + err);
    throw err;
  }
}

function sendConfirmationEmail_(details) {
  var fullName = [details.first_name, details.middle_name, details.last_name]
    .filter(Boolean)
    .join(' ');
  if (!fullName) {
    fullName = 'Visitor';
  }

  var submittedAt = Utilities.formatDate(
    new Date(),
    Session.getScriptTimeZone() || 'Asia/Manila',
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
    findValueByAliases_(byTitle, FIELD_TITLES.exitDate),
    findValueByAliases_(byTitle, FIELD_TITLES.exitTime)
  );

  return {
    first_name: String(findValueByAliases_(byTitle, FIELD_TITLES.firstName) || '').trim(),
    middle_name: String(findValueByAliases_(byTitle, FIELD_TITLES.middleName) || '').trim(),
    last_name: String(findValueByAliases_(byTitle, FIELD_TITLES.lastName) || '').trim(),
    contact_number: String(findValueByAliases_(byTitle, FIELD_TITLES.contactNumber) || '').trim(),
    email: String(findValueByAliases_(byTitle, FIELD_TITLES.email) || '').trim(),
    purpose: String(findValueByAliases_(byTitle, FIELD_TITLES.purpose) || '').trim(),
    office_to_visit: String(findValueByAliases_(byTitle, FIELD_TITLES.office) || '').trim(),
    expected_exit_at: exitRaw.iso || '',
    expected_exit_display: exitRaw.display || '',
    plate_number: String(findValueByAliases_(byTitle, FIELD_TITLES.plate) || '').trim(),
    vehicle_name: String(findValueByAliases_(byTitle, FIELD_TITLES.vehicleType) || '').trim(),
    vehicle_color: String(findValueByAliases_(byTitle, FIELD_TITLES.vehicleColor) || '').trim(),
  };
}

function findValueByAliases_(byTitle, aliases) {
  var titles = Object.keys(byTitle);
  for (var i = 0; i < aliases.length; i++) {
    var want = normalizeTitle_(aliases[i]);
    for (var j = 0; j < titles.length; j++) {
      if (normalizeTitle_(titles[j]) === want) {
        return byTitle[titles[j]];
      }
    }
  }
  return '';
}

function normalizeTitle_(title) {
  return String(title || '')
    .toLowerCase()
    .replace(/\s+/g, ' ')
    .replace(/[.:]/g, '')
    .trim();
}

function combineDateTime_(dateValue, timeValue) {
  if (!dateValue) {
    return { iso: '', display: '' };
  }

  var date = new Date(dateValue);
  if (isNaN(date.getTime())) {
    return { iso: String(dateValue), display: String(dateValue) };
  }

  if (timeValue) {
    var parts = String(timeValue).match(/(\d+):(\d+)/);
    if (parts) {
      date.setHours(parseInt(parts[1], 10), parseInt(parts[2], 10), 0, 0);
    }
  }

  var tz = Session.getScriptTimeZone() || 'Asia/Manila';
  return {
    iso: Utilities.formatDate(date, tz, "yyyy-MM-dd'T'HH:mm:ss"),
    display: Utilities.formatDate(date, tz, 'MMM d, yyyy · h:mm a'),
  };
}
