<?php
require __DIR__ . '/lib.php';
require_once 'helpers/retell-auth.php';
$pdo = db();
$run = bin2hex(random_bytes(3));
$pdo->exec("DELETE FROM settings WHERE setting_key = 'retell_api_key'");
$pdo->exec("INSERT INTO settings (restaurant_id, setting_key, setting_value) VALUES (1, 'retell_api_key', 'key_A_$run')");
$pdo->exec("INSERT INTO restaurants (name, slug, timezone) VALUES ('Second $run', 'second-$run', 'UTC')");
$r2 = (int)$pdo->lastInsertId();
$pdo->exec("INSERT INTO settings (restaurant_id, setting_key, setting_value) VALUES ($r2, 'retell_api_key', 'key_B_$run')");
$pdo->exec("INSERT INTO restaurants (name, slug, timezone) VALUES ('Third $run', 'third-$run', 'UTC')");
$r3 = (int)$pdo->lastInsertId();

function sign(string $body, string $key, ?int $ms = null): string {
    $ms ??= (int)(microtime(true) * 1000);
    return "v=$ms,d=" . hash_hmac('sha256', $body . $ms, $key);
}
$body = json_encode(['name' => 'check_availability', 'args' => ['restaurant_slug' => 'test-bistro', 'date' => date('Y-m-d', strtotime('+3 days')), 'party_size' => 2], 'call' => ['call_id' => "c$run"]]);
$post = fn($path, $b, $sig = null) => req('POST', $path, ['raw' => $b, 'headers' => array_filter(['Content-Type: application/json', $sig !== null ? "X-Retell-Signature: $sig" : null])]);

echo "S3 Retell signatures\n";
ok($post('/api/retell/check-availability.php', $body)['code'] === 401, 'custom function, unsigned: 401');
ok($post('/api/retell/check-availability.php', $body, 'deadbeef')['code'] === 401, 'custom function, malformed signature: 401');
ok($post('/api/retell/check-availability.php', $body, sign($body, "key_B_$run"))['code'] === 401, "custom function signed with another restaurant's key: 401");
ok($post('/api/retell/check-availability.php', $body, sign($body, "key_A_$run", (int)(microtime(true) * 1000) - 6 * 60 * 1000))['code'] === 401, 'signature older than five minutes: 401');
$r = $post('/api/retell/check-availability.php', $body, sign($body, "key_A_$run"));
ok($r['code'] === 200 && str_contains($r['body'], 'success'), "signed with the restaurant's own key: {$r['code']}");
$lk = json_encode(['args' => ['restaurant_slug' => 'test-bistro', 'phone' => '+15555550100']]);
ok($post('/api/retell/lookup-reservation.php', $lk)['code'] === 401, 'lookup-reservation by phone, unsigned: 401');
$wh = json_encode(['event' => 'call_ended', 'call' => ['call_id' => "c$run", 'agent_id' => 'agent_unknown', 'to_number' => '+15555550101']]);
ok($post('/api/retell/restaurant-webhook.php', $wh)['code'] === 401, 'restaurant webhook, unsigned: 401');
ok($post('/api/retell/webhook-call-begin.php', $wh)['code'] === 401, 'call-begin webhook, unsigned: 401');
ok($post('/api/retell/webhook-call-end.php', $wh)['code'] === 401, 'call-end webhook, unsigned: 401');
$pdo->exec("INSERT INTO restaurant_prompts (restaurant_id, agent_id) VALUES ($r2, 'agent_b$run')");
$wb = json_encode(['event' => 'call_started', 'call' => ['call_id' => "c$run", 'agent_id' => "agent_b$run", 'to_number' => '+15555550101']]);
ok($post('/api/retell/restaurant-webhook.php', $wb, sign($wb, "key_A_$run"))['code'] === 401, "webhook for restaurant B's agent signed with A's key: 401");
ok($post('/api/retell/restaurant-webhook.php', $wb, sign($wb, "key_B_$run"))['code'] === 200, "webhook for B's agent signed with B's key: 200");

echo "S6 no restaurant uses another's Retell key\n";
ok(getRetellApiKey(1) === "key_A_$run" && getRetellApiKey($r2) === "key_B_$run", 'each restaurant gets its own key');
ok(getRetellApiKey($r3) === '', 'a restaurant with no key gets none (not the first restaurant\'s)');
ok(getRetellApiKey() === '', 'no restaurant: only the server-wide key (none here)');
retellUseRestaurant($r2);
ok(getRetellApiKey() === "key_B_$run", 'the Retell API helpers use the chosen restaurant');
$pdo->exec("DELETE FROM restaurant_prompts WHERE agent_id = 'agent_b$run'");
finish();
