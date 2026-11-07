<script setup>
import { computed } from 'vue'
import { formatDateTime12h } from '@/utils/dates'

const props = defineProps({
  isDialogOpen: {
    type: Boolean,
    required: true,
  },
  transfer: {
    type: Object,
    default: null,
  },
})

const emit = defineEmits(['update:isDialogOpen'])

const handleClose = () => {
  emit('update:isDialogOpen', false)
}

const formatCurrency = val => {
  if (val === null || val === undefined || val === '')
    return '0.00'
  const num = Number(val)

  return Number.isNaN(num) ? '0.00' : num.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const movements = computed(() => {
  return props.transfer?.movements || []
})
</script>

<template>
  <VDialog
    :model-value="props.isDialogOpen"
    max-width="650"
    @update:model-value="handleClose"
  >
    <VCard v-if="props.transfer">
      <VCardItem class="pb-3">
        <template #prepend>
          <VAvatar
            :color="props.transfer.status_color || 'primary'"
            variant="tonal"
            rounded
          >
            <VIcon icon="tabler-arrows-left-right" />
          </VAvatar>
        </template>
        <VCardTitle class="d-flex align-center gap-2">
          <span>Transferencia {{ props.transfer.number }}</span>
          <VChip
            size="small"
            :color="props.transfer.status_color"
            variant="tonal"
          >
            {{ props.transfer.status_label }}
          </VChip>
        </VCardTitle>
        <VCardSubtitle>Detalle del traslado de fondos entre cajas</VCardSubtitle>
      </VCardItem>

      <VDivider />

      <VCardText class="pt-4">
        <!-- Resumen de Cajas y Monto -->
        <VRow class="mb-3">
          <VCol
            cols="12"
            sm="5"
          >
            <div class="text-caption text-medium-emphasis">
              Caja Origen (Salida)
            </div>
            <div class="font-weight-medium">
              {{ props.transfer.source_box?.name || 'N/A' }}
            </div>
            <div class="text-caption text-disabled">
              {{ props.transfer.source_branch?.name || '' }}
            </div>
          </VCol>

          <VCol
            cols="12"
            sm="2"
            class="d-flex align-center justify-center py-2"
          >
            <VIcon
              icon="tabler-arrow-right"
              size="24"
              color="primary"
            />
          </VCol>

          <VCol
            cols="12"
            sm="5"
            class="text-sm-end"
          >
            <div class="text-caption text-medium-emphasis">
              Caja Destino (Ingreso)
            </div>
            <div class="font-weight-medium">
              {{ props.transfer.target_box?.name || 'N/A' }}
            </div>
            <div class="text-caption text-disabled">
              {{ props.transfer.target_branch?.name || '' }}
            </div>
          </VCol>
        </VRow>

        <VDivider class="my-3" />

        <div class="d-flex justify-space-between align-center mb-3">
          <span class="text-subtitle-2 text-medium-emphasis">Monto transferido</span>
          <span class="text-h6 font-weight-bold text-primary">
            {{ formatCurrency(props.transfer.amount) }}
          </span>
        </div>

        <div class="mb-4">
          <div class="text-caption text-medium-emphasis mb-1">
            Motivo
          </div>
          <div class="text-body-2 bg-var-theme-background pa-2 rounded">
            {{ props.transfer.reason }}
          </div>
        </div>

        <!-- Trazabilidad / Auditoría -->
        <div class="text-subtitle-2 mb-2">
          Trazabilidad
        </div>
        <VList
          density="compact"
          class="border rounded"
        >
          <VListItem>
            <template #prepend>
              <VIcon
                icon="tabler-user"
                size="18"
                class="me-2 text-disabled"
              />
            </template>
            <VListItemTitle class="text-body-2">
              Solicitado por: {{ props.transfer.creator?.name || 'Sistema' }}
            </VListItemTitle>
            <VListItemSubtitle class="text-caption">
              {{ formatDateTime12h(props.transfer.created_at) }}
            </VListItemSubtitle>
          </VListItem>

          <VListItem v-if="props.transfer.status === 'approved'">
            <template #prepend>
              <VIcon
                icon="tabler-check"
                size="18"
                color="success"
                class="me-2"
              />
            </template>
            <VListItemTitle class="text-body-2 text-success">
              Aprobado por: {{ props.transfer.approver?.name || 'N/A' }}
            </VListItemTitle>
            <VListItemSubtitle class="text-caption">
              {{ formatDateTime12h(props.transfer.approved_at) }}
            </VListItemSubtitle>
          </VListItem>

          <VListItem v-if="props.transfer.status === 'rejected'">
            <template #prepend>
              <VIcon
                icon="tabler-x"
                size="18"
                color="error"
                class="me-2"
              />
            </template>
            <VListItemTitle class="text-body-2 text-error">
              Rechazado por: {{ props.transfer.rejecter?.name || 'N/A' }}
            </VListItemTitle>
            <VListItemSubtitle class="text-caption">
              {{ formatDateTime12h(props.transfer.rejected_at) }} · Motivo: {{ props.transfer.rejection_reason }}
            </VListItemSubtitle>
          </VListItem>

          <VListItem v-if="props.transfer.status === 'cancelled'">
            <template #prepend>
              <VIcon
                icon="tabler-ban"
                size="18"
                color="secondary"
                class="me-2"
              />
            </template>
            <VListItemTitle class="text-body-2 text-secondary">
              Cancelado por: {{ props.transfer.canceller?.name || 'N/A' }}
            </VListItemTitle>
            <VListItemSubtitle class="text-caption">
              {{ formatDateTime12h(props.transfer.cancelled_at) }} · Motivo: {{ props.transfer.cancel_reason }}
            </VListItemSubtitle>
          </VListItem>
        </VList>

        <!-- Movimientos de efectivo vinculados -->
        <div
          v-if="movements.length > 0"
          class="mt-4"
        >
          <div class="text-subtitle-2 mb-2">
            Movimientos en el Libro de Caja
          </div>
          <VTable density="compact">
            <thead>
              <tr>
                <th>Dirección</th>
                <th>Monto</th>
                <th>Fecha</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="mov in movements"
                :key="mov.id"
              >
                <td>
                  <VChip
                    size="x-small"
                    :color="mov.direction === 'in' ? 'success' : 'error'"
                    variant="tonal"
                  >
                    {{ mov.direction === 'in' ? 'Ingreso' : 'Egreso' }}
                  </VChip>
                </td>
                <td class="font-weight-medium">
                  {{ formatCurrency(mov.amount) }}
                </td>
                <td class="text-caption text-medium-emphasis">
                  {{ formatDateTime12h(mov.moved_at) }}
                </td>
              </tr>
            </tbody>
          </VTable>
        </div>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4">
        <VSpacer />
        <VBtn
          variant="tonal"
          color="secondary"
          @click="handleClose"
        >
          Cerrar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
