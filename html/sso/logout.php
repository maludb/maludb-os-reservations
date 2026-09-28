<?php
/**
 * /sso/logout — the kernel's sign-out notice (POST notice=, or JSON {"notice": …}): every session of that member ends.
 * Answers 204 whether or not the notice verified; the signed notice is the authority, so no CSRF token.
 */
require_once __DIR__ . '/../../helpers/os.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit;
}
if (os_enabled()) {
    $notice = (string)($_POST['notice'] ?? '');
    if ($notice === '' && is_array($body = json_decode((string)file_get_contents('php://input'), true))) {
        $notice = (string)($body['notice'] ?? '');
    }
    $memberId = $notice === '' ? null : os_verify_logout_notice($notice);
    if ($memberId !== null) {
        $ended = os_end_member_sessions($memberId, 'kernel');
        $st = db()->prepare("SELECT id FROM users WHERE os_member_id = ?");
        $st->execute([$memberId]);
        $userId = (int)($st->fetchColumn() ?: 0);
        svc_log(0, $userId, 'sign_out', 'user', $userId ?: null, "Signed out by the operating system ({$ended} session(s) ended)");
    }
}
header_remove('Set-Cookie');
http_response_code(204);
