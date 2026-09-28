<?php
require __DIR__ . '/lib.php';
$j = login_as('owner@example.invalid', 'Test1234Aa');
$t = page_csrf($j);
ok($t !== '', 'signed in as the owner (csrf on app.php)');
$r = req('POST', '/partials/settings/save-user.php', ['jar' => $j, 'headers' => ['HX-Request: true'], 'form' => ['csrf_token' => $t,
    'first_name' => 'Screen', 'last_name' => 'Invite', 'email' => 'screen-' . bin2hex(random_bytes(3)) . '@example.invalid', 'role' => 'user']]);
ok(preg_match('/id="save-user-invite-code-value">([A-Z2-9]{10})</', $r['body']) === 1, 'the staff screen shows the one-time code');
ok(!str_contains($r['body'], 'users-modal-container").innerHTML=""'), 'and keeps the form open');
finish();
