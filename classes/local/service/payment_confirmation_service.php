<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

declare(strict_types=1);

namespace paygw_mercadopago\local\service;

defined('MOODLE_INTERNAL') || die();

use paygw_mercadopago\local\client\mercadopago_client;

/**
 * Confirma los pagos informados por Mercado Pago.
 */
class payment_confirmation_service implements payment_confirmation_interface {

    /**
     * Cliente de Mercado Pago.
     *
     * @var mercadopago_client
     */
    private mercadopago_client $client;

    /**
     * Constructor.
     *
     * @param mercadopago_client $client Cliente de Mercado Pago.
     */
    public function __construct(
        mercadopago_client $client
    ) {
        $this->client = $client;
    }

    /**
     * Confirma un pago utilizando su identificador de Mercado Pago.
     *
     * @param string $paymentid Identificador del pago en Mercado Pago.
     */
    public function confirm(string $paymentid): void {
        $paymentid = trim($paymentid);

        if ($paymentid === '') {
            throw new \InvalidArgumentException(
                'El identificador del pago de Mercado Pago es obligatorio.'
            );
        }

        $payment = $this->client->get_payment($paymentid);

    }
}