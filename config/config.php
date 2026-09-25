<?php
// Retell AI Configuration
// Load from environment if set, otherwise fallback to empty string
// (settings table override handled in helpers/retell.php)
define('RETELL_API_KEY', getenv('RETELL_API_KEY') ?: '');
