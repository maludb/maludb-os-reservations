<?php
/**
 * PHP Information Page
 *
 * Displays complete PHP configuration using phpinfo()
 *
 * WARNING: Remove this file in production environments
 * This file exposes sensitive server configuration information
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP Information</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .warning-banner {
            background-color: #f87957;
            color: white;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
        }
        .info-container {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div id="warning-banner" class="warning-banner">
        ⚠️ WARNING: Remove this file in production environments - It exposes sensitive server information
    </div>

    <div id="phpinfo-container" class="info-container">
        <?php
        // Display PHP information
        phpinfo();
        ?>
    </div>

    <div id="footer-links" style="text-align: center; margin-top: 20px;">
        <a href="test-db.php" style="margin: 0 10px;">Database Test</a> |
        <a href="../utils/error-log-viewer.php" style="margin: 0 10px;">Error Logs</a> |
        <a href="index.php" style="margin: 0 10px;">Home</a>
    </div>
</body>
</html>
