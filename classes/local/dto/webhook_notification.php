<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace paygw_mercadopago\local\dto;

defined('MOODLE_INTERNAL') || die();

/**
 * Representa una notificación Webhook ya normalizada.
 */
class webhook_notification {

    /**
     * Tipo de notificación.
     *
     * @var string
     */
    public string $topic;

    /**
     * Identificador del pago en Mercado Pago.
     *
     * @var string
     */
    public string $paymentid;

    /**
     * Valor del encabezado x-signature.
     *
     * @var string
     */
    public string $signature;

    /**
     * Valor del encabezado x-request-id.
     *
     * @var string
     */
    public string $requestid;

    /**
     * Constructor.
     *
     * @param string $topic Tipo de notificación.
     * @param string $paymentid Identificador del pago.
     * @param string $signature Encabezado x-signature.
     * @param string $requestid Encabezado x-request-id.
     */
    public function __construct(
        string $topic,
        string $paymentid,
        string $signature,
        string $requestid,
        string $secret
    ) {
        $this->topic = trim($topic);
        $this->paymentid = trim($paymentid);
        $this->signature = trim($signature);
        $this->requestid = trim($requestid);
    }
}