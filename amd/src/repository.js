import Ajax from 'core/ajax';

export const createPreference = (
    component,
    paymentarea,
    itemid
) => Ajax.call([{
    methodname: 'paygw_mercadopago_create_preference',
    args: {
        component,
        paymentarea,
        itemid,
    },
}])[0];