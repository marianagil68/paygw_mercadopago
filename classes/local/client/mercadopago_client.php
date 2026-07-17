<?php
declare(strict_types=1);

namespace paygw_mercadopago\local\client;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->libdir . '/filelib.php');

/**
 * Client responsible for communicating with the Mercado Pago API.
 *
 * @package paygw_mercadopago
 */
class mercadopago_client {
    private const API_BASE_URL = 'https://api.mercadopago.com';
    private const ENVIRONMENT_SANDBOX = 'sandbox';
    private const ENVIRONMENT_PRODUCTION = 'production';

    private string $accesstoken;
    private string $environment;
    private \curl $curl;

    /**
     * Constructor.
     *
     * @param \stdClass $gatewayconfig Moodle payment gateway configuration.
     * @param \curl|null $curl Moodle curl instance.
     */
    public function __construct(
        \stdClass $gatewayconfig,
        ?\curl $curl = null
    ) {
        $this->validate_gateway_config($gatewayconfig);

        $this->accesstoken = trim(
            (string) $gatewayconfig->accesstoken
        );

        $this->environment = strtolower(
            trim((string) $gatewayconfig->environment)
        );

        $this->curl = $curl ?? new \curl();
    }

    /**
     * Creates a Mercado Pago Checkout Pro preference.
     *
     * @param array $data Payment data.
     * @return array Normalized preference data.
     */
    public function create_preference(array $data): array {
        $this->validate_preference_data($data);

        $externalreference = trim(
            (string) $data['externalreference']
        );

        $returnurl = new \moodle_url(
            '/payment/gateway/mercadopago/return.php'
           
        );

        $notificationurl = new \moodle_url(
            '/payment/gateway/mercadopago/webhook.php',
            ['accountid' => (int) $data['accountid']]
        );

        $payload = [
            'items' => [
                [
                    'id' => (string) $data['itemid'],
                    'title' => $this->build_item_title($data),
                    'quantity' => 1,
                    'currency_id' => strtoupper(
                        trim((string) $data['currency'])
                    ),
                    'unit_price' => (float) $data['amount'],
                ],
            ],
            'external_reference' => $externalreference,
            'back_urls' => [
                'success' => $returnurl->out(false),
                'pending' => $returnurl->out(false),
                'failure' => $returnurl->out(false),
            ],
            'notification_url' => $notificationurl->out(false),
            'auto_return' => 'approved',
        ];

        $response = $this->request(
            'POST',
            '/checkout/preferences',
            $payload
        );

        $preferenceid = trim(
            (string) ($response['id'] ?? '')
        );

        if ($this->environment === self::ENVIRONMENT_SANDBOX) {
            $initpoint = trim(
                (string) (
                    $response['sandbox_init_point']
                    ?? $response['init_point']
                    ?? ''
                )
            );
        } else {
            $initpoint = trim(
                (string) ($response['init_point'] ?? '')
            );
        }

        if ($preferenceid === '') {
            throw new \UnexpectedValueException(
                'Mercado Pago did not return a preference ID.'
            );
        }

        if ($initpoint === '') {
            throw new \UnexpectedValueException(
                'Mercado Pago did not return an init point.'
            );
        }

        return [
            'preferenceid' => $preferenceid,
            'initpoint' => $initpoint,
        ];
    }

    /**
     * Retrieves a payment from Mercado Pago.
     *
     * @param string $paymentid Mercado Pago payment ID.
     * @return array Normalized payment data.
     */
    public function get_payment(string $paymentid): array {
        $paymentid = trim($paymentid);

        if ($paymentid === '') {
            throw new \InvalidArgumentException(
                'Mercado Pago payment ID is required.'
            );
        }

        $response = $this->request(
            'GET',
            '/v1/payments/' . rawurlencode($paymentid)
        );

        return [
            'paymentid' => trim(
                (string) ($response['id'] ?? '')
            ),
            'status' => trim(
                (string) ($response['status'] ?? '')
            ),
            'statusdetail' => trim(
                (string) ($response['status_detail'] ?? '')
            ),
            'externalreference' => trim(
                (string) ($response['external_reference'] ?? '')
            ),
            'transactionamount' =>
                isset($response['transaction_amount'])
                    ? (float) $response['transaction_amount']
                    : null,
            'currencyid' => trim(
                (string) ($response['currency_id'] ?? '')
            ),
            'dateapproved' =>
                $response['date_approved'] ?? null,
            'payeremail' => trim(
                (string) ($response['payer']['email'] ?? '')
            ),
        ];
    }

