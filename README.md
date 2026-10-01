# SYRRates-API

Read exchange rates against the Syrian pound in Damascus, gold prices, and Syrian energy prices from a read-only JSON API.

**Base URL:** `https://api.exchanger.sy` · [Website](https://api.exchanger.sy/en) · [Arabic website](https://api.exchanger.sy/ar) · [Online docs](https://api.exchanger.sy/en/docs)

## Get a free key

Message the developer on Telegram at [@api365](https://t.me/api365) and mention your app or website. Requesting a key is free. Keep the secret on your server or in an environment variable. Never publish it in a repository or browser JavaScript.

Every API request requires one of these headers:

```http
X-API-Key: syp_YOUR_KEY
```

```http
Authorization: Bearer syp_YOUR_KEY
```

The examples use `SYR_API_KEY`; `SYR_API_URL` is optional and defaults to `https://api.exchanger.sy`.

```bash
export SYR_API_KEY='syp_YOUR_KEY'
```

In PowerShell: `$env:SYR_API_KEY = 'syp_YOUR_KEY'`. Avoid putting a real key in shared command history or logs.

## Quick start

```bash
curl --get 'https://api.exchanger.sy/api/v1/currencies/USD' \
  -H "X-API-Key: $SYR_API_KEY" -H 'Accept: application/json'
```

The equivalent Bearer form is:

```bash
curl -H "Authorization: Bearer $SYR_API_KEY" \
  -H 'Accept: application/json' \
  'https://api.exchanger.sy/api/v1/currencies/USD'
```

Add `-i` to either cURL command when you need to inspect HTTP status and rate-limit headers. The runnable cURL script below includes headers by default.

All API endpoints use HTTPS `GET` and return JSON. A successful response has `data` and `meta`. A single-item endpoint returns an object in `data`; a collection returns an array. `/rates` returns an object containing three arrays.

## Endpoints and examples

| Path | Result |
| --- | --- |
| `/api/v1/rates` | Available currencies, gold karats, and energy items together |
| `/api/v1/currencies` | All available currencies |
| `/api/v1/currencies/{CODE}` | One currency, for example `USD` |
| `/api/v1/gold` | All available gold karats |
| `/api/v1/gold/{KARAT}` | One karat, for example `21K` |
| `/api/v1/energy` | All available energy items |
| `/api/v1/energy/{CODE}` | One energy item, for example `GAS95` |
| `/api/v1/history` | Accepted changes for one currency or gold karat |

```bash
# Everything in one response
curl -H "X-API-Key: $SYR_API_KEY" 'https://api.exchanger.sy/api/v1/rates'

# Currency collection and one item
curl -H "X-API-Key: $SYR_API_KEY" 'https://api.exchanger.sy/api/v1/currencies'
curl -H "X-API-Key: $SYR_API_KEY" 'https://api.exchanger.sy/api/v1/currencies/USD'

# Gold collection and one karat
curl -H "X-API-Key: $SYR_API_KEY" 'https://api.exchanger.sy/api/v1/gold'
curl -H "X-API-Key: $SYR_API_KEY" 'https://api.exchanger.sy/api/v1/gold/21K'

# Energy collection and one item
curl -H "X-API-Key: $SYR_API_KEY" 'https://api.exchanger.sy/api/v1/energy'
curl -H "X-API-Key: $SYR_API_KEY" 'https://api.exchanger.sy/api/v1/energy/GAS95'
```

Available currency and gold codes can change. Read the collection endpoint or the [live code list](https://api.exchanger.sy/en/docs#available) before relying on a code. Hidden or unpriced items do not appear in collections. A known hidden code returns `404` on a single-item path. Currency, gold, and energy single-item paths accept lowercase letters too.

### Combined response

`GET /api/v1/rates` returns this envelope. Values below are abbreviated examples, not current prices.

```json
{
  "data": {
    "currencies": [{"type": "currency", "code": "USD", "buy": "13725.000000", "sell": "13800.000000"}],
    "gold": [{"type": "gold", "code": "21K", "buy": "...", "sell": "..."}],
    "energy": [{"type": "energy", "code": "GAS95", "price_syp": "...", "price_usd": "..."}]
  },
  "meta": {"denomination": "old", "round": 6, "timestamp_unit": "unix_seconds"}
}
```

The actual items include all fields shown below. `energy` can be empty if energy prices or the USD conversion rate are unavailable. A `503` is returned if no category has an available price.

### Currency and gold item fields

`GET /api/v1/currencies/USD` returns one object like this. Gold items use the same fields with `"type": "gold"` and a karat code such as `21K`.

```json
{
  "data": {
    "type": "currency",
    "code": "USD",
    "name": {"ar": "دولار أمريكي", "en": "US Dollar"},
    "symbol": "USD",
    "flag": "🇺🇸",
    "buy": "13725.000000",
    "sell": "13800.000000",
    "metrics": {},
    "source_updated_at": 1790513102,
    "accepted_at": 1790513110,
    "status": "fresh",
    "fallback_reason": null
  },
  "meta": {"denomination": "old", "round": 6, "timestamp_unit": "unix_seconds"}
}
```

`buy` and `sell` are decimal **strings**; use a decimal type for calculations. `status` is `fresh` or `cached`. A cached item retains its last accepted price, and `fallback_reason` may identify why. `accepted_at` is the last accepted price time. Time fields can be `null`. Collection endpoints return arrays of these objects.

### Energy items

| Code | Item | Unit |
| --- | --- | --- |
| `GAS95` | Gasoline 95 | liter |
| `GAS90` | Gasoline 90 | liter |
| `DIESEL` | Diesel | liter |
| `LPG` | Household gas | cylinder |
| `HOME_LT300` | Residential electricity up to 300 kWh | kWh |
| `HOME_GT300` | Residential electricity above 300 kWh | kWh |
| `INDUSTRIAL` | Industrial electricity | kWh |

An energy item appears only after a price is set and it is visible. `GET /api/v1/energy/GAS95` returns this shape (illustrative values):

```json
{
  "data": {
    "type": "energy",
    "code": "GAS95",
    "name": {"ar": "بنزين 95", "en": "Gasoline 95"},
    "unit": "liter",
    "price_syp": "15000.000000",
    "price_usd": "1.086957",
    "updated_at": 1790513110
  },
  "meta": {
    "usd_sell_old_syp": "13800.000000",
    "denomination": "old",
    "round": 6,
    "timestamp_unit": "unix_seconds"
  }
}
```

`price_syp` and `price_usd` are decimal strings. USD conversion uses the available USD **sell** rate. `usd_sell_old_syp` is that rate in old SYP units. Energy has no `buy` or `sell`. Combined `/rates` does not include `usd_sell_old_syp` in its `meta`.

### Price history

History is for `currency` and `gold`, not energy. All four main parameters are required:

| Parameter | Value |
| --- | --- |
| `type` | `currency` or `gold` |
| `code` | Item code, such as `USD` or `21K` |
| `from` | ISO 8601 timestamp with time zone, such as `2026-09-01T00:00:00Z` |
| `to` | ISO 8601 timestamp with time zone |
| `page` | Optional, default `1`, maximum `10000` |
| `per_page` | Optional, default `50`, maximum `100` |

The inclusive time range must be valid and at most **31 days**. Results are newest first and contain accepted price changes only.

```bash
curl --get 'https://api.exchanger.sy/api/v1/history' \
  -H "X-API-Key: $SYR_API_KEY" \
  --data-urlencode 'type=currency' --data-urlencode 'code=USD' \
  --data-urlencode 'from=2026-09-01T00:00:00Z' \
  --data-urlencode 'to=2026-09-30T00:00:00Z' \
  --data-urlencode 'page=1' --data-urlencode 'per_page=50'
```

```json
{
  "data": [
    {"buy": "13725.000000", "sell": "13800.000000", "metrics": {}, "source_updated_at": 1790513102, "accepted_at": 1790513110}
  ],
  "meta": {"type": "currency", "code": "USD", "page": 1, "per_page": 50, "total": 1, "denomination": "old", "round": 6, "timestamp_unit": "unix_seconds"}
}
```

These numbers are illustrative. An empty `data` array with `total: 0` is a successful result for an item without changes in the range. Paginate using `meta.total`, `meta.page`, and `meta.per_page`.

## Display units and time zones

The service administrator chooses `meta.denomination` (`old` or `new`) and `meta.round` (decimal places, `0`–`6`) for **all** clients. New SYP equals old SYP divided by 100. Query parameters `denomination` and `round` are not supported and return `400`.

Response timestamps are Unix **seconds** in UTC, or `null`. Convert them to your user's time zone. History *request* parameters `from` and `to` remain ISO 8601 strings.

```python
from datetime import datetime, timezone
print(datetime.fromtimestamp(1790513110, timezone.utc).isoformat())
```

## Limits, errors, and key usage

Keys may have a start time, expiry time, total allowance, minute/hour/day limits, or no limits. A valid authenticated request consumes one request even if it returns `400`, `404`, or `503`. Requests rejected for an invalid/inactive key or exhausted quota do not consume an allowance.

When a limit is configured, responses include `X-RateLimit-Limit` and `X-RateLimit-Remaining`. Timed limits also include `X-RateLimit-Reset` (Unix seconds). On `429`, a timed limit adds `Retry-After` (seconds). These headers describe one applicable limit, not every configured limit. Unlimited keys may have no rate-limit headers.

```http
HTTP/1.1 429 Too Many Requests
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1790513160
Retry-After: 18

{"error":{"code":"quota_exceeded","message":"API key request limit exceeded"}}
```

| HTTP | Meaning | Typical `error.code` |
| --- | --- | --- |
| `400` | Invalid parameters or display override | `bad_request` |
| `401` | Missing or invalid key | `invalid_key` |
| `403` | Disabled, not-yet-active, or expired key | `key_inactive` |
| `404` | Unknown endpoint or item | `not_found` |
| `405` | Method other than GET | `method_not_allowed` |
| `429` | Allowance reached | `quota_exceeded` |
| `500` | Internal processing failure | `server_error` |
| `503` | Required prices or service unavailable | `no_prices`, `no_usd_rate`, or `service_unavailable` |

Errors have an `error` object with `code` and `message`, for example `{"error":{"code":"not_found","message":"Instrument not found"}}`. Handle HTTP status first, then `error.code`. On `429`, wait for `Retry-After` when present.

Check your key at the [protected usage page](https://api.exchanger.sy/en/usage). Enter it into the form, not a URL. The check does not consume API quota. The page shows status, remaining total allowance, minute/hour/day windows, and recorded errors.

## Runnable examples

- [cURL shell script](examples/curl.sh): POSIX shell and cURL.
- [PHP example](examples/example.php): PHP 8.1+ with cURL.
- [Python example](examples/example.py): Python 3.8+ standard library.

After setting `SYR_API_KEY`, use the same command names with `php`, `python3`, or `sh`:

```bash
php examples/example.php rates
php examples/example.php currencies
php examples/example.php currency USD
php examples/example.php gold
php examples/example.php gold-item 21K
php examples/example.php energy
php examples/example.php energy-item GAS95
php examples/example.php history currency USD 2026-09-01T00:00:00Z 2026-09-30T00:00:00Z 1 50
```

Use `python3 examples/example.py ...` or `sh examples/curl.sh ...` with the same arguments. The dates above are examples; choose a relevant 31-day range for your history query.
