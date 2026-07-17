<?php
declare(strict_types=1);

namespace paygw_mercadopago\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use core_payment\helper;
use paygw_mercadopago\local\client\mercadopago_client;
use paygw_mercadopago\local\repository\transaction_repository;
use paygw_mercadopago\local\service\payment_service;

/**
 * Creates a Mercado Pago Checkout Pro preference.
 *
 * @package paygw_mercadopago
 */
class create_preference extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'component' => new external_value(
                PARAM_COMPONENT,
                'Component'
            ),
            'paymentarea' => new external_value(
                PARAM_AREA,
                'Payment area'
            ),
            'itemid' => new external_value(
                PARAM_INT,
                'Item ID'
            ),
            'description' => new external_value(
            PARAM_TEXT,
            'Payment description'
            )
        ]);
    }

    public static function execute(
        string $component,
        string $paymentarea,
        int $itemid,
        string $description
    ): array {
        global $USER;

        $params = self::validate_parameters(
            self::execute_parameters(),
            [
                'component' => $component,
                'paymentarea' => $paymentarea,
                'itemid' => $itemid,
                'description' => $description,                
            ]
        );

        $context = \context_system::instance();
        self::validate_context($context);

        require_login();
        require_capability(
            'paygw/mercadopago:use',
            $context
        );

        $payable = helper::get_payable(
            $params['component'],
            $params['paymentarea'],
            $params['itemid']
        );

        $config = (object) helper::get_gateway_configuration(
            $params['component'],
            $params['paymentarea'],
            $params['itemid'],
            'mercadopago'
        );

        $currency = $payable->get_currency();

        $surcharge = helper::get_gateway_surcharge(
            'mercadopago'
        );

        $amount = helper::get_rounded_cost(
            $payable->get_amount(),
            $currency,
            $surcharge
        );

        $repository = new transaction_repository();
        $client = new mercadopago_client($config);
        $service = new payment_service(
            $repository,
            $client,
            $description
        );

        $result = $service->start_payment(
            $payable->get_account_id(),
            (int) $USER->id,
            $params['component'],
            $params['paymentarea'],
            $params['itemid'],
            $amount,
            $currency,
            $params['description']
        );

        return [
            'transactionid' => $result['transactionid'],
            'externalreference' => $result['externalreference'],
            'preferenceid' => $result['preferenceid'],
            'initpoint' => $result['initpoint'],
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'transactionid' => new external_value(
                PARAM_INT,
                'Local transaction ID'
            ),
            'externalreference' => new external_value(
                PARAM_TEXT,
                'External reference'
            ),
            'preferenceid' => new external_value(
                PARAM_TEXT,
                'Mercado Pago preference ID'
            ),
            'initpoint' => new external_value(
                PARAM_URL,
                'Checkout Pro URL'
            ),
        ]);
    }
}