const { Component } = Shopware;

Component.register('mh-dashboard-failed', {
    inject: ['mhDashboardApiService'],
    mixins: [
        Shopware.Mixin.getByName('notification')
    ],
    template: `
        <sw-page class="mh-dashboard-failed">
            <template #smart-bar-header>
                <h2>Fehlgeschlagene Nachrichten</h2>
            </template>

            <template #content>
                <sw-card title="Fehlgeschlagene Nachrichten">
                    <div style="display:grid;grid-template-columns:repeat(3,minmax(220px,1fr));gap:16px;align-items:end;margin-bottom:16px;">
                        <sw-text-field
                            v-model:value="searchTerm"
                            label="Suche"
                            placeholder="Nachricht suchen">
                        </sw-text-field>

                        <sw-text-field
                            v-model:value="messageClass"
                            label="Klasse"
                            placeholder="z. B. Order">
                        </sw-text-field>

                        <sw-text-field
                            v-model:value="businessReference"
                            label="Business-Referenz"
                            placeholder="z. B. Bestellnummer">
                        </sw-text-field>

                        <sw-single-select
                            v-model:value="selectedTopicGroup"
                            label="Bereich"
                            :options="topicGroupOptions">
                        </sw-single-select>

                        <sw-text-field
                            v-model:value="transportName"
                            label="Transport"
                            placeholder="z. B. async">
                        </sw-text-field>

                        <sw-single-select
                            v-model:value="selectedTimeRange"
                            label="Zeitraum"
                            :options="timeRangeOptions">
                        </sw-single-select>

                        <sw-single-select
                            v-model:value="selectedLimit"
                            label="Pro Seite"
                            :options="limitOptions">
                        </sw-single-select>
                    </div>

                    <div style="display:flex;justify-content:flex-end;margin-bottom:16px;">
                        <sw-button variant="primary" @click="applyFilters">Filter anwenden</sw-button>
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

                        <template #actions="{ item }">
                            <sw-context-menu-item
                                :router-link="{ name: 'mh.dashboard.detail', params: { id: item.id } }">
                                Details
                            </sw-context-menu-item>
                            <sw-context-menu-item
                                :disabled="item.status !== 'failed'"
                                @click="runAction('retry', item)">
                                Erneut senden
                            </sw-context-menu-item>
                            <sw-context-menu-item
                                :disabled="!['failed', 'received', 'dispatched'].includes(item.status)"
                                @click="runAction('quarantine', item)">
                                Ausblenden
                            </sw-context-menu-item>
                            <sw-context-menu-item
                                :disabled="!['failed', 'received', 'dispatched', 'handled'].includes(item.status)"
                                @click="runAction('dismiss', item)">
                                Erledigen
                            </sw-context-menu-item>
                        </template>

                        <template #pagination>
                            <sw-pagination
                                :page="page"
                                :total="total"
                                :limit="selectedLimit"
                                :auto-hide="false"
                                @page-change="onPageChange">
                            </sw-pagination>
                        </template>
                    </sw-data-grid>

                    <div v-else style="margin-top:16px;">
                        Keine fehlgeschlagenen Nachrichten vorhanden.
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
            topicGroupOptions: [
                { value: '', label: 'Alle Bereiche' },
                { value: 'Bestellungen', label: 'Bestellungen' },
                { value: 'Zahlungen', label: 'Zahlungen' },
                { value: 'System', label: 'System' }
            ],
            timeRangeOptions: [
                { value: '', label: 'Gesamter Zeitraum' },
                { value: '1d', label: 'Letzte 24 Stunden' },
                { value: '7d', label: 'Letzte 7 Tage' },
                { value: '30d', label: 'Letzte 30 Tage' },
                { value: '90d', label: 'Letzte 90 Tage' }
            ],
            limitOptions: [
                { value: 10, label: '10' },
                { value: 25, label: '25' },
                { value: 50, label: '50' },
                { value: 100, label: '100' }
            ],
            columns: [
                { property: 'created_at', label: 'Erstellt', primary: true },
                { property: 'message_type', label: 'Typ' },
                { property: 'status', label: 'Status' },
                { property: 'retry_count', label: 'Versuche' },
                { property: 'available_actions', label: 'Mögliche Aktion' }
            ]
        };
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
                    title: 'Messenger Audit',
                    message: this.resolveErrorMessage(error, 'Die fehlgeschlagenen Nachrichten konnten nicht geladen werden.')
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
                    title: 'Messenger Audit',
                    message: this.resolveErrorMessage(error, 'Die Aktion konnte nicht ausgeführt werden.')
                });
            }
        },

        onPageChange(page) {
            this.page = page;
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
