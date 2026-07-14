<?php
namespace paygw_mercadopago;

defined('MOODLE_INTERNAL') || die();

class gateway extends \core_payment\gateway {
    public static function get_supported_currencies(): array {
        return ['ARS'];
    }

    public static function add_configuration_to_gateway_form(
        \core_payment\form\account_gateway $form
    ): void {
        $mform = $form->get_mform();

        $environments = [
            'sandbox' => get_string('environment_sandbox', 'paygw_mercadopago'),
            'production' => get_string('environment_production', 'paygw_mercadopago'),
        ];

        $mform->addElement(
            'select',
            'environment',
            get_string('environment', 'paygw_mercadopago'),
            $environments
        );

        $mform->addElement(
            'passwordunmask',
            'accesstoken',
            get_string('accesstoken', 'paygw_mercadopago')
        );
        $mform->setType('accesstoken', PARAM_RAW_TRIMMED);

        $mform->addElement(
            'passwordunmask',
            'webhooksecret',
            get_string('webhooksecret', 'paygw_mercadopago')
        );
        $mform->setType('webhooksecret', PARAM_RAW_TRIMMED);
    }

    public static function validate_gateway_form(
        \core_payment\form\account_gateway $form,
        \stdClass $data,
        array $files,
        array &$errors
    ): void {
        if (!$data->enabled) {
            return;
        }

        if (empty(trim($data->accesstoken ?? ''))) {
            $errors['accesstoken'] = get_string(
                'accesstokenrequired',
                'paygw_mercadopago'
            );
        }

        if (empty(trim($data->webhooksecret ?? ''))) {
            $errors['webhooksecret'] = get_string(
                'webhooksecretrequired',
                'paygw_mercadopago'
            );
        }
    }
}