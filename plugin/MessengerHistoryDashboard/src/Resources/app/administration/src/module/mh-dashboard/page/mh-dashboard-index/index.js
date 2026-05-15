const { Component } = Shopware;

Component.register('mh-dashboard-index', {
    inject: ['mhDashboardApiService'],
    mixins: [
        Shopware.Mixin.getByName('notification')
    ],
    template: `
        <sw-page class="mh-dashboard-index">
            <template #smart-bar-header>
                <h2>{{ $tc('mh-dashboard.index.header') }}</h2>
            </template>

            <template #content>
                <sw-card :title="$tc('mh-dashboard.index.overviewTitle')">
                    <sw-button @click="loadAll">{{ $tc('mh-dashboard.index.reload') }}</sw-button>

                    <div class="mh-dashboard-index__metrics" style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-top:16px;">
                        <sw-card :title="$tc('mh-dashboard.index.metrics.entries')"><strong>{{ metrics.total || 0 }}</strong></sw-card>
                        <sw-card :title="$tc('mh-dashboard.index.metrics.failed')"><strong>{{ metrics.messenger_failed || 0 }}</strong></sw-card>
                        <sw-card :title="$tc('mh-dashboard.index.metrics.stateChanges')"><strong>{{ metrics.state_changes || 0 }}</strong></sw-card>
                        <sw-card :title="$tc('mh-dashboard.index.metrics.payments')"><strong>{{ metrics.payments || 0 }}</strong></sw-card>
                    </div>
                </sw-card>

                <sw-card :title="$tc('mh-dashboard.index.entriesTitle')" style="margin-top:16px;">
                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
                        <sw-button
                            v-for="option in topicGroupOptions"
                            :key="option.value || 'all'"
                            size="small"
                            :variant="selectedTopicGroup === option.value ? 'primary' : 'secondary'"
                            @click="selectTopicGroup(option.value)">
                            {{ option.label }}
                        </sw-button>
                    </div>

                    <div style="display:grid;grid-template-columns:repeat(3,minmax(220px,1fr));gap:16px;align-items:end;margin-bottom:16px;">
                        <sw-text-field
                            v-model:value="searchTerm"
                            :label="$tc('mh-dashboard.filters.search')"
                            :placeholder="$tc('mh-dashboard.filters.searchPlaceholder')">
                        </sw-text-field>

                        <sw-text-field
                            v-model:value="messageClass"
                            :label="$tc('mh-dashboard.filters.class')"
                            :placeholder="$tc('mh-dashboard.filters.classPlaceholder')">
                        </sw-text-field>

                        <sw-text-field
                            v-model:value="businessReference"
                            :label="$tc('mh-dashboard.filters.reference')"
                            :placeholder="$tc('mh-dashboard.filters.referencePlaceholder')">
                        </sw-text-field>

                        <sw-single-select
                            v-model:value="selectedStatus"
                            :label="$tc('mh-dashboard.filters.status')"
                            :options="statusOptions">
                        </sw-single-select>

                        <sw-single-select
                            v-model:value="selectedTopicGroup"
                            :label="$tc('mh-dashboard.filters.topicGroup')"
                            :options="topicGroupOptions">
                        </sw-single-select>

                        <sw-text-field
                            v-model:value="transportName"
                            :label="$tc('mh-dashboard.filters.transport')"
                            :placeholder="$tc('mh-dashboard.filters.transportPlaceholder')">
                        </sw-text-field>

                        <sw-single-select
                            v-model:value="selectedTimeRange"
                            :label="$tc('mh-dashboard.filters.timeRange')"
                            :options="timeRangeOptions">
                        </sw-single-select>
                    </div>

                    <div style="display:flex;justify-content:flex-end;margin-bottom:16px;">
                        <sw-button variant="primary" @click="applyFilters">{{ $tc('mh-dashboard.index.applyFilters') }}</sw-button>
                    </div>

                    <div style="display:grid;grid-template-columns:minmax(220px,1fr) auto auto;gap:16px;align-items:end;margin-bottom:16px;padding:16px;border:1px solid #d1d9e0;border-radius:8px;background:#f8fafc;">
                        <div style="color:#52667a;font-size:14px;">
                            {{ $tc('mh-dashboard.index.currentFilterCount', 0, { count: total }) }}
                        </div>

                        <sw-single-select
                            v-model:value="cleanupDays"
                            :label="$tc('mh-dashboard.index.cleanupLabel')"
                            :options="cleanupOptions">
                        </sw-single-select>

                        <sw-button variant="danger" @click="cleanupOldEntries">{{ $tc('mh-dashboard.index.cleanup') }}</sw-button>
                    </div>

                    <sw-data-grid
                        v-if="messages.length > 0"
                        :data-source="messages"
                        :columns="columns"
                        :show-selection="false">
                        <template #column-available_actions="{ item }">
                            {{ item.available_actions }}
                        </template>

                        <template #column-status="{ item }">
                            {{ item.status_label }}
                        </template>

                        <template #column-business_reference="{ item }">
                            {{ item.business_reference || '-' }}
                        </template>

                        <template #column-group_count="{ item }">
                            {{ item.group_count || 1 }}
                        </template>

                        <template #actions="{ item }">
                            <sw-context-menu-item
                                :router-link="{ name: 'mh.dashboard.detail', params: { id: item.id } }">
                                {{ $tc('mh-dashboard.actions.details') }}
                            </sw-context-menu-item>
                            <sw-context-menu-item
                                :disabled="!item.allowed_actions?.includes('retry')"
                                @click="runAction('retry', item)">
                                {{ $tc('mh-dashboard.actions.retry') }}
                            </sw-context-menu-item>
                            <sw-context-menu-item
                                :disabled="!item.allowed_actions?.includes('quarantine')"
                                @click="runAction('quarantine', item)">
                                {{ $tc('mh-dashboard.actions.quarantine') }}
                            </sw-context-menu-item>
                            <sw-context-menu-item
                                :disabled="!item.allowed_actions?.includes('dismiss')"
                                @click="runAction('dismiss', item)">
                                {{ $tc('mh-dashboard.actions.dismiss') }}
                            </sw-context-menu-item>
                        </template>

                    </sw-data-grid>

                    <div
                        v-if="messages.length > 0"
                        style="display:flex;justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap;margin-top:16px;">
                        <div style="width:160px;">
                            <sw-single-select
                                :value="selectedLimit"
                                :label="$tc('mh-dashboard.pagination.perPage')"
                                :options="limitOptions"
                                @update:value="onLimitChange">
                            </sw-single-select>
                        </div>

                        <sw-pagination
                            :page="page"
                            :total="total"
                            :limit="selectedLimit"
                            :steps="[selectedLimit]"
                            :auto-hide="false"
                            @page-change="onPageChange">
                        </sw-pagination>
                    </div>

                    <div v-else style="margin-top:16px;">
                        {{ $tc('mh-dashboard.index.empty') }}
                    </div>
                </sw-card>
            </template>
        </sw-page>
    `,
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
            selectedTopicGroup: '',
            selectedStatus: '',
            selectedTimeRange: '30d',
            selectedLimit: 25,
            cleanupDays: 30,
            limitOptions: [
                { value: 10, label: '10' },
                { value: 25, label: '25' },
                { value: 50, label: '50' },
                { value: 100, label: '100' }
            ]
        };
    },
    computed: {
        topicGroupOptions() {
            return [
                { value: '', label: this.$tc('mh-dashboard.topicGroups.all') },
                { value: 'Bestellungen', label: this.$tc('mh-dashboard.topicGroups.orders') },
                { value: 'Zahlungen', label: this.$tc('mh-dashboard.topicGroups.payments') },
                { value: 'System', label: this.$tc('mh-dashboard.topicGroups.system') }
            ];
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
            return [
                { value: '', label: this.$tc('mh-dashboard.timeRanges.all') },
                { value: '1d', label: this.$tc('mh-dashboard.timeRanges.1d') },
                { value: '7d', label: this.$tc('mh-dashboard.timeRanges.7d') },
                { value: '30d', label: this.$tc('mh-dashboard.timeRanges.30d') },
                { value: '90d', label: this.$tc('mh-dashboard.timeRanges.90d') }
            ];
        },

        cleanupOptions() {
            return [
                { value: 7, label: this.$tc('mh-dashboard.cleanupOptions.7d') },
                { value: 30, label: this.$tc('mh-dashboard.cleanupOptions.30d') },
                { value: 90, label: this.$tc('mh-dashboard.cleanupOptions.90d') },
                { value: 180, label: this.$tc('mh-dashboard.cleanupOptions.180d') }
            ];
        },

        columns() {
            return [
                { property: 'created_at', label: this.$tc('mh-dashboard.grid.createdAt'), primary: true },
                { property: 'message_name', label: this.$tc('mh-dashboard.grid.name') },
                { property: 'message_type', label: this.$tc('mh-dashboard.grid.type') },
                { property: 'group_count', label: this.$tc('mh-dashboard.grid.groupCount') },
                { property: 'status', label: this.$tc('mh-dashboard.grid.status') }
            ];
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
                const [messagesResponse, metricsResponse] = await Promise.all([
                    this.mhDashboardApiService.listMessages({
                        status: this.selectedStatus,
                        searchTerm: this.searchTerm.trim(),
                        page: this.page,
                        limit: this.selectedLimit,
                        topicGroup: this.selectedTopicGroup,
                        createdFrom: range.createdFrom,
                        createdTo: range.createdTo,
                        messageClass: this.messageClass.trim(),
                        transportName: this.transportName.trim(),
                        businessReference: this.businessReference.trim()
                    }),
                    this.mhDashboardApiService.loadMetrics()
                ]);

                this.messages = messagesResponse.data.data || [];
                this.total = messagesResponse.data.total || 0;
                this.metrics = metricsResponse.data || {};
            } catch (error) {
                this.messages = [];
                this.metrics = {};
                this.total = 0;

                this.createNotificationError({
                    title: this.$tc('mh-dashboard.notifications.title'),
                    message: this.resolveErrorMessage(error, this.$tc('mh-dashboard.notifications.loadDataError'))
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

        selectTopicGroup(topicGroup) {
            this.selectedTopicGroup = topicGroup;
            this.page = 1;
            this.loadAll();
        },

        applyFilters() {
            this.page = 1;
            this.loadAll();
        },

        resolveTimeRange() {
            if (!this.selectedTimeRange) {
                return { createdFrom: '', createdTo: '' };
            }

            const now = new Date();
            const from = new Date(now);

            if (this.selectedTimeRange === '1d') {
                from.setDate(now.getDate() - 1);
            }

            if (this.selectedTimeRange === '7d') {
                from.setDate(now.getDate() - 7);
            }

            if (this.selectedTimeRange === '30d') {
                from.setDate(now.getDate() - 30);
            }

            if (this.selectedTimeRange === '90d') {
                from.setDate(now.getDate() - 90);
            }

            return {
                createdFrom: from.toISOString().slice(0, 10),
                createdTo: now.toISOString().slice(0, 10)
            };
        },

        async cleanupOldEntries() {
            try {
                const response = await this.mhDashboardApiService.cleanupMessages(this.cleanupDays);
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
});
