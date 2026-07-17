<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

define('NO_DEBUG_DISPLAY', true);

require_once(__DIR__ . '/../../../config.php');

use core_payment\account_gateway;
use paygw_mercadopago\local\dto\webhook_notification;
use paygw_mercadopago\local\exception\invalid_webhook_exception;
use paygw_mercadopago\local\service\payment_confirmation_service;
use paygw_mercadopago\local\service\webhook_service;
use paygw_mercadopago\local\validation\webhook_signature_validator;

header('Content-Type: application/json; charset=utf-8');

/**
 * Devuelve una respuesta JSON y finaliza la ejecución.
 *
 * @param int $status Código de estado HTTP.
 * @param string $result Resultado de la operación.
 * @return never
 */
function paygw_mercadopago_webhook_response(
    int $status,
    string $result
): never {
    http_response_code($status);

    echo json_encode(
        ['status' => $result],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

try {
    $accountid = optional_param(
        'accountid',
        0,
        PARAM_INT
    );

    if ($accountid <= 0) {
        throw new invalid_webhook_exception(
            'No se recibió una cuenta de pago válida.'
        );
    }

    $rawbody = file_get_contents('php://input');

    if ($rawbody === false) {
        $rawbody = '';
    }

    $jsonbody = json_decode(
        $rawbody,
        true
    );

    if (!is_array($jsonbody)) {
        $jsonbody = [];
    }

    $topic = trim(
        (string)(
            $jsonbody['type']
            ?? $jsonbody['topic']
            ?? optional_param('type', '', PARAM_RAW_TRIMMED)
            ?? optional_param('topic', '', PARAM_RAW_TRIMMED)
        )
    );

    $paymentid = trim(
        (string)(
            $jsonbody['data']['id']
            ?? $jsonbody['id']
            ?? optional_param('data.id', '', PARAM_RAW_TRIMMED)
            ?? optional_param('id', '', PARAM_RAW_TRIMMED)
        )
    );

    $signature = trim(
        (string)($_SERVER['HTTP_X_SIGNATURE'] ?? '')
    );

    $requestid = trim(
        (string)($_SERVER['HTTP_X_REQUEST_ID'] ?? '')
    );

    if (
        $topic === ''
        || $paymentid === ''
        || $signature === ''
        || $requestid === ''
    ) {
        throw new invalid_webhook_exception(
            'La notificación Webhook está incompleta.'
        );
    }

    $accountgateway = account_gateway::get_record(
        [
            'accountid' => $accountid,
            'gateway' => 'mercadopago',
        ]
    );

    if (!$accountgateway) {
        throw new invalid_webhook_exception(
            'No se encontró la configuración de la pasarela.'
        );
    }

    $gatewayconfig = $accountgateway->get_configuration();

    $webhooksecret = trim(
        (string)($gatewayconfig['webhooksecret'] ?? '')
    );

    if ($webhooksecret === '') {
        throw new invalid_webhook_exception(
            'La clave secreta del Webhook no está configurada.'
        );
    }

    $notification = new webhook_notification(
        $topic,
        $paymentid,
        $signature,
        $requestid
    );

    $signaturevalidator = new webhook_signature_validator();

    $paymentconfirmation = new payment_confirmation_service();

    $webhookservice = new webhook_service(
        $signaturevalidator,
        $paymentconfirmation
    );

    $webhookservice->process(
        $notification,
        $webhooksecret
    );

    paygw_mercadopago_webhook_response(
        200,
        'ok'
    );
} catch (invalid_webhook_exception $exception) {
    debugging(
        $exception->getMessage(),
        DEBUG_DEVELOPER
    );

    paygw_mercadopago_webhook_response(
        400,
        'invalid_notification'
    );
} catch (\Throwable $exception) {
    debugging(
        $exception->getMessage(),
        DEBUG_DEVELOPER
    );

    paygw_mercadopago_webhook_response(
        500,
        'internal_error'
    );
}