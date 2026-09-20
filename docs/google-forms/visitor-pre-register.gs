/**
 * Visitor Pre-Registration — Google Form confirmation email (no Laravel webhook)
 *
 * Blank details in the email almost always mean question TITLES on the form
 * do not match what this script looks for. Run diagnoseFormTitles after paste.
 *
 * Setup:
 * 1. Form → Extensions → Apps Script → paste this file → Save
 * 2. Set TEST_EMAIL below → run testSendConfirmationEmail
 * 3. Run installFormSubmitTrigger (Allow permissions)
 * 4. Run diagnoseFormTitles — confirm name/email/purpose titles are found
 * 5. Prefer these question titles (or close wording):
 *      First Name, Middle Name, Last Name  (or one "Full Name")
 *      Email (required), Contact Number
 *      Purpose of Visit, Office / Person to Visit
 *      Expected Exit Date + Expected Exit Time  (or one "Expected Exit")
 *      Plate Number, Vehicle Type, Vehicle Color
 * 6. Submit the form and show the email to the guard
 */

var TEST_EMAIL = 'your.email@gmail.com';

var FIELD_TITLES = {
  fullName: ['Full Name', 'Complete Name', 'Visitor Name', 'Name'],
  firstName: ['First Name', 'First name', 'Given Name', 'Given name'],
  middleName: ['Middle Name', 'Middle name', 'M.I.', 'MI'],
  lastName: ['Last Name', 'Last name', 'Surname', 'Family Name'],
  contactNumber: ['Contact Number', 'Contact No', 'Phone', 'Mobile Number', 'Mobile', 'Contact'],
  email: ['Email', 'E-mail', 'Email Address', 'E-mail Address'],
  purpose: ['Purpose of Visit', 'Purpose', 'Purpose of visit', 'Reason for Visit'],
  office: [
    'Office / Person to Visit',
    'Office/Person to Visit',
    'Person to Visit',
    'Office to Visit',
    'Whom to Visit',
    'Office',
  ],
  exitCombined: [
    'Expected Exit',
    'Expected Exit Date and Time',
    'Expected Exit Date/Time',
    'Date and Time of Exit',
    'Expected date and time of exit',
  ],
  exitDate: ['Expected Exit Date', 'Exit Date', 'Expected Date of Exit', 'Date of Exit'],
  exitTime: ['Expected Exit Time', 'Exit Time', 'Expected Time of Exit', 'Time of Exit'],
  plate: ['Plate Number', 'Plate No', 'Vehicle Plate', 'Plate'],
  vehicleType: ['Vehicle Type', 'Type of Vehicle', 'Vehicle'],
  vehicleColor: ['Vehicle Color', 'Color of Vehicle', 'Color'],
};

function installFormSubmitTrigger() {
  ScriptApp.getProjectTriggers().forEach(function (trigger) {
    if (trigger.getHandlerFunction() === 'onFormSubmit') {
      ScriptApp.deleteTrigger(trigger);
    }
  });

  var form = FormApp.getActiveForm();
  if (form) {
    ScriptApp.newTrigger('onFormSubmit').forForm(form).onFormSubmit().create();
    Logger.log('OK: form-bound trigger installed for "' + form.getTitle() + '".');
    return;
  }

  var spreadsheet = SpreadsheetApp.getActiveSpreadsheet();
  if (spreadsheet) {
    ScriptApp.newTrigger('onFormSubmit').forSpreadsheet(spreadsheet).onFormSubmit().create();
    Logger.log('OK: spreadsheet-bound trigger installed.');
    return;
  }

  throw new Error('Open Apps Script from the Google Form (Extensions → Apps Script).');
}

function testSendConfirmationEmail() {
  var to = String(TEST_EMAIL || '').trim();
  if (!to || to === 'your.email@gmail.com') {
    throw new Error('Set TEST_EMAIL at the top of Code.gs, Save, then Run again.');
  }

  var sample = {
    first_name: 'Juan',
    middle_name: 'Santos',
    last_name: 'Dela Cruz',
    email: to,
    contact_number: '09171234567',
    purpose: 'Campus meeting',
    office_to_visit: 'Registrar',
    expected_exit_display: 'Sep 20, 2026 · 5:00 PM',
    plate_number: 'ABC1234',
    vehicle_name: 'Automobiles',
    vehicle_color: 'White',
    raw_rows: [],
  };

  sendConfirmationEmail_(sample);
  Logger.log('Sample designed confirmation sent to ' + to);
}

