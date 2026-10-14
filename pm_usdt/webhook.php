<?php
/**
 * USDT Pay webhook receiver for JoomShopping.
 * The gateway POSTs here once the blockchain confirms the payment, even if
 * the buyer has already closed the tab. Signature is HMAC-SHA256 with the
 * plugin's api_key.
 */
define('_JEXEC', true);
$root = dirname(__DIR__, 4);
require_once $root . '/configuration.php';

$jconfig = new JConfig();
$dsn = 'mysql:host=' . $jconfig->host . ';dbname=' . $jconfig->db . ';charset=utf8mb4';
$pdo = new PDO($dsn, $jconfig->user, $jconfig->password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT]);

$cfg = $pdo->query("SELECT payment_params FROM {$jconfig->dbprefix}jshopping_payment_method
                    WHERE payment_class='pm_usdt' LIMIT 1")->fetchColumn();
$pmconfigs = (array)json_decode((string)$cfg, true);
$secret = (string)($pmconfigs['api_key'] ?? '');

$body = file_get_contents('php://input');
$sig = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
if (!hash_equals(hash_hmac('sha256', $body, $secret), $sig)) {
    http_response_code(401); echo 'bad signature'; exit;
}

$payload = json_decode($body, true);
if (!is_array($payload) || !in_array($payload['status'] ?? '', ['completed', 'cancelled'], true)) {
    http_response_code(200); echo 'ignored'; exit;
}

$orderId = (int)($payload['order_id'] ?? 0);
if ($orderId <= 0) { http_response_code(400); echo 'no order_id'; exit; }

$status = $payload['status'];
$target = ($status === 'completed')
    ? (int)($pmconfigs['transaction_end_status'] ?? 2)      // paid
    : (int)($pmconfigs['transaction_failed_status'] ?? 3);  // cancelled/failed

$pdo->prepare("UPDATE {$jconfig->dbprefix}jshopping_orders SET order_status=? WHERE order_id=?")
    ->execute([$target, $orderId]);
$pdo->prepare("INSERT INTO {$jconfig->dbprefix}jshopping_order_history
               (order_id, order_status_id, status_date_added, comments)
               VALUES (?, ?, NOW(), ?)")
    ->execute([$orderId, $target, 'USDT payment ' . $status]);

http_response_code(200);
echo 'ok';