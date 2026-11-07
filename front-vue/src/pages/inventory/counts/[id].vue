<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { formatDateDMY, formatDateTime12h } from '@/utils/dates'
import PageHeader from '@/components/common/PageHeader.vue'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import { useAcl } from '@/composables/useAcl'
import { useConfirm } from '@/composables/useConfirm'
import { useNotify } from '@/composables/useNotify'
import CloseCountDialog from '@/modules/inventory/components/counts/CloseCountDialog.vue'
import CountItemsGrid from '@/modules/inventory/components/counts/CountItemsGrid.vue'
import { inventoryCountsApi } from '@/modules/inventory/services/inventoryCountsApi'

definePage({
  meta: {
    action: 'inventory_counts.view',
    title: 'Detalle de toma de inventario',
    icon: 'tabler-clipboard-check',
    description: 'Registro de conteo físico, conciliación de diferencias y generación de ajustes de inventario',
  },
})

const route = useRoute()
const router = useRouter()
const countId = route.params.id

const { can } = useAcl()
const { confirm, alert } = useConfirm()
const notify = useNotify()

const count = ref(null)
const isLoading = ref(true)
const errorMessage = ref(null)
const isSavingItems = ref(false)
const isClosing = ref(false)
const isCancelling = ref(false)
const closeDialogOpen = ref(false)

const itemsGridRef = ref(null)

const isCounting = computed(() => count.value?.status === 'counting')
const isClosed = computed(() => count.value?.status === 'closed')
const isCancelled = computed(() => count.value?.status === 'cancelled')

const adjustmentNotes = computed(() => count.value?.adjustment_notes || [])

const statusColor = status => {
  if (status === 'counting') return 'info'
  if (status === 'closed') return 'success'
  if (status === 'cancelled') return 'error'
  
  return 'secondary'
}

