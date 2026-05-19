import {
    buildEntryFilterOptions,
    buildGridTemplate,
    buildGridColumns,
    buildTimeRangeOptions,
    formatDashboardDateTime,
    resolveDashboardTimeRange,
    sharedFilterFieldsTemplate
} from './mh-dashboard-page-helpers';

const defaultLimitOptions = [
    { value: 10, label: '10' },
    { value: 25, label: '25' },
    { value: 50, label: '50' },
    { value: 100, label: '100' }
];

function buildMetricsTemplate() {
    return `
        <sw-card :title="$tc('mh-dashboard.index.overviewTitle')">
            <sw-button @click="loadAll">{{ $tc('mh-dashboard.index.reload') }}</sw-button>

            <div class="mh-dashboard-index__metrics" style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-top:16px;">
                <div class="mh-dashboard-index__metric-card">
                    <div class="mh-dashboard-index__metric-label">{{ $tc('mh-dashboard.index.metrics.messenger24h') }}</div>
                    <div class="mh-dashboard-index__metric-value">{{ metrics.messenger_24h || 0 }}</div>
                </div>
                <div class="mh-dashboard-index__metric-card">
                    <div class="mh-dashboard-index__metric-label">{{ $tc('mh-dashboard.index.metrics.retries') }}</div>
                    <div class="mh-dashboard-index__metric-value">{{ metrics.retries || 0 }}</div>
                </div>
                <div class="mh-dashboard-index__metric-card">
                    <div class="mh-dashboard-index__metric-label">{{ $tc('mh-dashboard.index.metrics.failed') }}</div>
                    <div class="mh-dashboard-index__metric-value">{{ metrics.failures || 0 }}</div>
                </div>
                <div class="mh-dashboard-index__metric-card">
                    <div class="mh-dashboard-index__metric-label">{{ $tc('mh-dashboard.index.metrics.orderPaymentStates') }}</div>
                    <div class="mh-dashboard-index__metric-value">{{ metrics.order_payment_states || 0 }}</div>
                </div>
            </div>
        </sw-card>
    `;
}

function buildEntryButtonsTemplate() {
    return `
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
            <sw-button
                v-for="option in entryFilterOptions"
                :key="option.value || 'all'"
                size="small"
                :variant="selectedEntryFilter === option.value ? 'primary' : 'secondary'"
                @click="selectEntryFilter(option.value)">
                {{ option.label }}
            </sw-button>
        </div>
    `;
}

function buildStatusFilterTemplate() {
    return `
        <sw-single-select
            v-model:value="selectedStatus"
            :label="$tc('mh-dashboard.filters.status')"
            :options="statusOptions">
        </sw-single-select>
    `;
}

function buildCleanupTemplate() {
    return `
        <div style="display:flex;justify-content:flex-end;align-items:flex-end;gap:12px;flex-wrap:wrap;margin-bottom:16px;padding:16px;border:1px solid #d1d9e0;border-radius:8px;background:#f8fafc;">
            <div style="color:#52667a;font-size:14px;line-height:32px;white-space:nowrap;">
                {{ $tc('mh-dashboard.index.cleanupLabel') }}
            </div>

            <sw-single-select
                v-model:value="cleanupHours"
                label=""
                style="min-width:300px;"
                :options="cleanupOptions">
            </sw-single-select>

            <sw-button variant="danger" @click="cleanupOldEntries">{{ $tc('mh-dashboard.index.cleanup') }}</sw-button>
        </div>
    `;
}

function buildDashboardTemplate(options) {
    const metricsTemplate = options.showMetrics ? buildMetricsTemplate() : '';
    const entryButtonsTemplate = options.showEntryButtons ? buildEntryButtonsTemplate() : '';
    const statusFilterTemplate = options.showStatusFilter ? buildStatusFilterTemplate() : '';
    const cleanupTemplate = options.showCleanup ? buildCleanupTemplate() : '';
    const entriesMarginTop = options.showMetrics ? ' style="margin-top:16px;"' : '';

    return `
        <sw-page class="${options.pageClass}">
            <template #smart-bar-header>
                <h2>{{ $tc('${options.headerKey}') }}</h2>
            </template>

            <template #content>
                ${metricsTemplate}

                <sw-card :title="$tc('${options.cardTitleKey}')"${entriesMarginTop}>
                    ${entryButtonsTemplate}

                    <div style="display:grid;grid-template-columns:repeat(3,minmax(220px,1fr));gap:16px;align-items:end;margin-bottom:16px;">
                        ${sharedFilterFieldsTemplate}
                        ${statusFilterTemplate}
                    </div>

                    <div style="display:flex;justify-content:flex-end;margin-bottom:16px;">
                        <sw-button variant="primary" @click="applyFilters">{{ $tc('mh-dashboard.index.applyFilters') }}</sw-button>
                    </div>

                    ${cleanupTemplate}
                    ${buildGridTemplate(options.emptyKey)}
                </sw-card>
            </template>
        </sw-page>
    `;
}

