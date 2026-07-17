<?php
/**
 * Language strings for the Mercado Pago payment gateway.
 *
 * @package    paygw_mercadopago
 * @copyright  2026 Mariana Gil
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['accesstoken'] = 'Access token';
$string['environment'] = 'Environment';
$string['environment_production'] = 'Production';
$string['environment_sandbox'] = 'Sandbox';
$string['gatewaydescription'] = 'Mercado Pago payment gateway using Checkout Pro.';
$string['gatewayname'] = 'Mercado Pago';
$string['pluginname'] = 'Mercado Pago';
$string['pluginname_desc'] = 'The Mercado Pago plugin allows payments through Mercado Pago Checkout Pro.';
$string['privacy:metadata'] = 'The Mercado Pago payment gateway stores transaction information required to process and audit payments.';
$string['webhooksecret'] = 'Webhook secret';

$string['environment_desc_help'] = 'Select Sandbox for testing or Production to process real payments.';
$string['accesstoken_desc_help'] = 'Paste the Mercado Pago Access Token for the selected environment. This value is confidential and must not be shared.';
$string['webhooksecret_desc_help'] = 'Enter the secret configured in Mercado Pago to validate the authenticity of Webhook notifications.';

$string['accesstokeninvalidlength'] = 'The Access Token must contain at least 20 characters.';
$string['webhooksecretinvalidlength'] = 'The Webhook secret must contain at least 16 characters.';

$string['paymentapproved'] = 'The payment was approved. The enrolment will be updated automatically.';
$string['paymentpending'] = 'The payment is awaiting confirmation.';
$string['paymentnotcompleted'] = 'The payment was not completed.';