function diagnoseFormTitles() {
  var form = FormApp.getActiveForm();
  if (!form) {
    Logger.log('Open script from Form → Extensions → Apps Script.');
    return;
  }

  Logger.log('Form: ' + form.getTitle());
  var sample = {};
  form.getItems().forEach(function (item) {
    var title = item.getTitle();
    if (title) {
      Logger.log('Question: [' + title + ']');
      sample[title] = 'sample';
    }
  });

  var details = buildDetailsFromTitles_(sample);
  Logger.log('Mapped first_name=' + !!details.first_name +
    ' last_name=' + !!details.last_name +
    ' full_name=' + !!details.full_name +
    ' email=' + !!details.email +
    ' purpose=' + !!details.purpose +
    ' office=' + !!details.office_to_visit +
    ' exit=' + !!(details.expected_exit_display || details.expected_exit_at) +
    ' plate=' + !!details.plate_number);
}

function listTriggers() {
  var triggers = ScriptApp.getProjectTriggers();
  if (!triggers.length) {
    Logger.log('No triggers. Run installFormSubmitTrigger.');
    return;
  }
  triggers.forEach(function (t) {
    Logger.log(t.getHandlerFunction() + ' / ' + t.getEventType());
  });
}

function onFormSubmit(e) {
  try {
    if (!e) {
      Logger.log('Do not Run onFormSubmit manually — submit the Google Form.');
      return;
    }

    var byTitle;
    if (e.response) {
      byTitle = mapFromFormResponse_(e.response);
    } else if (e.namedValues) {
      byTitle = mapFromNamedValues_(e.namedValues);
    } else {
      Logger.log('Unsupported event. Keys: ' + Object.keys(e).join(', '));
      return;
    }

    Logger.log('Raw answers: ' + JSON.stringify(byTitle));

    var details = buildDetailsFromTitles_(byTitle);
    Logger.log('Mapped: ' + JSON.stringify(details));

    if (!details.email) {
      Logger.log('FAIL: could not find Email answer. Rename that question to "Email".');
      return;
    }

    sendConfirmationEmail_(details);
    Logger.log('OK: confirmation sent to ' + details.email);
  } catch (err) {
    Logger.log('ERROR: ' + err);
    throw err;
  }
}

function sendConfirmationEmail_(details) {
  var fullName = displayName_(details);
  var submittedAt = Utilities.formatDate(
    new Date(),
    Session.getScriptTimeZone() || 'Asia/Manila',
    'MMM d, yyyy · h:mm a'
  );
  var visitWhen = details.expected_exit_display || details.expected_exit_at || '—';

  var rows = [
    ['Full name', fullName],
    ['Submitted', submittedAt],
    ['Expected exit', visitWhen],
    ['Purpose of visit', details.purpose || '—'],
    ['Office / Person to visit', details.office_to_visit || '—'],
    ['Contact number', details.contact_number || '—'],
    ['Plate number', details.plate_number || '—'],
    ['Vehicle type', details.vehicle_name || '—'],
    ['Vehicle color', details.vehicle_color || '—'],
  ];

  // If mapping failed, still show every raw answer so the guard sees something useful.
  var mappedCount = 0;
  for (var i = 0; i < rows.length; i++) {
    if (rows[i][1] && rows[i][1] !== '—' && rows[i][1] !== 'Visitor') mappedCount++;
  }
  if (mappedCount <= 2 && details.raw_rows && details.raw_rows.length) {
    rows = [['Submitted', submittedAt]].concat(details.raw_rows);
  }

  var plain = [
    'Thank you, ' + fullName + '.',
    '',
    'Your visit is pre-registered via the campus Google Form.',
    'Show this email to the guard at the booth.',
    '',
    'VISIT CONFIRMATION',
  ];
  rows.forEach(function (row) {
    plain.push(row[0] + ': ' + row[1]);
  });
  plain.push('', 'Status: Pre-registered (Google Form)', 'Next: Guard will verify your ID and issue a temporary RFID.');

  MailApp.sendEmail({
    to: details.email,
    subject: 'CSPC visit confirmation — ' + fullName,
    body: plain.join('\n'),
    htmlBody: buildHtmlConfirmation_(fullName, rows),
    name: 'CSPC Smart Campus VMS',
  });
}

