<?php

namespace App\Utils\Sslcommerz;

use App\Models\PaymentAttempt;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SSLCommerz (Bangladesh, BDT only). Credentials are static: .env -> config/billing.php.
 *
 *   generate()             : session API  -> the page the customer must be sent to          (called ONCE per payment)
 *   sslPaymentValidation() : "transaction query by session key" -> did the customer really pay?
 */
class Sslcommerzgateway
{
    protected string $mode;          // sandbox | securepay (live)
    protected string $store_id = '';
    protected string $store_pass = '';
    protected string $sslc_submit_url;
    protected string $sslc_validation_url;
    protected bool $verify_ssl;
    public string $error = '';

    public function __construct()
    {
        $this->getCredential();
    }

    public function getCredential(): void
    {
        $live = config('billing.online.sslcommerz.mode') === 'live';
        $cred = config('billing.online.sslcommerz.' . ($live ? 'live' : 'sandbox'), []);

        $this->mode       = $live ? 'securepay' : 'sandbox';
        $this->store_id   = trim((string) ($cred['store_id'] ?? ''));
        $this->store_pass = trim((string) ($cred['store_pass'] ?? ''));
        $this->verify_ssl = (bool) config('billing.online.sslcommerz.verify_ssl');

        $this->sslc_submit_url     = "https://{$this->mode}.sslcommerz.com/gwprocess/v4/api.php";
        $this->sslc_validation_url = "https://{$this->mode}.sslcommerz.com/validator/api/merchantTransIDvalidationAPI.php";
    }

    public function isConfigured(): bool
    {
        return $this->store_id !== '' && $this->store_pass !== '';
    }

    /**
     * Creates the payment session at SSLCommerz.
     *
     * @return array{ok: bool, url?: string, session_key?: string, request?: array, response?: array, error?: string}
     */
    public function generate(PaymentAttempt $attempt): array
    {
        try {
            if (! $this->isConfigured()) {
                return ['ok' => false, 'error' => 'SSLCommerz store id / password are not set in the .env file.'];
            }

            $account = $attempt->account;
            $owner   = $account->owner;
            $phone   = preg_replace('/[^0-9+]/', '', (string) ($account->phone ?: optional($owner)->mobile)) ?: '01700000000';
            $return  = route('ssl_process', [$attempt->unique_id]);       // success, fail and cancel all come back here

            $post = [
                'store_id'     => $this->store_id,
                'store_passwd' => $this->store_pass,
                'total_amount' => number_format((float) $attempt->amount, 2, '.', ''),
                'currency'     => 'BDT',
                'tran_id'      => $attempt->tran_id,
                'success_url'  => $return,
                'fail_url'     => $return,
                'cancel_url'   => $return,

                // the customer (we have no street address: SSLCommerz only needs something here)
                'cus_name'     => optional($owner)->name ?: $account->name,
                'cus_email'    => $account->email,
                'cus_add1'     => 'Dhaka',
                'cus_city'     => 'Dhaka',
                'cus_state'    => 'Dhaka',
                'cus_postcode' => '1000',
                'cus_country'  => 'Bangladesh',
                'cus_phone'    => $phone,

                // what is sold: a software subscription (nothing is shipped)
                'shipping_method'  => 'NO',
                'num_of_item'      => 1,
                'product_name'     => 'Warranty Tracker subscription',
                'product_category' => 'Subscription',
                'product_profile'  => 'non-physical-goods',

                // we get these back: for our own tracing
                'value_a' => (string) $attempt->invoice_id,
                'value_b' => (string) $attempt->account_id,
                'value_c' => $attempt->unique_id,
            ];

            $response = Http::asForm()->connectTimeout(10)->timeout(40)
                ->withOptions(['verify' => $this->verify_ssl])
                ->post($this->sslc_submit_url, $post);

            $sent = Arr::except($post, ['store_passwd']);                    // the password is never stored

            if (! $response->successful()) {
                return ['ok' => false, 'request' => $sent, 'error' => 'Connectivity issue: SSLCommerz answered with HTTP ' . $response->status() . '.'];
            }

            $data = $response->json();
            if (! is_array($data)) {
                return ['ok' => false, 'request' => $sent, 'error' => 'Connectivity issue: SSLCommerz sent an answer that could not be read.'];
            }

            if (($data['status'] ?? '') !== 'SUCCESS') {
                return ['ok' => false, 'request' => $sent, 'response' => $data,
                        'error' => $data['failedreason'] ?? 'SSLCommerz refused the payment (check the store id and password).'];
            }
            if (empty($data['GatewayPageURL']) || empty($data['sessionkey'])) {
                return ['ok' => false, 'request' => $sent, 'response' => $data, 'error' => 'SSLCommerz did not send a payment page.'];
            }

            return ['ok' => true, 'url' => $data['GatewayPageURL'], 'session_key' => $data['sessionkey'], 'request' => $sent, 'response' => $data];
        } catch (\Throwable $e) {
            Log::error('SSLCommerz payment creation failed', ['tran_id' => $attempt->tran_id, 'error' => $e->getMessage()]);
            return ['ok' => false, 'error' => 'Could not reach SSLCommerz: ' . $e->getMessage()];
        }
    }

