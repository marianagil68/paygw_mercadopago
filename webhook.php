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
use paygw_mercadopago\local\client\mercadopago_client;
use paygw_mercadopago\local\dto\webhook_notification;
use paygw_mercadopago\local\exception\invalid_webhook_exception;
use paygw_mercadopago\local\repository\transaction_repository;
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
        (string)($jsonbody['type'] ?? '')
    );

    if ($topic === '') {
        $topic = trim(
            (string)($jsonbody['topic'] ?? '')
        );
    }

    if ($topic === '') {
        $topic = trim(
            (string)($_GET['type'] ?? '')
        );
    }

    if ($topic === '') {
        $topic = trim(
            (string)($_GET['topic'] ?? '')
        );
    }

    if ($topic === '') {
        throw new invalid_webhook_exception(
            'No se recibió el tipo de notificación.'
        );
    }

    /*
     * Esta integración procesa únicamente notificaciones de pagos.
     * Mercado Pago también puede enviar notificaciones legacy de
     * merchant_order, que se reconocen pero no se procesan.
     */
    if ($topic !== 'payment') {
        paygw_mercadopago_webhook_response(
            200,
            'ignored'
        );
    }

    /*
    * Las notificaciones legacy IPN utilizan id y topic en la URL.
    * Esta integración procesa únicamente Webhooks modernos con data.id.
    */
    if (!array_key_exists('data_id', $_GET)) {
        paygw_mercadopago_webhook_response(
            200,
            'ignored'
        );
    }


    /*
     * Mercado Pago construye la firma con data.id recibido en la URL.
     * PHP convierte el nombre data.id en data_id dentro de $_GET.
     */
    $paymentid = trim(
        (string)($_GET['data_id'] ?? '')
    );

    if ($paymentid === '') {
        throw new invalid_webhook_exception(
            'No se recibió el identificador del pago.'
        );
    }

    /*
     * Se conserva el cuerpo como alternativa para compatibilidad con
     * notificaciones que no incluyan data.id en la URL.
     */
    if ($paymentid === '') {
        $paymentid = trim(
            (string)($jsonbody['data']['id'] ?? '')
        );
    }

    if ($paymentid === '') {
        throw new invalid_webhook_exception(
            'No se recibió el identificador del pago.'
        );
    }

    $signature = trim(
        (string)($_SERVER['HTTP_X_SIGNATURE'] ?? '')
    );

    $requestid = trim(
        (string)($_SERVER['HTTP_X_REQUEST_ID'] ?? '')
    );

    if ($signature === '' || $requestid === '') {
        throw new invalid_webhook_exception(
            'La notificación Webhook está incompleta.'
        );
    }

    $accountgateway = account_gateway::get_record([
        'accountid' => $accountid,
        'gateway' => 'mercadopago',
    ]);

    if (!$accountgateway) {
        throw new invalid_webhook_exception(
            'No se encontró la configuración de la pasarela.'
        );
    }

    $gatewayconfig = $accountgateway->get_configuration();

    $webhooksecret = trim(
        (string)($gatewayconfig['webhooksecret'] ?? '')
    );

    $environment = trim(
        (string)($gatewayconfig['environment'] ?? 'production')
    );

    $validatesignature = $environment === 'production';


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

    $clientconfig = (object)$gatewayconfig;

    $client = new mercadopago_client(
        $clientconfig
    );

    $signaturevalidator = new webhook_signature_validator();
    $repository = new transaction_repository();

    $paymentconfirmation = new payment_confirmation_service(
        $client,
        $repository
    );

    $webhookservice = new webhook_service(
        $signaturevalidator,
        $paymentconfirmation
    );

    $webhookservice->process(
        $notification,
        $webhooksecret,
        $validatesignature
    );

    paygw_mercadopago_webhook_response(
        200,
        'ok'
    );
} catch (invalid_webhook_exception $exception) {
    error_log(
        'Mercado Pago webhook inválido: '
        . $exception->getMessage()
    );

    paygw_mercadopago_webhook_response(
        400,
        'invalid_notification'
    );
} catch (\Throwable $exception) {
    error_log(
        'Mercado Pago webhook error: '
        . get_class($exception)
        . ' - '
        . $exception->getMessage()
        . ' en '
        . $exception->getFile()
        . ':'
        . $exception->getLine()
    );

    paygw_mercadopago_webhook_response(
        500,
        'internal_error'
    );
}