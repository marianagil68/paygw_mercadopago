<?php
declare(strict_types=1);

require_once(__DIR__ . '/../../../../config.php');

use paygw_mercadopago\external\create_preference;

require_login();
require_capability(
    'paygw/mercadopago:use',
    context_system::instance()
);

$component = required_param('component', PARAM_COMPONENT);
$paymentarea = required_param('paymentarea', PARAM_AREA);
$itemid = required_param('itemid', PARAM_INT);

echo '<pre>';

try {
    $result = create_preference::execute(
        $component,
        $paymentarea,
        $itemid
    );

    print_r($result);
} catch (Throwable $exception) {
    echo 'ERROR: ' . $exception->getMessage();
}

echo '</pre>';