    /**
     * Asks SSLCommerz (server to server) what happened to the payment of this session.
     * Works without the customer's return: it only needs the session key we got in generate().
     *
     * @return array{flag: bool, state: string, payment_info: array, message: string}
     *         state = success | failed | cancelled | mismatch | pending | unknown
     */
    public function sslPaymentValidation(PaymentAttempt $attempt): array
    {
        $result = ['flag' => false, 'state' => 'unknown', 'payment_info' => [], 'message' => ''];

        if (! $attempt->gateway_session_key) {
            $result['message'] = 'This payment never reached SSLCommerz (there is no session), so there is nothing to verify.';
            return $result;
        }
        if (! $this->isConfigured()) {
            $result['message'] = 'SSLCommerz store id / password are not set in the .env file.';
            return $result;
        }

        try {
            $response = Http::connectTimeout(10)->timeout(40)
                ->withOptions(['verify' => $this->verify_ssl])
                ->get($this->sslc_validation_url, [
                    'sessionkey'   => $attempt->gateway_session_key,
                    'Store_Id'     => $this->store_id,
                    'Store_Passwd' => $this->store_pass,
                    'v'            => 1,
                    'format'       => 'json',
                ]);
        } catch (\Throwable $e) {
            Log::error('SSLCommerz validation failed', ['tran_id' => $attempt->tran_id, 'error' => $e->getMessage()]);
            $result['message'] = 'Could not reach SSLCommerz: ' . $e->getMessage();
            return $result;
        }

        $data = $response->successful() ? $response->json() : null;
        if (! is_array($data)) {
            $result['message'] = 'SSLCommerz answered with HTTP ' . $response->status() . '.';
            return $result;
        }

        $result['payment_info'] = $data;
        $status = strtoupper((string) ($data['status'] ?? ''));

        if (in_array($status, ['VALID', 'VALIDATED'], true)) {
            // the money must be for exactly this payment
            $amountOk = isset($data['amount']) && abs((float) $data['amount'] - (float) $attempt->amount) < 0.01;
            $tranOk   = empty($data['tran_id']) || $data['tran_id'] === $attempt->tran_id;

            if ($amountOk && $tranOk) {
                return ['flag' => true, 'state' => 'success', 'payment_info' => $data, 'message' => 'SSLCommerz says: ' . $status];
            }
            $result['state']   = 'mismatch';
            $result['message'] = 'SSLCommerz says ' . $status . ', but the amount or the transaction id does not match this payment.';
            return $result;
        }

        $result['message'] = 'SSLCommerz says: ' . ($status ?: 'no status');
        $result['state']   = match ($status) {
            'FAILED', 'EXPIRED'      => 'failed',
            'CANCELLED'              => 'cancelled',
            'PENDING', 'UNATTEMPTED' => 'pending',
            default                  => 'unknown',            // for example INVALID_TRANSACTION: the customer never paid
        };

        return $result;
    }
}
