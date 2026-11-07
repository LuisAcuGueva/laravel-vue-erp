<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { salesApi } from '@/modules/sales/services/salesApi'
import { formatAmount } from '@/utils/documentTotals'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  sale: {
    type: Object,
    default: null,
  },
})

const emit = defineEmits(['update:modelValue', 'newSale'])

const router = useRouter()

const isOpen = computed({
  get: () => props.modelValue,
  set: val => emit('update:modelValue', val),
})

const printTicket = () => {
  if (!props.sale) return
  salesApi.downloadPdf(props.sale.id, 'ticket', `${props.sale.full_number}-ticket.pdf`)
}

const printA4 = () => {
  if (!props.sale) return
  salesApi.downloadPdf(props.sale.id, 'a4', `${props.sale.full_number}-a4.pdf`)
}

const viewDetail = () => {
  if (!props.sale) return
  isOpen.value = false
  router.push(`/sales/history/${props.sale.id}`)
}

const startNewSale = () => {
  isOpen.value = false
  emit('newSale')
}
</script>

<template>
  <VDialog
    v-model="isOpen"
    max-width="500"
    persistent
  >
    <VCard v-if="sale">
      <VCardText class="pa-8 text-center">
        <VAvatar
          size="64"
          color="success"
          variant="tonal"
          class="mb-4"
        >
          <VIcon
            size="36"
            icon="tabler-check"
          />
        </VAvatar>

        <h3 class="text-h5 font-weight-bold mb-1">
          ¡Venta Registrada!
        </h3>
        <p class="text-subtitle-1 text-medium-emphasis mb-4">
          Comprobante: <strong class="text-high-emphasis">{{ sale.full_number }}</strong>
        </p>

        <div class="voucher-summary pa-4 rounded-lg mb-6 text-left">
          <div class="d-flex justify-space-between align-center mb-2">
            <span class="text-body-2 text-medium-emphasis">Cliente:</span>
            <span class="text-body-2 font-weight-medium text-right">{{ sale.client_name }}</span>
          </div>
          <div class="d-flex justify-space-between align-center mb-2">
            <span class="text-body-2 text-medium-emphasis">Condición:</span>
            <span class="text-body-2 font-weight-medium">
              {{ sale.payment_condition === 'cash' ? 'Al Contado' : 'Al Crédito' }}
            </span>
          </div>
          <div class="d-flex justify-space-between align-center">
            <span class="text-body-2 text-medium-emphasis">Total:</span>
            <span class="text-h6 font-weight-bold text-primary">{{ formatAmount(sale.total) }}</span>
          </div>
          <VDivider class="my-3" />
          <div class="d-flex justify-space-between align-center">
            <span class="text-caption text-medium-emphasis">Estado SUNAT:</span>
            <VChip
              size="x-small"
              :color="sale.einvoice_status === 'accepted' ? 'success' : (sale.einvoice_status === 'error' ? 'error' : 'info')"
              variant="tonal"
            >
              {{ sale.einvoice_status_label || sale.einvoice_status }}
            </VChip>
          </div>
        </div>

        <div class="d-flex flex-column gap-3">
          <div class="d-flex gap-2">
            <VBtn
              color="primary"
              variant="flat"
              class="flex-grow-1"
              prepend-icon="tabler-printer"
              @click="printTicket"
            >
              Ticket
            </VBtn>

            <VBtn
              color="primary"
              variant="tonal"
              class="flex-grow-1"
              prepend-icon="tabler-file-download"
              @click="printA4"
            >
              PDF A4
            </VBtn>
          </div>

          <VBtn
            variant="text"
            color="secondary"
            prepend-icon="tabler-eye"
            @click="viewDetail"
          >
            Ver detalle completo
          </VBtn>

          <VDivider />

          <VBtn
            color="success"
            size="large"
            prepend-icon="tabler-shopping-cart-plus"
            @click="startNewSale"
          >
            Nueva Venta (Enter)
          </VBtn>
        </div>
      </VCardText>
    </VCard>
  </VDialog>
</template>

<style scoped>
.voucher-summary {
  background: rgba(var(--v-theme-on-surface), 0.04);
  border: 1px solid rgba(var(--v-theme-on-surface), 0.08);
}
</style>
