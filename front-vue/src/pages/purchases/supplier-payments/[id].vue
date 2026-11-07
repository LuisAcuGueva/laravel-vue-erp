<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, ref } from 'vue'
import CancelDocumentDialog from '@/components/common/CancelDocumentDialog.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import { useAcl } from '@/composables/useAcl'
import { useConfirm } from '@/composables/useConfirm'
import { useNotify } from '@/composables/useNotify'
import SupplierPaymentAllocationsTable from '@/modules/purchases/components/payments/SupplierPaymentAllocationsTable.vue'
import { supplierPaymentsApi } from '@/modules/purchases/services/supplierPaymentsApi'
import {
  supplierPaymentStatusColor,
  supplierPaymentStatusTitle,
} from '@/modules/purchases/utils/status'
import { formatDateDMY } from '@/utils/dates'
import { formatAmount } from '@/utils/documentTotals'

definePage({
  meta: {
    action: 'supplier_payments.view',
    title: 'Detalle de Pago a Proveedor',
    icon: 'tabler-cash',
    description: 'Detalle del pago, cuotas liquidadas y comprobante de pago.',
  },
})

const route = useRoute()
const { can } = useAcl()
const notify = useNotify()
const { alert } = useConfirm()
const { goBack } = useBackNavigation({ name: 'purchases-accounts-payable' })

const paymentId = route.params.id
const payment = ref(null)
const isLoading = ref(true)
const isBusy = ref(false)
const isCancelOpen = ref(false)

const canCancel = computed(() => payment.value?.status === 'completed' && can('supplier_payments.cancel'))

const load = async () => {
  isLoading.value = true
  try {
    const res = await supplierPaymentsApi.get(paymentId)

    payment.value = res?.data || null
  }
  catch {
    notify.error('No se pudo cargar el pago a proveedor.')
  }
  finally {
    isLoading.value = false
  }
}

onMounted(load)

const handleCancel = async reason => {
  isBusy.value = true
  isCancelOpen.value = false
  try {
    await supplierPaymentsApi.cancel(paymentId, reason)
    notify.success('Pago a proveedor anulado correctamente')
    await load()
  }
  catch (err) {
    await alert({ message: err?.data?.message || 'No se pudo anular el pago.' })
  }
  finally {
    isBusy.value = false
  }
}
</script>

