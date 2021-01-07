let routes = [
    {
        path: '/steama-meters/steama-overview',
        component: require('./plugins/steama-meter/js/components/Overview/Overview').default,
        meta: { layout: 'default' },
    },
    {
        path: '/steama-meters/steama-site/page/:page_number',
        component: require('./plugins/steama-meter/js/components/Site/SiteList').default,
        meta: { layout: 'default' },
    },
    {
        path: '/steama-meters/steama-customer/page/:page_number',
        component: require('./plugins/steama-meter/js/components/Customer/CustomerList').default,
        meta: { layout: 'default' },
    },
    {
        path: '/steama-meters/steama-transaction/:customer_id/page/:page_number',
        component: require('./plugins/steama-meter/js/components/Customer/CustomerMovements').default,
        meta: { layout: 'default' },
    },
    {
        path: '/steama-meters/steama-meter/page/:page_number',
        component: require('./plugins/steama-meter/js/components/Meter/MeterList').default,
        meta: { layout: 'default' },
    },

    {
        path: '/steama-meters/steama-agent/page/:page_number',
        component: require('./plugins/steama-meter/js/components/Agent/AgentList').default,
        meta: { layout: 'default' },
    },

]