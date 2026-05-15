const { Component } = Shopware;

Component.register('mh-dashboard-failed', {
    inject: ['mhDashboardApiService'],
    mixins: [
        Shopware.Mixin.getByName('notification')
    ],
    template: `
        <sw-page class="mh-dashboard-failed">
            <template #smart-bar-header>
                <h2>{{ $tc('mh-dashboard.failed.header') }}</h2>
            </template>

            <template #content>
                <sw-card :title="$tc('mh-dashboard.failed.title')">
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
                        {{ $tc('mh-dashboard.failed.empty') }}
                    </div>
                </sw-card>
            </template>
        </sw-page>
    `,
    data() {
        return {
            messages: [],
            isLoading: false,
            total: 0,
            page: 1,
            searchTerm: '',
            messageClass: '',
            transportName: '',
            businessReference: '',
            selectedTopicGroup: '',
            selectedTimeRange: '30d',
            selectedLimit: 25,
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

        timeRangeOptions() {
            return [
                { value: '', label: this.$tc('mh-dashboard.timeRanges.all') },
                { value: '1d', label: this.$tc('mh-dashboard.timeRanges.1d') },
                { value: '7d', label: this.$tc('mh-dashboard.timeRanges.7d') },
                { value: '30d', label: this.$tc('mh-dashboard.timeRanges.30d') },
                { value: '90d', label: this.$tc('mh-dashboard.timeRanges.90d') }
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
        this.loadMessages();
    },
    methods: {
        async loadMessages() {
            this.isLoading = true;

            try {
                const range = this.resolveTimeRange();
                const response = await this.mhDashboardApiService.listMessages({
                    status: 'failed',
                    searchTerm: this.searchTerm.trim(),
                    page: this.page,
                    limit: this.selectedLimit,
                    topicGroup: this.selectedTopicGroup,
                    createdFrom: range.createdFrom,
                    createdTo: range.createdTo,
                    messageClass: this.messageClass.trim(),
                    transportName: this.transportName.trim(),
                    businessReference: this.businessReference.trim()
                });

                this.messages = response.data.data || [];
                this.total = response.data.total || 0;
            } catch (error) {
                this.messages = [];
                this.total = 0;

                this.createNotificationError({
                    title: this.$tc('mh-dashboard.notifications.title'),
                    message: this.resolveErrorMessage(error, this.$tc('mh-dashboard.notifications.loadFailedError'))
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

                await this.loadMessages();
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
            this.loadMessages();
        },

        onLimitChange(limit) {
            this.selectedLimit = Number(limit);
            this.page = 1;
            this.loadMessages();
        },

        applyFilters() {
            this.page = 1;
            this.loadMessages();
        },

        resolveErrorMessage(error, fallbackMessage) {
            return error?.response?.data?.errors?.[0]?.detail || error?.message || fallbackMessage;
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
        }
    }
});
