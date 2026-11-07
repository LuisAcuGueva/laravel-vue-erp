<script setup>
/* eslint-disable camelcase */
import { ref, watch } from 'vue'
import { useNotify } from '@/composables/useNotify'
import { providersApi } from '@/modules/purchases/services/providersApi'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  presentationId: {
    type: String,
    default: null,
  },
  title: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['update:modelValue'])

const notify = useNotify()

const prices = ref([])
const lastPurchaseCost = ref(null)
const isLoading = ref(false)

const load = async () => {
  if (!props.presentationId)
    return

  isLoading.value = true
  try {
    const res = await providersApi.comparePrices(props.presentationId)

    prices.value = res?.data?.prices || []
    lastPurchaseCost.value = res?.data?.last_purchase_cost ?? null
  }
  catch {
    prices.value = []
    lastPurchaseCost.value = null
    notify.error('No se pudo cargar la comparación de precios.')
  }
  finally {
    isLoading.value = false
  }
}

watch(() => [props.modelValue, props.presentationId], ([open]) => {
  if (open)
    load()
})

const formatAmount = (value, symbol = '') => (value === null || value === undefined ? '—' : `${symbol} ${Number(value).toFixed(2)}`.trim())
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="720"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard>
      <VCardTitle class="d-flex align-center gap-2 pt-4">
        <VIcon icon="tabler-scale" />
        Comparar precios
      </VCardTitle>
      <VCardSubtitle v-if="props.title">
        {{ props.title }}
      </VCardSubtitle>

      <VCardText>
        <VProgressLinear
          v-if="isLoading"
          indeterminate
          class="mb-4"
        />

        <VAlert
          v-if="lastPurchaseCost !== null"
          type="info"
          variant="tonal"
          density="compact"
          class="mb-4"
        >
          Último costo de compra: <strong>{{ formatAmount(lastPurchaseCost) }}</strong>
        </VAlert>

        <VTable density="comfortable">
          <thead>
            <tr>
              <th>PROVEEDOR</th>
              <th class="text-end">
                PRECIO
              </th>
              <th class="text-end">
                EQUIV. MONEDA BASE
              </th>
              <th>VIGENCIA</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!prices.length && !isLoading">
              <td
                colspan="4"
                class="text-center text-medium-emphasis py-6"
              >
                Ningún proveedor tiene un precio vigente para esta presentación.
              </td>
            </tr>
            <tr
              v-for="(price, index) in prices"
              :key="price.id"
            >
              <td>
                <VChip
                  v-if="index === 0"
                  size="x-small"
                  color="success"
                  class="me-2"
                >
                  Más bajo
                </VChip>
                {{ price.provider?.business_name }}
              </td>
              <td class="text-end">
                {{ formatAmount(price.price, price.currency?.symbol) }}
              </td>
              <td class="text-end">
                {{ formatAmount(price.base_price) }}
              </td>
              <td>{{ price.valid_from }} → {{ price.valid_until || 'Indefinida' }}</td>
            </tr>
          </tbody>
        </VTable>
      </VCardText>

      <VCardActions class="px-6 pb-4">
        <VSpacer />
        <VBtn
          variant="tonal"
          color="secondary"
          @click="emit('update:modelValue', false)"
        >
          Cerrar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
