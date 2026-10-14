<?php
/**
 * JoomShopping 5.x payment plugin: USDT (TRC20) via USDT Pay gateway.
 * Copy this folder to components/com_jshopping/payments/pm_usdt
 * Signatures must match PaymentRoot exactly.
 */
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

class pm_usdt extends PaymentRoot
{
    function showAdminFormParams($params)
    {
        $array_params = array('api_url', 'api_key', 'transaction_end_status', 'transaction_pending_status', 'transaction_failed_status');
        foreach ($array_params as $key) {
            if (!isset($params[$key])) $params[$key] = '';
        }
        $orders = JSFactory::getModel('orders');
        include(dirname(__FILE__) . "/adminparamsform.php");
    }

    /**
     * Checkout step3: JoomShopping renders this HTML inline (captured via
     * loadPaymentForm), so we may NOT redirect here. Emit the button that
     * posts the cart to our redirector; the invoice is created in step6.
     */
    function showPaymentForm($params, $pmconfigs)
    {
        // JoomShopping renders this HTML inline inside its own step3 form
        // (payment_form), which submits to step5. No nested form allowed.
        $pm = $this->getPmMethod();
        ?>
        <div style="margin:4px 0;color:#666;font-size:12px;">
            Click "Next" and you will be redirected to the USDT (TRC20)
            payment page with a QR code.
        </div>
        <?php
    }

