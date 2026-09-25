<?php
/**
 * Date Helper Functions
 *
 * Functions for date formatting and manipulation
 */

/**
 * Convert timestamp to "time ago" format
 *
 * @param string $datetime Datetime string
 * @return string Time ago string
 */
function timeAgo($datetime) {
    if (empty($datetime)) {
        return 'Never';
    }

    $timestamp = strtotime($datetime);
    $current = time();
    $diff = $current - $timestamp;

    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' ' . ($mins == 1 ? 'minute' : 'minutes') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' ' . ($hours == 1 ? 'hour' : 'hours') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' ' . ($days == 1 ? 'day' : 'days') . ' ago';
    } elseif ($diff < 2592000) {
        $weeks = floor($diff / 604800);
        return $weeks . ' ' . ($weeks == 1 ? 'week' : 'weeks') . ' ago';
    } elseif ($diff < 31536000) {
        $months = floor($diff / 2592000);
        return $months . ' ' . ($months == 1 ? 'month' : 'months') . ' ago';
    } else {
        $years = floor($diff / 31536000);
        return $years . ' ' . ($years == 1 ? 'year' : 'years') . ' ago';
    }
}

/**
 * Format date for display
 *
 * @param string $date Date string
 * @param string $format Format string (default: 'M d, Y')
 * @return string Formatted date
 */
function formatDate($date, $format = 'M d, Y') {
    if (empty($date)) {
        return 'N/A';
    }

    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return 'Invalid Date';
    }

    return date($format, $timestamp);
}

/**
 * Format datetime for display
 *
 * @param string $datetime Datetime string
 * @param string $format Format string (default: 'M d, Y g:i A')
 * @return string Formatted datetime
 */
function formatDateTime($datetime, $format = 'M d, Y g:i A') {
    if (empty($datetime)) {
        return 'N/A';
    }

    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return 'Invalid Date';
    }

    return date($format, $timestamp);
}

/**
 * Check if a task is overdue
 *
 * @param string|null $dueDate Due date
 * @param string $status Task status
 * @return bool True if overdue
 */
function isOverdue($dueDate, $status) {
    if (empty($dueDate)) {
        return false;
    }

    // Completed and cancelled tasks are not overdue
    if (in_array($status, ['completed', 'cancelled'])) {
        return false;
    }

    $dueDateTimestamp = strtotime($dueDate);
    $today = strtotime('today');

    return $dueDateTimestamp < $today;
}

/**
 * Get days until due date
 *
 * @param string|null $dueDate Due date
 * @return int|null Days until due (negative if overdue, null if no date)
 */
function daysUntilDue($dueDate) {
    if (empty($dueDate)) {
        return null;
    }

    $dueDateTimestamp = strtotime($dueDate);
    $today = strtotime('today');

    $diff = $dueDateTimestamp - $today;
    return floor($diff / 86400);
}

/**
 * Get friendly due date text
 *
 * @param string|null $dueDate Due date
 * @param string $status Task status
 * @return string Friendly due date text
 */
function getFriendlyDueDate($dueDate, $status) {
    if (empty($dueDate)) {
        return 'No due date';
    }

    if (in_array($status, ['completed', 'cancelled'])) {
        return formatDate($dueDate);
    }

    $days = daysUntilDue($dueDate);

    if ($days === null) {
        return 'No due date';
    } elseif ($days < 0) {
        $overdueDays = abs($days);
        return $overdueDays . ' ' . ($overdueDays == 1 ? 'day' : 'days') . ' overdue';
    } elseif ($days == 0) {
        return 'Due today';
    } elseif ($days == 1) {
        return 'Due tomorrow';
    } elseif ($days <= 7) {
        return 'Due in ' . $days . ' days';
    } else {
        return 'Due ' . formatDate($dueDate);
    }
}

/**
 * Get current date in Y-m-d format
 *
 * @return string Current date
 */
function getCurrentDate() {
    return date('Y-m-d');
}

/**
 * Get current datetime in Y-m-d H:i:s format
 *
 * @return string Current datetime
 */
function getCurrentDateTime() {
    return date('Y-m-d H:i:s');
}

/**
 * Check if date is today
 *
 * @param string $date Date string
 * @return bool True if today
 */
function isToday($date) {
    if (empty($date)) {
        return false;
    }

    return date('Y-m-d', strtotime($date)) === date('Y-m-d');
}

/**
 * Check if date is this week
 *
 * @param string $date Date string
 * @return bool True if this week
 */
function isThisWeek($date) {
    if (empty($date)) {
        return false;
    }

    $timestamp = strtotime($date);
    $weekStart = strtotime('monday this week');
    $weekEnd = strtotime('sunday this week');

    return $timestamp >= $weekStart && $timestamp <= $weekEnd;
}
