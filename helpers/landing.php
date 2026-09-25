<?php
/**
 * Landing page routing helpers.
 *
 * Public requests can render different landing pages based on the
 * incoming host. The database stores relative PHP include paths, and
 * those paths are validated to stay inside the deployable html tree.
 */

require_once __DIR__ . '/db.php';

function landingDefaultPageInclude(): string
{
    return 'landing/default/coming-soon.php';
}

function normalizeLandingHost(string $value): string
{
    $value = strtolower(trim($value));
    if ($value === '') {
        return '';
    }

    // HTTP_HOST may not include a scheme, while canonical URLs will.
    if (!preg_match('#^[a-z][a-z0-9+.-]*://#', $value)) {
        $value = 'https://' . $value;
    }

    $host = parse_url($value, PHP_URL_HOST);
    if (!is_string($host) || $host === '') {
        return '';
    }

    return rtrim($host, '.');
}

function currentLandingRequestHost(): string
{
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';

    // Drop any port suffix from HTTP_HOST.
    if (strpos($host, ':') !== false) {
        $host = explode(':', $host, 2)[0];
    }

    return normalizeLandingHost($host);
}

function fetchLandingPageRouteByHost(string $host): ?array
{
    if ($host === '') {
        return null;
    }

    try {
        $stmt = db()->prepare(
            "SELECT *
             FROM landing_page_routes
             WHERE host = ? AND is_active = 1
             LIMIT 1"
        );
        $stmt->execute([$host]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    } catch (Throwable $e) {
        error_log('Landing route lookup failed: ' . $e->getMessage());
        return null;
    }
}

function resolveLandingPageRouteForRequest(): ?array
{
    $host = currentLandingRequestHost();
    if ($host === '') {
        return null;
    }

    $candidates = [$host];

    // Let a bare-domain mapping catch www traffic unless a dedicated
    // www record is added later.
    if (str_starts_with($host, 'www.')) {
        $candidates[] = substr($host, 4);
    }

    foreach ($candidates as $candidate) {
        $route = fetchLandingPageRouteByHost($candidate);
        if ($route !== null) {
            return $route;
        }
    }

    return null;
}

function resolveLandingPageInclude(?string $relativePath): ?string
{
    $relativePath = trim((string)$relativePath);
    if ($relativePath === '') {
        return null;
    }

    $relativePath = ltrim($relativePath, '/');
    if (str_contains($relativePath, '..')) {
        return null;
    }

    $htmlRoot = realpath(__DIR__ . '/../html');
    if ($htmlRoot === false) {
        return null;
    }

    $candidate = $htmlRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
    if (!is_file($candidate)) {
        return null;
    }

    $resolved = realpath($candidate);
    if ($resolved === false) {
        return null;
    }

    if ($resolved !== $htmlRoot && !str_starts_with($resolved, $htmlRoot . DIRECTORY_SEPARATOR)) {
        return null;
    }

    return $resolved;
}
