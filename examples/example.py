#!/usr/bin/env python3
"""Read any SYR Rates endpoint with only the Python standard library."""

import argparse
import json
import os
import re
import sys
import urllib.error
import urllib.parse
import urllib.request
from datetime import datetime, timezone


def code(value):
    if not re.fullmatch(r"[A-Za-z0-9_]{2,24}", value):
        raise argparse.ArgumentTypeError("Code must contain only letters, digits, or underscores")
    return value


parser = argparse.ArgumentParser(description="Query https://api.exchanger.sy")
commands = parser.add_subparsers(dest="command", required=True)
for name in ("rates", "currencies", "gold", "energy"):
    commands.add_parser(name)
for name in ("currency", "gold-item", "energy-item"):
    commands.add_parser(name).add_argument("code", type=code)
history = commands.add_parser("history")
history.add_argument("type", choices=("currency", "gold"))
history.add_argument("code", type=code)
history.add_argument("from_time", help="ISO 8601 time, e.g. 2026-09-01T00:00:00Z")
history.add_argument("to_time", help="ISO 8601 time")
history.add_argument("page", nargs="?", type=int, default=1)
history.add_argument("per_page", nargs="?", type=int, default=50)
args = parser.parse_args()

key = os.environ.get("SYR_API_KEY")
if not key:
    parser.error("Set SYR_API_KEY in your environment")
base = os.environ.get("SYR_API_URL", "https://api.exchanger.sy").rstrip("/")
if not urllib.parse.urlsplit(base).scheme or not urllib.parse.urlsplit(base).netloc:
    parser.error("Invalid SYR_API_URL")

paths = {
    "rates": "/api/v1/rates",
    "currencies": "/api/v1/currencies",
    "gold": "/api/v1/gold",
    "energy": "/api/v1/energy",
}
if args.command in paths:
    path = paths[args.command]
elif args.command in ("currency", "gold-item", "energy-item"):
    group = {"currency": "currencies", "gold-item": "gold", "energy-item": "energy"}[args.command]
    path = "/api/v1/" + group + "/" + args.code
else:
    path = "/api/v1/history?" + urllib.parse.urlencode(
        {
            "type": args.type,
            "code": args.code,
            "from": args.from_time,
            "to": args.to_time,
            "page": args.page,
            "per_page": args.per_page,
        }
    )

request = urllib.request.Request(
    base + path,
    headers={"X-API-Key": key, "Accept": "application/json"},
    method="GET",
)
try:
    with urllib.request.urlopen(request, timeout=15) as response:
        status = response.status
        body = response.read()
        headers = response.headers
except urllib.error.HTTPError as error:
    status = error.code
    body = error.read()
    headers = error.headers
except urllib.error.URLError as error:
    sys.exit("Network error: " + str(error.reason))

try:
    payload = json.loads(body.decode("utf-8"))
except (UnicodeError, json.JSONDecodeError):
    sys.exit("The server did not return valid JSON (HTTP {}).".format(status))

print(json.dumps(payload, ensure_ascii=False, indent=2))
for name in ("X-RateLimit-Limit", "X-RateLimit-Remaining", "X-RateLimit-Reset", "Retry-After"):
    if headers.get(name) is not None:
        print("{}: {}".format(name, headers[name]), file=sys.stderr)
if not 200 <= status < 300:
    message = payload.get("error", {}).get("message", "Request failed")
    sys.exit("HTTP {}: {}".format(status, message))

# API timestamps are Unix seconds; convert a single item's accepted time to UTC.
item = payload.get("data")
if isinstance(item, dict) and item.get("accepted_at") is not None:
    print(
        "Accepted at (UTC): "
        + datetime.fromtimestamp(item["accepted_at"], timezone.utc).isoformat(),
        file=sys.stderr,
    )
