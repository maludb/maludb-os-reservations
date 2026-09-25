<?php
/**
 * Input Validation Helper
 *
 * Provides validation functions for user input
 */

/**
 * Validate email format
 *
 * @param string $email Email to validate
 * @return bool True if valid email format
 */
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate password strength
 * Requirements: At least 8 characters, one uppercase, one lowercase, one number
 *
 * @param string $password Password to validate
 * @return array ['valid' => bool, 'message' => string]
 */
function validate_password($password) {
    if (strlen($password) < 8) {
        return [
            'valid' => false,
            'message' => 'Password must be at least 8 characters long'
        ];
    }

    if (!preg_match('/[A-Z]/', $password)) {
        return [
            'valid' => false,
            'message' => 'Password must contain at least one uppercase letter'
        ];
    }

    if (!preg_match('/[a-z]/', $password)) {
        return [
            'valid' => false,
            'message' => 'Password must contain at least one lowercase letter'
        ];
    }

    if (!preg_match('/[0-9]/', $password)) {
        return [
            'valid' => false,
            'message' => 'Password must contain at least one number'
        ];
    }

    return ['valid' => true, 'message' => ''];
}

/**
 * Validate required fields are present and not empty
 *
 * @param array $fields Array of field names to check
 * @param array $data Data array to validate
 * @return array Empty if valid, or array of missing field names
 */
function validate_required($fields, $data) {
    $missing = [];

    foreach ($fields as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            $missing[] = $field;
        }
    }

    return $missing;
}

/**
 * Sanitize user input
 *
 * @param mixed $data String or array of strings to sanitize
 * @return mixed Sanitized data
 */
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }

    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}
