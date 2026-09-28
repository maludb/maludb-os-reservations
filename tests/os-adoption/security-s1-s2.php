<?php
require __DIR__ . '/lib.php';
$pdo = db();
$run = bin2hex(random_bytes(3));
$key = api_create_key(1, 1, "proof $run", 1);
$auth = ['headers' => ["Authorization: Bearer $key"]];

echo "S1 invitation code\n";
$email = "invitee-$run@example.invalid";
$r = req('POST', '/api/v1/index.php/users', $auth + ['json' => ['first_name' => 'In', 'last_name' => 'Vitee', 'email' => $email, 'role' => 'user']]);
$d = json_decode($r['body'], true);
$code = $d['data']['user']['invite_code'] ?? $d['user']['invite_code'] ?? '';
ok($r['code'] === 201 || $r['code'] === 200, "users_create answers {$r['code']}");
ok(strlen($code) === 10, "the answer carries a 10-character invite code");
$row = $pdo->query("SELECT invite_code_hash, password_hash FROM users WHERE email = " . $pdo->quote($email))->fetch();
ok($row['invite_code_hash'] === hash('sha256', $code) && $row['password_hash'] === '!INVITED', "only its hash is stored; the user is invited");

$accept = function (string $c) use ($email) {
    $j = jar(); $page = req('GET', '/register.php?invite', ['jar' => $j]);
    return req('POST', '/partials/auth/accept-invite.php', ['jar' => $j, 'form' => ['csrf_token' => csrf_from($page['body']),
        'email' => $email, 'invite_code' => $c, 'password' => 'Proof1234Aa', 'confirm_password' => 'Proof1234Aa']]);
};
$r = $accept('');
ok(str_contains($r['body'], 'invite-error-required'), "no code: refused");
$r = $accept('WRONGCODE2');
ok(str_contains($r['body'], 'invite-error-code'), "wrong code: refused");
ok($pdo->query("SELECT password_hash FROM users WHERE email = " . $pdo->quote($email))->fetchColumn() === '!INVITED', "password untouched after the refusals");
$pdo->exec("UPDATE users SET invite_expires_at = NOW() - INTERVAL '1 minute' WHERE email = " . $pdo->quote($email));
$r = $accept($code);
ok(str_contains($r['body'], 'invite-error-code'), "expired code: refused");
$pdo->exec("UPDATE users SET invite_expires_at = NOW() + INTERVAL '1 day' WHERE email = " . $pdo->quote($email));
$r = $accept(strtolower(substr($code, 0, 5)) . '-' . substr($code, 5));
ok(str_contains($r['body'], 'invite-success') && str_contains($r['headers'], 'HX-Redirect: /app.php'), "right code (any case, with a dash): accepted and signed in");
$row = $pdo->query("SELECT invite_code_hash, password_hash FROM users WHERE email = " . $pdo->quote($email))->fetch();
ok($row['invite_code_hash'] === null && password_verify('Proof1234Aa', $row['password_hash']), "password set, code used up");
$r = $accept($code);
ok(str_contains($r['body'], 'invite-error-already'), "the code cannot be used again");

echo "S2 adding an existing person never changes their password\n";
$pdo->exec("INSERT INTO restaurants (name, slug, timezone) VALUES ('Other $run', 'other-$run', 'UTC')");
$other = (int)$pdo->lastInsertId();
$victim = "victim-$run@example.invalid";
$pdo->prepare("INSERT INTO users (first_name,last_name,email,password_hash) VALUES ('Vic','Tim',?,?)")->execute([$victim, password_hash('Victim1234Aa', PASSWORD_DEFAULT)]);
$vid = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO user_restaurants (user_id, restaurant_id, role) VALUES (?, ?, 'user')")->execute([$vid, $other]);
$r = req('POST', '/api/v1/index.php/users', $auth + ['json' => ['first_name' => 'Evil', 'last_name' => 'Rename', 'email' => $victim, 'role' => 'user', 'password' => 'Taken1234Aa']]);
$row = $pdo->query("SELECT first_name, password_hash FROM users WHERE id = $vid")->fetch();
ok(password_verify('Victim1234Aa', $row['password_hash']), "add-existing with a password: their password is unchanged (answer {$r['code']})");
ok($row['first_name'] === 'Vic', "and their name is unchanged");
$r = req('PATCH', "/api/v1/index.php/users/$vid", $auth + ['json' => ['password' => 'Taken1234Aa']]);
$row = $pdo->query("SELECT password_hash FROM users WHERE id = $vid")->fetch();
ok(password_verify('Victim1234Aa', $row['password_hash']), "edit of someone who also belongs elsewhere: password refused (answer {$r['code']})");
$pdo->prepare("UPDATE users SET role = 'super-admin' WHERE id = ?")->execute([$vid]);
$pdo->prepare("DELETE FROM user_restaurants WHERE user_id = ? AND restaurant_id = ?")->execute([$vid, $other]);
$r = req('PATCH', "/api/v1/index.php/users/$vid", $auth + ['json' => ['password' => 'Taken1234Aa']]);
$row = $pdo->query("SELECT password_hash FROM users WHERE id = $vid")->fetch();
ok(password_verify('Victim1234Aa', $row['password_hash']), "edit of a platform super-admin: password refused (answer {$r['code']})");
$own = "own-$run@example.invalid";
$r = req('POST', '/api/v1/index.php/users', $auth + ['json' => ['first_name' => 'Own', 'last_name' => 'Staff', 'email' => $own, 'role' => 'user', 'password' => 'First1234Aa']]);
$oid = (int)$pdo->query("SELECT id FROM users WHERE email = " . $pdo->quote($own))->fetchColumn();
$r = req('PATCH', "/api/v1/index.php/users/$oid", $auth + ['json' => ['password' => 'Second1234Aa']]);
ok(password_verify('Second1234Aa', $pdo->query("SELECT password_hash FROM users WHERE id = $oid")->fetchColumn()), "edit of this restaurant's own staff: password still settable (answer {$r['code']})");
finish();
