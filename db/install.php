<?php
/**
 * Installation functions for the Mercado Pago payment gateway.
 *
 * @package    paygw_mercadopago
 * @copyright  2026 Mariana Gil
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Installs the Mercado Pago payment gateway.
 */
function xmldb_paygw_mercadopago_install(): void {
    global $CFG;

    $order = !empty($CFG->paygw_plugins_sortorder)
        ? explode(',', $CFG->paygw_plugins_sortorder)
        : [];

    if (!in_array('mercadopago', $order, true)) {
        $order[] = 'mercadopago';
        set_config('paygw_plugins_sortorder', implode(',', $order));
    }
}