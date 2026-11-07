<script setup>
/* eslint-disable camelcase */
import { ref, watch } from 'vue'
import { useAcl } from '@/composables/useAcl'
import { useApiFormErrors } from '@/composables/useApiFormErrors'
import { useNotify } from '@/composables/useNotify'
import { clientsApi } from '@/modules/clients/services/clientsApi'
import { formatAmount } from '@/utils/documentTotals'

const props = defineProps({
  client: {
    type: Object,
    required: true,
  },
})

const emit = defineEmits(['updated'])

const { can } = useAcl()
const notify = useNotify()
const { errors, generalError, setErrors, clearErrors, clearFieldError } = useApiFormErrors()

const isDialogOpen = ref(false)
const isSubmitting = ref(false)

const form = ref({
  credit_limit: 0,
  credit_days: 0,
})

watch(() => props.client, c => {
  if (c) {
    form.value = {
      credit_limit: c.credit_limit ?? 0,
      credit_days: c.credit_days ?? 0,
    }
  }
}, { immediate: true })

const openDialog = () => {
  form.value = {
    credit_limit: props.client.credit_limit ?? 0,
    credit_days: props.client.credit_days ?? 0,
  }
  clearErrors()
  isDialogOpen.value = true
}

const handleSave = async () => {
  isSubmitting.value = true
  clearErrors()
  try {
    await clientsApi.updateCredit(props.client.id, {
      credit_limit: form.value.credit_limit,
      credit_days: Number(form.value.credit_days),
    })
    notify.success('Condiciones de crédito actualizadas correctamente')
    isDialogOpen.value = false
    emit('updated')
  }
  catch (err) {
    setErrors(err)
  }
  finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <div>
    <VCardText>
      <div class="d-flex justify-space-between align-center mb-6">
        <div>
          <h3 class="text-h6 font-weight-bold mb-1">
            Condiciones de Crédito
          </h3>
          <span class="text-body-2 text-medium-emphasis">
            Límite asignado, plazo de pago y saldo de crédito disponible.
          </span>
        </div>

        <VBtn
          v-if="can('clients.manage_credit') && !props.client.is_system"
          prepend-icon="tabler-edit"
          variant="tonal"
          color="primary"
          @click="openDialog"
        >
          Modificar crédito
        </VBtn>
      </div>

      <VAlert
        v-if="props.client.is_system"
        type="info"
        variant="tonal"
        class="mb-6"
      >
        El cliente genérico del sistema opera únicamente con pagos al contado (crédito fijo S/ 0.00).
      </VAlert>

      <VRow>
        <!-- Límite de Crédito -->
        <VCol
          cols="12"
          sm="6"
          md="3"
        >
          <VCard
            variant="outlined"
            class="pa-4 h-100"
          >
            <div class="d-flex align-center gap-3">
              <VAvatar
                color="primary"
                variant="tonal"
                rounded
                size="40"
              >
                <VIcon
                  icon="tabler-cash"
                  size="24"
                />
              </VAvatar>
              <div>
                <div class="text-caption text-medium-emphasis">
                  LÍMITE DE CRÉDITO
                </div>
                <div class="text-h6 font-weight-bold">
                  {{ formatAmount(props.client.credit_limit, 'S/') }}
                </div>
              </div>
            </div>
          </VCard>
        </VCol>

        <!-- Días de Crédito -->
        <VCol
          cols="12"
          sm="6"
          md="3"
        >
          <VCard
            variant="outlined"
            class="pa-4 h-100"
          >
            <div class="d-flex align-center gap-3">
              <VAvatar
                color="info"
                variant="tonal"
                rounded
                size="40"
              >
                <VIcon
                  icon="tabler-calendar-time"
                  size="24"
                />
              </VAvatar>
              <div>
                <div class="text-caption text-medium-emphasis">
                  PLAZO MÁXIMO
                </div>
                <div class="text-h6 font-weight-bold">
                  {{ props.client.credit_days > 0 ? `${props.client.credit_days} días` : 'Contado (0 d.)' }}
                </div>
              </div>
            </div>
          </VCard>
        </VCol>

        <!-- Deuda Vigente -->
        <VCol
          cols="12"
          sm="6"
          md="3"
        >
          <VCard
            variant="outlined"
            class="pa-4 h-100"
          >
            <div class="d-flex align-center gap-3">
              <VAvatar
                color="warning"
                variant="tonal"
                rounded
                size="40"
              >
                <VIcon
                  icon="tabler-receipt-2"
                  size="24"
                />
              </VAvatar>
              <div>
                <div class="text-caption text-medium-emphasis">
                  DEUDA VIGENTE
                </div>
                <div class="text-h6 font-weight-bold">
                  {{ formatAmount(props.client.account?.debt ?? 0, 'S/') }}
                </div>
              </div>
            </div>
          </VCard>
        </VCol>

        <!-- Crédito Disponible -->
        <VCol
          cols="12"
          sm="6"
          md="3"
        >
          <VCard
            variant="outlined"
            class="pa-4 h-100"
          >
            <div class="d-flex align-center gap-3">
              <VAvatar
                color="success"
                variant="tonal"
                rounded
                size="40"
              >
                <VIcon
                  icon="tabler-circle-check"
                  size="24"
                />
              </VAvatar>
              <div>
                <div class="text-caption text-medium-emphasis">
                  CRÉDITO DISPONIBLE
                </div>
                <div class="text-h6 font-weight-bold text-success">
                  {{ formatAmount(props.client.account?.available_credit ?? props.client.credit_limit, 'S/') }}
                </div>
              </div>
            </div>
          </VCard>
        </VCol>
      </VRow>
    </VCardText>

    <!-- Diálogo de Modificación de Crédito -->
    <VDialog
      v-model="isDialogOpen"
      max-width="480"
    >
      <VCard>
        <VCardTitle class="pa-4">
          Gestionar Condiciones de Crédito
        </VCardTitle>
        <VDivider />

        <VCardText class="pa-4">
          <VAlert
            v-if="generalError"
            type="error"
            variant="tonal"
            class="mb-4"
          >
            {{ generalError }}
          </VAlert>

          <VForm @submit.prevent="handleSave">
            <VRow>
              <VCol cols="12">
                <VTextField
                  v-model="form.credit_limit"
                  label="Límite de crédito (S/) *"
                  placeholder="Ej. 5000.00"
                  type="number"
                  step="0.01"
                  min="0"
                  :error-messages="errors.credit_limit"
                  @update:model-value="clearFieldError('credit_limit')"
                />
              </VCol>

              <VCol cols="12">
                <VTextField
                  v-model.number="form.credit_days"
                  label="Días de crédito *"
                  placeholder="Ej. 30"
                  type="number"
                  min="0"
                  max="365"
                  :error-messages="errors.credit_days"
                  @update:model-value="clearFieldError('credit_days')"
                />
              </VCol>
            </VRow>

            <div class="d-flex justify-end gap-3 mt-4">
              <VBtn
                variant="tonal"
                color="secondary"
                :disabled="isSubmitting"
                @click="isDialogOpen = false"
              >
                Cancelar
              </VBtn>
              <VBtn
                type="submit"
                color="primary"
                :loading="isSubmitting"
              >
                Guardar
              </VBtn>
            </div>
          </VForm>
        </VCardText>
      </VCard>
    </VDialog>
  </div>
</template>
