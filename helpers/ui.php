<?php
/**
 * UI Helper Functions
 *
 * Functions for generating UI components
 */

/**
 * Get Bootstrap badge HTML for task status
 *
 * @param string $status Task status
 * @return string Bootstrap badge HTML
 */
function getStatusBadge($status) {
    $statusMap = [
        'pending' => ['class' => 'warning', 'text' => 'Pending'],
        'in_progress' => ['class' => 'info', 'text' => 'In Progress'],
        'review' => ['class' => 'primary', 'text' => 'Review'],
        'completed' => ['class' => 'success', 'text' => 'Completed'],
        'cancelled' => ['class' => 'secondary', 'text' => 'Cancelled'],
        'archived' => ['class' => 'secondary', 'text' => 'Archived']
    ];

    $config = $statusMap[$status] ?? ['class' => 'secondary', 'text' => ucfirst($status)];

    return sprintf(
        '<span class="badge bg-%s">%s</span>',
        $config['class'],
        htmlspecialchars($config['text'])
    );
}

/**
 * Get Bootstrap badge HTML for priority
 *
 * @param string $priority Task priority
 * @return string Bootstrap badge HTML
 */
function getPriorityBadge($priority) {
    $priorityMap = [
        'low' => ['class' => 'success', 'text' => 'Low'],
        'medium' => ['class' => 'warning', 'text' => 'Medium'],
        'high' => ['class' => 'danger', 'text' => 'High'],
        'critical' => ['class' => 'danger', 'text' => 'Critical', 'icon' => 'bi-exclamation-triangle-fill']
    ];

    $config = $priorityMap[$priority] ?? ['class' => 'secondary', 'text' => ucfirst($priority)];

    $icon = isset($config['icon']) ? '<i class="' . $config['icon'] . ' me-1"></i>' : '';

    return sprintf(
        '<span class="badge bg-%s">%s%s</span>',
        $config['class'],
        $icon,
        htmlspecialchars($config['text'])
    );
}

/**
 * Get colored priority dot indicator
 *
 * @param string $priority Task priority
 * @return string Priority dot HTML
 */
function getPriorityDot($priority) {
    $colorMap = [
        'low' => 'success',
        'medium' => 'info',
        'high' => 'warning',
        'critical' => 'danger'
    ];

    $color = $colorMap[$priority] ?? 'secondary';

    return sprintf(
        '<span class="badge rounded-circle bg-%s" style="width: 10px; height: 10px; padding: 0; display: inline-block;" title="%s"></span>',
        $color,
        htmlspecialchars(ucfirst($priority))
    );
}

/**
 * Get Bootstrap icon for status
 *
 * @param string $status Task status
 * @return string Bootstrap icon class
 */
function getStatusIcon($status) {
    $iconMap = [
        'pending' => 'bi-clock',
        'in_progress' => 'bi-arrow-repeat',
        'review' => 'bi-eye',
        'completed' => 'bi-check-circle',
        'cancelled' => 'bi-x-circle',
        'archived' => 'bi-archive'
    ];

    return $iconMap[$status] ?? 'bi-circle';
}

/**
 * Get Bootstrap icon for priority
 *
 * @param string $priority Task priority
 * @return string Bootstrap icon class
 */
function getPriorityIcon($priority) {
    $iconMap = [
        'low' => 'bi-arrow-down',
        'medium' => 'bi-dash',
        'high' => 'bi-arrow-up',
        'critical' => 'bi-exclamation-triangle-fill'
    ];

    return $iconMap[$priority] ?? 'bi-dash';
}

/**
 * Get color class for status
 *
 * @param string $status Task status
 * @return string Bootstrap color class
 */
function getStatusColor($status) {
    $colorMap = [
        'pending' => 'warning',
        'in_progress' => 'info',
        'review' => 'primary',
        'completed' => 'success',
        'cancelled' => 'secondary',
        'archived' => 'secondary'
    ];

    return $colorMap[$status] ?? 'secondary';
}

/**
 * Get color class for priority
 *
 * @param string $priority Task priority
 * @return string Bootstrap color class
 */
function getPriorityColor($priority) {
    $colorMap = [
        'low' => 'success',
        'medium' => 'warning',
        'high' => 'danger',
        'critical' => 'danger'
    ];

    return $colorMap[$priority] ?? 'secondary';
}

/**
 * Generate avatar initials
 *
 * @param string $firstName First name
 * @param string $lastName Last name
 * @return string Initials (max 2 chars)
 */
function getInitials($firstName, $lastName) {
    $first = !empty($firstName) ? strtoupper(substr($firstName, 0, 1)) : '';
    $last = !empty($lastName) ? strtoupper(substr($lastName, 0, 1)) : '';
    return $first . $last;
}

/**
 * Generate avatar HTML with initials
 *
 * @param string $firstName First name
 * @param string $lastName Last name
 * @param string $size Size class (sm, md, lg)
 * @return string Avatar HTML
 */
function getAvatarHtml($firstName, $lastName, $size = 'md') {
    $initials = getInitials($firstName, $lastName);
    $sizeClass = $size === 'sm' ? 'avatar-sm' : ($size === 'lg' ? 'avatar-lg' : 'avatar-md');

    $colors = ['primary', 'success', 'info', 'warning', 'danger', 'secondary'];
    $colorIndex = (ord($firstName[0] ?? 'A') + ord($lastName[0] ?? 'A')) % count($colors);
    $color = $colors[$colorIndex];

    return sprintf(
        '<div class="avatar %s bg-%s text-white rounded-circle d-flex align-items-center justify-content-center">%s</div>',
        $sizeClass,
        $color,
        htmlspecialchars($initials)
    );
}

/**
 * Truncate text with ellipsis
 *
 * @param string $text Text to truncate
 * @param int $length Maximum length
 * @param string $suffix Suffix (default: '...')
 * @return string Truncated text
 */
function truncateText($text, $length = 50, $suffix = '...') {
    if (strlen($text) <= $length) {
        return htmlspecialchars($text);
    }

    return htmlspecialchars(substr($text, 0, $length)) . $suffix;
}

/**
 * Generate empty state HTML
 *
 * @param string $icon Bootstrap icon class
 * @param string $title Empty state title
 * @param string $message Empty state message
 * @param string|null $actionHtml Optional action button HTML
 * @return string Empty state HTML
 */
function getEmptyState($icon, $title, $message, $actionHtml = null) {
    $html = '<div class="text-center py-5">';
    $html .= '<i class="' . $icon . ' fs-1 text-muted mb-3 d-block"></i>';
    $html .= '<h5 class="text-muted">' . htmlspecialchars($title) . '</h5>';
    $html .= '<p class="text-muted">' . htmlspecialchars($message) . '</p>';
    if ($actionHtml) {
        $html .= '<div class="mt-3">' . $actionHtml . '</div>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * Generate progress bar HTML
 *
 * @param int $completed Completed count
 * @param int $total Total count
 * @param string $color Bootstrap color class
 * @return string Progress bar HTML
 */
function getProgressBar($completed, $total, $color = 'primary') {
    $percentage = $total > 0 ? round(($completed / $total) * 100) : 0;

    return sprintf(
        '<div class="progress" style="height: 8px;">
            <div class="progress-bar bg-%s" role="progressbar" style="width: %d%%" aria-valuenow="%d" aria-valuemin="0" aria-valuemax="100"></div>
        </div>
        <small class="text-muted">%d of %d completed (%d%%)</small>',
        $color,
        $percentage,
        $percentage,
        $completed,
        $total,
        $percentage
    );
}
