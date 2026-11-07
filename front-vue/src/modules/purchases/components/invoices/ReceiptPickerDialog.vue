<script setup>
/* eslint-disable camelcase */
import { computed, ref, watch } from 'vue'
import { purchaseReceiptsApi } from '@/modules/purchases/services/purchaseReceiptsApi'
import { formatDateDMY } from '@/utils/dates'
import { formatAmount } from '@/utils/documentTotals'

/**
 * Diálogo para elegir recepciones confirmadas y aún no facturadas de un proveedor.
 * Emite `select` con la lista de ids elegidos.
 */
const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  providerId: {
    type: String,
    default: null,
  },
  selectedIds: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['update:modelValue', 'select'])

const receipts = ref([])
const chosen = ref([])
const isLoading = ref(false)

const isOpen = computed({
  get: () => props.modelValue,
  set: value => emit('update:modelValue', value),
})

const load = async () => {
  if (!props.providerId) {
    receipts.value = []

    return
  }

  isLoading.value = true
  try {
    const res = await purchaseReceiptsApi.list({
      status: 'confirmed',
      provider_id: props.providerId,
      not_invoiced: 1,
      per_page: 100,
    })

    receipts.value = res?.data || []
  }
  catch {
    receipts.value = []
  }
  finally {
    isLoading.value = false
  }
}

watch(() => props.modelValue, open => {
  if (!open)
    return

  chosen.value = [...props.selectedIds]
  load()
})

const toggle = id => {
  chosen.value = chosen.value.includes(id)
    ? chosen.value.filter(x => x !== id)
    : [...chosen.value, id]
}

const confirm = () => {
  emit('select', chosen.value)
  isOpen.value = false
}
</script>

<template>
  <VDialog
    v-model="isOpen"
    max-width="720"
  >
    <VCard>
      <VCardItem>
        <VCardTitle>Cargar desde recepciones</VCardTitle>
        <VCardSubtitle>Recepciones confirmadas del proveedor que aún no tienen factura.</VCardSubtitle>
      </VCardItem>

      <VCardText>
        <VProgressLinear
          v-if="isLoading"
          indeterminate
          class="mb-4"
        />

        <VAlert
          v-else-if="!receipts.length"
          type="info"
          variant="tonal"
          density="compact"
        >
          No hay recepciones confirmadas pendientes de facturar para este proveedor.
        </VAlert>

        <VList
          v-else
          lines="two"
          density="comfortable"
        >
          <VListItem
            v-for="receipt in receipts"
            :key="receipt.id"
            @click="toggle(receipt.id)"
          >
            <template #prepend>
              <VCheckboxBtn
                :model-value="chosen.includes(receipt.id)"
                @click.stop="toggle(receipt.id)"
              />
            </template>
            <VListItemTitle class="font-weight-medium">
              {{ receipt.number }} · {{ receipt.order?.number || '—' }}
            </VListItemTitle>
            <VListItemSubtitle>
              {{ formatDateDMY(receipt.receipt_date) }} · {{ receipt.provider_document_number || 'Sin guía' }} · {{ formatAmount(receipt.total_cost, receipt.order?.currency?.symbol || 'S/') }}
            </VListItemSubtitle>
          </VListItem>
        </VList>
      </VCardText>

      <VCardActions class="justify-end pa-4">
        <VBtn
          variant="tonal"
          color="secondary"
          @click="isOpen = false"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          :disabled="!chosen.length"
          @click="confirm"
        >
          Cargar {{ chosen.length ? `(${chosen.length})` : '' }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
