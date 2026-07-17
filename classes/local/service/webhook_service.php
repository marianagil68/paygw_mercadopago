<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace paygw_mercadopago\local\service;

defined('MOODLE_INTERNAL') || die();

use paygw_mercadopago\local\dto\webhook_notification;
use paygw_mercadopago\local\exception\invalid_webhook_exception;
use paygw_mercadopago\local\validation\webhook_signature_validator;

/**
 * Procesa las notificaciones Webhook recibidas desde Mercado Pago.
 */
class webhook_service {

    /**
     * Validador de firmas.
     *
     * @var webhook_signature_validator
     */
    private webhook_signature_validator $signaturevalidator;

    /**
     * Servicio de confirmación de pagos.
     *
     * @var payment_confirmation_interface
     */
    private payment_confirmation_interface $paymentconfirmation;

    /**
     * Constructor.
     *
     * @param webhook_signature_validator $signaturevalidator Validador de firmas.
     * @param payment_confirmation_interface $paymentconfirmation Servicio de confirmación.
     */
    public function __construct(
        webhook_signature_validator $signaturevalidator,
        payment_confirmation_interface $paymentconfirmation
    ) {
        $this->signaturevalidator = $signaturevalidator;
        $this->paymentconfirmation = $paymentconfirmation;
    }

    /**
     * Procesa una notificación Webhook.
     *
     * @param webhook_notification $notification Notificación ya normalizada.
     * @param string $secret Clave secreta configurada para validar la firma.
     * @throws invalid_webhook_exception Si la notificación es inválida.
     */
    public function process(
        webhook_notification $notification,
        string $secret,
        bool $validatesignature = true
    ): void {
        if ($notification->get_topic() !== 'payment') {
            throw new invalid_webhook_exception(
                'El tipo de notificación no es soportado.'
            );
        }

        if ($validatesignature) {
            $this->signaturevalidator->validate(
                $notification,
                $secret
            );
        }

        $this->paymentconfirmation->confirm(
            $notification->get_paymentid()
        );
    }
}