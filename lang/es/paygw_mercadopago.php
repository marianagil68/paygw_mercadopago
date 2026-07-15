<?php
/**
 * Cadenas de idioma para el gateway Mercado Pago.
 *
 * @package    paygw_mercadopago
 * @copyright  2026 Mariana Gil
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['accesstoken'] = 'Access Token';
$string['accesstokenrequired'] = 'El Access Token es obligatorio.';
$string['environment'] = 'Entorno';
$string['environment_production'] = 'Producción';
$string['environment_sandbox'] = 'Sandbox';
$string['gatewaydescription'] = 'Gateway de pagos Mercado Pago utilizando Checkout Pro.';
$string['gatewayname'] = 'Mercado Pago';
$string['pluginname'] = 'Mercado Pago';
$string['pluginname_desc'] = 'El plugin Mercado Pago permite cobrar mediante Mercado Pago Checkout Pro.';
$string['privacy:metadata'] = 'El gateway Mercado Pago almacena la información necesaria para procesar y auditar los pagos.';
$string['webhooksecret'] = 'Secreto del Webhook';
$string['webhooksecretrequired'] = 'El secreto del Webhook es obligatorio.';

$string['environment_desc'] = 'Seleccione el entorno de Mercado Pago que utilizará esta cuenta.';
$string['environment_sandbox'] = 'Sandbox (Pruebas)';
$string['environment_production'] = 'Producción';

$string['accesstoken_desc'] = 'Pegue aquí el Access Token de Mercado Pago correspondiente al entorno seleccionado.';

$string['webhooksecret_desc'] = 'Ingrese el secreto utilizado para validar las notificaciones (Webhooks) enviadas por Mercado Pago.';
$string['environment_desc_help'] = 'Seleccione Sandbox para realizar pruebas o Producción para cobrar pagos reales.';
$string['accesstoken_desc_help'] = 'Pegue el Access Token de Mercado Pago correspondiente al entorno seleccionado. Este dato es confidencial y no debe compartirse.';
$string['webhooksecret_desc_help'] = 'Ingrese el secreto configurado en Mercado Pago para validar la autenticidad de las notificaciones Webhook.';

$string['accesstokeninvalidlength'] = 'El Access Token debe tener al menos 20 caracteres.';
$string['webhooksecretinvalidlength'] = 'El secreto del Webhook debe tener al menos 16 caracteres.';