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

use core_payment\helper;
use paygw_mercadopago\local\client\mercadopago_client;
use paygw_mercadopago\local\repository\transaction_repository;

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
     * Repositorio de transacciones.
     *
     * @var transaction_repository
     */
    private transaction_repository $repository;

    /**
     * Constructor.
     *
     * @param mercadopago_client $client Cliente de Mercado Pago.
     * @param transaction_repository $repository Repositorio de transacciones.
     */
    public function __construct(
        mercadopago_client $client,
        transaction_repository $repository
    ) {
        $this->client = $client;
        $this->repository = $repository;
    }

    /**
     * Confirma un pago utilizando su identificador de Mercado Pago.
     *
     * @param string $paymentid Identificador del pago en Mercado Pago.
     */
    public function confirm(string $paymentid): void {
        global $DB;

        $paymentid = trim($paymentid);

        if ($paymentid === '') {
            throw new \InvalidArgumentException(
                'El identificador del pago de Mercado Pago es obligatorio.'
            );
        }

        $payment = $this->client->get_payment($paymentid);

        $confirmedpaymentid = trim(
            (string)($payment['paymentid'] ?? '')
        );

        $externalreference = trim(
            (string)($payment['externalreference'] ?? '')
        );

        $externalstatus = strtolower(
            trim((string)($payment['status'] ?? ''))
        );

        $statusdetail = trim(
            (string)($payment['statusdetail'] ?? '')
        );

        if ($confirmedpaymentid === '') {
            throw new \UnexpectedValueException(
                'Mercado Pago no devolvió el identificador del pago.'
            );
        }

        if ($confirmedpaymentid !== $paymentid) {
            throw new \UnexpectedValueException(
                'El identificador devuelto por Mercado Pago no coincide con el solicitado.'
            );
        }

        if ($externalreference === '') {
            throw new \UnexpectedValueException(
                'Mercado Pago no devolvió la referencia externa del pago.'
            );
        }

        if ($externalstatus === '') {
            throw new \UnexpectedValueException(
                'Mercado Pago no devolvió el estado del pago.'
            );
        }

        $transaction = $this->repository->find_by_external_reference(
            $externalreference
        );

        if ($transaction === null) {
            throw new \UnexpectedValueException(
                'No existe una transacción local para la referencia externa recibida.'
            );
        }

        $this->repository->increment_attempts((int)$transaction->id);

        $paymenttransaction = $this->repository->find_by_payment_id(
            $confirmedpaymentid
        );

        if (
            $paymenttransaction !== null
            && (int)$paymenttransaction->id !== (int)$transaction->id
        ) {
            throw new \UnexpectedValueException(
                'El pago de Mercado Pago ya está asociado a otra transacción.'
            );
        }

        $storedpaymentid = trim(
            (string)($transaction->paymentid ?? '')
        );

        if (
            $storedpaymentid !== ''
            && $storedpaymentid !== $confirmedpaymentid
        ) {
            throw new \UnexpectedValueException(
                'La transacción local ya tiene asociado otro pago de Mercado Pago.'
            );
        }

        if ($storedpaymentid === '') {
            $this->repository->save_payment_reference(
                (int)$transaction->id,
                $confirmedpaymentid
            );
        }

        $internalstatus = $this->map_internal_status(
            $externalstatus
        );

        $timeapproved = null;

        if ($externalstatus === 'approved') {
            $timeapproved = $this->parse_approval_time(
                $payment['dateapproved'] ?? null
            );
        }

        $this->repository->update_status(
            (int)$transaction->id,
            $internalstatus,
            $externalstatus,
            $timeapproved
        );

        if ($externalstatus !== 'approved') {
            return;
        }

        $expectedamount = (float)$transaction->amount;
        $receivedamount = (float)($payment['transactionamount'] ?? 0);

        if (abs($expectedamount - $receivedamount) > 0.01) {
            throw new \UnexpectedValueException(
                'El importe confirmado por Mercado Pago no coincide con el importe esperado.'
            );
        }

        $expectedcurrency = strtoupper(
            trim((string)$transaction->currency)
        );

        $receivedcurrency = strtoupper(
            trim((string)($payment['currencyid'] ?? ''))
        );

        if ($expectedcurrency !== $receivedcurrency) {
            throw new \UnexpectedValueException(
                'La moneda confirmada por Mercado Pago no coincide con la moneda esperada.'
            );
        }

        if ((int)$transaction->delivered === 1) {
            return;
        }

        $dbtransaction = $DB->start_delegated_transaction();

        try {
            $moodlepaymentid = helper::save_payment(
                (int)$transaction->accountid,
                (string)$transaction->component,
                (string)$transaction->paymentarea,
                (int)$transaction->itemid,
                (int)$transaction->userid,
                $receivedamount,
                $receivedcurrency,
                'mercadopago'
            );

            $delivered = helper::deliver_order(
                (string)$transaction->component,
                (string)$transaction->paymentarea,
                (int)$transaction->itemid,
                $moodlepaymentid,
                (int)$transaction->userid
            );

            if (!$delivered) {
                throw new \RuntimeException(
                    'Moodle no pudo entregar el pedido asociado al pago.'
                );
            }

            $this->repository->mark_as_delivered(
                (int)$transaction->id
            );

            $dbtransaction->allow_commit();
        } catch (\Throwable $exception) {
            $dbtransaction->rollback($exception);
        }
    }

    /**
     * Convierte el estado de Mercado Pago al estado interno del plugin.
     *
     * @param string $externalstatus Estado informado por Mercado Pago.
     * @return string Estado interno.
     */
    private function map_internal_status(string $externalstatus): string {
        return match ($externalstatus) {
            'approved' => 'approved',
            'rejected' => 'rejected',
            'cancelled' => 'cancelled',
            'refunded', 'charged_back' => 'refunded',
            default => 'pending',
        };
    }

    /**
     * Convierte la fecha de aprobación de Mercado Pago a una marca de tiempo.
     *
     * @param mixed $dateapproved Fecha informada por Mercado Pago.
     * @return int|null Marca de tiempo o null cuando no está disponible.
     */
    private function parse_approval_time(mixed $dateapproved): ?int {
        if (!is_string($dateapproved) || trim($dateapproved) === '') {
            return null;
        }

        $timestamp = strtotime($dateapproved);

        return $timestamp === false ? null : $timestamp;
    }
}