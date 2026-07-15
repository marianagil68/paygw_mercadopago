<?php
declare(strict_types=1);

require_once(dirname(__DIR__, 4) . '/config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

use paygw_mercadopago\local\repository\transaction_repository;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/payment/gateway/mercadopago/test_repository.php'));
$PAGE->set_title('Prueba del repositorio Mercado Pago');
$PAGE->set_heading('Prueba del repositorio Mercado Pago');

echo $OUTPUT->header();

$accounts = $DB->get_records(
    'payment_accounts',
    null,
    'id ASC',
    'id',
    0,
    1
);

if (!$accounts) {
    echo $OUTPUT->notification(
        'No existe ninguna cuenta de pago para realizar la prueba.',
        'notifyproblem'
    );
    echo $OUTPUT->footer();
    exit;
}

$account = reset($accounts);
$repository = new transaction_repository();

$random = bin2hex(random_bytes(8));
$externalreference = sprintf(
    '%s-%s-4%s-%s-%s',
    substr($random, 0, 8),
    substr($random, 8, 4),
    substr($random, 12, 3),
    substr(bin2hex(random_bytes(2)), 0, 4),
    substr(bin2hex(random_bytes(6)), 0, 12)
);

$preferenceid = 'PREFERENCE_TEST_' . bin2hex(random_bytes(5));
$paymentid = 'PAYMENT_TEST_' . bin2hex(random_bytes(5));

$transaction = (object) [
    'userid' => $USER->id,
    'accountid' => $account->id,
    'component' => 'paygw_mercadopago',
    'paymentarea' => 'repositorytest',
    'itemid' => 1,
    'amount' => 1000,
    'currency' => 'ARS',
    'externalreference' => $externalreference,
];

$transactionid = null;

try {
    $transactionid = $repository->create($transaction);

    $byid = $repository->find_by_id($transactionid);
    if (!$byid || $byid->internalstatus !== 'created') {
        throw new moodle_exception('Falló find_by_id().');
    }

    $byreference = $repository->find_by_external_reference($externalreference);
    if (!$byreference || (int) $byreference->id !== $transactionid) {
        throw new moodle_exception('Falló find_by_external_reference().');
    }

    $repository->save_preference($transactionid, $preferenceid);

    $bypreference = $repository->find_by_preference_id($preferenceid);
    if (!$bypreference || (int) $bypreference->id !== $transactionid) {
        throw new moodle_exception('Falló find_by_preference_id().');
    }

    $repository->save_payment_reference($transactionid, $paymentid);

    $bypayment = $repository->find_by_payment_id($paymentid);
    if (!$bypayment || (int) $bypayment->id !== $transactionid) {
        throw new moodle_exception('Falló find_by_payment_id().');
    }

    $repository->update_status(
        $transactionid,
        'approved',
        'approved',
        time()
    );

    $approved = $repository->find_by_id($transactionid);
    if (
        !$approved ||
        $approved->internalstatus !== 'approved' ||
        $approved->externalstatus !== 'approved'
    ) {
        throw new moodle_exception('Falló update_status().');
    }

    $repository->increment_attempts($transactionid);

    $attempted = $repository->find_by_id($transactionid);
    if (!$attempted || (int) $attempted->attempts !== 1) {
        throw new moodle_exception('Falló increment_attempts().');
    }

    $repository->register_error($transactionid, 'Error temporal de prueba');

    $witherror = $repository->find_by_id($transactionid);
    if (
        !$witherror ||
        $witherror->internalstatus !== 'error' ||
        $witherror->lasterror !== 'Error temporal de prueba' ||
        (int) $witherror->attempts !== 2
    ) {
        throw new moodle_exception('Falló register_error().');
    }

    $repository->mark_as_delivered($transactionid);

    $delivered = $repository->find_by_id($transactionid);
    if (
        !$delivered ||
        (int) $delivered->delivered !== 1 ||
        $delivered->internalstatus !== 'delivered' ||
        empty($delivered->timedelivered)
    ) {
        throw new moodle_exception('Falló mark_as_delivered().');
    }

    echo $OUTPUT->notification(
        'Todas las operaciones del repositorio funcionaron correctamente.',
        'notifysuccess'
    );
} catch (Throwable $exception) {
    echo $OUTPUT->notification(
        'La prueba falló: ' . s($exception->getMessage()),
        'notifyproblem'
    );
} finally {
    if ($transactionid !== null) {
        $DB->delete_records(
            'paygw_mercadopago_transactions',
            ['id' => $transactionid]
        );
    }
}

echo html_writer::tag(
    'p',
    'La transacción temporal fue eliminada al finalizar la prueba.'
);

echo $OUTPUT->footer();