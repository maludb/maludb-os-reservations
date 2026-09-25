<?php
/**
 * Error Log Viewer Utility
 *
 * Displays Apache and PHP error logs for debugging purposes
 * WARNING: Remove or restrict access in production environments
 */

// Enable error display for this utility
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Configuration
$apacheLogPath = '/var/log/apache2/error.log';
$phpLogPath = ini_get('error_log');
$linesToShow = 50;

/**
 * Read last N lines from a file
 *
 * @param string $filepath Path to the log file
 * @param int $lines Number of lines to read
 * @return array|string Array of lines or error message
 */
function tailFile($filepath, $lines = 50) {
    if (!file_exists($filepath)) {
        return "File not found: " . $filepath;
    }

    if (!is_readable($filepath)) {
        return "File not readable. Check permissions: " . $filepath;
    }

    // Use tail command for efficiency
    $output = [];
    $returnVar = 0;
    exec("tail -n " . intval($lines) . " " . escapeshellarg($filepath) . " 2>&1", $output, $returnVar);

    if ($returnVar !== 0) {
        return "Error reading file: " . implode("\n", $output);
    }

    return $output;
}

/**
 * Get PHP error reporting level as string
 *
 * @param int $level Error reporting level
 * @return string Human-readable error level
 */
function getErrorReportingLevel($level) {
    $levels = [];
    $constants = [
        'E_ERROR', 'E_WARNING', 'E_PARSE', 'E_NOTICE', 'E_CORE_ERROR',
        'E_CORE_WARNING', 'E_COMPILE_ERROR', 'E_COMPILE_WARNING',
        'E_USER_ERROR', 'E_USER_WARNING', 'E_USER_NOTICE', 'E_STRICT',
        'E_RECOVERABLE_ERROR', 'E_DEPRECATED', 'E_USER_DEPRECATED', 'E_ALL'
    ];

    foreach ($constants as $constant) {
        if (defined($constant) && ($level & constant($constant))) {
            $levels[] = $constant;
        }
    }

    return implode(' | ', $levels);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error Log Viewer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .log-content {
            background-color: #1e1e1e;
            color: #d4d4d4;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            padding: 1rem;
            border-radius: 5px;
            max-height: 500px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .log-line {
            padding: 2px 0;
            border-bottom: 1px solid #333;
        }
        .log-error { color: #f87957; }
        .log-warning { color: #ffae1f; }
        .log-notice { color: #3688fa; }
        .settings-table td:first-child {
            font-weight: bold;
            width: 30%;
        }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <div id="page-header" class="mb-4">
            <h1><i class="bi bi-bug"></i> Error Log Viewer</h1>
            <p class="text-muted">Development utility for viewing error logs</p>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i>
                <strong>Warning:</strong> This utility should be removed or restricted in production environments.
            </div>
        </div>

        <!-- PHP Configuration Section -->
        <div id="php-config-section" class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0"><i class="bi bi-gear"></i> PHP Error Configuration</h3>
            </div>
            <div class="card-body">
                <table class="table table-striped settings-table">
                    <tbody>
                        <tr>
                            <td>Error Reporting Level</td>
                            <td>
                                <code><?php echo error_reporting(); ?></code>
                                <br>
                                <small class="text-muted"><?php echo getErrorReportingLevel(error_reporting()); ?></small>
                            </td>
                        </tr>
                        <tr>
                            <td>Display Errors</td>
                            <td>
                                <span class="badge bg-<?php echo ini_get('display_errors') ? 'success' : 'secondary'; ?>">
                                    <?php echo ini_get('display_errors') ? 'ON' : 'OFF'; ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td>Log Errors</td>
                            <td>
                                <span class="badge bg-<?php echo ini_get('log_errors') ? 'success' : 'secondary'; ?>">
                                    <?php echo ini_get('log_errors') ? 'ON' : 'OFF'; ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td>Error Log Location</td>
                            <td>
                                <code><?php echo ini_get('error_log') ?: 'Default (php_errors.log)'; ?></code>
                            </td>
                        </tr>
                        <tr>
                            <td>PHP Version</td>
                            <td><code><?php echo PHP_VERSION; ?></code></td>
                        </tr>
                        <tr>
                            <td>Server API</td>
                            <td><code><?php echo php_sapi_name(); ?></code></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Apache Error Log Section -->
        <div id="apache-log-section" class="card mb-4">
            <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                <h3 class="mb-0"><i class="bi bi-file-text"></i> Apache Error Log</h3>
                <a href="?refresh=1#apache-log-section" class="btn btn-light btn-sm">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </a>
            </div>
            <div class="card-body">
                <p><strong>File:</strong> <code><?php echo htmlspecialchars($apacheLogPath); ?></code></p>
                <p><strong>Showing:</strong> Last <?php echo $linesToShow; ?> lines</p>

                <div id="apache-log-content" class="log-content">
                    <?php
                    $apacheLog = tailFile($apacheLogPath, $linesToShow);

                    if (is_array($apacheLog)) {
                        if (count($apacheLog) > 0) {
                            foreach ($apacheLog as $line) {
                                $class = '';
                                if (stripos($line, 'error') !== false) {
                                    $class = 'log-error';
                                } elseif (stripos($line, 'warning') !== false) {
                                    $class = 'log-warning';
                                } elseif (stripos($line, 'notice') !== false) {
                                    $class = 'log-notice';
                                }

                                echo '<div class="log-line ' . $class . '">';
                                echo htmlspecialchars($line);
                                echo '</div>';
                            }
                        } else {
                            echo '<div class="text-muted">Log file is empty</div>';
                        }
                    } else {
                        echo '<div class="text-danger">' . htmlspecialchars($apacheLog) . '</div>';
                    }
                    ?>
                </div>
            </div>
        </div>

        <!-- PHP Error Log Section -->
        <div id="php-log-section" class="card mb-4">
            <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                <h3 class="mb-0"><i class="bi bi-file-text"></i> PHP Error Log</h3>
                <a href="?refresh=1#php-log-section" class="btn btn-dark btn-sm">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </a>
            </div>
            <div class="card-body">
                <?php if ($phpLogPath): ?>
                    <p><strong>File:</strong> <code><?php echo htmlspecialchars($phpLogPath); ?></code></p>
                    <p><strong>Showing:</strong> Last <?php echo $linesToShow; ?> lines</p>

                    <div id="php-log-content" class="log-content">
                        <?php
                        $phpLog = tailFile($phpLogPath, $linesToShow);

                        if (is_array($phpLog)) {
                            if (count($phpLog) > 0) {
                                foreach ($phpLog as $line) {
                                    $class = '';
                                    if (stripos($line, 'fatal') !== false || stripos($line, 'error') !== false) {
                                        $class = 'log-error';
                                    } elseif (stripos($line, 'warning') !== false) {
                                        $class = 'log-warning';
                                    } elseif (stripos($line, 'notice') !== false) {
                                        $class = 'log-notice';
                                    }

                                    echo '<div class="log-line ' . $class . '">';
                                    echo htmlspecialchars($line);
                                    echo '</div>';
                                }
                            } else {
                                echo '<div class="text-muted">Log file is empty</div>';
                            }
                        } else {
                            echo '<div class="text-danger">' . htmlspecialchars($phpLog) . '</div>';
                        }
                        ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        PHP error logging is not configured or using default syslog.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div id="quick-actions" class="card mb-4">
            <div class="card-body">
                <h5><i class="bi bi-lightning"></i> Quick Actions</h5>
                <div class="btn-group" role="group">
                    <a href="?refresh=1" class="btn btn-primary">
                        <i class="bi bi-arrow-clockwise"></i> Refresh All Logs
                    </a>
                    <a href="../html/test-db.php" class="btn btn-secondary">
                        <i class="bi bi-database"></i> Test Database
                    </a>
                    <a href="../html/info.php" class="btn btn-info">
                        <i class="bi bi-info-circle"></i> PHP Info
                    </a>
                </div>
            </div>
        </div>

        <div id="footer-note" class="text-center text-muted mb-4">
            <small>
                <i class="bi bi-shield-exclamation"></i>
                Last refreshed: <?php echo date('Y-m-d H:i:s'); ?>
            </small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