export function createDashboardPage(options) {
    return {
        inject: ['mhDashboardApiService'],
        mixins: [
            Shopware.Mixin.getByName('notification')
        ],
        template: buildDashboardTemplate(options),
        data() {
            return {
                messages: [],
                metrics: {},
                isLoading: false,
                total: 0,
                page: 1,
                searchTerm: '',
                messageClass: '',
                transportName: '',
                businessReference: '',
                selectedEntryFilter: '',
                selectedStatus: '',
                selectedTimeRange: '30d',
                selectedLimit: 25,
                cleanupHours: 24,
                limitOptions: defaultLimitOptions
            };
        },
        computed: {
            currentLocale() {
                return Shopware.Store.get('session').currentLocale || 'de-DE';
            },

            currentTimeZone() {
                return Shopware.Store.get('session').currentUser?.timeZone || 'UTC';
            },

            entryFilterOptions() {
                return buildEntryFilterOptions(this);
            },

            statusOptions() {
                return [
                    { value: '', label: this.$tc('mh-dashboard.statuses.all') },
                    { value: 'dispatched', label: this.$tc('mh-dashboard.statuses.dispatched') },
                    { value: 'received', label: this.$tc('mh-dashboard.statuses.received') },
                    { value: 'handled', label: this.$tc('mh-dashboard.statuses.handled') },
                    { value: 'failed', label: this.$tc('mh-dashboard.statuses.failed') }
                ];
            },

            timeRangeOptions() {
                return buildTimeRangeOptions(this);
            },

            cleanupOptions() {
                return [
                    { value: 1, label: this.$tc('mh-dashboard.cleanupOptions.1h') },
                    { value: 8, label: this.$tc('mh-dashboard.cleanupOptions.8h') },
                    { value: 24, label: this.$tc('mh-dashboard.cleanupOptions.24h') },
                    { value: 72, label: this.$tc('mh-dashboard.cleanupOptions.3d') },
                    { value: 168, label: this.$tc('mh-dashboard.cleanupOptions.7d') },
                    { value: 720, label: this.$tc('mh-dashboard.cleanupOptions.30d') }
                ];
            },

            columns() {
                return buildGridColumns(this);
            }
        },
        created() {
            this.loadAll();
        },
        methods: {
            async loadAll() {
                this.isLoading = true;

                try {
                    const range = this.resolveTimeRange();
                    const status = options.fixedStatus || this.selectedStatus;
                    const messagesPromise = this.mhDashboardApiService.listMessages({
                        status,
                        searchTerm: this.searchTerm.trim(),
                        page: this.page,
                        limit: this.selectedLimit,
                        entryFilter: this.selectedEntryFilter,
                        createdFrom: range.createdFrom,
                        createdTo: range.createdTo,
                        messageClass: this.messageClass.trim(),
                        transportName: this.transportName.trim(),
                        businessReference: this.businessReference.trim()
                    });

                    if (!options.showMetrics) {
                        const response = await messagesPromise;
                        this.messages = response.data.data || [];
                        this.total = response.data.total || 0;

                        return;
                    }

                    const [messagesResponse, metricsResponse] = await Promise.all([
                        messagesPromise,
                        this.mhDashboardApiService.loadMetrics()
                    ]);

                    this.messages = messagesResponse.data.data || [];
                    this.total = messagesResponse.data.total || 0;
                    this.metrics = metricsResponse.data || {};
                } catch (error) {
                    this.messages = [];
                    this.total = 0;
                    this.metrics = {};

                    this.createNotificationError({
                        title: this.$tc('mh-dashboard.notifications.title'),
                        message: this.resolveErrorMessage(error, this.$tc(options.loadErrorKey))
                    });
                } finally {
                    this.isLoading = false;
                }
            },

            async runAction(action, item) {
                try {
                    if (action === 'retry') {
                        await this.mhDashboardApiService.retryMessage(item.id);
                    }

                    if (action === 'quarantine') {
                        await this.mhDashboardApiService.quarantineMessage(item.id);
                    }

                    if (action === 'dismiss') {
                        await this.mhDashboardApiService.dismissMessage(item.id);
                    }

                    await this.loadAll();
                } catch (error) {
                    this.createNotificationError({
                        title: this.$tc('mh-dashboard.notifications.title'),
                        message: this.resolveErrorMessage(error, this.$tc('mh-dashboard.notifications.actionError'))
                    });
                }
            },

            onPageChange(pagination) {
                const nextPage = typeof pagination === 'number' ? pagination : pagination.page;
                const nextLimit = typeof pagination === 'number' ? this.selectedLimit : Number(pagination.limit);

                this.page = nextPage;
                this.selectedLimit = nextLimit;
                this.loadAll();
            },

            onLimitChange(limit) {
                this.selectedLimit = Number(limit);
                this.page = 1;
                this.loadAll();
            },

            applyFilters() {
                this.page = 1;
                this.loadAll();
            },

            selectEntryFilter(entryFilter) {
                this.selectedEntryFilter = entryFilter;
                this.page = 1;
                this.loadAll();
            },

            resolveTimeRange() {
                return resolveDashboardTimeRange(this.selectedTimeRange);
            },

            formatDateTime(value) {
                return formatDashboardDateTime(value, this.currentLocale, this.currentTimeZone);
            },

            async cleanupOldEntries() {
                if (!options.showCleanup) {
                    return;
                }

                try {
                    const response = await this.mhDashboardApiService.cleanupMessages(this.cleanupHours);
                    const deleted = response.data.deleted || {};

                    this.createNotificationSuccess({
                        title: this.$tc('mh-dashboard.notifications.title'),
                        message: this.$tc('mh-dashboard.notifications.cleanupSuccess', 0, { count: deleted.messages || 0 })
                    });

                    this.page = 1;
                    await this.loadAll();
                } catch (error) {
                    this.createNotificationError({
                        title: this.$tc('mh-dashboard.notifications.title'),
                        message: this.resolveErrorMessage(error, this.$tc('mh-dashboard.notifications.cleanupError'))
                    });
                }
            },

            resolveErrorMessage(error, fallbackMessage) {
                return error?.response?.data?.errors?.[0]?.detail || error?.message || fallbackMessage;
            }
        }
    };
}
