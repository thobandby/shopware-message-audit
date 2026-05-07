const { Component } = Shopware;

Component.register('mh-dashboard-detail', {
    inject: ['mhDashboardApiService'],
    mixins: [
        Shopware.Mixin.getByName('notification')
    ],
    template: `
        <sw-page class="mh-dashboard-detail">
            <template #smart-bar-header>
                <h2>Nachrichtendetails</h2>
            </template>

            <template #smart-bar-actions>
                <sw-button @click="goBack">Zur Übersicht</sw-button>
                <sw-button variant="primary" @click="runAction('retry')" :disabled="!canRetry">Erneut senden</sw-button>
                <sw-button @click="runAction('quarantine')" :disabled="!canQuarantine">Ausblenden</sw-button>
                <sw-button @click="runAction('dismiss')" :disabled="!canDismiss">Erledigen</sw-button>
            </template>

            <template #content>
                <sw-card v-if="message" title="Nachricht">
                    <dl style="display:grid;grid-template-columns:220px 1fr;gap:12px 16px;">
                        <dt>ID</dt><dd>{{ message.id }}</dd>
                        <dt>Erstellt</dt><dd>{{ message.created_at }}</dd>
                        <dt>Aktualisiert</dt><dd>{{ message.updated_at }}</dd>
                        <dt>Bereich</dt><dd>{{ message.topic_group }}</dd>
                        <dt>Typ</dt><dd>{{ message.message_type }}</dd>
                        <dt>Name</dt><dd>{{ message.message_name }}</dd>
                        <dt>Bedeutung</dt><dd>{{ message.business_summary }}</dd>
                        <dt>Wirkung</dt><dd>{{ message.business_impact }}</dd>
                        <dt>Klasse</dt><dd>{{ message.message_class }}</dd>
                        <dt>Status</dt><dd>{{ message.status_label || message.status }}</dd>
                        <dt>Versuche</dt><dd>{{ message.retry_count }}</dd>
                    </dl>
                </sw-card>

                <sw-card v-if="message" title="Inhalt" style="margin-top:16px;">
                    <pre style="white-space:pre-wrap;word-break:break-word;">{{ message.payload_json }}</pre>
                </sw-card>

                <sw-card title="Verlauf" style="margin-top:16px;">
                    <sw-data-grid
                        v-if="transitions.length > 0"
                        :data-source="transitions"
                        :columns="transitionColumns"
                        :show-selection="false"
                        :show-actions="false">
                    </sw-data-grid>
                    <div v-else>Kein Verlauf vorhanden.</div>
                </sw-card>

                <sw-card title="Fehler" style="margin-top:16px;">
                    <sw-data-grid
                        v-if="failures.length > 0"
                        :data-source="failures"
                        :columns="failureColumns"
                        :show-selection="false"
                        :show-actions="false">
                    </sw-data-grid>
                    <div v-else>Keine Fehler vorhanden.</div>
                </sw-card>

                <sw-card title="Aktionen" style="margin-top:16px;">
                    <sw-data-grid
                        v-if="actions.length > 0"
                        :data-source="actions"
                        :columns="actionColumns"
                        :show-selection="false"
                        :show-actions="false">
                    </sw-data-grid>
                    <div v-else>Keine Aktionen vorhanden.</div>
                </sw-card>
            </template>
        </sw-page>
    `,
    data() {
        return {
            message: null,
            transitions: [],
            failures: [],
            actions: [],
            transitionColumns: [
                { property: 'created_at', label: 'Zeitpunkt' },
                { property: 'event', label: 'Ereignis' }
            ],
            failureColumns: [
                { property: 'created_at', label: 'Zeitpunkt' },
                { property: 'exception_class', label: 'Fehlerklasse' },
                { property: 'error_message', label: 'Fehlermeldung' }
            ],
            actionColumns: [
                { property: 'created_at', label: 'Zeitpunkt' },
                { property: 'action', label: 'Aktion' },
                { property: 'reason', label: 'Grund' }
            ]
        };
    },
    computed: {
        messageId() {
            return this.$route.params.id;
        },

        canRetry() {
            return this.message && this.message.status === 'failed';
        },

        canQuarantine() {
            return this.message && ['failed', 'received', 'dispatched'].includes(this.message.status);
        },

        canDismiss() {
            return this.message && ['failed', 'received', 'dispatched', 'handled'].includes(this.message.status);
        }
    },
    created() {
        void this.loadDetail();
    },
    methods: {
        async loadDetail() {
            try {
                const response = await this.mhDashboardApiService.getMessageDetail(this.messageId);

                this.message = response.data.message || null;
                this.transitions = response.data.transitions || [];
                this.failures = response.data.failures || [];
                this.actions = response.data.actions || [];
            } catch (error) {
                this.createNotificationError({
                    title: 'Messenger Audit',
                    message: 'Die Details konnten nicht geladen werden.'
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
                    title: 'Messenger Audit',
                    message: 'Die Aktion konnte nicht ausgeführt werden.'
                });
            }
        },

        goBack() {
            this.$router.push({ name: 'mh.dashboard.index' });
        }
    }
});
