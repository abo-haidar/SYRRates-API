<?php
declare(strict_types=1);

// Examples: php examples/example.php currency USD
//           php examples/example.php history currency USD 2026-09-01T00:00:00Z 2026-09-30T00:00:00Z
function usage(): void
{
    fwrite(STDERR, "Usage: php examples/example.php COMMAND [arguments]\n"
        . "  rates | currencies | currency CODE | gold | gold-item KARAT\n"
        . "  energy | energy-item CODE\n"
        . "  history TYPE CODE FROM TO [PAGE] [PER_PAGE]\n");
    exit(2);
}

function safeCode(string $code): string
{
    if (!preg_match('/^[A-Za-z0-9_]{2,24}$/', $code)) usage();
    return $code;
}

$key = getenv('SYR_API_KEY');
if (!$key) { fwrite(STDERR, "Set SYR_API_KEY in your environment.\n"); exit(2); }
$base = rtrim(getenv('SYR_API_URL') ?: 'https://api.exchanger.sy', '/');
if (!filter_var($base, FILTER_VALIDATE_URL)) { fwrite(STDERR, "Invalid SYR_API_URL.\n"); exit(2); }

$command = $argv[1] ?? '';
$path = '';
$query = [];
switch ($command) {
    case 'rates':
        if ($argc !== 2) usage();
        $path = '/api/v1/rates';
        break;
    case 'currencies':
        if ($argc !== 2) usage();
        $path = '/api/v1/currencies';
        break;
    case 'currency':
        if ($argc !== 3) usage();
        $path = '/api/v1/currencies/' . safeCode($argv[2]);
        break;
    case 'gold':
        if ($argc !== 2) usage();
        $path = '/api/v1/gold';
        break;
    case 'gold-item':
        if ($argc !== 3) usage();
        $path = '/api/v1/gold/' . safeCode($argv[2]);
        break;
    case 'energy':
        if ($argc !== 2) usage();
        $path = '/api/v1/energy';
        break;
    case 'energy-item':
        if ($argc !== 3) usage();
        $path = '/api/v1/energy/' . safeCode($argv[2]);
        break;
    case 'history':
        if ($argc < 6 || $argc > 8 || !in_array($argv[2], ['currency', 'gold'], true)) usage();
        $path = '/api/v1/history';
        $query = [
            'type' => $argv[2], 'code' => safeCode($argv[3]),
            'from' => $argv[4], 'to' => $argv[5],
            'page' => $argv[6] ?? '1', 'per_page' => $argv[7] ?? '50',
        ];
        break;
    default:
        usage();
}

$url = $base . $path . ($query ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');
$rateHeaders = [];
$curl = curl_init($url);
curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['X-API-Key: ' . $key, 'Accept: application/json'],
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$rateHeaders): int {
        if (preg_match('/^(X-RateLimit-[A-Za-z-]+|Retry-After):\s*(.+)$/i', trim($line), $match)) {
            $rateHeaders[$match[1]] = $match[2];
        }
        return strlen($line);
    },
]);
$body = curl_exec($curl);
$status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
if ($body === false) {
    fwrite(STDERR, 'Network error: ' . curl_error($curl) . "\n");
    curl_close($curl);
    exit(1);
}
curl_close($curl);

try {
    $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    fwrite(STDERR, "The server did not return valid JSON (HTTP $status).\n");
    exit(1);
}
if (!is_array($payload)) { fwrite(STDERR, "Unexpected JSON response.\n"); exit(1); }

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
foreach ($rateHeaders as $name => $value) fwrite(STDERR, "$name: $value\n");
if ($status < 200 || $status >= 300) {
    fwrite(STDERR, "HTTP $status: " . ($payload['error']['message'] ?? 'Request failed') . "\n");
    exit(1);
}

// API timestamps are Unix seconds. Format a single item's accepted time in UTC.
if (isset($payload['data']['accepted_at']) && is_numeric($payload['data']['accepted_at'])) {
    fwrite(STDERR, 'Accepted at (UTC): ' . gmdate('Y-m-d\TH:i:s\Z', (int)$payload['data']['accepted_at']) . "\n");
}
