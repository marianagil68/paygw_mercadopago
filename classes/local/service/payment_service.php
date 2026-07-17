<?php
declare(strict_types=1);

namespace paygw_mercadopago\local\service;

defined('MOODLE_INTERNAL') || die();

use paygw_mercadopago\local\client\mercadopago_client;
use paygw_mercadopago\local\repository\transaction_repository;

/**
 * Service responsible for starting Mercado Pago payment operations.
 *
 * @package paygw_mercadopago
 */
class payment_service {
    private transaction_repository $repository;
    private mercadopago_client $client;

    /**
     * Constructor.
     *
     * @param transaction_repository $repository Transaction repository.
     * @param mercadopago_client $client Mercado Pago API client.
     */
    public function __construct(
        transaction_repository $repository,
        mercadopago_client $client
    ) {
        $this->repository = $repository;
        $this->client = $client;
    }

    /**
     * Starts a payment operation.
     *
     * @param int $accountid Moodle payment account ID.
     * @param int $userid Moodle user ID.
     * @param string $component Component requesting the payment.
     * @param string $paymentarea Payment area.
     * @param int $itemid Item being purchased.
     * @param float $amount Payment amount.
     * @param string $currency ISO currency code.
     * @return array Payment initialization data.
     */
    public function start_payment(
        int $accountid,
        int $userid,
        string $component,
        string $paymentarea,
        int $itemid,
        float $amount,
        string $currency,
        string $description
    ): array {
        $this->validate_payment_data(
            $accountid,
            $userid,
            $component,
            $paymentarea,
            $itemid,
            $amount,
            $currency
        );

        $component = trim($component);
        $paymentarea = trim($paymentarea);
        $currency = strtoupper(trim($currency));
        $externalreference = $this->generate_uuid();

        $transaction = new \stdClass();
        $transaction->userid = $userid;
        $transaction->accountid = $accountid;
        $transaction->component = $component;
        $transaction->paymentarea = $paymentarea;
        $transaction->itemid = $itemid;
        $transaction->amount = $amount;
        $transaction->currency = $currency;
        $transaction->externalreference = $externalreference;

        $transactionid = $this->repository->create($transaction);

        try {
            $preference = $this->client->create_preference([
                'accountid' => $accountid,
                'externalreference' => $externalreference,
                'amount' => $amount,
                'currency' => $currency,
                'itemid' => $itemid,
                'component' => $component,
                'paymentarea' => $paymentarea,
                'description' => trim($description),
            ]);

            $this->validate_preference_response($preference);

            $preferenceid = trim((string) $preference['preferenceid']);
            $initpoint = trim((string) $preference['initpoint']);

            $this->repository->save_preference($transactionid, $preferenceid);
            $this->repository->update_status($transactionid, 'pending');

            return [
                'transactionid' => $transactionid,
                'externalreference' => $externalreference,
                'preferenceid' => $preferenceid,
                'initpoint' => $initpoint,
            ];
        } catch (\Throwable $exception) {
            $this->repository->register_error(
                $transactionid,
                $exception->getMessage()
            );
            throw $exception;
        }
    }

    /**
     * Validates the payment input data.
     *
     * @param int $accountid Moodle payment account ID.
     * @param int $userid Moodle user ID.
     * @param string $component Component requesting the payment.
     * @param string $paymentarea Payment area.
     * @param int $itemid Item being purchased.
     * @param float $amount Payment amount.
     * @param string $currency ISO currency code.
     */
    private function validate_payment_data(
        int $accountid,
        int $userid,
        string $component,
        string $paymentarea,
        int $itemid,
        float $amount,
        string $currency
    ): void {
        if ($accountid <= 0) {
            throw new \InvalidArgumentException('Invalid payment account ID.');
        }

        if ($userid <= 0) {
            throw new \InvalidArgumentException('Invalid user ID.');
        }

        if (trim($component) === '') {
            throw new \InvalidArgumentException('Component is required.');
        }

        if (trim($paymentarea) === '') {
            throw new \InvalidArgumentException('Payment area is required.');
        }

        if ($itemid <= 0) {
            throw new \InvalidArgumentException('Invalid item ID.');
        }

        if (!is_finite($amount) || $amount <= 0) {
            throw new \InvalidArgumentException('Payment amount must be greater than zero.');
        }

        $currency = strtoupper(trim($currency));

        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new \InvalidArgumentException('Invalid currency code.');
        }
    }

    /**
     * Validates the normalized preference response.
     *
     * @param array $preference Preference returned by Mercado Pago client.
     */
    private function validate_preference_response(array $preference): void {
        if (
            !isset($preference['preferenceid']) ||
            trim((string) $preference['preferenceid']) === ''
        ) {
            throw new \UnexpectedValueException(
                'Mercado Pago did not return a preference ID.'
            );
        }

        if (
            !isset($preference['initpoint']) ||
            trim((string) $preference['initpoint']) === ''
        ) {
            throw new \UnexpectedValueException(
                'Mercado Pago did not return an init point.'
            );
        }
    }

    /**
     * Generates a UUID version 4.
     *
     * @return string UUID.
     */
    private function generate_uuid(): string {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return sprintf(
            '%s-%s-%s-%s-%s',
            bin2hex(substr($bytes, 0, 4)),
            bin2hex(substr($bytes, 4, 2)),
            bin2hex(substr($bytes, 6, 2)),
            bin2hex(substr($bytes, 8, 2)),
            bin2hex(substr($bytes, 10, 6))
        );
    }
}