#!/bin/bash
# Run on the VPS as root. Keeps the SSH tunnel (127.0.0.1:18090) as the first
# choice for /cctv/ and falls back to the HTTPS uplink relay (127.0.0.1:18091).
set -euo pipefail
APP=/var/www/smart-campus
SITE_CONF=/etc/nginx/sites-enabled/www.iscvms.com.conf
FEED_CONF=/etc/nginx/sites-enabled/00-camera-feed.conf
BACKUP_DIR=/root/nginx-backups
STAMP=$(date +%Y%m%d-%H%M%S)

mkdir -p "$BACKUP_DIR"
cp -a "$SITE_CONF" "$BACKUP_DIR/www.iscvms.com.conf.$STAMP"

cat > "$FEED_CONF" <<'NGINX'
upstream camera_feed {
    server 127.0.0.1:18090 max_fails=1 fail_timeout=3s;
    server 127.0.0.1:18091 backup;
}

# Laravel reads camera health and crops here: tunnel first, uplink relay second.
server {
    listen 127.0.0.1:18089;
    server_name _;
    access_log off;

    location / {
        proxy_pass http://camera_feed;
        proxy_buffering off;
        proxy_request_buffering off;
        gzip off;
        proxy_cache off;
        proxy_http_version 1.1;
        proxy_set_header Connection "";
        proxy_connect_timeout 2s;
        proxy_next_upstream error timeout;
        proxy_read_timeout 3600s;
        proxy_send_timeout 3600s;
        client_max_body_size 10m;
    }
}
NGINX

python3 - "$SITE_CONF" <<'PY'
import sys
from pathlib import Path

path = Path(sys.argv[1])
text = path.read_text()

old_pass = "proxy_pass http://127.0.0.1:18090/;"
new_pass = (
    "proxy_pass http://camera_feed/;\n"
    "        proxy_connect_timeout 2s;\n"
    "        proxy_next_upstream error timeout;"
)
if old_pass in text:
    text = text.replace(old_pass, new_pass)
elif "proxy_pass http://camera_feed/;" not in text:
    raise SystemExit("cctv proxy_pass not found")

uplink = """
    location = /camera-uplink/status {
        proxy_pass http://127.0.0.1:18091/status;
        proxy_http_version 1.1;
        proxy_set_header Connection "";
        add_header Cache-Control "no-store";
    }

    location = /camera-uplink/health {
        limit_except POST { deny all; }
        proxy_pass http://127.0.0.1:18091/health;
        proxy_http_version 1.1;
        proxy_set_header Connection "";
        client_max_body_size 64k;
    }

    location = /camera-uplink/push {
        limit_except POST { deny all; }
        proxy_pass http://127.0.0.1:18091/push;
        proxy_buffering off;
        proxy_request_buffering off;
        gzip off;
        proxy_http_version 1.1;
        proxy_set_header Connection "";
        proxy_read_timeout 3600s;
        proxy_send_timeout 3600s;
        client_body_timeout 60s;
        client_max_body_size 0;
    }
"""
if "location = /camera-uplink/push" not in text:
    start = text.find("location ^~ /cctv/")
    end = text.find("\n    }\n", start)
    if start < 0 or end < 0:
        raise SystemExit("could not place uplink locations")
    end += len("\n    }\n")
    text = text[:end] + uplink + text[end:]

path.write_text(text)
print("nginx site updated")
PY

if ! nginx -t; then
    cp -a "$BACKUP_DIR/www.iscvms.com.conf.$STAMP" "$SITE_CONF"
    rm -f "$FEED_CONF"
    echo "nginx test failed; restored the previous config" >&2
    exit 1
fi

install -o iscvms -g iscvms -m 755 /tmp/camera_uplink_relay.py "$APP/scripts/camera_uplink_relay.py"
rm -f /tmp/camera_uplink_relay.py

cat > /etc/systemd/system/camera-uplink.service <<'UNIT'
[Unit]
Description=Campus camera uplink relay
After=network.target

[Service]
ExecStart=/usr/bin/python3 /var/www/smart-campus/scripts/camera_uplink_relay.py
Restart=always
RestartSec=2
User=iscvms
Group=iscvms
NoNewPrivileges=true

[Install]
WantedBy=multi-user.target
UNIT
systemctl daemon-reload
systemctl enable camera-uplink.service >/dev/null 2>&1
systemctl restart camera-uplink.service
systemctl reload nginx

# Drop a dead tunnel within about 45 seconds so port 18090 frees up and the
# PC can reconnect. Existing SSH sessions keep their current settings.
cat > /etc/ssh/sshd_config.d/40-keepalive.conf <<'SSHD'
ClientAliveInterval 15
ClientAliveCountMax 3
SSHD
if sshd -t; then
    systemctl reload ssh
else
    rm -f /etc/ssh/sshd_config.d/40-keepalive.conf
    echo "sshd test failed; keepalive not applied" >&2
fi

sed -i 's#^AI_PARKING_STREAM_BASE=.*#AI_PARKING_STREAM_BASE=http://127.0.0.1:18089#' "$APP/.env"
cd "$APP"
sudo -u iscvms php artisan config:cache >/dev/null
systemctl restart php8.2-fpm php8.4-fpm

sleep 2
echo "relay: $(systemctl is-active camera-uplink.service)"
curl -sS -m 6 -o /dev/null -w "feed_health:%{http_code}\n" http://127.0.0.1:18089/health || true
curl -sS -m 4 -o /dev/null -w "uplink_get_push:%{http_code}\n" https://www.iscvms.com/camera-uplink/push || true
curl -sS -m 4 -o /dev/null -w "uplink_status_no_token:%{http_code}\n" https://www.iscvms.com/camera-uplink/status || true
