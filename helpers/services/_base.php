<?php
/**
 * Service layer base
 *
 * Service functions hold the business logic shared by the HTMX partials,
 * the REST API (/api/v1/index.php) and the admin MCP server (/api/mcp/admin.php).
 * They take the restaurant and user explicitly (never read $_SESSION), return
 * plain arrays, and throw ServiceError for anything the caller should show.
 */

require_once __DIR__ . '/../db.php';

class ServiceError extends RuntimeException
{
    public string $errorCode;
    public int $status;
    public string $field;

    public function __construct(string $message, string $errorCode = 'VALIDATION_ERROR', int $status = 422, string $field = '')
    {
        parent::__construct($message);
        $this->errorCode = $errorCode;
        $this->status = $status;
        $this->field = $field;
    }
}

/** Throw a validation error (422). $field names the input at fault. */
function svc_fail(string $message, string $field = ''): never
{
    throw new ServiceError($message, 'VALIDATION_ERROR', 422, $field);
}

/** Throw a not-found error (404). */
function svc_not_found(string $what): never
{
    throw new ServiceError("{$what} not found.", 'NOT_FOUND', 404);
}

/** Fetch one row scoped to a restaurant or throw NOT_FOUND. */
function svc_row(string $table, int $id, int $restaurantId, string $what): array
{
    $stmt = db()->prepare("SELECT * FROM {$table} WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$id, $restaurantId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) svc_not_found($what);
    return $row;
}

/** Write an activity_log row. */
function svc_log(int $restaurantId, int $userId, string $action, string $entityType, ?int $entityId,
                 string $description, $oldValue = null, $newValue = null): void
{
    $stmt = db()->prepare(
        "INSERT INTO activity_log (restaurant_id, user_id, action, entity_type, entity_id, description, old_value, new_value, ip_address)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $restaurantId ?: null, $userId ?: null, $action, $entityType, $entityId, mb_substr($description, 0, 500),
        $oldValue === null ? null : json_encode($oldValue),
        $newValue === null ? null : json_encode($newValue),
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

/** limit/offset from input: default 50, max 200. */
function svc_page(array $in): array
{
    $limit = max(1, min(200, (int)($in['limit'] ?? 50)));
    $offset = max(0, (int)($in['offset'] ?? 0));
    return [$limit, $offset];
}

/** Validate a YYYY-MM-DD date string. */
function svc_date(?string $value, string $field, bool $required = true): ?string
{
    $value = trim((string)$value);
    if ($value === '') {
        if ($required) svc_fail(ucfirst(str_replace('_', ' ', $field)) . ' is required.', $field);
        return null;
    }
    $d = DateTime::createFromFormat('Y-m-d', $value);
    if (!$d || $d->format('Y-m-d') !== $value) svc_fail("{$field} must be a date in YYYY-MM-DD format.", $field);
    return $value;
}

/** Validate an HH:MM or HH:MM:SS time string, returned as HH:MM:SS. */
function svc_time(?string $value, string $field, bool $required = true): ?string
{
    $value = trim((string)$value);
    if ($value === '') {
        if ($required) svc_fail(ucfirst(str_replace('_', ' ', $field)) . ' is required.', $field);
        return null;
    }
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $value)) svc_fail("{$field} must be a time in HH:MM format.", $field);
    return strlen($value) === 5 ? $value . ':00' : $value;
}
