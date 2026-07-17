<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace paygw_mercadopago\local\validation;

defined('MOODLE_INTERNAL') || die();

use paygw_mercadopago\local\dto\webhook_notification;
use paygw_mercadopago\local\exception\invalid_webhook_exception;

/**
 * Valida la autenticidad de las notificaciones Webhook de Mercado Pago.
 */
class webhook_signature_validator {

    /**
     * Valida la firma del Webhook.
     *
     * @param webhook_notification $notification Notificación recibida.
     * @param string $secret Secreto configurado para el Webhook.
     * @throws invalid_webhook_exception Si la firma no es válida.
     */
    public function validate(
        webhook_notification $notification,
        string $secret
    ): void {
        $signature = trim($notification->get_signature());
        $requestid = trim($notification->get_requestid());
        $paymentid = trim($notification->get_paymentid());
        $secret = trim($secret);

        if ($signature === '') {
            throw new invalid_webhook_exception(
                'No se recibió el encabezado x-signature.'
            );
        }

        if ($requestid === '') {
            throw new invalid_webhook_exception(
                'No se recibió el encabezado x-request-id.'
            );
        }

        if ($paymentid === '') {
            throw new invalid_webhook_exception(
                'No se recibió el identificador del pago.'
            );
        }

        if ($secret === '') {
            throw new invalid_webhook_exception(
                'No se configuró el secreto del Webhook.'
            );
        }

        $signatureparts = $this->parse_signature($signature);
        $timestamp = $signatureparts['ts'];
        $receivedsignature = $signatureparts['v1'];

        $manifest = sprintf(
            'id:%s;request-id:%s;ts:%s;',
            $paymentid,
            $requestid,
            $timestamp
        );

        $calculatedsignature = hash_hmac(
            'sha256',
            $manifest,
            $secret
        );

        if (!hash_equals($calculatedsignature, $receivedsignature)) {
            throw new invalid_webhook_exception(
                'La firma del Webhook no es válida.'
            );
        }
    }

    /**
     * Extrae el timestamp y la firma del encabezado x-signature.
     *
     * @param string $signature Valor del encabezado x-signature.
     * @return array Datos normalizados de la firma.
     * @throws invalid_webhook_exception Si el encabezado no tiene el formato esperado.
     */
    private function parse_signature(string $signature): array {
        $parts = explode(',', $signature);
        $values = [];

        foreach ($parts as $part) {
            $pair = explode('=', trim($part), 2);

            if (count($pair) !== 2) {
                continue;
            }

            $key = trim($pair[0]);
            $value = trim($pair[1]);

            if ($key !== '' && $value !== '') {
                $values[$key] = $value;
            }
        }

        if (
            empty($values['ts']) ||
            empty($values['v1'])
        ) {
            throw new invalid_webhook_exception(
                'El encabezado x-signature no tiene el formato esperado.'
            );
        }

        if (!ctype_digit($values['ts'])) {
            throw new invalid_webhook_exception(
                'El timestamp de la firma no es válido.'
            );
        }

        if (!preg_match('/^[a-f0-9]{64}$/i', $values['v1'])) {
            throw new invalid_webhook_exception(
                'El valor v1 de la firma no es válido.'
            );
        }

        return [
            'ts' => $values['ts'],
            'v1' => strtolower($values['v1']),
        ];
    }
}