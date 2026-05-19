export function buildEntryFilterOptions(vm) {
    return [
        { value: '', label: vm.$tc('mh-dashboard.entryFilters.all') },
        { value: 'shopware_messenger', label: vm.$tc('mh-dashboard.entryFilters.shopwareMessenger') },
        { value: 'internal_jobs', label: vm.$tc('mh-dashboard.entryFilters.internalJobs') },
        { value: 'orders', label: vm.$tc('mh-dashboard.entryFilters.orders') },
        { value: 'payments', label: vm.$tc('mh-dashboard.entryFilters.payments') }
    ];
}

export const sharedFilterFieldsTemplate = `
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
        v-model:value="selectedEntryFilter"
        :label="$tc('mh-dashboard.filters.entryFilter')"
        :options="entryFilterOptions">
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
`;

export function buildGridTemplate(emptyTranslationKey) {
    return `
        <sw-data-grid
            v-if="messages.length > 0"
            :data-source="messages"
            :columns="columns"
            :show-selection="false">
            <template #column-created_at="{ item }">
                {{ formatDateTime(item.created_at) }}
            </template>

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
            {{ $tc('${emptyTranslationKey}') }}
        </div>
    `;
}

export function buildTimeRangeOptions(vm) {
    return [
        { value: '', label: vm.$tc('mh-dashboard.timeRanges.all') },
        { value: '1d', label: vm.$tc('mh-dashboard.timeRanges.1d') },
        { value: '7d', label: vm.$tc('mh-dashboard.timeRanges.7d') },
        { value: '30d', label: vm.$tc('mh-dashboard.timeRanges.30d') },
        { value: '90d', label: vm.$tc('mh-dashboard.timeRanges.90d') }
    ];
}

export function buildGridColumns(vm) {
    return [
        { property: 'created_at', label: vm.$tc('mh-dashboard.grid.createdAt'), primary: true },
        { property: 'message_name', label: vm.$tc('mh-dashboard.grid.name') },
        { property: 'message_type', label: vm.$tc('mh-dashboard.grid.type') },
        { property: 'group_count', label: vm.$tc('mh-dashboard.grid.groupCount') },
        { property: 'status', label: vm.$tc('mh-dashboard.grid.status') }
    ];
}

export function resolveDashboardTimeRange(selectedTimeRange) {
    if (!selectedTimeRange) {
        return { createdFrom: '', createdTo: '' };
    }

    const now = new Date();
    const from = new Date(now);

    if (selectedTimeRange === '1d') {
        from.setDate(now.getDate() - 1);
    }

    if (selectedTimeRange === '7d') {
        from.setDate(now.getDate() - 7);
    }

    if (selectedTimeRange === '30d') {
        from.setDate(now.getDate() - 30);
    }

    if (selectedTimeRange === '90d') {
        from.setDate(now.getDate() - 90);
    }

    return {
        createdFrom: from.toISOString().slice(0, 10),
        createdTo: now.toISOString().slice(0, 10)
    };
}

export function formatDashboardDateTime(value, locale, timeZone) {
    if (!value) {
        return '-';
    }

    const date = new Date(String(value).replace(' ', 'T') + 'Z');

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat(locale, {
        timeZone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    }).format(date);
}
