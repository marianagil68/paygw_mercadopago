<?php
declare(strict_types=1);

defined('MOODLE_INTERNAL') || die();

$functions = [
    'paygw_mercadopago_create_preference' => [
        'classname' => 'paygw_mercadopago\external\create_preference',
        'description' => 'Creates a Mercado Pago Checkout Pro preference.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
        'capabilities' => 'paygw/mercadopago:use',
    ],
];