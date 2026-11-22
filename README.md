# SYRRates-API | Exchanger API

Exchange rates against the Syrian pound, available through a clear, read-only JSON API. Exchanger API provides Damascus currency rates, gold karat prices, and selected energy prices in one interface for websites, applications, and data services.

**Base URL:** `https://api.exchanger.sy`  
**Website:** [English](https://api.exchanger.sy/en) · [العربية](https://api.exchanger.sy/ar)  
**Full documentation:** [api.exchanger.sy/en/docs](https://api.exchanger.sy/en/docs)

## What you can access

- Currency buy and sell rates against the Syrian pound, including a single currency by code or the complete available list.
- Gold buy and sell prices by karat.
- Energy prices in both Syrian pounds and US dollars.
- Historical changes for a currency or gold karat, with a date range and pagination.
- A combined endpoint for the currently available currency, gold, and energy data.

The website presents a small selection of rates. The API provides the full set of currently visible and priced items. The available currency and gold codes may change; use their collection endpoints or the [live code list](https://api.exchanger.sy/en/docs#available) to discover them.

## Get an API key

**A free API key is available to everyone.** Open the [developer console](https://api.exchanger.sy/console?lang=en), sign in with Telegram, and create a key under the free plan. The console also shows your key status, usage, and available plans. For assistance, contact [@api365 on Telegram](https://t.me/api365).

Your secret key is displayed once when created or rotated. Store it in a server-side environment variable or secret manager. Do not place it in a public repository, browser code, or a URL.

Send the key in either HTTP header:

```http
X-API-Key: syp_YOUR_KEY
```

```http
Authorization: Bearer syp_YOUR_KEY
```

All endpoints below use HTTPS `GET` and return JSON. For example:

```bash
curl 'https://api.exchanger.sy/api/v1/currencies/USD' \
  -H 'X-API-Key: syp_YOUR_KEY' \
  -H 'Accept: application/json'
```

## Endpoints

| Endpoint | Description |
| --- | --- |
| `GET /api/v1/rates` | Currencies, gold karats, and energy items in one response |
| `GET /api/v1/currencies` | All available currencies |
| `GET /api/v1/currencies/{CODE}` | One currency, such as `USD` |
| `GET /api/v1/gold` | All available gold karats |
| `GET /api/v1/gold/{KARAT}` | One karat, such as `21K` |
| `GET /api/v1/energy` | All available energy items |
| `GET /api/v1/energy/{CODE}` | One energy item, such as `GAS95` |
| `GET /api/v1/history` | Accepted changes for one currency or gold karat |

Collection endpoints return arrays in `data`. A single-item endpoint returns an object. The combined `/rates` endpoint returns a `data` object with `currencies`, `gold`, and `energy` arrays. A hidden item is not listed; a request for its code returns `404`.

### Response format

A currency response has this structure. Values shown here illustrate the format and are **not current prices**.

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
  "meta": {
    "denomination": "old",
    "round": 6,
    "timestamp_unit": "unix_seconds"
  }
}
```

Gold items use the same price fields with `"type": "gold"`. `buy` and `sell` are decimal **strings**, so applications should parse them with a decimal type where exact arithmetic matters. `status` can be `fresh` or `cached`; a cached item retains its last accepted price. `accepted_at` identifies when that price was accepted. A time field can be `null` when no time is available.

### Energy prices

Energy prices are shown as `price_syp` and `price_usd` decimal strings, with a `unit` and an `updated_at` timestamp. USD conversion uses the available USD sell rate. An energy item is returned only when it has a visible price; the energy endpoints require a USD rate to provide both currencies.

| Code | Item | Unit |
| --- | --- | --- |
| `GAS95` | Gasoline 95 | liter |
| `GAS90` | Gasoline 90 | liter |
| `DIESEL` | Diesel | liter |
| `LPG` | Household gas | cylinder |
| `HOME_LT300` | Residential electricity up to 300 kWh | kWh |
| `HOME_GT300` | Residential electricity above 300 kWh | kWh |
| `INDUSTRIAL` | Industrial electricity | kWh |

### Historical changes

`/api/v1/history` covers currencies and gold karats. It returns accepted price changes in newest-first order. Supply all four required parameters:

| Parameter | Description |
| --- | --- |
| `type` | `currency` or `gold` |
| `code` | An item code such as `USD` or `21K` |
| `from` | ISO 8601 timestamp with a time zone |
| `to` | ISO 8601 timestamp with a time zone |
| `page` | Optional; defaults to `1`, maximum `10000` |
| `per_page` | Optional; defaults to `50`, maximum `100` |

The inclusive range must be no longer than **31 days**. The response includes `meta.total`, `meta.page`, and `meta.per_page` for pagination. An empty `data` array is a successful result when the item has no changes in that range. Energy history is not available.

```text
GET /api/v1/history?type=currency&code=USD&from=2026-09-01T00:00:00Z&to=2026-09-30T00:00:00Z&page=1&per_page=50
```

## Display units and timestamps

The service administrator sets the display denomination and decimal precision for all clients. `meta.denomination` is `old` or `new`; **one new SYP equals 100 old SYP**. `meta.round` indicates the number of decimal places, from `0` to `6`. Request parameters named `denomination` or `round` are not supported.

Response timestamps are Unix **seconds** in UTC, or `null`. Convert them to the user's preferred time zone in your application. History request parameters `from` and `to` remain ISO 8601 timestamps.

## Usage limits and errors

A key may have a start time, expiry, total request allowance, and independent minute, hour, and day limits. A plan can also allow unlimited duration, requests, or both. The [key usage page](https://api.exchanger.sy/en/usage) shows status and remaining allowance without consuming API quota; submit the key through its form, never in a URL.

When a limit applies, responses include `X-RateLimit-Limit` and `X-RateLimit-Remaining`. A timed limit also includes `X-RateLimit-Reset` as Unix seconds. If a limit is reached, the API returns `429`; timed limits include `Retry-After` in seconds. Unlimited keys may have no rate-limit headers.

| HTTP | Meaning |
| --- | --- |
| `400` | Invalid query parameters |
| `401` | Missing or invalid API key |
| `403` | Disabled, not-yet-active, or expired key |
| `404` | Unknown endpoint or item |
| `405` | Unsupported HTTP method |
| `429` | Request allowance reached |
| `500` | Internal processing error |
| `503` | Required price data is not available |

Errors use a consistent JSON envelope:

```json
{"error":{"code":"quota_exceeded","message":"API key request limit exceeded"}}
```

Use the HTTP status first, then `error.code` for application-specific handling. A valid authenticated request counts toward the key's allowance even if the requested path or parameters produce an error. Invalid or inactive keys and quota-rejected requests do not consume the allowance.

## Further information

- [English API documentation](https://api.exchanger.sy/en/docs)
- [التوثيق بالعربية](https://api.exchanger.sy/ar/docs)
- [Developer console](https://api.exchanger.sy/console?lang=en)
- [Telegram support](https://t.me/api365)
