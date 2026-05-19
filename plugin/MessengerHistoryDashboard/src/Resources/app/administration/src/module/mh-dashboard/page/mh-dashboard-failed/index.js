import { createDashboardPage } from '../create-dashboard-page';

const { Component } = Shopware;

Component.register('mh-dashboard-failed', createDashboardPage({
    pageClass: 'mh-dashboard-failed',
    headerKey: 'mh-dashboard.failed.header',
    cardTitleKey: 'mh-dashboard.failed.title',
    emptyKey: 'mh-dashboard.failed.empty',
    loadErrorKey: 'mh-dashboard.notifications.loadFailedError',
    showMetrics: false,
    showEntryButtons: false,
    showStatusFilter: false,
    showCleanup: false,
    fixedStatus: 'failed'
}));
