/**
 * Smoke-test Live Gate realtime: connect to Reverb, auth private-gate.scans,
 * trigger an RFID scan, expect GateScanProcessed.
 *
 * Usage (with serve + reverb running):
 *   node scripts/smoke-gate-reverb.mjs
 */
import Pusher from 'pusher-js';

const APP_URL = process.env.APP_URL || 'http://127.0.0.1:8000';
const REVERB_KEY = process.env.REVERB_APP_KEY || 'qbpukx2508hye4g139st';
const REVERB_HOST = process.env.REVERB_HOST || '127.0.0.1';
const REVERB_PORT = Number(process.env.REVERB_PORT || 8080);
const GUARD_EMAIL = process.env.GUARD_EMAIL || 'guard@my.cspc.edu.ph';
const GUARD_PASSWORD = process.env.GUARD_PASSWORD || 'password123';
const RFID_TOKEN = process.env.RFID_API_TOKEN || 'capstone-rfid-dev-token-change-me';
const RFID_UID = process.env.RFID_UID || '5AB48FF8';

function cookieHeader(res) {
    const raw = typeof res.headers.getSetCookie === 'function'
        ? res.headers.getSetCookie()
        : [];
    if (raw.length) {
        return raw.map((c) => c.split(';')[0]).join('; ');
    }
    const single = res.headers.get('set-cookie');
    return single ? single.split(',').map((c) => c.split(';')[0].trim()).join('; ') : '';
}

function mergeCookies(existing, res) {
    const map = new Map();
    String(existing || '')
        .split(';')
        .map((p) => p.trim())
        .filter(Boolean)
        .forEach((pair) => {
            const i = pair.indexOf('=');
            if (i > 0) map.set(pair.slice(0, i), pair.slice(i + 1));
        });
    cookieHeader(res)
        .split(';')
        .map((p) => p.trim())
        .filter(Boolean)
        .forEach((pair) => {
            const i = pair.indexOf('=');
            if (i > 0) map.set(pair.slice(0, i), pair.slice(i + 1));
        });
    return [...map.entries()].map(([k, v]) => `${k}=${v}`).join('; ');
}

async function main() {
    let cookies = '';

    const loginPage = await fetch(`${APP_URL}/login`, { redirect: 'manual' });
    cookies = mergeCookies(cookies, loginPage);
    const loginHtml = await loginPage.text();
    const tokenMatch = loginHtml.match(/name="_token"\s+value="([^"]+)"/);
    if (!tokenMatch) {
        throw new Error('Could not find CSRF token on /login');
    }

    const loginRes = await fetch(`${APP_URL}/login`, {
        method: 'POST',
        redirect: 'manual',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            Cookie: cookies,
        },
        body: new URLSearchParams({
            _token: tokenMatch[1],
            email: GUARD_EMAIL,
            password: GUARD_PASSWORD,
        }),
    });
    cookies = mergeCookies(cookies, loginRes);
    if (![302, 303].includes(loginRes.status)) {
        throw new Error(`Login failed with HTTP ${loginRes.status}`);
    }
    console.log('Logged in as guard');

    const pusher = new Pusher(REVERB_KEY, {
        wsHost: REVERB_HOST,
        wsPort: REVERB_PORT,
        wssPort: REVERB_PORT,
        forceTLS: false,
        enabledTransports: ['ws'],
        disableStats: true,
        cluster: '',
        authorizer: (channel) => ({
            authorize: async (socketId, callback) => {
                try {
                    const authRes = await fetch(`${APP_URL}/broadcasting/auth`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            Cookie: cookies,
                            'X-XSRF-TOKEN': decodeURIComponent(
                                (cookies.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/) || [])[1] || ''
                            ),
                        },
                        body: new URLSearchParams({
                            socket_id: socketId,
                            channel_name: channel.name,
                        }),
                    });
                    const body = await authRes.json();
                    if (!authRes.ok) {
                        callback(new Error(`Auth HTTP ${authRes.status}`), null);
                        return;
                    }
                    callback(null, body);
                } catch (err) {
                    callback(err, null);
                }
            },
        }),
    });

    const eventPromise = new Promise((resolve, reject) => {
        const timer = setTimeout(() => reject(new Error('Timed out waiting for GateScanProcessed')), 20000);
        const channel = pusher.subscribe('private-gate.scans');
        channel.bind('pusher:subscription_succeeded', () => {
            console.log('Subscribed to private-gate.scans');
        });
        channel.bind('pusher:subscription_error', (status) => {
            clearTimeout(timer);
            reject(new Error(`Subscription failed: ${JSON.stringify(status)}`));
        });
        channel.bind('GateScanProcessed', (payload) => {
            clearTimeout(timer);
            resolve(payload);
        });
    });

    await new Promise((r) => setTimeout(r, 1500));

    const scanRes = await fetch(`${APP_URL}/api/rfid/scan`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-RFID-TOKEN': RFID_TOKEN,
        },
        body: JSON.stringify({
            uid: RFID_UID,
            gate_id: 'GATE-IN-SMOKE',
            direction: 'Entry',
        }),
    });
    const scanJson = await scanRes.json();
    console.log(`RFID HTTP ${scanRes.status}:`, scanJson.status || scanJson.message || scanJson);

    const payload = await eventPromise;
    console.log('Received GateScanProcessed:', {
        id: payload.id,
        name: payload.name,
        granted: payload.granted,
        result: payload.result,
        action: payload.action,
    });

    pusher.disconnect();
    console.log('SMOKE OK — Live Gate realtime path works');
    process.exit(0);
}

main().catch((err) => {
    console.error('SMOKE FAIL:', err.message || err);
    process.exit(1);
});
