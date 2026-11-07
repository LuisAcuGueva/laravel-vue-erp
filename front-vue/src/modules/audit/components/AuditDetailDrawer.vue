<script setup>
import DrawerHeader from '@/components/common/DrawerHeader.vue'
import { formatEventLabel, formatSubjectType, getEventColor } from '@/modules/audit/utils/events'
import { useScrollLock } from '@vueuse/core'
import { computed, onBeforeUnmount, watch } from 'vue'

const props = defineProps({
  isDrawerOpen: {
    type: Boolean,
    required: true,
  },
  auditLog: {
    type: Object,
    default: null,
  },
})

const emit = defineEmits(['update:isDrawerOpen'])

// Con el panel abierto se bloquea el scroll de la página para que solo
// quede visible el scroll interno del detalle.
const isPageScrollLocked = useScrollLock(document.documentElement)

watch(() => props.isDrawerOpen, open => {
  isPageScrollLocked.value = open
}, { immediate: true })

onBeforeUnmount(() => {
  isPageScrollLocked.value = false
})

const closeDrawer = () => {
  emit('update:isDrawerOpen', false)
}

const eventLabel = computed(() => {
  return formatEventLabel(props.auditLog?.event, props.auditLog?.description)
})

const eventColor = computed(() => {
  return getEventColor(props.auditLog?.event, props.auditLog?.description)
})

const shortSubjectType = computed(() => formatSubjectType(props.auditLog?.subject_type))

const changes = computed(() => {
  const propsObj = props.auditLog?.properties || {}
  const oldVals = propsObj.old || {}
  const newVals = propsObj.attributes || {}

  const keys = Array.from(new Set([...Object.keys(oldVals), ...Object.keys(newVals)]))

  return keys.map(key => ({
    field: key,
    old: oldVals[key] !== undefined ? JSON.stringify(oldVals[key]) : '—',
    new: newVals[key] !== undefined ? JSON.stringify(newVals[key]) : '—',
  }))
})
</script>

<template>
  <VNavigationDrawer
    temporary
    location="end"
    :model-value="props.isDrawerOpen"
    width="500"
    class="scrollable-content"
    @update:model-value="val => emit('update:isDrawerOpen', val)"
  >
    <DrawerHeader
      title="Detalle de Auditoría"
      icon="tabler-history"
      @close="closeDrawer"
    />

    <div class="audit-drawer-scrollable">
      <div
        v-if="props.auditLog"
        class="pa-4"
      >
        <VCard
          variant="outlined"
          class="mb-4"
        >
          <VCardText class="d-flex flex-column gap-2">
            <div class="d-flex justify-space-between align-center">
              <span class="text-caption text-medium-emphasis">Evento:</span>
              <VChip
                :color="eventColor"
                size="small"
              >
                {{ eventLabel }}
              </VChip>
            </div>

            <div class="d-flex justify-space-between">
              <span class="text-caption text-medium-emphasis">Canal / Log:</span>
              <span class="font-weight-medium">{{ props.auditLog.log_name || 'default' }}</span>
            </div>

            <div class="d-flex justify-space-between">
              <span class="text-caption text-medium-emphasis">Fecha y hora:</span>
              <span class="font-weight-medium">{{ props.auditLog.created_at ? new Date(props.auditLog.created_at).toLocaleString() : '—' }}</span>
            </div>

            <div class="d-flex justify-space-between">
              <span class="text-caption text-medium-emphasis">Causante:</span>
              <span class="font-weight-medium">
                {{ props.auditLog.causer?.name ? `${props.auditLog.causer.name} (${props.auditLog.causer.email})` : 'Sistema / Anónimo' }}
              </span>
            </div>

            <div class="d-flex justify-space-between">
              <span class="text-caption text-medium-emphasis">Entidad:</span>
              <span class="font-weight-medium">{{ shortSubjectType }}</span>
            </div>

            <div class="d-flex justify-space-between">
              <span class="text-caption text-medium-emphasis">ID Entidad:</span>
              <span
                class="font-family-monospace text-caption text-truncate"
                style="max-width: 250px;"
              >
                {{ props.auditLog.subject_id || '—' }}
              </span>
            </div>
          </VCardText>
        </VCard>

        <h6 class="text-subtitle-1 font-weight-medium mb-2">
          Cambios Registrados
        </h6>

        <div v-if="changes.length > 0">
          <VTable
            density="compact"
            class="border rounded"
          >
            <thead>
              <tr>
                <th>Campo</th>
                <th>Valor Anterior</th>
                <th>Valor Nuevo</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="item in changes"
                :key="item.field"
              >
                <td class="font-weight-medium text-caption">
                  {{ item.field }}
                </td>
                <td class="text-error text-caption font-family-monospace">
                  {{ item.old }}
                </td>
                <td class="text-success text-caption font-family-monospace">
                  {{ item.new }}
                </td>
              </tr>
            </tbody>
          </VTable>
        </div>

        <div
          v-else
          class="text-caption text-medium-emphasis pa-4 text-center border rounded"
        >
          No se registraron diferencias de atributos (ej. creación sin estado previo).
        </div>

        <div class="mt-4">
          <span class="text-caption text-medium-emphasis">Carga útil sin procesar:</span>
          <pre class="audit-raw-payload bg-surface-variant pa-2 rounded text-caption mt-1">{{ JSON.stringify(props.auditLog.properties, null, 2) }}</pre>
        </div>
      </div>
    </div>
  </VNavigationDrawer>
</template>

<style lang="scss">
.audit-drawer-scrollable {
  flex-grow: 1;
  max-block-size: calc(100vh - 65px);
  overflow-y: auto;
}

.audit-raw-payload {
  max-block-size: 320px;
  overflow: auto;
  overscroll-behavior: contain;
}
</style>
