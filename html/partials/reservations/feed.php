<?php
/**
 * HTMX endpoint: Refresh calendar for a different date
 * Redirects to the calendar with the requested date
 */
require_once '../../../helpers/auth.php';

requireAuth();

$date = trim($_GET['date'] ?? date('Y-m-d'));
$status = trim($_GET['status'] ?? '');
$sectionId = (int)($_GET['section_id'] ?? 0);

$params = ['date=' . urlencode($date)];
if ($status !== '') $params[] = 'status=' . urlencode($status);
if ($sectionId > 0) $params[] = 'section_id=' . $sectionId;

$url = '/partials/reservations/calendar.php?' . implode('&', $params);

header('HX-Redirect: ' . $url);
exit;