    /**
     * Sends an HTTP request to Mercado Pago.
     *
     * @param string $method HTTP method.
     * @param string $endpoint API endpoint.
     * @param array|null $payload Request payload.
     * @return array Decoded response.
     */
    private function request(
        string $method,
        string $endpoint,
        ?array $payload = null
    ): array {
        $url = self::API_BASE_URL . $endpoint;

        $this->curl->setHeader([
            'Authorization: Bearer ' . $this->accesstoken,
            'Content-Type: application/json',
            'Accept: application/json',
        ]);

        try {
            if ($method === 'POST') {
                $body = json_encode(
                    $payload,
                    JSON_THROW_ON_ERROR
                    | JSON_UNESCAPED_SLASHES
                );

                $responsebody = $this->curl->post(
                    $url,
                    $body
                );
            } else if ($method === 'GET') {
                $responsebody = $this->curl->get($url);
            } else {
                throw new \InvalidArgumentException(
                    'Unsupported HTTP method.'
                );
            }
        } catch (\JsonException $exception) {
            throw new \RuntimeException(
                'Unable to encode the Mercado Pago request.',
                0,
                $exception
            );
        }

        $info = $this->curl->get_info();
        $httpcode = (int) ($info['http_code'] ?? 0);

        try {
            $response = json_decode(
                (string) $responsebody,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $exception) {
            throw new \RuntimeException(
                'Mercado Pago returned an invalid JSON response.',
                0,
                $exception
            );
        }

        if (!is_array($response)) {
            throw new \UnexpectedValueException(
                'Mercado Pago returned an invalid response.'
            );
        }

        if ($httpcode < 200 || $httpcode >= 300) {
            throw new \RuntimeException(
                $this->build_api_error_message(
                    $httpcode,
                    $response
                )
            );
        }

        return $response;
    }

    /**
     * Validates the gateway configuration.
     *
     * @param \stdClass $gatewayconfig Gateway configuration.
     */
    private function validate_gateway_config(
        \stdClass $gatewayconfig
    ): void {
        $accesstoken = trim(
            (string) ($gatewayconfig->accesstoken ?? '')
        );

        if ($accesstoken === '') {
            throw new \InvalidArgumentException(
                'Mercado Pago Access Token is required.'
            );
        }

        $environment = strtolower(
            trim((string) ($gatewayconfig->environment ?? ''))
        );

        if (!in_array(
            $environment,
            [
                self::ENVIRONMENT_SANDBOX,
                self::ENVIRONMENT_PRODUCTION,
            ],
            true
        )) {
            throw new \InvalidArgumentException(
                'Invalid Mercado Pago environment.'
            );
        }
    }

    /**
     * Validates the data required to create a preference.
     *
     * @param array $data Payment data.
     */
    private function validate_preference_data(
        array $data
    ): void {
        $requiredfields = [
            'accountid',
            'externalreference',
            'amount',
            'currency',
            'itemid',
            'component',
            'paymentarea',
        ];

        foreach ($requiredfields as $field) {
            if (!array_key_exists($field, $data)) {
                throw new \InvalidArgumentException(
                    'Missing preference field: '
                    . $field
                    . '.'
                );
            }
        }

        if (
            trim((string) $data['externalreference']) === ''
        ) {
            throw new \InvalidArgumentException(
                'External reference is required.'
            );
        }

        if (
            !is_numeric($data['amount'])
            || !is_finite((float) $data['amount'])
            || (float) $data['amount'] <= 0
        ) {
            throw new \InvalidArgumentException(
                'Preference amount must be greater than zero.'
            );
        }

        $currency = strtoupper(
            trim((string) $data['currency'])
        );

        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new \InvalidArgumentException(
                'Invalid preference currency.'
            );
        }

        if ((int) $data['accountid'] <= 0) {
            throw new \InvalidArgumentException(
                'Invalid payment account ID.'
            );
        }
        
        if ((int) $data['itemid'] <= 0) {
            throw new \InvalidArgumentException(
                'Invalid preference item ID.'
            );
        }

        if (trim((string) $data['component']) === '') {
            throw new \InvalidArgumentException(
                'Preference component is required.'
            );
        }

        if (trim((string) $data['paymentarea']) === '') {
            throw new \InvalidArgumentException(
                'Preference payment area is required.'
            );
        }
    }

    /**
     * Builds the preference item title.
     *
     * @param array $data Payment data.
     * @return string Item title.
     */
    private function build_item_title(array $data): string {
        $description = trim((string) ($data['description'] ?? ''));

        if ($description !== '') {
            return $description;
        }

        return sprintf(
            'Inscripción al curso #%d',
            (int) $data['itemid']
        );
    }

    /**
     * Builds an API error message without exposing credentials.
     *
     * @param int $httpcode HTTP status code.
     * @param array $response Mercado Pago response.
     * @return string Error message.
     */
    private function build_api_error_message(
        int $httpcode,
        array $response
    ): string {
        $message = trim(
            (string) (
                $response['message']
                ?? $response['error']
                ?? 'Unknown Mercado Pago API error.'
            )
        );

        return sprintf(
            'Mercado Pago API error. HTTP %d: %s',
            $httpcode,
            $message
        );
    }
}