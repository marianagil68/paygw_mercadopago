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
 * Representa una notificación Webhook recibida desde Mercado Pago.
 */
class webhook_notification {

    /** @var string Tipo de notificación. */
    private string $topic;

    /** @var string Identificador del pago. */
    private string $paymentid;

    /** @var string Encabezado x-signature. */
    private string $signature;

    /** @var string Encabezado x-request-id. */
    private string $requestid;

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
        string $requestid
    ) {
        $this->topic = trim($topic);
        $this->paymentid = trim($paymentid);
        $this->signature = trim($signature);
        $this->requestid = trim($requestid);
    }

    /**
     * Devuelve el tipo de notificación.
     *
     * @return string
     */
    public function get_topic(): string {
        return $this->topic;
    }

    /**
     * Devuelve el identificador del pago.
     *
     * @return string
     */
    public function get_paymentid(): string {
        return $this->paymentid;
    }

    /**
     * Devuelve la firma recibida.
     *
     * @return string
     */
    public function get_signature(): string {
        return $this->signature;
    }

    /**
     * Devuelve el identificador de la solicitud.
     *
     * @return string
     */
    public function get_requestid(): string {
        return $this->requestid;
    }
}