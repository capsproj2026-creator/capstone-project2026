@php
    $campusBg = is_file(public_path('images/cspc-campus-bg.png'))
        ? asset('images/cspc-campus-bg.png')
        : null;
    $hasLogo = is_file(public_path('images/cspc-logo.png'));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome - Smart Campus VMS</title>
    @include('partials.favicon')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        *, *::before, *::after { box-sizing: border-box; }
        html, body {
            margin: 0;
            min-height: 100%;
            font-family: 'Inter', sans-serif;
        }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow-x: hidden;
            background: #0f172a;
        }
        .page-bg {
            position: fixed;
            inset: 0;
            z-index: 0;
            overflow: hidden;
            pointer-events: none;
        }
        .page-bg img,
        .page-bg .bg-fallback {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
        }
        .page-bg .bg-fallback {
            background: linear-gradient(160deg, #1e3a8a 0%, #0f172a 55%, #1e293b 100%);
        }
        .page-bg::after {
            content: '';
            position: absolute;
            inset: 0;
            background:
                linear-gradient(160deg, rgba(15, 23, 42, 0.45) 0%, rgba(30, 58, 138, 0.35) 40%, rgba(15, 23, 42, 0.55) 100%);
        }
        .welcome-container {
            position: relative;
            z-index: 1;
            background: rgba(255, 255, 255, 0.94);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            padding: 48px 40px;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.35);
            border: 1px solid rgba(255, 255, 255, 0.4);
            text-align: center;
            max-width: 450px;
            width: calc(100% - 2rem);
            margin: 1rem;
        }
        .logo-wrap {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .logo-wrap img {
            width: 80px;
            height: 80px;
            object-fit: contain;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.25));
        }
        .logo-circle {
            background: #2563eb;
            color: white;
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin: 0 auto 20px;
        }
        h1 { margin: 0 0 10px; color: #0f172a; font-size: 1.75rem; }
        p { color: #475569; font-size: 0.95rem; line-height: 1.5; margin: 0 0 24px; }
        .button-group { display: flex; flex-direction: column; gap: 12px; }
        .btn {
            padding: 14px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: block;
            cursor: pointer;
        }
        .btn-login { background: #0f172a; color: #fff; }
        .btn-login:hover { background: #1e293b; }
        .btn-register { background: #fff; color: #0f172a; border: 1px solid #e2e8f0; }
        .btn-register:hover { background: #f8fafc; }
        .footer { margin-top: 25px; font-size: 12px; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="page-bg" aria-hidden="true">
        @if ($campusBg)
            <img src="{{ $campusBg }}" alt="" decoding="async">
        @else
            <div class="bg-fallback"></div>
        @endif
    </div>

    <div class="welcome-container">
        @if ($hasLogo)
            <div class="logo-wrap">
                <img src="{{ asset('images/cspc-logo.png') }}" alt="Camarines Sur Polytechnic Colleges">
            </div>
        @else
            <div class="logo-circle"><i class="fa-solid fa-shield-halved"></i></div>
        @endif
        <h1>Smart Campus VMS</h1>
        <p>Welcome to the Vehicle Management System. Please log in to access your dashboard or register a new vehicle.</p>
        <div class="button-group">
            <a href="{{ route('login') }}" class="btn btn-login"><i class="fa-solid fa-right-to-bracket"></i> Login to Portal</a>
            <a href="{{ route('register') }}" class="btn btn-register"><i class="fa-solid fa-user-plus"></i> Create New Account</a>
        </div>
        <div class="footer">&copy; {{ date('Y') }} Smart Campus Security Department</div>
    </div>
</body>
</html>
