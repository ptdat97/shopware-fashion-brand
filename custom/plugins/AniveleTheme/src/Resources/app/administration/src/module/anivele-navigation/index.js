import './page/anivele-navigation-index';
import viVN from '../../snippet/vi-VN.json';
import enGB from '../../snippet/en-GB.json';

Shopware.Module.register('anivele-navigation', {
    type: 'plugin',
    name: 'anivele-navigation',
    title: 'anivele-navigation.general.mainMenuItem',
    description: 'anivele-navigation.general.description',
    color: '#6b4a34',
    icon: 'regular-content',

    snippets: {
        'vi-VN': viVN,
        'en-GB': enGB,
    },

    routes: {
        index: {
            component: 'anivele-navigation-index',
            path: 'index',
            meta: {
                privilege: 'category.viewer',
            },
        },
    },

    navigation: [
        {
            id: 'anivele-navigation',
            label: 'anivele-navigation.general.mainMenuItem',
            color: '#6b4a34',
            icon: 'regular-content',
            path: 'anivele.navigation.index',
            parent: 'sw-content',
            position: 100,
            privilege: 'category.viewer',
        },
    ],
});
