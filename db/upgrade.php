<?php
/**
 * Upgrade steps for the Mercado Pago payment gateway.
 *
 * @package    paygw_mercadopago
 * @copyright  2026 Mariana Gil
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade the Mercado Pago payment gateway.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_paygw_mercadopago_upgrade(int $oldversion): bool {
    if ($oldversion < 2026071302) {
        $sortorder = get_config('core', 'paygw_plugins_sortorder');
        $gateways = $sortorder ? explode(',', $sortorder) : [];

        if (!in_array('mercadopago', $gateways, true)) {
            $gateways[] = 'mercadopago';
            set_config('paygw_plugins_sortorder', implode(',', $gateways));
        }

        upgrade_plugin_savepoint(true, 2026071302, 'paygw', 'mercadopago');
    }

    return true;
}