const formatCurrency = val => {
  const num = Number(val || 0)
  
  return `S/ ${num.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

const summaryItems = computed(() => {
  const c = count.value
  if (!c) return []

  const items = [
    { icon: 'tabler-building-warehouse', label: 'Almacén', value: c.warehouse?.name || '—' },
    { icon: 'tabler-category', label: 'Alcance / Categoría', value: c.category?.name || 'Todo el almacén' },
    { icon: 'tabler-calendar', label: 'Fecha de la toma', value: formatDateDMY(c.count_date) },
    { icon: 'tabler-user', label: 'Iniciado por', value: c.creator?.name || '—' },
  ]

  if (c.closed_at) {
    items.push({ icon: 'tabler-lock', label: 'Cerrado por', value: c.closer?.name || '—' })
    items.push({ icon: 'tabler-calendar-time', label: 'Fecha / hora de cerrado', value: formatDateTime12h(c.closed_at) })
  }
  if (c.cancelled_at) {
    items.push({ icon: 'tabler-ban', label: 'Anulado por', value: c.canceller?.name || '—' })
    items.push({ icon: 'tabler-calendar-time', label: 'Fecha / hora de anulación', value: formatDateTime12h(c.cancelled_at) })
  }

  return items
})

const loadCount = async () => {
  isLoading.value = true
  errorMessage.value = null
  try {
    const res = await inventoryCountsApi.get(countId)

    count.value = res?.data || null
  }
  catch (err) {
    console.error('Error al cargar toma de inventario', err)
    errorMessage.value = err?.data?.message || err?.message || 'No se pudo cargar la toma de inventario'
  }
  finally {
    isLoading.value = false
  }
}

const handleSaveItems = async itemsToSave => {
  isSavingItems.value = true
  try {
    const res = await inventoryCountsApi.updateItems(countId, itemsToSave)

    notify.success('Conteo guardado correctamente')
    if (res?.data) {
      count.value = res.data
    } else {
      await loadCount()
    }
  }
  catch (err) {
    const message = err?.data?.message || err?.message || 'Error al guardar conteo'

    alert({ title: 'No se pudo guardar', message })
  }
  finally {
    isSavingItems.value = false
  }
}

const handleConfirmClose = async ({ treat_uncounted_as_zero }) => {
  isClosing.value = true
  try {
    const res = await inventoryCountsApi.close(countId, { treat_uncounted_as_zero })

    notify.success('Toma de inventario cerrada y ajustes generados correctamente')
    closeDialogOpen.value = false
    if (res?.data) {
      count.value = res.data
    } else {
      await loadCount()
    }
  }
  catch (err) {
    const message = err?.data?.message || err?.message || 'Error al cerrar la toma'

    alert({ title: 'No se pudo cerrar la toma', message })
  }
  finally {
    isClosing.value = false
  }
}

const handleCancelCount = async () => {
  if (!count.value) return

  const confirmed = await confirm({
    title: 'Anular toma de inventario',
    message: `¿Estás seguro de anular la toma de inventario ${count.value.number}? Esta acción es irreversible.`,
    confirmText: 'Sí, anular',
    cancelText: 'Cancelar',
  })

  if (!confirmed) return

  isCancelling.value = true
  try {
    const res = await inventoryCountsApi.cancel(countId)

    notify.success('Toma de inventario anulada correctamente')
    if (res?.data) {
      count.value = res.data
    } else {
      await loadCount()
    }
  }
  catch (err) {
    const message = err?.data?.message || err?.message || 'Error al anular la toma'

    alert({ title: 'No se pudo anular', message })
  }
  finally {
    isCancelling.value = false
  }
}

onMounted(() => {
  loadCount()
})
</script>

<template>
  <div>
    <VCard>
      <VCardText>
        <PageHeader>
          <template #actions>
            <VBtn
              variant="tonal"
              color="secondary"
              prepend-icon="tabler-arrow-left"
              @click="router.push({ name: 'inventory-counts' })"
            >
              Volver
            </VBtn>

            <template v-if="count && isCounting">
              <VBtn
                v-if="can('inventory_counts.close')"
                color="error"
                variant="tonal"
                prepend-icon="tabler-ban"
                :loading="isCancelling"
                @click="handleCancelCount"
              >
                Anular
              </VBtn>

              <VBtn
                v-if="can('inventory_counts.manage')"
                variant="tonal"
                color="primary"
                prepend-icon="tabler-device-floppy"
                :loading="isSavingItems"
                @click="itemsGridRef?.handleSave()"
              >
                Guardar conteo
              </VBtn>

              <VBtn
                v-if="can('inventory_counts.close')"
                color="primary"
                prepend-icon="tabler-clipboard-check"
                :loading="isClosing"
                @click="closeDialogOpen = true"
              >
                Cerrar y conciliar
              </VBtn>
            </template>
          </template>
        </PageHeader>
      </VCardText>

      <VDivider />

      <div
        v-if="isLoading"
        class="d-flex justify-center align-center py-12"
      >
        <VProgressCircular
          indeterminate
          color="primary"
          size="48"
        />
      </div>

      <VCardText v-else-if="errorMessage">
        <VAlert
          type="error"
          variant="tonal"
        >
          {{ errorMessage }}
        </VAlert>
      </VCardText>

      <template v-else-if="count">
        <!-- Resumen de la toma -->
        <VCardText>
          <div class="d-flex align-center flex-wrap gap-3 mb-4">
            <span class="text-h6 font-weight-bold">{{ count.number }}</span>
            <VChip
              :color="statusColor(count.status)"
              size="small"
              variant="tonal"
            >
              {{ count.status_label }}
            </VChip>
          </div>

          <VRow>
            <VCol
              v-for="info in summaryItems"
              :key="info.label"
              cols="12"
              sm="6"
              md="3"
            >
              <div class="d-flex align-center gap-3">
                <VAvatar
                  variant="tonal"
                  color="primary"
                  rounded
                  size="38"
                >
                  <VIcon
                    :icon="info.icon"
                    size="22"
                  />
                </VAvatar>
                <div class="text-truncate">
                  <div class="text-caption text-medium-emphasis">
                    {{ info.label }}
                  </div>
                  <div class="font-weight-medium text-high-emphasis text-truncate">
                    {{ info.value }}
                  </div>
                </div>
              </div>
            </VCol>
          </VRow>

          <div
            v-if="count.notes"
            class="mt-4"
          >
            <div class="text-caption text-medium-emphasis">
              Observaciones
            </div>
            <div class="text-body-2">
              {{ count.notes }}
            </div>
          </div>
        </VCardText>

        <!-- Notas de ajuste generadas -->
        <template v-if="isClosed && adjustmentNotes.length > 0">
          <VDivider />

          <VCardText>
            <h6 class="text-h6 mb-1">
              Notas de ajuste generadas
            </h6>
            <p class="text-body-2 text-medium-emphasis mb-4">
              El cierre de este conteo generó las siguientes notas de almacén para nivelar el inventario.
            </p>

            <div class="d-flex flex-column gap-3">
              <div
                v-for="an in adjustmentNotes"
                :key="an.id"
                class="d-flex align-center justify-space-between pa-3 rounded border flex-wrap gap-2"
              >
                <div class="d-flex align-center gap-2">
                  <VChip
                    size="small"
                    :color="an.type === 'in' ? 'success' : 'warning'"
                    variant="tonal"
                  >
                    {{ an.type === 'in' ? 'Ingreso por ajuste' : 'Salida por ajuste' }}
                  </VChip>
                  <RouterLink
                    :to="{ name: 'inventory-warehouse-notes-id', params: { id: an.id } }"
                    class="font-weight-bold text-primary text-decoration-none"
                  >
                    {{ an.number }}
                  </RouterLink>
                  <span class="text-caption text-medium-emphasis">({{ an.reason?.name }})</span>
                </div>

                <div class="d-flex align-center gap-4">
                  <span class="text-caption">Artículos: <strong>{{ an.items?.length || 0 }}</strong></span>
                  <span class="font-weight-medium">Costo total: {{ formatCurrency(an.total_cost) }}</span>
                  <RowActionButton
                    action="view"
                    tooltip="Ver nota"
                    @click="router.push({ name: 'inventory-warehouse-notes-id', params: { id: an.id } })"
                  />
                </div>
              </div>
            </div>
          </VCardText>
        </template>

        <VDivider />

        <!-- Grilla de conteo físico -->
        <CountItemsGrid
          ref="itemsGridRef"
          :items="count.items || []"
          :readonly="!isCounting || !can('inventory_counts.manage')"
          :is-saving="isSavingItems"
          @save="handleSaveItems"
        />
      </template>
    </VCard>

    <CloseCountDialog
      v-if="count"
      v-model="closeDialogOpen"
      :count="count"
      :loading="isClosing"
      @confirm="handleConfirmClose"
    />
  </div>
</template>
