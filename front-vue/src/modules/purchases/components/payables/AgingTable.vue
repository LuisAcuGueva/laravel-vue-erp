<script setup>
/* eslint-disable camelcase */
import { onMounted, ref, watch } from 'vue'
import { useNotify } from '@/composables/useNotify'
import { accountsPayableApi } from '@/modules/purchases/services/accountsPayableApi'
import { formatAmount } from '@/utils/documentTotals'

/**
 * Antigüedad de saldos por proveedor y moneda (corriente, 1–30, 31–60, 61–90 y más de 90 días).
 * Cada fila es de una sola moneda: los montos de monedas distintas no se suman.
 */
const props = defineProps({
  asOf: {
    type: String,
    default: null,
  },
})

const emit = defineEmits(['statement'])

const notify = useNotify()
const rows = ref([])
const isLoading = ref(false)

const BUCKETS = [
  { key: 'current', title: 'CORRIENTE' },
  { key: 'd1_30', title: '1–30 DÍAS' },
  { key: 'd31_60', title: '31–60 DÍAS' },
  { key: 'd61_90', title: '61–90 DÍAS' },
  { key: 'over_90', title: '> 90 DÍAS' },
]

const load = async () => {
  isLoading.value = true
  try {
    const res = await accountsPayableApi.aging(props.asOf ? { as_of: props.asOf } : {})

    rows.value = res?.data || []
  }
  catch {
    notify.error('No se pudo cargar la antigüedad de saldos.')
  }
  finally {
    isLoading.value = false
  }
}

onMounted(load)
watch(() => props.asOf, load)

defineExpose({ reload: load })
</script>

<template>
  <div>
    <VProgressLinear
      v-if="isLoading"
      indeterminate
    />

    <VAlert
      v-else-if="!rows.length"
      type="success"
      variant="tonal"
      density="compact"
    >
      No hay saldos pendientes con proveedores.
    </VAlert>

    <VTable
      v-else
      density="comfortable"
    >
      <thead>
        <tr>
          <th>PROVEEDOR</th>
          <th>MONEDA</th>
          <th
            v-for="bucket in BUCKETS"
            :key="bucket.key"
            class="text-end"
          >
            {{ bucket.title }}
          </th>
          <th class="text-end">
            TOTAL
          </th>
          <th class="text-center">
            ESTADO DE CUENTA
          </th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="row in rows"
          :key="`${row.provider.id}-${row.currency.id}`"
        >
          <td>
            <RouterLink
              v-if="row.provider.id"
              :to="{ name: 'purchases-providers-id', params: { id: row.provider.id } }"
              class="font-weight-medium text-primary text-decoration-none"
            >
              {{ row.provider.business_name }}
            </RouterLink>
            <div
              v-else
              class="font-weight-medium"
            >
              {{ row.provider.business_name }}
            </div>
            <div
              v-if="row.provider.document_number"
              class="text-caption text-medium-emphasis"
            >
              {{ row.provider.document_type }} {{ row.provider.document_number }}
            </div>
          </td>
          <td>{{ row.currency.code }}</td>
          <td
            v-for="bucket in BUCKETS"
            :key="bucket.key"
            class="text-end"
            :class="{ 'text-error': bucket.key !== 'current' && Number(row[bucket.key]) > 0 }"
          >
            {{ Number(row[bucket.key]) > 0 ? formatAmount(row[bucket.key], row.currency.symbol) : '—' }}
          </td>
          <td class="text-end font-weight-medium">
            {{ formatAmount(row.total, row.currency.symbol) }}
          </td>
          <td class="text-center">
            <VBtn
              v-if="row.provider.id"
              size="small"
              variant="text"
              prepend-icon="tabler-file-text"
              @click="emit('statement', { provider: row.provider, currency: row.currency })"
            >
              Ver
            </VBtn>
          </td>
        </tr>
      </tbody>
    </VTable>
  </div>
</template>
