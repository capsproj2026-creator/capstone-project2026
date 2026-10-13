@php
    $tone = $tone ?? 'on-light';
@endphp
<footer class="public-footer {{ $tone }} {{ $class ?? '' }}" aria-label="Legal">
    <nav class="public-footer-links" aria-label="Policies">
        <a href="{{ route('legal.privacy') }}" @if (request()->routeIs('legal.privacy')) aria-current="page" @endif>Privacy Notice</a>
        <span aria-hidden="true">|</span>
        <a href="{{ route('legal.terms') }}" @if (request()->routeIs('legal.terms')) aria-current="page" @endif>Terms of Use</a>
        <span aria-hidden="true">|</span>
        <a href="{{ route('legal.policy') }}" @if (request()->routeIs('legal.policy')) aria-current="page" @endif>Campus Vehicle Policy</a>
        <span aria-hidden="true">|</span>
        <a href="{{ route('legal.accessibility') }}" @if (request()->routeIs('legal.accessibility')) aria-current="page" @endif>Accessibility</a>
    </nav>
    <p class="public-footer-copy">&copy; {{ date('Y') }} Smart Campus Security Department</p>
</footer>
<style>
    .public-footer { margin-top: 2rem; text-align: center; font-size: 12px; line-height: 1.6; }
    .public-footer.home-footer { margin-top: 25px; }
    .public-footer-links { display: flex; flex-wrap: wrap; justify-content: center; gap: 0.2rem 0.45rem; }
    .public-footer a { text-decoration: none; font-weight: 600; }
    .public-footer-copy { margin: 0.35rem 0 0; font-size: 12px; font-weight: 400; line-height: 1.6; color: inherit; }
    .public-footer.on-photo { color: rgba(219, 234, 254, 0.75); }
    .public-footer.on-photo a { color: rgba(239, 246, 255, 0.92); }
    .public-footer.on-photo a:hover,
    .public-footer.on-photo a[aria-current="page"] { color: #fff; text-decoration: underline; }
    .public-footer.on-light { color: #94a3b8; }
    .public-footer.on-light a { color: #475569; }
    .public-footer.on-light a:hover,
    .public-footer.on-light a[aria-current="page"] { color: #0f172a; text-decoration: underline; }
    html.dark .public-footer.on-light,
    html.dark .public-footer.on-light a { color: #cbd5e1; }
    html.dark .public-footer.on-light a:hover,
    html.dark .public-footer.on-light a[aria-current="page"] { color: #fff; }
</style>
