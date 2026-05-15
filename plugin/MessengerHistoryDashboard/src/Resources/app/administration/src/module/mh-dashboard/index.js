import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

Shopware.Component.register('mh-dashboard-settings-icon', () => import('./components/mh-dashboard-settings-icon'));

import './mh-dashboard.scss';
import './service/mh-dashboard-api.service';
import './page/mh-dashboard-index';
import './page/mh-dashboard-failed';
import './page/mh-dashboard-detail';

Shopware.Locale.extend('de-DE', deDE);
Shopware.Locale.extend('en-GB', enGB);

Shopware.Module.register('mh-dashboard', {
    type: 'plugin',
    name: 'mh-dashboard',
    title: 'mh-dashboard.general.mainMenuItemGeneral',
    description: 'mh-dashboard.general.descriptionTextModule',
    color: '#3d91ff',
    icon: 'regular-history',
    favicon: 'icon-module-settings.png',
    routes: {
        index: {
            component: 'mh-dashboard-index',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index.plugins',
                privilege: 'system.plugin_maintain'
            }
        },
        failed: {
            component: 'mh-dashboard-failed',
            path: 'failed',
            meta: {
                parentPath: 'mh.dashboard.index',
                privilege: 'system.plugin_maintain'
            }
        },
        detail: {
            component: 'mh-dashboard-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'mh.dashboard.index',
                privilege: 'system.plugin_maintain'
            }
        }
    },
    settingsItem: {
        group: 'plugins',
        to: 'mh.dashboard.index',
        iconComponent: 'mh-dashboard-settings-icon',
        backgroundEnabled: true,
        privilege: 'system.plugin_maintain'
    }
});
