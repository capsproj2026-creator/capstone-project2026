import re
import sys
import urllib.parse
import urllib.request
import http.cookiejar


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def fetch(url: str, opener: urllib.request.OpenerDirector, data: bytes | None = None, headers: dict[str, str] | None = None):
    req = urllib.request.Request(url, data=data, headers=headers or {})
    try:
        resp = opener.open(req, timeout=15)
        return resp.getcode(), dict(resp.headers), resp.read().decode("utf-8", errors="replace")
    except urllib.error.HTTPError as e:
        body = e.read().decode("utf-8", errors="replace")
        return e.code, dict(e.headers), body


def extract_csrf(html: str) -> str:
    m = re.search(r'name="_token"\s+value="([^"]+)"', html)
    if not m:
        raise RuntimeError("CSRF token not found in /login HTML")
    return m.group(1)


def main():
    base = sys.argv[1].rstrip("/") if len(sys.argv) > 1 else "http://127.0.0.1:8001"
    email = sys.argv[2] if len(sys.argv) > 2 else "admin@cspc.edu"
    password = sys.argv[3] if len(sys.argv) > 3 else "admin123"

    cj = http.cookiejar.CookieJar()
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))
    no_redirect_opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj), NoRedirect())

    status, headers, html = fetch(f"{base}/login", opener)
    print(f"GET /login -> {status}")
    token = extract_csrf(html)

    form = urllib.parse.urlencode({"_token": token, "email": email, "password": password}).encode("utf-8")
    status, headers, body = fetch(
        f"{base}/login",
        no_redirect_opener,
        data=form,
        headers={"Content-Type": "application/x-www-form-urlencoded"},
    )
    loc = headers.get("Location")
    print(f"POST /login ({email}) -> {status}" + (f" Location={loc}" if loc else ""))

    if loc:
        status2, _, _ = fetch(urllib.parse.urljoin(base + "/", loc.lstrip("/")), opener)
        print(f"GET {loc} -> {status2}")


if __name__ == "__main__":
    main()

