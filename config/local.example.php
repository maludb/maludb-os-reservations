<?php
/**
 * Per-server settings. Copy to config/local.php (git-ignored) and fill in:
 *
 *   cp config/local.example.php config/local.php
 *   chmod 640 config/local.php && sudo chgrp www-data config/local.php
 *
 * Any key can instead be set as an environment variable of the same name.
 * Restaurant-level keys (Retell, Twilio, MailerSend, OpenAI) are NOT here: they are
 * entered in the app (Settings → Integrations / Notifications) and live in the database.
 */

return [
    // PostgreSQL connection
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '5432',
    'DB_NAME' => 'zozocal',
    'DB_USER' => 'zozocal',
    'DB_PASS' => '',

    // Public URL of this install, no trailing slash. Used for links given to
    // external services (e.g. the MCP server URL registered with Retell voice agents).
    'APP_URL' => 'https://example.com',

    // Google sign-in (optional). Register <APP_URL>/google-callback.php as a redirect URI.
    'GOOGLE_CLIENT_ID'     => '',
    'GOOGLE_CLIENT_SECRET' => '',

    // Retell account-wide fallback key, used when a restaurant has not saved its own (optional)
    'RETELL_DEFAULT_API_KEY' => '',

    // Only for scripts/launch-agent.php (manual outbound test calls; optional)
    'RETELL_LAUNCH_API_KEY'  => '',
    'RETELL_LAUNCH_AGENT_ID' => '',
];
