<?php
/**
 * Database helper — shortcut to get PDO connection
 */

require_once __DIR__ . '/../config/database.php';

function db() {
    return Database::getInstance()->getConnection();
}
