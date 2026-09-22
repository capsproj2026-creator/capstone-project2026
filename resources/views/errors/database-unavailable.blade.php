<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Database Unavailable - Smart Campus VMS</title>
    @include('partials.favicon')
    <style>
        :root {
            --navy: #1A365D;
            --navy-deep: #122844;
            --slate: #64748b;
            --ink: #0f172a;
            --line: #e2e8f0;
            --amber: #b45309;
            --amber-bg: #fffbeb;
            --amber-border: #fcd34d;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", system-ui, sans-serif;
            color: var(--ink);
            background: linear-gradient(160deg, #eff6ff 0%, #f8fafc 42%, #e2e8f0 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .card {
            width: 100%;
            max-width: 480px;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }
        .head {
            background: linear-gradient(135deg, var(--navy), var(--navy-deep));
            color: #fff;
            padding: 22px 24px;
        }
        .eyebrow {
            margin: 0;
            font-size: 11px;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #bfdbfe;
            font-weight: 700;
        }
        h1 {
            margin: 8px 0 0;
            font-size: 1.35rem;
            font-weight: 700;
        }
        .body { padding: 22px 24px 24px; }
        .notice {
            margin: 0 0 16px;
            padding: 12px 14px;
            border-radius: 12px;
            background: var(--amber-bg);
            border: 1px solid var(--amber-border);
            color: var(--amber);
            font-size: 0.9rem;
            line-height: 1.45;
        }
        p {
            margin: 0 0 12px;
            color: var(--slate);
            font-size: 0.95rem;
            line-height: 1.55;
        }
        ul {
            margin: 0 0 20px;
            padding-left: 1.15rem;
            color: var(--slate);
            font-size: 0.9rem;
            line-height: 1.55;
        }
        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        a.btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 16px;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
        }
        a.primary {
            background: var(--navy);
            color: #fff;
        }
        a.primary:hover { background: var(--navy-deep); }
        a.secondary {
            background: #f8fafc;
            color: var(--ink);
            border: 1px solid var(--line);
        }
        a.secondary:hover { background: #f1f5f9; }
    </style>
</head>
<body>
    <main class="card" role="alert">
        <div class="head">
            <p class="eyebrow">Smart Campus VMS</p>
            <h1>Database unavailable</h1>
        </div>
        <div class="body">
            <p class="notice">{{ $message ?? \App\Support\DatabaseUnavailable::MESSAGE }}</p>
            <p>The system cannot reach MongoDB right now. Campus pages that need stored data (login, dashboards, RFID verification, visitors) will not work until the database is back.</p>
            <ul>
                <li>If this is a campus laptop, check that the local MongoDB service is running.</li>
                <li>If this is cloud / Atlas mode, check internet access and the Atlas cluster status.</li>
            </ul>
            <div class="actions">
                <a class="btn primary" href="{{ url()->current() }}">Try again</a>
                <a class="btn secondary" href="{{ url('/') }}">Back to home</a>
            </div>
        </div>
    </main>
</body>
</html>
