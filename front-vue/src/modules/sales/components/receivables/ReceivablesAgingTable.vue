<script setup>
/* eslint-disable camelcase */
import { onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useNotify } from '@/composables/useNotify'
import { receivablesApi } from '@/modules/sales/services/receivablesApi'
import { formatAmount } from '@/utils/documentTotals'

const props = defineProps({
  asOf: {
    type: String,
    default: null,
  },
})

const emit = defineEmits(['statement'])

const router = useRouter()
const notify = useNotify()

const rows = ref([])
const totals = ref([])
const isLoading = ref(false)
const localAsOf = ref(props.asOf || new Date().toISOString().slice(0, 10))

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
    const res = await receivablesApi.aging(localAsOf.value ? { as_of: localAsOf.value } : {})

    rows.value = res?.data || []
    totals.value = res?.meta?.totals || []
  }
  catch {
    notify.error('No se pudo cargar la antigüedad de saldos.')
  }
  finally {
    isLoading.value = false
  }
}

onMounted(load)
watch(() => props.asOf, val => {
  if (val) {
    localAsOf.value = val
    load()
  }
})

const goToClient = clientId => {
  if (clientId) {
    router.push(`/clients/${clientId}`)
  }
}

defineExpose({ reload: load })
</script>

<template>
  <div>
    <div class="d-flex align-center justify-space-between mb-4 flex-wrap gap-4">
      <div class="d-flex align-center gap-2">
        <VTextField
          v-model="localAsOf"
          type="date"
          density="compact"
          label="Fecha de corte"
          placeholder="YYYY-MM-DD"
          style="max-width: 200px;"
          @update:model-value="load"
        />
        <VBtn
          size="small"
          variant="tonal"
          color="primary"
          prepend-icon="tabler-refresh"
          :loading="isLoading"
          @click="load"
        >
          Actualizar
        </VBtn>
      </div>

      <div class="text-caption text-medium-emphasis">
        Montos agrupados por moneda y tramos de vencimiento
      </div>
    </div>

    <VProgressLinear
      v-if="isLoading"
      indeterminate
      class="mb-4"
    />

    <VAlert
      v-else-if="!rows.length"
      type="success"
      variant="tonal"
      density="compact"
      class="my-4"
    >
      No hay saldos vencidos ni pendientes por cobrar a la fecha de corte.
    </VAlert>

    <div
      v-else
      class="border rounded overflow-hidden"
    >
      <VTable
        density="comfortable"
        hover
      >
        <thead>
          <tr>
            <th class="font-weight-bold">
              CLIENTE
            </th>
            <th class="font-weight-bold">
              DOCUMENTO
            </th>
            <th class="font-weight-bold text-center">
              MONEDA
            </th>
            <th
              v-for="bucket in BUCKETS"
              :key="bucket.key"
              class="font-weight-bold text-end"
            >
              {{ bucket.title }}
            </th>
            <th class="font-weight-bold text-end">
              TOTAL
            </th>
            <th class="font-weight-bold text-center">
              ACCIONES
            </th>
          </tr>
        </thead>

        <tbody>
          <tr
            v-for="row in rows"
            :key="`${row.client_id}-${row.currency_id}`"
          >
            <td>
              <span class="font-weight-medium">{{ row.client_name }}</span>
            </td>
            <td>
              <span class="text-caption font-mono text-medium-emphasis">
                {{ row.client_document || '—' }}
              </span>
            </td>
            <td class="text-center">
              <VChip
                size="x-small"
                variant="tonal"
                color="secondary"
              >
                {{ row.currency_code }}
              </VChip>
            </td>
            <td
              v-for="bucket in BUCKETS"
              :key="bucket.key"
              class="text-end font-mono"
              :class="{ 'text-error font-weight-bold': bucket.key !== 'current' && Number(row[bucket.key]) > 0 }"
            >
              {{ Number(row[bucket.key]) > 0 ? formatAmount(row[bucket.key]) : '—' }}
            </td>
            <td class="text-end font-mono font-weight-bold">
              {{ row.currency_symbol }} {{ formatAmount(row.total) }}
            </td>
            <td class="text-center">
              <div class="d-flex align-center justify-center gap-1">
                <VBtn
                  icon="tabler-file-report"
                  variant="text"
                  color="info"
                  size="small"
                  title="Estado de cuenta"
                  @click="emit('statement', row)"
                />
                <VBtn
                  icon="tabler-user"
                  variant="text"
                  color="secondary"
                  size="small"
                  title="Ficha del cliente"
                  @click="goToClient(row.client_id)"
                />
              </div>
            </td>
          </tr>
        </tbody>

        <tfoot v-if="totals.length">
          <tr
            v-for="tot in totals"
            :key="`tot-${tot.currency_id}`"
            class="bg-var-theme-background font-weight-bold"
          >
            <td
              colspan="2"
              class="text-end"
            >
              TOTAL {{ tot.currency_code }}:
            </td>
            <td class="text-center">
              <VChip
                size="x-small"
                variant="elevated"
                color="primary"
              >
                {{ tot.currency_code }}
              </VChip>
            </td>
            <td
              v-for="bucket in BUCKETS"
              :key="bucket.key"
              class="text-end font-mono"
              :class="{ 'text-error': bucket.key !== 'current' && Number(tot[bucket.key]) > 0 }"
            >
              {{ formatAmount(tot[bucket.key]) }}
            </td>
            <td class="text-end font-mono text-primary font-weight-bold">
              {{ tot.currency_symbol }} {{ formatAmount(tot.total) }}
            </td>
            <td />
          </tr>
        </tfoot>
      </VTable>
    </div>
  </div>
</template>
