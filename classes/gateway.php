<?php
namespace paygw_mercadopago;
use core_text;

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

        $mform->addHelpButton(
            'environment',
            'environment_desc',
            'paygw_mercadopago'
        );

        $mform->addElement(
            'passwordunmask',
            'accesstoken',
            get_string('accesstoken', 'paygw_mercadopago')
        );

        $mform->setType('accesstoken', PARAM_RAW_TRIMMED);

        $mform->addHelpButton(
            'accesstoken',
            'accesstoken_desc',
            'paygw_mercadopago'
        );

        $mform->addElement(
            'passwordunmask',
            'webhooksecret',
            get_string('webhooksecret', 'paygw_mercadopago')
        );

        $mform->setType('webhooksecret', PARAM_RAW_TRIMMED);

        $mform->addHelpButton(
            'webhooksecret',
            'webhooksecret_desc',
            'paygw_mercadopago'
        );
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

        $accesstoken = trim($data->accesstoken ?? '');

        if ($accesstoken === '') {
            $errors['accesstoken'] = get_string(
                'accesstokenrequired',
                'paygw_mercadopago'
            );
        } else if (mb_strlen($accesstoken) < 20) {
            $errors['accesstoken'] = get_string(
                'accesstokeninvalidlength',
                'paygw_mercadopago'
            );
        }

        $webhooksecret = trim($data->webhooksecret ?? '');

        if ($webhooksecret === '') {
            $errors['webhooksecret'] = get_string(
                'webhooksecretrequired',
                'paygw_mercadopago'
            );
        } else if (mb_strlen($webhooksecret) < 16) {
            $errors['webhooksecret'] = get_string(
                'webhooksecretinvalidlength',
                'paygw_mercadopago'
            );
        }
    }
}