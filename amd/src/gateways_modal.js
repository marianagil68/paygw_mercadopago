// Este archivo forma parte de Moodle - http://moodle.org/
//
// Moodle es software libre: puede redistribuirlo y/o modificarlo
// bajo los términos de la Licencia Pública General GNU publicada por
// la Free Software Foundation, ya sea la versión 3 de la licencia o
// (a su elección) cualquier versión posterior.
//
// Moodle se distribuye con la esperanza de que sea útil,
// pero SIN NINGUNA GARANTÍA, ni siquiera la garantía implícita de
// COMERCIABILIDAD o IDONEIDAD PARA UN PROPÓSITO PARTICULAR.
// Consulte la Licencia Pública General GNU para más detalles.
//
// Debería haber recibido una copia de la Licencia Pública General GNU
// junto con Moodle. Si no es así, consulte <http://www.gnu.org/licenses/>.

/**
 * Inicia un pago de Mercado Pago desde el modal estándar de gateways de Moodle.
 *
 * @module     paygw_mercadopago/gateways_modal
 * @copyright  2026 Mariana Gil
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 o posterior
 */

import {createPreference} from './repository';

/**
 * Crea la preferencia de Mercado Pago y redirige al usuario a Checkout Pro.
 *
 * @param {string} component Componente que origina el pago.
 * @param {string} paymentArea Área de pago dentro del componente.
 * @param {number} itemId Identificador del elemento a pagar.
 * @param {string} description Descripción enviada por Moodle.
 * @returns {Promise<string>}
 */
export const process = async(component, paymentArea, itemId, description) => {
    const result = await createPreference(
        component,
        paymentArea,
        itemId,
        description
    );

    window.location.assign(result.initpoint);

    return new Promise(() => {});
};