    /**
     * Checkout step6: order is persisted, customer is redirected to the
     * hosted checkout which renders QR, address and countdown.
     */
    /**
     * HTTP helper. Prefers cURL — shared hosts often disable
     * allow_url_fopen, which silently kills file_get_contents on URLs.
     */
    private function http($method, $url, $apiKey, $body = null)
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => ($method === 'POST'),
                CURLOPT_TIMEOUT        => 25,
                CURLOPT_HTTPHEADER     => array_filter([
                    $apiKey !== '' ? 'X-API-Key: ' . $apiKey : null,
                    $method === 'POST' ? 'Content-Type: application/json' : null,
                ]),
            ]);
            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
            $out = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);
            return ['body' => $out, 'error' => $err !== '' ? $err : null];
        }
        if (!ini_get('allow_url_fopen')) {
            return ['body' => null, 'error' => 'allow_url_fopen disabled and cURL unavailable'];
        }
        $opts = ['http' => ['method' => $method, 'timeout' => 25]];
        $hdr = '';
        if ($apiKey !== '') { $hdr .= "X-API-Key: $apiKey\r\n"; }
        if ($method === 'POST') {
            $hdr .= "Content-Type: application/json\r\n";
            $opts['http']['content'] = $body;
        }
        $opts['http']['header'] = $hdr;
        $out = @file_get_contents($url, false, stream_context_create($opts));
        return ['body' => $out, 'error' => $out === false ? 'file_get_contents failed' : null];
    }

    function showEndForm($pmconfigs, $order)
    {
        $apiUrl = rtrim($pmconfigs['api_url'] ?? '', '/');
        $apiKey = (string)($pmconfigs['api_key'] ?? '');
        if ($apiUrl === '' || $apiKey === '') {
            echo '<div class="alert alert-error">Plugin not configured: Gateway URL and API key are required.</div>';
            return;
        }
        $body = json_encode([
            'amount'         => round((float)$order->order_total, 4),
            'order_id'       => (string)$order->order_id,
            'customer_name'  => trim(($order->f_name ?? '') . ' ' . ($order->l_name ?? '')),
            'customer_email' => (string)($order->email ?? ''),
            'return_url'     => Uri::root() . 'index.php?option=com_jshopping&controller=checkout&task=step7&js_paymentclass=pm_usdt',
            'cancel_url'     => Uri::root() . 'index.php?option=com_jshopping&controller=checkout&task=step7&js_paymentclass=pm_usdt&order_id=' . $order->order_id . '&act=cancel',
            // The gateway calls this on blockchain confirmation, even if the
            // buyer has already closed the tab.
            'webhook_url'    => Uri::root() . 'components/com_jshopping/payments/pm_usdt/webhook.php',
        ]);
        // Some front proxies (nginx) drop X-API-Key, so also pass the key in the
        // query string. api/order accepts api_key alongside the header.
        $sep = strpos($apiUrl, '/api/order') === false ? '?' : '';
        $r = $this->http('POST', $apiUrl . '/api/order?api_key=' . urlencode($apiKey), $apiKey, $body);
        $res = json_decode((string)$r['body'], true);
        if (!empty($res['checkout_url'])) {
            echo '<meta http-equiv="refresh" content="0; url=' . htmlspecialchars($res['checkout_url'], ENT_QUOTES) . '">';
            ?>
            <div style="text-align:center;padding:30px;">
                <p>Redirecting to the USDT payment page...</p>
                <a href="<?php echo htmlspecialchars($res['checkout_url'], ENT_QUOTES); ?>"
                   class="btn btn-primary" style="background:#4f46e5;border-color:#4f46e5;">
                    Click here if you are not redirected
                </a>
            </div>
            <?php
            return;
        }
        // Show the real reason so the merchant can fix it.
        $detail = $r['error'];
        if ($detail === null) {
            $detail = is_string($r['body']) && $r['body'] !== ''
                ? 'gateway said: ' . $r['body']
                : 'gateway returned an empty response';
        }
        error_log('pm_usdt createInvoice failed: ' . $detail);
        echo '<div class="alert alert-error">USDT gateway unavailable: '
             . htmlspecialchars($detail, ENT_QUOTES)
             . '</div>';
    }

    /**
     * Step7 return. JoomShopping reads order_id from here; without it the
     * checkout dies with "#001 - Error payment method file" / "Ошибка Платежа".
     */
    function getUrlParams($pmconfigs)
    {
        $input = Factory::getApplication()->input;
        return [
            'order_id'         => $input->getInt('order_id'),
            'hash'             => '',
            'checkHash'        => 0,
            'checkReturnParams' => 0,
        ];
    }

    /**
     *Step7 cancel: JoomShopping calls cancelPayOrder() which loads the order,
     * maps the cancel/failed status and redirects to step5 (cart). We must NOT
     * leave the gateway transaction dangling - mark it cancelled right away.
     */
    function beforeCancel($pmconfigs, $order, $payment)
    {
        $orderId = (string)$order->order_id;
        $apiUrl = rtrim($pmconfigs['api_url'] ?? '', '/');
        $apiKey = (string)($pmconfigs['api_key'] ?? '');
        if ($apiUrl === '' || $apiKey === '' || $orderId === '') {
            return;
        }
        $url = $apiUrl . '/api/cancel?order_id=' . urlencode($orderId) . '&api_key=' . urlencode($apiKey);
        // Fire and forget: the shop response must not depend on the gateway.
        $this->http('POST', $url, $apiKey);
    }

    /**
     * Must return [rescode, restext, transaction, transactiondata].
     * JoomShopping feeds $rescode into getStatusFromResCode() and writes
     * $transaction/$transactiondata into the order log; returning nulls there
     * triggers "PDO::quote(): Passing null" deprecations under PHP 8.4.
     */
    function checkTransaction($pmconfigs, $order, $act)
    {
        $orderId = (string)$order->order_id;
        $apiUrl = rtrim($pmconfigs['api_url'] ?? '', '/');
        $url = $apiUrl . '/api/status?order_id=' . urlencode($orderId) . '&api_key=' . urlencode((string)($pmconfigs['api_key'] ?? ''));
        $r = $this->http('GET', $url, (string)($pmconfigs['api_key'] ?? ''));
        $st = json_decode((string)$r['body'], true);

        // JoomShopping writes every $transactiondata value through
        // PDO::quote(); nulls trigger "Passing null" deprecations under PHP 8.4.
        $transaction = '';
        $data = [];
        if (is_array($st)) {
            $transaction = json_encode($st);
            foreach ($st as $k => $v) {
                if ($v === null) continue;
                if (is_array($v)) { $v = json_encode($v); }
                $data[(string)$k] = (string)$v;
            }
        }

        if (($st['status'] ?? '') === 'completed') {
            // rescode 1 => paid, mapped to transaction_end_status.
            return [1, 'Payment completed', $transaction, $data];
        }
        if (($st['status'] ?? '') === 'cancelled' || ($st['status'] ?? '') === 'failed') {
            // rescode 3 => transaction_failed_status.
            return [3, 'Payment failed', $transaction, $data];
        }
        // rescode 2 => transaction_pending_status.
        return [2, 'Payment pending', $transaction, $data];
    }

    function nofify($pmconfigs, $order, $act)
    {
        return $this->checkTransaction($pmconfigs, $order, $act);
    }
}