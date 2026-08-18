<?php
declare(strict_types=1);

/**
 * Lightweight health endpoint for the assignment runtime.
 *
 * Baked to pub/assignment-health.php and served by an exact-match nginx
 * location. It deliberately does NOT bootstrap Magento: it reads the database
 * credentials straight from app/etc/env.php with PDO and probes the pieces the
 * stack needs to be usable. Returns HTTP 200 with {"status":"ok"} only when
 * every check passes, otherwise 503 with a hint.
 */

header('Content-Type: application/json');

$root = '/var/www/html';
$checks = [];
$ok = true;

$add = static function (string $name, bool $pass, string $detail = '') use (&$checks, &$ok): void {
    $checks[$name] = ['ok' => $pass, 'detail' => $detail];
    if (!$pass) {
        $ok = false;
    }
};

$httpGet = static function (string $url, int $timeout = 3): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => $timeout,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = (string) curl_error($ch);
    curl_close($ch);
    return ['code' => $code, 'body' => $body === false ? '' : (string) $body, 'error' => $err];
};

// --- boot marker ------------------------------------------------------------
$add('boot_marker', is_file($root . '/var/.assignment-booted'), 'var/.assignment-booted written by the entrypoint');

// --- writable runtime paths -------------------------------------------------
$writable = ['var/cache', 'var/page_cache', 'var/log', 'var/session', 'pub/media', 'generated'];
foreach ($writable as $rel) {
    $path = $root . '/' . $rel;
    $add('writable:' . $rel, is_dir($path) && is_writable($path), $path);
}

// --- database (env.php + PDO) ----------------------------------------------
$pdo = null;
$prefix = '';
$envFile = $root . '/app/etc/env.php';
if (!is_readable($envFile)) {
    $add('db_config', false, 'app/etc/env.php not readable');
} else {
    /** @var array $env */
    $env = include $envFile;
    $conn = $env['db']['connection']['default'] ?? null;
    $prefix = (string) ($env['db']['table_prefix'] ?? '');
    if (!is_array($conn) || !isset($conn['host'], $conn['dbname'])) {
        $add('db_config', false, 'default connection missing from env.php');
    } else {
        $host = (string) $conn['host'];
        $port = '3306';
        if (strpos($host, ':') !== false) {
            [$host, $port] = explode(':', $host, 2);
        }
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $conn['dbname']);
        try {
            $pdo = new PDO($dsn, (string) ($conn['username'] ?? ''), (string) ($conn['password'] ?? ''), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 3,
            ]);
            $add('db_connect', true, $host . ':' . $port);
        } catch (Throwable $e) {
            $add('db_connect', false, 'PDO connect failed');
        }
    }
}

if ($pdo instanceof PDO) {
    // mp_refund table present
    $refundTable = $prefix . 'mp_refund';
    try {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$refundTable]);
        $add('table:mp_refund', $stmt->fetchColumn() !== false, $refundTable);
    } catch (Throwable $e) {
        $add('table:mp_refund', false, 'query failed');
    }

    // seed-version marker matches the baked file
    $markerFile = '/etc/assignment/seed-version';
    $expected = is_readable($markerFile) ? trim((string) file_get_contents($markerFile)) : '';
    try {
        $cfg = $prefix . 'core_config_data';
        $stmt = $pdo->prepare("SELECT value FROM {$cfg} WHERE path = ? LIMIT 1");
        $stmt->execute(['acme_assignment/seed/version']);
        $stored = (string) ($stmt->fetchColumn() ?: '');
        $match = $expected !== '' && $stored !== '' && $expected === $stored;
        $add('seed_version', $match, sprintf('expected=%s stored=%s', $expected, $stored));
    } catch (Throwable $e) {
        $add('seed_version', false, 'core_config_data query failed');
    }
}

// --- search -----------------------------------------------------------------
$searchHost = getenv('SEARCH_HOST') ?: 'search';
$searchPort = getenv('SEARCH_PORT') ?: '9200';
$searchBase = sprintf('http://%s:%s', $searchHost, $searchPort);

$health = $httpGet($searchBase . '/_cluster/health', 3);
$status = '';
if ($health['code'] === 200) {
    $decoded = json_decode($health['body'], true);
    $status = is_array($decoded) ? (string) ($decoded['status'] ?? '') : '';
}
$add('search_health', in_array($status, ['green', 'yellow'], true), 'cluster status=' . ($status ?: 'unknown'));

$alias = $httpGet($searchBase . '/_alias/magento2_product_1', 3);
$add('search_product_alias', $alias['code'] === 200, 'magento2_product_1');

// --- ERP stub ---------------------------------------------------------------
$stubBase = rtrim(getenv('ERP_STUB_BASE_URL') ?: 'http://erp-refund-stub:8081', '/');
$stub = $httpGet($stubBase . '/health', 3);
$add('erp_stub', $stub['code'] === 200, $stubBase . '/health');

// --- response ---------------------------------------------------------------
http_response_code($ok ? 200 : 503);
$out = ['status' => $ok ? 'ok' : 'unhealthy', 'checks' => $checks];
if (!$ok) {
    $out['hint'] = 'One or more checks failed; inspect checks[].detail, then: docker compose logs web';
}
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
