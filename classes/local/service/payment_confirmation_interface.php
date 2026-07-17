<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace paygw_mercadopago\local\service;

defined('MOODLE_INTERNAL') || die();

/**
 * Define el contrato para confirmar pagos informados por Mercado Pago.
 */
interface payment_confirmation_interface {

    /**
     * Confirma un pago utilizando su identificador de Mercado Pago.
     *
     * @param string $paymentid Identificador del pago en Mercado Pago.
     */
    public function confirm(string $paymentid): void;
}