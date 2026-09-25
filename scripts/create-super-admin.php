<?php
/**
 * Create the first platform super-admin (or promote an existing user),
 * optionally with a first restaurant they administer.
 *
 *   php scripts/create-super-admin.php --email=owner@example.com --first=Pat --last=Lee \
 *       [--restaurant="Pat's Bistro" --slug=pats-bistro --timezone=America/New_York]
 *
 * The password is asked for interactively (not echoed). For automation pipe it in:
 *   printf '%s\n' "$PASSWORD" | php scripts/create-super-admin.php --email=... --first=... --last=... --password-stdin
 *
 * An existing user with that email is promoted to super-admin (password changed only if given).
 */

require_once __DIR__ . '/../helpers/services/platform.php';

$o = getopt('', ['email:', 'first:', 'last:', 'password-stdin', 'restaurant:', 'slug:', 'timezone:']);
$email = trim($o['email'] ?? '');
if ($email === '') {
    fwrite(STDERR, "Usage: php scripts/create-super-admin.php --email=EMAIL --first=FIRST --last=LAST [--restaurant=NAME --slug=SLUG --timezone=TZ] [--password-stdin]\n");
    exit(2);
}

$stmt = db()->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$existing = $stmt->fetch(PDO::FETCH_ASSOC);

// Password: stdin, or a hidden prompt; optional when promoting an existing user
$password = '';
if (isset($o['password-stdin'])) {
    $password = rtrim((string)fgets(STDIN), "\r\n");
} elseif (function_exists("posix_isatty") && posix_isatty(STDIN)) {
    $prompt = $existing ? 'New password (Enter to keep the current one): ' : 'Password (8+ characters): ';
    echo $prompt;
    shell_exec('stty -echo');
    $password = rtrim((string)fgets(STDIN), "\r\n");
    shell_exec('stty echo');
    echo "\n";
    if ($password !== '') {
        echo 'Repeat password: ';
        shell_exec('stty -echo');
        $again = rtrim((string)fgets(STDIN), "\r\n");
        shell_exec('stty echo');
        echo "\n";
        if ($again !== $password) { fwrite(STDERR, "Passwords do not match.\n"); exit(1); }
    }
}

try {
    $in = [
        'email' => $email,
        'first_name' => $o['first'] ?? ($existing['first_name'] ?? ''),
        'last_name' => $o['last'] ?? ($existing['last_name'] ?? ''),
        'phone' => $existing['phone'] ?? '',
        'password' => $password,
        'role' => 'super-admin',
    ];
    $user = $existing
        ? svc_platform_user_save((int)$existing['id'], $in, (int)$existing['id'])
        : svc_platform_user_save(0, $in, 0);
    echo ($existing ? 'Promoted' : 'Created') . " super-admin {$user['email']} (user id {$user['id']})\n";

    if (!empty($o['restaurant'])) {
        $name = $o['restaurant'];
        $slug = $o['slug'] ?? trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(str_replace(["'", '’'], '', $name))), '-');
        $restaurant = svc_platform_restaurant_save(0, [
            'name' => $name, 'slug' => $slug, 'timezone' => $o['timezone'] ?? 'America/New_York', 'email' => $email,
        ], (int)$user['id']);
        echo "Created restaurant \"{$restaurant['name']}\" (id {$restaurant['id']}, slug {$restaurant['slug']}) with {$email} as admin\n";
    }
} catch (ServiceError $e) {
    fwrite(STDERR, "Error: {$e->getMessage()}\n");
    exit(1);
}