<template>
  <div>
    <VCard class="mb-6">
      <VCardText>
        <PageHeader
          :title="payment ? `Pago ${payment.number}` : 'Cargando pago...'"
          :description="payment ? `${payment.provider?.business_name || payment.counterparty_name} · ${formatAmount(payment.amount, payment.currency?.symbol)}` : undefined"
        >
          <template #actions>
            <VChip
              v-if="payment"
              size="small"
              class="text-uppercase"
              :color="supplierPaymentStatusColor(payment.status)"
            >
              {{ supplierPaymentStatusTitle(payment.status) }}
            </VChip>

            <VBtn
              v-if="canCancel"
              color="error"
              variant="outlined"
              prepend-icon="tabler-ban"
              :disabled="isBusy"
              @click="isCancelOpen = true"
            >
              Anular
            </VBtn>

            <VBtn
              variant="tonal"
              color="secondary"
              prepend-icon="tabler-arrow-left"
              @click="goBack"
            >
              Volver
            </VBtn>
          </template>
        </PageHeader>
      </VCardText>
    </VCard>

    <VProgressLinear
      v-if="isLoading"
      indeterminate
    />

    <template v-if="payment">
      <VAlert
        v-if="payment.status === 'cancelled'"
        type="error"
        variant="tonal"
        class="mb-6"
      >
        <strong>Pago anulado.</strong> Motivo: {{ payment.cancel_reason || 'Sin motivo especificado' }}
        <span v-if="payment.canceller"> · Por {{ payment.canceller.name }} el {{ formatDateDMY(payment.cancelled_at) }}</span>
      </VAlert>

      <VCard class="mb-6">
        <VCardText>
          <VRow>
            <VCol
              cols="12"
              md="3"
            >
              <div class="text-caption text-medium-emphasis">
                Proveedor / beneficiario
              </div>
              <div class="font-weight-medium">
                <RouterLink
                  v-if="payment.provider"
                  :to="{ name: 'purchases-providers-id', params: { id: payment.provider.id } }"
                  class="text-primary text-decoration-none"
                >
                  {{ payment.provider.business_name }}
                </RouterLink>
                <span v-else>{{ payment.counterparty_name }}</span>
              </div>
              <div
                v-if="payment.provider"
                class="text-caption"
              >
                {{ payment.provider.document_type }} {{ payment.provider.document_number }}
              </div>
            </VCol>

            <VCol
              cols="12"
              md="3"
            >
              <div class="text-caption text-medium-emphasis">
                Fecha de pago
              </div>
              <div class="font-weight-medium">
                {{ formatDateDMY(payment.payment_date) }}
              </div>
            </VCol>

            <VCol
              cols="12"
              md="3"
            >
              <div class="text-caption text-medium-emphasis">
                Monto
              </div>
              <div class="font-weight-medium">
                {{ formatAmount(payment.amount, payment.currency?.symbol) }}
              </div>
            </VCol>

            <VCol
              cols="12"
              md="3"
            >
              <div class="text-caption text-medium-emphasis">
                Método de pago
              </div>
              <div class="font-weight-medium">
                {{ payment.payment_method?.name || '—' }}
              </div>
            </VCol>

            <VCol
              cols="12"
              md="3"
            >
              <div class="text-caption text-medium-emphasis">
                Referencia
              </div>
              <div class="font-weight-medium">
                {{ payment.reference || '—' }}
              </div>
            </VCol>

            <VCol
              v-if="payment.cash_session"
              cols="12"
              md="3"
            >
              <div class="text-caption text-medium-emphasis">
                Sesión de caja
              </div>
              <div class="font-weight-medium">
                <RouterLink
                  :to="{ name: 'finance-cash-sessions-id', params: { id: payment.cash_session.id } }"
                  class="text-primary text-decoration-none"
                >
                  {{ payment.cash_session.number }}
                </RouterLink>
              </div>
            </VCol>

            <VCol
              v-if="payment.bank_account"
              cols="12"
              md="3"
            >
              <div class="text-caption text-medium-emphasis">
                Cuenta bancaria
              </div>
              <div class="font-weight-medium">
                {{ payment.bank_account.alias }}
              </div>
              <div class="text-caption">
                {{ payment.bank_account.account_number }}
              </div>
            </VCol>

            <VCol
              cols="12"
              md="3"
            >
              <div class="text-caption text-medium-emphasis">
                Registrado por
              </div>
              <div class="font-weight-medium">
                {{ payment.creator?.name || '—' }}
              </div>
            </VCol>

            <VCol
              v-if="payment.notes"
              cols="12"
            >
              <div class="text-caption text-medium-emphasis">
                Observaciones
              </div>
              <div>{{ payment.notes }}</div>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>

      <VCard>
        <VCardItem>
          <template #title>
            Cuotas liquidadas ({{ payment.allocations?.length || 0 }})
          </template>
        </VCardItem>

        <VDivider />

        <VCardText>
          <SupplierPaymentAllocationsTable
            :allocations="payment.allocations || []"
            :currency-symbol="payment.currency?.symbol || 'S/'"
          />
        </VCardText>
      </VCard>
    </template>

    <CancelDocumentDialog
      v-model="isCancelOpen"
      :title="`Anular pago ${payment?.number || ''}`"
      :document-number="payment?.number || ''"
      description="Se revertirá el movimiento de caja o banco del pago y se restaurará el saldo de las cuotas. La sesión de caja o el periodo bancario deben seguir abiertos."
      reason-label="Motivo de anulación *"
      reason-placeholder="Ej. Pago registrado dos veces"
      confirm-label="Confirmar anulación"
      color="error"
      required-message="El motivo de anulación es obligatorio."
      :loading="isBusy"
      @confirm="handleCancel"
    />
  </div>
</template>
