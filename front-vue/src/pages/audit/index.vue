<script setup>
/* eslint-disable camelcase */
import { onMounted, ref } from 'vue'
import { useServerTable } from '@/composables/useServerTable'
import PageHeader from '@/components/common/PageHeader.vue'
import ServerDataTable from '@/components/common/ServerDataTable.vue'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import AuditDetailDrawer from '@/modules/audit/components/AuditDetailDrawer.vue'
import { useAuditStore } from '@/modules/audit/stores/auditStore'
import { EVENT_OPTIONS, formatEventLabel, formatSubjectType, getEventColor } from '@/modules/audit/utils/events'

definePage({
  meta: {
    action: 'audit.view',
    title: 'Auditoría',
    icon: 'tabler-history',
    description: 'Historial inmutable de eventos y cambios en las entidades del sistema',
  },
})

const auditStore = useAuditStore()

const isDrawerOpen = ref(false)
const selectedAudit = ref(null)

const table = useServerTable({
  fetch: params => auditStore.fetchAuditLogs(params),
  filters: { event: null, subject_type: null },
})

const headers = [
  { title: 'EVENTO', key: 'event', sortable: false, alwaysVisible: true },
  { title: 'CANAL', key: 'log_name', sortable: false },
  { title: 'ENTIDAD', key: 'subject_type', sortable: false },
  { title: 'CAUSANTE', key: 'causer', sortable: false },
  { title: 'FECHA', key: 'created_at', sortable: false },
  { title: 'ACCIONES', key: 'actions', sortable: false, align: 'center' },
]

onMounted(() => {
  auditStore.fetchSubjectTypes().catch(() => {})
})

const openDetailDrawer = log => {
  selectedAudit.value = log
  isDrawerOpen.value = true
}

const eventLabel = item => formatEventLabel(item.event, item.description)

const eventColor = item => getEventColor(item?.event, item?.description)
</script>

<template>
  <div>
    <VCard>
      <VCardText>
        <PageHeader />
      </VCardText>

      <VDivider />

      <ServerDataTable
        v-model:options="table.options"
        v-model:search="table.search"
        table-id="audit"
        :headers="headers"
        :items="auditStore.items"
        :total-items="auditStore.meta.total"
        :loading="auditStore.isLoading"
        :has-filters="table.hasFilters"
        search-placeholder="Sede Central"
        @refresh="table.reload"
      >
        <template #filters>
          <VSelect
            v-model="table.filters.event"
            :items="EVENT_OPTIONS"
            label="Evento"
            placeholder="Actualización"
            density="compact"
            clearable
          />
          <VAutocomplete
            v-model="table.filters.subject_type"
            :items="auditStore.subjectTypes"
            label="Entidad"
            placeholder="BankAccount"
            density="compact"
            clearable
          />
        </template>

        <template #item.event="{ item }">
          <VChip
            :color="eventColor(item)"
            size="small"
          >
            {{ eventLabel(item) }}
          </VChip>
        </template>

        <template #item.log_name="{ item }">
          <span class="text-caption">{{ item.log_name || 'default' }}</span>
        </template>

        <template #item.subject_type="{ item }">
          <div class="font-weight-medium">
            {{ formatSubjectType(item.subject_type) }}
          </div>
          <div
            class="text-caption text-medium-emphasis font-family-monospace text-truncate"
            style="max-inline-size: 180px;"
          >
            {{ item.subject_id || '—' }}
          </div>
        </template>

        <template #item.causer="{ item }">
          <div
            v-if="item.causer"
            class="d-flex flex-column"
          >
            <span class="font-weight-medium">{{ item.causer.name }}</span>
            <span class="text-caption text-medium-emphasis">{{ item.causer.email }}</span>
          </div>
          <span
            v-else
            class="text-caption text-medium-emphasis"
          >Sistema</span>
        </template>

        <template #item.created_at="{ item }">
          <span class="text-caption">
            {{ item.created_at ? new Date(item.created_at).toLocaleString('es-PE') : '—' }}
          </span>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex justify-center gap-2">
            <RowActionButton
              action="view"
              @click="openDetailDrawer(item)"
            />
          </div>
        </template>
      </ServerDataTable>
    </VCard>

    <AuditDetailDrawer
      v-model:is-drawer-open="isDrawerOpen"
      :audit-log="selectedAudit"
    />
  </div>
</template>
