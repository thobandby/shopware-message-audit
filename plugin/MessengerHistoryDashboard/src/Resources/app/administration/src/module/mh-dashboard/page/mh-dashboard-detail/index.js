const { Component } = Shopware;

Component.register('mh-dashboard-detail', {
    inject: ['mhDashboardApiService'],
    mixins: [
        Shopware.Mixin.getByName('notification')
    ],
    template: `
        <sw-page class="mh-dashboard-detail">
            <template #smart-bar-header>
                <h2>{{ $tc('mh-dashboard.detail.header') }}</h2>
            </template>

            <template #smart-bar-actions>
                <sw-button @click="goBack">{{ $tc('mh-dashboard.detail.back') }}</sw-button>
                <sw-button variant="primary" @click="runAction('retry')" :disabled="!canRetry">{{ $tc('mh-dashboard.actions.retry') }}</sw-button>
                <sw-button @click="runAction('quarantine')" :disabled="!canQuarantine">{{ $tc('mh-dashboard.actions.quarantine') }}</sw-button>
                <sw-button @click="runAction('dismiss')" :disabled="!canDismiss">{{ $tc('mh-dashboard.actions.dismiss') }}</sw-button>
            </template>

            <template #content>
                <sw-card v-if="message" :title="$tc('mh-dashboard.detail.entryTitle')">
                    <dl style="display:grid;grid-template-columns:220px 1fr;gap:12px 16px;">
                        <dt>{{ $tc('mh-dashboard.detail.fields.id') }}</dt><dd>{{ message.id }}</dd>
                        <dt>{{ $tc('mh-dashboard.detail.fields.source') }}</dt><dd>{{ message.source_label || '-' }}</dd>
                        <dt>{{ $tc('mh-dashboard.detail.fields.createdAt') }}</dt><dd>{{ formatDateTime(message.created_at) }}</dd>
                        <dt>{{ $tc('mh-dashboard.detail.fields.updatedAt') }}</dt><dd>{{ formatDateTime(message.updated_at) }}</dd>
                        <dt>{{ $tc('mh-dashboard.detail.fields.topicGroup') }}</dt><dd>{{ message.topic_group }}</dd>
                        <dt>{{ $tc('mh-dashboard.detail.fields.type') }}</dt><dd>{{ message.message_type }}</dd>
                        <dt>{{ $tc('mh-dashboard.detail.fields.name') }}</dt><dd>{{ message.message_name }}</dd>
                        <dt>{{ $tc('mh-dashboard.detail.fields.summary') }}</dt><dd>{{ message.business_summary }}</dd>
                        <dt>{{ $tc('mh-dashboard.detail.fields.impact') }}</dt><dd>{{ message.business_impact }}</dd>
                        <dt>{{ $tc('mh-dashboard.detail.fields.reference') }}</dt><dd>{{ message.business_reference || '-' }}</dd>
                        <dt>{{ $tc('mh-dashboard.detail.fields.class') }}</dt><dd>{{ message.message_class }}</dd>
                        <dt>{{ $tc('mh-dashboard.detail.fields.correlation') }}</dt><dd>{{ message.correlation_id || '-' }}</dd>
                        <dt>{{ $tc('mh-dashboard.detail.fields.causation') }}</dt><dd>{{ message.causation_id || '-' }}</dd>
                        <dt>{{ $tc('mh-dashboard.detail.fields.transport') }}</dt><dd>{{ message.transport_name || '-' }}</dd>
                        <dt>{{ $tc('mh-dashboard.detail.fields.status') }}</dt><dd>{{ message.status_label || message.status }}</dd>
                        <dt>{{ $tc('mh-dashboard.detail.fields.retryCount') }}</dt><dd>{{ message.attempt_count ?? message.retry_count }}</dd>
                    </dl>
                </sw-card>

                <sw-card v-if="message" :title="$tc('mh-dashboard.detail.contentTitle')" style="margin-top:16px;">
                    <pre style="white-space:pre-wrap;word-break:break-word;">{{ message.payload_json }}</pre>
                </sw-card>

                <sw-card :title="$tc('mh-dashboard.detail.historyTitle')" style="margin-top:16px;">
                    <sw-data-grid
                        v-if="transitions.length > 0"
                        :data-source="transitions"
                        :columns="transitionColumns"
                        :show-selection="false"
                        :show-actions="false">
                        <template #column-created_at="{ item }">
                            {{ formatDateTime(item.created_at) }}
                        </template>
                    </sw-data-grid>
                    <div v-else>{{ $tc('mh-dashboard.detail.noHistory') }}</div>
                </sw-card>

                <sw-card v-if="relatedMessages.length > 0" :title="$tc('mh-dashboard.detail.relatedTitle')" style="margin-top:16px;">
                    <sw-data-grid
                        :data-source="relatedMessages"
                        :columns="relatedMessageColumns"
                        :show-selection="false"
                        :show-actions="false">
                        <template #column-created_at="{ item }">
                            {{ formatDateTime(item.created_at) }}
                        </template>
                    </sw-data-grid>
                </sw-card>

                <sw-card :title="$tc('mh-dashboard.detail.errorsTitle')" style="margin-top:16px;">
                    <sw-data-grid
                        v-if="failures.length > 0"
                        :data-source="failures"
                        :columns="failureColumns"
                        :show-selection="false"
                        :show-actions="false">
                        <template #column-created_at="{ item }">
                            {{ formatDateTime(item.created_at) }}
                        </template>
                    </sw-data-grid>
                    <div v-else>{{ $tc('mh-dashboard.detail.noErrors') }}</div>
                </sw-card>

                <sw-card :title="$tc('mh-dashboard.detail.actionsTitle')" style="margin-top:16px;">
                    <sw-data-grid
                        v-if="actions.length > 0"
                        :data-source="actions"
                        :columns="actionColumns"
                        :show-selection="false"
                        :show-actions="false">
                        <template #column-created_at="{ item }">
                            {{ formatDateTime(item.created_at) }}
                        </template>
                    </sw-data-grid>
                    <div v-else>{{ $tc('mh-dashboard.detail.noActions') }}</div>
                </sw-card>
            </template>
        </sw-page>
    `,
    data() {
        return {
            message: null,
            relatedMessages: [],
            transitions: [],
            failures: [],
            actions: []
        };
    },
    computed: {
        currentLocale() {
            return Shopware.Store.get('session').currentLocale || 'de-DE';
        },

        currentTimeZone() {
            return Shopware.Store.get('session').currentUser?.timeZone || 'UTC';
        },

        messageId() {
            return this.$route.params.id;
        },

        canRetry() {
            return this.message?.allowed_actions?.includes('retry') ?? false;
        },

        canQuarantine() {
            return this.message?.allowed_actions?.includes('quarantine') ?? false;
        },

        canDismiss() {
            return this.message?.allowed_actions?.includes('dismiss') ?? false;
        },

        relatedMessageColumns() {
            return [
                { property: 'created_at', label: this.$tc('mh-dashboard.grid.timestamp') },
                { property: 'message_type', label: this.$tc('mh-dashboard.grid.type') },
                { property: 'message_name', label: this.$tc('mh-dashboard.grid.process') },
                { property: 'status_label', label: this.$tc('mh-dashboard.grid.status') }
            ];
        },

        transitionColumns() {
            return [
                { property: 'created_at', label: this.$tc('mh-dashboard.grid.timestamp') },
                { property: 'event', label: this.$tc('mh-dashboard.grid.event') }
            ];
        },

        failureColumns() {
            return [
                { property: 'created_at', label: this.$tc('mh-dashboard.grid.timestamp') },
                { property: 'exception_class', label: this.$tc('mh-dashboard.grid.errorClass') },
                { property: 'error_message', label: this.$tc('mh-dashboard.grid.errorMessage') }
            ];
        },

        actionColumns() {
            return [
                { property: 'created_at', label: this.$tc('mh-dashboard.grid.timestamp') },
                { property: 'action', label: this.$tc('mh-dashboard.grid.action') },
                { property: 'reason', label: this.$tc('mh-dashboard.grid.reason') },
                { property: 'operator_label', label: this.$tc('mh-dashboard.grid.operator') }
            ];
        }
    },
    created() {
        this.loadDetail();
    },
    methods: {
        async loadDetail() {
            try {
                const response = await this.mhDashboardApiService.getMessageDetail(this.messageId);

                this.message = response.data.message || null;
                this.relatedMessages = response.data.relatedMessages || [];
                this.transitions = response.data.transitions || [];
                this.failures = response.data.failures || [];
                this.actions = response.data.actions || [];
            } catch (error) {
                this.createNotificationError({
                    title: this.$tc('mh-dashboard.notifications.title'),
                    message: this.resolveErrorMessage(error, this.$tc('mh-dashboard.notifications.loadDetailError'))
                });
            }
        },

        async runAction(action) {
            try {
                if (action === 'retry') {
                    await this.mhDashboardApiService.retryMessage(this.messageId);
                }

                if (action === 'quarantine') {
                    await this.mhDashboardApiService.quarantineMessage(this.messageId);
                }

                if (action === 'dismiss') {
                    await this.mhDashboardApiService.dismissMessage(this.messageId);
                }

                await this.loadDetail();
            } catch (error) {
                this.createNotificationError({
                    title: this.$tc('mh-dashboard.notifications.title'),
                    message: this.resolveErrorMessage(error, this.$tc('mh-dashboard.notifications.actionError'))
                });
            }
        },

        resolveErrorMessage(error, fallbackMessage) {
            return error?.response?.data?.errors?.[0]?.detail || error?.message || fallbackMessage;
        },

        formatDateTime(value) {
            if (!value) {
                return '-';
            }

            const date = new Date(String(value).replace(' ', 'T') + 'Z');

            if (Number.isNaN(date.getTime())) {
                return value;
            }

            return new Intl.DateTimeFormat(this.currentLocale, {
                timeZone: this.currentTimeZone,
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            }).format(date);
        },

        goBack() {
            this.$router.push({ name: 'mh.dashboard.index' });
        }
    }
});
