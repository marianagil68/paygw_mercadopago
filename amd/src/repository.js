import Ajax from 'core/ajax';

export const createPreference = (
    component,
    paymentarea,
    itemid,
    description

) => Ajax.call([{
    methodname: 'paygw_mercadopago_create_preference',
    args: {
        component,
        paymentarea,
        itemid,
        description
    },
}])[0];