function buildHtmlConfirmation_(fullName, rows) {
  var rowHtml = rows
    .map(function (row, index) {
      var bg = index % 2 === 0 ? '#f8fafc' : '#ffffff';
      return (
        '<tr style="background:' +
        bg +
        ';">' +
        '<td style="padding:12px 16px;font-size:13px;color:#64748b;width:42%;border-bottom:1px solid #e2e8f0;">' +
        escapeHtml_(row[0]) +
        '</td>' +
        '<td style="padding:12px 16px;font-size:14px;color:#0f172a;font-weight:600;border-bottom:1px solid #e2e8f0;">' +
        escapeHtml_(row[1]) +
        '</td>' +
        '</tr>'
      );
    })
    .join('');

  return (
    '<div style="margin:0;padding:24px;background:#e2e8f0;font-family:Segoe UI,Arial,sans-serif;">' +
    '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 10px 30px rgba(15,23,42,0.12);">' +
    '<tr><td style="background:linear-gradient(135deg,#1A365D,#122844);padding:28px 24px;text-align:center;color:#ffffff;">' +
    '<div style="font-size:12px;letter-spacing:0.14em;text-transform:uppercase;color:#bfdbfe;font-weight:700;">Camarines Sur Polytechnic Colleges</div>' +
    '<div style="margin-top:8px;font-size:22px;font-weight:700;">Visit Confirmation</div>' +
    '<div style="margin-top:10px;display:inline-block;padding:6px 12px;border-radius:999px;background:#059669;font-size:12px;font-weight:700;">PRE-REGISTERED</div>' +
    '</td></tr>' +
    '<tr><td style="padding:24px;">' +
    '<p style="margin:0 0 6px;font-size:18px;font-weight:700;color:#0f172a;">Thank you, ' +
    escapeHtml_(fullName) +
    '</p>' +
    '<p style="margin:0 0 18px;font-size:14px;line-height:1.5;color:#475569;">Your visit is pre-registered. Show this email to the guard at the booth for ID verification and temporary RFID.</p>' +
    '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">' +
    rowHtml +
    '</table>' +
    '<div style="margin-top:18px;padding:14px 16px;border-radius:12px;background:#fffbeb;border:1px solid #fcd34d;color:#92400e;font-size:13px;line-height:1.45;">' +
    '<strong>For the guard:</strong> Verify the visitor ID against the name above, then assign a temporary RFID in Smart Campus VMS.' +
    '</div>' +
    '</td></tr>' +
    '<tr><td style="padding:14px 24px 22px;text-align:center;font-size:11px;color:#94a3b8;">Smart Campus Vehicle Management System · CSPC</td></tr>' +
    '</table></div>'
  );
}

function displayName_(details) {
  var parts = [details.first_name, details.middle_name, details.last_name].filter(Boolean);
  if (parts.length) return parts.join(' ');
  if (details.full_name) return details.full_name;
  return 'Visitor';
}

function mapFromFormResponse_(formResponse) {
  var byTitle = {};
  formResponse.getItemResponses().forEach(function (itemResponse) {
    byTitle[itemResponse.getItem().getTitle()] = stringifyAnswer_(itemResponse.getResponse());
  });
  return byTitle;
}

function mapFromNamedValues_(namedValues) {
  var byTitle = {};
  Object.keys(namedValues).forEach(function (title) {
    if (title === 'Timestamp') return;
    byTitle[title] = stringifyAnswer_(namedValues[title]);
  });
  return byTitle;
}

function stringifyAnswer_(value) {
  if (value == null) return '';
  if (Object.prototype.toString.call(value) === '[object Array]') {
    return value.filter(Boolean).join(', ');
  }
  return String(value).trim();
}

function buildDetailsFromTitles_(byTitle) {
  var rawRows = Object.keys(byTitle).map(function (title) {
    return [title, byTitle[title] || '—'];
  });

  var combinedExit = findValueByAliases_(byTitle, FIELD_TITLES.exitCombined);
  var exitRaw = combinedExit
    ? combineDateTime_(combinedExit, '')
    : combineDateTime_(
        findValueByAliases_(byTitle, FIELD_TITLES.exitDate),
        findValueByAliases_(byTitle, FIELD_TITLES.exitTime)
      );

  return {
    full_name: String(findValueByAliases_(byTitle, FIELD_TITLES.fullName) || '').trim(),
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
    raw_rows: rawRows,
  };
}

function findValueByAliases_(byTitle, aliases) {
  var titles = Object.keys(byTitle);

  // 1) Exact normalized match
  for (var i = 0; i < aliases.length; i++) {
    var want = normalizeTitle_(aliases[i]);
    for (var j = 0; j < titles.length; j++) {
      if (normalizeTitle_(titles[j]) === want) {
        return byTitle[titles[j]];
      }
    }
  }

  // 2) Fuzzy: form title contains the alias (or alias contains title)
  for (var a = 0; a < aliases.length; a++) {
    var alias = normalizeTitle_(aliases[a]);
    if (alias.length < 4) continue;
    for (var t = 0; t < titles.length; t++) {
      var title = normalizeTitle_(titles[t]);
      if (title.indexOf(alias) !== -1 || alias.indexOf(title) !== -1) {
        return byTitle[titles[t]];
      }
    }
  }

  return '';
}

function normalizeTitle_(title) {
  return String(title || '')
    .toLowerCase()
    .replace(/\s+/g, ' ')
    .replace(/[.:/_|-]+/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function combineDateTime_(dateValue, timeValue) {
  if (!dateValue) return { iso: '', display: '' };

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

function escapeHtml_(text) {
  return String(text == null ? '' : text)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}
