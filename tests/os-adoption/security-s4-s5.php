<?php
require __DIR__ . '/lib.php';
require_once 'helpers/twilio.php';
$pdo = db();
$pdo->exec("DELETE FROM settings WHERE restaurant_id = 1 AND setting_key IN ('sms_from_number','sms_api_secret','sms_api_key','openai_api_key')");
$pdo->exec("INSERT INTO settings (restaurant_id, setting_key, setting_value) VALUES (1,'sms_from_number','+15555550199'),(1,'sms_api_secret','tok_A')");
$params = ['From' => '+15555550100', 'To' => '+15555550199', 'Body' => 'Table for two tonight?', 'MessageSid' => 'SMproof'];
$url = BASE . '/api/sms/webhook.php';
$sig = function (string $tok) use ($params, $url) { $p = $params; ksort($p); $d = $url; foreach ($p as $k => $v) $d .= $k . $v; return base64_encode(hash_hmac('sha1', $d, $tok, true)); };
$logBefore = (int)$pdo->query("SELECT COUNT(*) FROM sms_message_log")->fetchColumn();

echo "S4 Twilio SMS webhook\n";
ok(req('POST', '/api/sms/webhook.php', ['form' => $params])['code'] === 403, 'unsigned: 403');
ok(req('POST', '/api/sms/webhook.php', ['form' => $params, 'headers' => ['X-Twilio-Signature: ' . $sig('tok_other')]])['code'] === 403, "signed with another account's token: 403");
ok((int)$pdo->query("SELECT COUNT(*) FROM sms_message_log")->fetchColumn() === $logBefore, 'nothing logged or answered for the refused requests');
$r = req('POST', '/api/sms/webhook.php', ['form' => $params, 'headers' => ['X-Twilio-Signature: ' . $sig('tok_A')]]);
ok($r['code'] === 200, "signed with the restaurant's own token: accepted ({$r['code']})");

echo "S5 inbound email webhook\n";
$mail = ['from' => 'Guest <guest@example.invalid>', 'to' => 'nobody@example.invalid', 'subject' => 'Hi', 'text' => 'Hello'];
ok(req('POST', '/api/email/webhook.php', ['form' => $mail])['code'] === 403, 'no key: 403');
ok(req('POST', '/api/email/webhook.php?key=wrong', ['form' => $mail])['code'] === 403, 'wrong key: 403');
$r = req('POST', '/api/email/webhook.php?key=proof-email-secret', ['form' => $mail]);
ok($r['code'] === 200, "right key: accepted ({$r['code']}: " . substr($r['body'], 0, 60) . ')');
finish();
