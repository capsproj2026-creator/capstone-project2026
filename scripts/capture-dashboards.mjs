import { mkdir } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, '..');
const outDir = join(root, 'files', 'images');
const base = (process.env.APP_URL || 'http://127.0.0.1:8000').replace(/\/$/, '');

const accounts = {
  admin: { email: 'admin@my.cspc.edu.ph', password: 'admin123' },
  guard: { email: 'guard@my.cspc.edu.ph', password: 'password123' },
  user: { email: 'student@my.cspc.edu.ph', password: 'password123' },
};

const pages = [
  { file: '01-home.png', path: '/', auth: null },
  { file: '02-login.png', path: '/login', auth: null },
  { file: '03-register.png', path: '/register', auth: null },

  { file: '04-admin-dashboard.png', path: '/admin', auth: 'admin' },
  { file: '05-admin-registrations.png', path: '/admin/registrations', auth: 'admin' },
  { file: '06-admin-rfid-assignment.png', path: '/admin/rfid', auth: 'admin' },
  { file: '07-admin-user-management.png', path: '/admin/users', auth: 'admin' },
  { file: '08-admin-create-guard.png', path: '/admin/guards/create', auth: 'admin' },
  { file: '09-admin-registered-visitors.png', path: '/admin/visitors/active', auth: 'admin' },
  { file: '10-admin-visitor-history.png', path: '/admin/visitors/history', auth: 'admin' },
  { file: '11-admin-violations.png', path: '/admin/violations', auth: 'admin' },
  { file: '12-admin-access-logs.png', path: '/admin/access-logs', auth: 'admin' },
  { file: '13-admin-parking.png', path: '/admin/parking', auth: 'admin', fullPage: false },
  { file: '14-admin-parking-zone-access.png', path: '/admin/parking/zone-access', auth: 'admin' },
  { file: '15-admin-live-cameras.png', path: '/admin/live-cameras', auth: 'admin' },
  { file: '16-admin-reports.png', path: '/admin/reports', auth: 'admin' },
  { file: '17-admin-settings-general.png', path: '/admin/settings?section=general', auth: 'admin' },
  { file: '18-admin-settings-admins.png', path: '/admin/settings?section=admins', auth: 'admin' },
  { file: '19-admin-settings-notifications.png', path: '/admin/settings?section=notifications', auth: 'admin' },
  { file: '20-admin-settings-violations.png', path: '/admin/settings?section=violations', auth: 'admin' },
  { file: '21-admin-settings-access.png', path: '/admin/settings?section=access', auth: 'admin' },
  { file: '22-admin-profile.png', path: '/profile', auth: 'admin' },

  { file: '23-guard-dashboard.png', path: '/guard', auth: 'guard' },
  { file: '24-guard-live-gate-monitor.png', path: '/guard/gate', auth: 'guard' },
  { file: '25-guard-user-monitor.png', path: '/guard/monitor', auth: 'guard' },
  { file: '26-guard-register-visitor.png', path: '/guard/visitors/register', auth: 'guard' },
  { file: '27-guard-active-visitors.png', path: '/guard/visitors/active', auth: 'guard' },
  { file: '28-guard-visitor-history.png', path: '/guard/visitors/history', auth: 'guard' },
  { file: '29-guard-violations.png', path: '/guard/violations', auth: 'guard' },
  { file: '30-guard-updates.png', path: '/guard/notifications', auth: 'guard' },
  { file: '31-guard-access-logs.png', path: '/guard/access-logs', auth: 'guard' },
  { file: '32-guard-parking.png', path: '/guard/parking', auth: 'guard', fullPage: false },
  { file: '33-guard-ai-parking-monitor.png', path: '/guard/ai-parking', auth: 'guard' },
  { file: '34-guard-plate-lookup.png', path: '/guard/plate-lookup', auth: 'guard' },
  { file: '35-guard-live-cameras.png', path: '/guard/live-cameras', auth: 'guard' },
  { file: '36-guard-profile.png', path: '/profile', auth: 'guard' },

  { file: '37-user-dashboard.png', path: '/user', auth: 'user' },
  { file: '38-user-notifications.png', path: '/user/notifications', auth: 'user' },
  { file: '39-user-violations.png', path: '/user/violations', auth: 'user' },
  { file: '40-user-entry-exit.png', path: '/user/entry-exit', auth: 'user' },
  { file: '41-user-parking.png', path: '/user/parking', auth: 'user' },
  { file: '42-user-profile.png', path: '/profile', auth: 'user' },
];

