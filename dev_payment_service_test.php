<?php
declare(strict_types=1);
$deletetransaction = true;


require_once(__DIR__ . '/../../../../config.php');

use paygw_mercadopago\local\client\mercadopago_client;
use paygw_mercadopago\local\repository\transaction_repository;
use paygw_mercadopago\local\service\payment_service;

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(
    new moodle_url(
        '/payment/gateway/mercadopago/dev_payment_service_test.php'
    )
);
$PAGE->set_title('Prueba de Payment Service');
$PAGE->set_heading('Prueba de Payment Service');

/**
 * Simulated Mercado Pago client.
 *
 * It does not perform external HTTP requests.
 */
class mercadopago_client_mock extends mercadopago_client {
    /**
     * Constructor intentionally does not call the parent constructor.
     */
    public function __construct() {
    }

    /**
     * Returns a simulated preference.
     *
     * @param array $data Preference data.
     * @return array Simulated response.
     */
    public function create_preference(array $data): array {
        return [
            'preferenceid' => 'TEST_PREFERENCE_' . bin2hex(random_bytes(5)),
            'initpoint' => 'https://www.mercadopago.com.ar/checkout/test',
        ];
    }
}

echo $OUTPUT->header();
echo html_writer::start_div('card');
echo html_writer::start_div('card-body');

echo html_writer::tag(
    'p',
    'Este script prueba payment_service con un cliente simulado y elimina la transacción temporal al finalizar.'
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
$client = new mercadopago_client_mock();
$service = new payment_service($repository, $client);

$transactionid = null;
$result = null;
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
        'Crear las dependencias del servicio',
        static function () use (
            $repository,
            $client,
            $service
        ): void {
            if (
                !$repository instanceof transaction_repository
                || !$client instanceof mercadopago_client
                || !$service instanceof payment_service
            ) {
                throw new runtime_exception(
                    'No se crearon correctamente las dependencias.'
                );
            }
        }
    );

    $showstep(
        'Ejecutar start_payment()',
        static function () use (
            $service,
            $account,
            $USER,
            &$result,
            &$transactionid
        ): void {
            $result = $service->start_payment(
                (int) $account->id,
                (int) $USER->id,
                'paygw_mercadopago',
                'paymentservicetest',
                1,
                1000.00,
                'ARS'
            );

            $transactionid = (int) ($result['transactionid'] ?? 0);

            if ($transactionid <= 0) {
                throw new runtime_exception(
                    'El servicio no devolvió un Transaction ID válido.'
                );
            }
        }
    );

    $showstep(
        'Verificar el resultado devuelto',
        static function () use (&$result): void {
            if (
                empty($result['externalreference'])
                || empty($result['preferenceid'])
                || empty($result['initpoint'])
            ) {
                throw new runtime_exception(
                    'El resultado del servicio está incompleto.'
                );
            }

            if (
                !preg_match(
                    '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
                    $result['externalreference']
                )
            ) {
                throw new runtime_exception(
                    'El External Reference no es un UUID versión 4 válido.'
                );
            }
        }
    );

    $showstep(
        'Verificar la transacción guardada',
        static function () use (
            $repository,
            &$transactionid,
            &$result
        ): void {
            $transaction = $repository->find_by_id(
                $transactionid
            );

            if (!$transaction) {
                throw new runtime_exception(
                    'La transacción no fue encontrada.'
                );
            }

            if (
                $transaction->externalreference
                    !== $result['externalreference']
            ) {
                throw new runtime_exception(
                    'El External Reference guardado no coincide.'
                );
            }

            if (
                $transaction->preferenceid
                    !== $result['preferenceid']
            ) {
                throw new runtime_exception(
                    'El Preference ID guardado no coincide.'
                );
            }

            if ($transaction->internalstatus !== 'pending') {
                throw new runtime_exception(
                    'La transacción no quedó en estado pending.'
                );
            }
        }
    );

    echo $OUTPUT->notification(
        'Todas las pruebas de payment_service finalizaron correctamente.',
        'notifysuccess'
    );

    echo html_writer::tag(
        'pre',
        s(print_r($result, true))
    );
} catch (Throwable $exception) {
    echo $OUTPUT->notification(
        'La ejecución se detuvo porque una prueba falló.',
        'notifyproblem'
    );
} finally {
    if ($deletetransaction && $transactionid !== null && $transactionid > 0) {
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