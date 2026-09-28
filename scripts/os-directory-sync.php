<?php
/**
 * The Business OS directory sync — run every minute by deploy/reservations-directory-sync.timer.
 *
 *   php scripts/os-directory-sync.php              changes since the last run (the kernel's change feed)
 *   php scripts/os-directory-sync.php --full       everything again: every site first (scopes.php), then the full feed
 *   php scripts/os-directory-sync.php --from-file F apply a fixture in the kernel's format ({"scopes": …, "feed": …}),
 *                                                   for building and proving without a kernel
 *
 * The kernel's sites become restaurants (seeded like any new restaurant; a removed site closes its restaurant and
 * keeps its data), grants become the 'os' memberships, a deactivated or revoked person loses their sessions.
 * One run at a time (advisory lock), all in one transaction; the cursor moves only when everything applied.
 * Does nothing unless OS_ENABLED is on.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../helpers/os.php';

$opts = getopt('', ['full', 'from-file:']);
if (!os_enabled()) {
    fwrite(STDERR, "OS_ENABLED is off — nothing to do.\n");
    exit(0);
}

$pdo = db();
if (!$pdo->query("SELECT pg_try_advisory_lock(hashtext('zozocal_os_directory_sync'))")->fetchColumn()) {
    echo "Another sync is running.\n";
    exit(0);
}

$state = $pdo->query("SELECT next_cursor, full_at FROM directory_sync_state WHERE id = 1")->fetch(PDO::FETCH_ASSOC) ?: [];
$full = isset($opts['full']) || empty($state['next_cursor']);

try {
    $docs = [];
    if (isset($opts['from-file'])) {
        $fixture = json_decode((string)file_get_contents($opts['from-file']), true);
        if (!is_array($fixture)) throw new RuntimeException('the fixture is not JSON');
        if (isset($fixture['scopes'])) $docs[] = ['scopes' => $fixture['scopes']];
        if (isset($fixture['feed'])) $docs[] = $fixture['feed'];
    } else {
        if ($full) {
            $scopes = os_kernel_get('/api/v1/directory/scopes.php');
            if ($scopes === null) throw new RuntimeException('the kernel did not answer scopes.php');
            $docs[] = $scopes;
        }
        $feed = os_kernel_get('/api/v1/directory/changes.php' . ($full ? '' : '?since=' . rawurlencode((string)$state['next_cursor'])));
        if ($feed === null || ($feed['schema'] ?? '') !== 'os.directory-changes/1') throw new RuntimeException('the kernel did not answer changes.php');
        $docs[] = $feed;
    }

    $pdo->beginTransaction();
    $total = ['members' => 0, 'scopes' => 0, 'access' => 0, 'sessions_ended' => 0];
    $next = null;
    foreach ($docs as $doc) {
        foreach (os_apply_feed($pdo, $doc) as $k => $v) $total[$k] += $v;
        if (!empty($doc['next'])) $next = (string)$doc['next'];
    }
    $pdo->prepare("UPDATE directory_sync_state SET next_cursor = COALESCE(?, next_cursor), last_run_at = now(), last_error = NULL,
                          full_at = CASE WHEN ? THEN now() ELSE full_at END, updated_at = now() WHERE id = 1")
        ->execute([$next, $full ? 't' : 'f']);
    $pdo->commit();
    echo date('c') . ' ' . ($full ? 'full' : 'changes') . ': ' . json_encode($total) . "\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $pdo->prepare("UPDATE directory_sync_state SET last_run_at = now(), last_error = ?, updated_at = now() WHERE id = 1")
        ->execute([mb_substr($e->getMessage(), 0, 500)]);
    fwrite(STDERR, date('c') . ' sync failed: ' . $e->getMessage() . "\n");
    exit(1);
} finally {
    $pdo->query("SELECT pg_advisory_unlock(hashtext('zozocal_os_directory_sync'))");
}
