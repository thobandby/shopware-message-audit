import { createDashboardPage } from '../create-dashboard-page';

const { Component } = Shopware;

Component.register('mh-dashboard-index', createDashboardPage({
    pageClass: 'mh-dashboard-index',
    headerKey: 'mh-dashboard.index.header',
    cardTitleKey: 'mh-dashboard.index.entriesTitle',
    emptyKey: 'mh-dashboard.index.empty',
    loadErrorKey: 'mh-dashboard.notifications.loadDataError',
    showMetrics: true,
    showEntryButtons: true,
    showStatusFilter: true,
    showCleanup: true,
    fixedStatus: ''
}));
