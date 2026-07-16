<?php
declare(strict_types=1);

require_once(dirname(__DIR__, 4) . '/config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

use paygw_mercadopago\local\repository\transaction_repository;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/payment/gateway/mercadopago/dev_test_repository.php'));
$PAGE->set_title('Prueba del repositorio Mercado Pago');
$PAGE->set_heading('Prueba del repositorio Mercado Pago');

echo $OUTPUT->header();

echo html_writer::start_div('card');
echo html_writer::start_div('card-body');

echo html_writer::tag(
    'p',
    'Este script prueba todas las operaciones del repositorio y elimina la transacción temporal al finalizar.'
);

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

    echo html_writer::end_div();
    echo html_writer::end_div();
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
$step = 0;

$showstep = static function (
    string $description,
    callable $test
) use (&$step, $OUTPUT): void {
    $step++;

    echo html_writer::start_div(
        'alert alert-light border mb-3'
    );

    echo html_writer::tag(
        'h5',
        'Paso ' . $step . ': ' . s($description)
    );

    try {
        $test();

        echo $OUTPUT->notification(
            'OK',
            'notifysuccess'
        );
    } catch (Throwable $exception) {
        echo $OUTPUT->notification(
            'ERROR: ' . s($exception->getMessage()),
            'notifyproblem'
        );

        echo html_writer::end_div();

        throw $exception;
    }

    echo html_writer::end_div();
};

try {
    $showstep(
        'Crear una transacción',
        function () use (
            $repository,
            $transaction,
            &$transactionid
        ): void {
            $transactionid = $repository->create($transaction);

            if ($transactionid <= 0) {
                throw new runtime_exception(
                    'No se obtuvo un identificador válido.'
                );
            }
        }
    );

    $showstep(
        'Buscar la transacción por ID',
        function () use (
            $repository,
            &$transactionid
        ): void {
            $record = $repository->find_by_id($transactionid);

            if (
                !$record ||
                (int) $record->id !== $transactionid ||
                $record->internalstatus !== 'created'
            ) {
                throw new runtime_exception(
                    'La transacción no fue recuperada correctamente.'
                );
            }
        }
    );

    $showstep(
        'Buscar la transacción por External Reference',
        function () use (
            $repository,
            $externalreference,
            &$transactionid
        ): void {
            $record = $repository->find_by_external_reference(
                $externalreference
            );

            if (
                !$record ||
                (int) $record->id !== $transactionid
            ) {
                throw new runtime_exception(
                    'La búsqueda por External Reference falló.'
                );
            }
        }
    );

    $showstep(
        'Guardar el Preference ID',
        function () use (
            $repository,
            $preferenceid,
            &$transactionid
        ): void {
            $repository->save_preference(
                $transactionid,
                $preferenceid
            );

            $record = $repository->find_by_id($transactionid);

            if (
                !$record ||
                $record->preferenceid !== $preferenceid
            ) {
                throw new runtime_exception(
                    'El Preference ID no fue guardado correctamente.'
                );
            }
        }
    );

    $showstep(
        'Buscar la transacción por Preference ID',
        function () use (
            $repository,
            $preferenceid,
            &$transactionid
        ): void {
            $record = $repository->find_by_preference_id(
                $preferenceid
            );

            if (
                !$record ||
                (int) $record->id !== $transactionid
            ) {
                throw new runtime_exception(
                    'La búsqueda por Preference ID falló.'
                );
            }
        }
    );

    $showstep(
        'Guardar el Payment ID',
        function () use (
            $repository,
            $paymentid,
            &$transactionid
        ): void {
            $repository->save_payment_reference(
                $transactionid,
                $paymentid
            );

            $record = $repository->find_by_id($transactionid);

            if (
                !$record ||
                $record->paymentid !== $paymentid
            ) {
                throw new runtime_exception(
                    'El Payment ID no fue guardado correctamente.'
                );
            }
        }
    );

    $showstep(
        'Buscar la transacción por Payment ID',
        function () use (
            $repository,
            $paymentid,
            &$transactionid
        ): void {
            $record = $repository->find_by_payment_id(
                $paymentid
            );

            if (
                !$record ||
                (int) $record->id !== $transactionid
            ) {
                throw new runtime_exception(
                    'La búsqueda por Payment ID falló.'
                );
            }
        }
    );

    $showstep(
        'Actualizar el estado a approved',
        function () use (
            $repository,
            &$transactionid
        ): void {
            $repository->update_status(
                $transactionid,
                'approved',
                'approved',
                time()
            );

            $record = $repository->find_by_id($transactionid);

            if (
                !$record ||
                $record->internalstatus !== 'approved' ||
                $record->externalstatus !== 'approved' ||
                empty($record->timeapproved)
            ) {
                throw new runtime_exception(
                    'Los estados no fueron actualizados correctamente.'
                );
            }
        }
    );

    $showstep(
        'Incrementar la cantidad de intentos',
        function () use (
            $repository,
            &$transactionid
        ): void {
            $repository->increment_attempts($transactionid);

            $record = $repository->find_by_id($transactionid);

            if (
                !$record ||
                (int) $record->attempts !== 1
            ) {
                throw new runtime_exception(
                    'La cantidad de intentos no fue incrementada.'
                );
            }
        }
    );

    $showstep(
        'Registrar un error',
        function () use (
            $repository,
            &$transactionid
        ): void {
            $message = 'Error temporal de prueba';

            $repository->register_error(
                $transactionid,
                $message
            );

            $record = $repository->find_by_id($transactionid);

            if (
                !$record ||
                $record->internalstatus !== 'error' ||
                $record->lasterror !== $message ||
                (int) $record->attempts !== 2
            ) {
                throw new runtime_exception(
                    'El error no fue registrado correctamente.'
                );
            }
        }
    );

    $showstep(
        'Marcar la transacción como entregada',
        function () use (
            $repository,
            &$transactionid
        ): void {
            $repository->mark_as_delivered(
                $transactionid
            );

            $record = $repository->find_by_id($transactionid);

            if (
                !$record ||
                (int) $record->delivered !== 1 ||
                $record->internalstatus !== 'delivered' ||
                empty($record->timedelivered)
            ) {
                throw new runtime_exception(
                    'La transacción no fue marcada como entregada.'
                );
            }
        }
    );

    echo $OUTPUT->notification(
        'Todas las pruebas del repositorio finalizaron correctamente.',
        'notifysuccess'
    );
} catch (Throwable $exception) {
    echo $OUTPUT->notification(
        'La ejecución se detuvo porque una prueba falló.',
        'notifyproblem'
    );
} finally {
    if ($transactionid !== null) {
        $DB->delete_records(
            'paygw_mercadopago_transactions',
            ['id' => $transactionid]
        );

        echo $OUTPUT->notification(
            'OK: la transacción temporal fue eliminada.',
            'notifysuccess'
        );
    }
}

echo html_writer::end_div();
echo html_writer::end_div();

echo $OUTPUT->footer();