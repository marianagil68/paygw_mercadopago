<?php
declare(strict_types=1);

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'paygw/mercadopago:use' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'user' => CAP_ALLOW,
        ],
    ],
];