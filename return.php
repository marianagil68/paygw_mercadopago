<?php
// Este archivo forma parte de Moodle - http://moodle.org/
//
// Moodle es software libre: puede redistribuirlo y/o modificarlo
// bajo los términos de la Licencia Pública General GNU publicada por
// la Free Software Foundation, ya sea la versión 3 de la licencia o
// (a su elección) cualquier versión posterior.
//
// Moodle se distribuye con la esperanza de que sea útil,
// pero SIN NINGUNA GARANTÍA, ni siquiera la garantía implícita de
// COMERCIABILIDAD o IDONEIDAD PARA UN PROPÓSITO PARTICULAR.
//
// @package    paygw_mercadopago
// @copyright  2026 Mariana Gil
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 o posterior

require_once(__DIR__ . '/../../../config.php');

require_login();

$externalreference = optional_param(
    'externalreference',
    '',
    PARAM_ALPHANUMEXT
);

$status = optional_param(
    'status',
    '',
    PARAM_ALPHANUMEXT
);

$collectionstatus = optional_param(
    'collection_status',
    '',
    PARAM_ALPHANUMEXT
);

$paymentstatus = $status !== '' ? $status : $collectionstatus;

if ($paymentstatus === 'approved') {
    redirect(
        new moodle_url('/my/'),
        get_string('paymentapproved', 'paygw_mercadopago'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

if ($paymentstatus === 'pending' || $paymentstatus === 'in_process') {
    redirect(
        new moodle_url('/my/'),
        get_string('paymentpending', 'paygw_mercadopago'),
        null,
        \core\output\notification::NOTIFY_INFO
    );
}

redirect(
    new moodle_url('/my/'),
    get_string('paymentnotcompleted', 'paygw_mercadopago'),
    null,
    \core\output\notification::NOTIFY_WARNING
);