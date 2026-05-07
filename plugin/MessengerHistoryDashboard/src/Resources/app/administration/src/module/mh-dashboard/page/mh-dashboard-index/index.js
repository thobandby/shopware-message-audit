const { Component } = Shopware;

Component.register('mh-dashboard-index', {
    inject: ['mhDashboardApiService'],
    mixins: [
        Shopware.Mixin.getByName('notification')
    ],
    template: `
        <sw-page class="mh-dashboard-index">
            <template #smart-bar-header>
                <h2>Messenger Audit</h2>
            </template>

            <template #content>
                <sw-card title="Metrics">
                    <sw-button @click="loadAll">Neu laden</sw-button>

                    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-top:16px;">
                        <sw-card title="Total"><strong>{{ metrics.total || 0 }}</strong></sw-card>
                        <sw-card title="Failed"><strong>{{ metrics.failed || 0 }}</strong></sw-card>
                        <sw-card title="Handled"><strong>{{ metrics.handled || 0 }}</strong></sw-card>
                        <sw-card title="Received"><strong>{{ metrics.received || 0 }}</strong></sw-card>
                    </div>
                </sw-card>

                <sw-card title="Messages" style="margin-top:16px;">
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

                    <div style="display:grid;grid-template-columns:minmax(220px,2fr) minmax(160px,1fr) minmax(220px,1.2fr) minmax(120px,0.8fr);gap:16px;align-items:end;margin-bottom:16px;">
                        <sw-text-field
                            v-model:value="searchTerm"
                            label="Suche"
                            placeholder="Nachricht suchen">
                        </sw-text-field>

                        <sw-single-select
                            v-model:value="selectedStatus"
                            label="Status"
                            :options="statusOptions">
                        </sw-single-select>

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

                    <div style="display:grid;grid-template-columns:minmax(220px,1fr) auto auto;gap:16px;align-items:end;margin-bottom:16px;padding:16px;border:1px solid #d1d9e0;border-radius:8px;background:#f8fafc;">
                        <div style="color:#52667a;font-size:14px;">
                            {{ total }} Einträge im aktuellen Filter
                        </div>

                        <sw-single-select
                            v-model:value="cleanupDays"
                            label="Einträge löschen älter als"
                            :options="cleanupOptions">
                        </sw-single-select>

                        <sw-button variant="danger" @click="cleanupOldEntries">Löschen</sw-button>
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
                        Keine Daten vorhanden.
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
            selectedTopicGroup: '',
            selectedStatus: '',
            selectedTimeRange: '30d',
            selectedLimit: 25,
            cleanupDays: 30,
            topicGroupOptions: [
                { value: '', label: 'Alle Bereiche' },
                { value: 'Bestellungen', label: 'Bestellungen' },
                { value: 'Zahlungen', label: 'Zahlungen' },
                { value: 'System', label: 'System' }
            ],
            statusOptions: [
                { value: '', label: 'Alle' },
                { value: 'dispatched', label: 'Gesendet' },
                { value: 'received', label: 'Empfangen' },
                { value: 'handled', label: 'Verarbeitet' },
                { value: 'failed', label: 'Fehlgeschlagen' }
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
            cleanupOptions: [
                { value: 7, label: 'Älter als 7 Tage' },
                { value: 30, label: 'Älter als 30 Tage' },
                { value: 90, label: 'Älter als 90 Tage' },
                { value: 180, label: 'Älter als 180 Tage' }
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
        void this.loadAll();
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
                        createdTo: range.createdTo
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
                    title: 'Messenger Audit',
                    message: 'Die Messenger-Daten konnten nicht geladen werden.'
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
                    title: 'Messenger Audit',
                    message: 'Die Aktion konnte nicht ausgeführt werden.'
                });
            }
        },

        onPageChange(page) {
            this.page = page;
            void this.loadAll();
        },

        selectTopicGroup(topicGroup) {
            this.selectedTopicGroup = topicGroup;
            this.page = 1;
            void this.loadAll();
        },

        applyFilters() {
            this.page = 1;
            void this.loadAll();
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
                    title: 'Messenger Audit',
                    message: `${deleted.messages || 0} Nachrichten wurden bereinigt.`
                });

                this.page = 1;
                await this.loadAll();
            } catch (error) {
                this.createNotificationError({
                    title: 'Messenger Audit',
                    message: 'Die Bereinigung konnte nicht ausgeführt werden.'
                });
            }
        }
    }
});