async function launchBrowser() {
  const channels = ['msedge', 'chrome', null];
  let lastError;
  for (const channel of channels) {
    try {
      return await chromium.launch({
        headless: true,
        ...(channel ? { channel } : {}),
        args: ['--hide-scrollbars'],
      });
    } catch (error) {
      lastError = error;
    }
  }
  throw lastError;
}

async function waitForPageReady(page) {
  await page.waitForLoadState('domcontentloaded');
  try {
    await page.waitForLoadState('networkidle', { timeout: 12000 });
  } catch {
    // Live camera / AI streams may never go idle.
  }
  await page.waitForTimeout(900);
  await page.evaluate(async () => {
    if (document.fonts?.ready) {
      await document.fonts.ready;
    }
    document.getElementById('portal-root')?.classList.add('portal-sidebar-open');
    document.getElementById('portal-root')?.classList.remove('portal-sidebar-closed');
    if (window.lucide?.createIcons) {
      window.lucide.createIcons();
    }
  });
  await page.waitForTimeout(400);
}

async function login(page, role) {
  const account = accounts[role];
  await page.goto(`${base}/login`, { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.fill('#email', account.email);
  await page.fill('#password', account.password);
  await page.click('button[type="submit"]');
  await page.waitForURL((url) => !url.pathname.endsWith('/login'), {
    timeout: 30000,
    waitUntil: 'domcontentloaded',
  });
  await waitForPageReady(page);
}

async function logout(page) {
  await page.goto(`${base}/logout`, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(300);
}

async function capture(page, item) {
  const dest = join(outDir, item.file);
  await page.goto(`${base}${item.path}`, { waitUntil: 'domcontentloaded', timeout: 60000 });
  await waitForPageReady(page);
  await page.screenshot({
    path: dest,
    fullPage: item.fullPage !== false,
    animations: 'disabled',
  });
  console.log(`saved ${item.file}`);
}

async function main() {
  await mkdir(outDir, { recursive: true });
  const browser = await launchBrowser();
  const context = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    deviceScaleFactor: 1,
    reducedMotion: 'reduce',
  });
  await context.addInitScript(() => {
    try {
      localStorage.setItem('portal-sidebar-open', '1');
    } catch {
      // ignore
    }
  });
  const page = await context.newPage();
  page.setDefaultTimeout(25000);

  const only = new Set(process.argv.slice(2).filter((a) => a.endsWith('.png')));
  const selected = only.size ? pages.filter((p) => only.has(p.file)) : pages;

  let currentRole = null;
  const results = [];

  for (const item of selected) {
    try {
      if (item.auth !== currentRole) {
        if (currentRole) {
          await logout(page);
        }
        currentRole = item.auth;
        if (item.auth) {
          await login(page, item.auth);
        }
      }
      await capture(page, item);
      results.push({ file: item.file, ok: true });
    } catch (error) {
      console.error(`FAILED ${item.file}: ${error.message}`);
      results.push({ file: item.file, ok: false, error: error.message });
    }
  }

  await browser.close();

  const failed = results.filter((r) => !r.ok);
  console.log(`\nCaptured ${results.filter((r) => r.ok).length}/${results.length} screenshots`);
  console.log(`Output: ${outDir}`);
  if (failed.length) {
    process.exitCode = 1;
  }
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
