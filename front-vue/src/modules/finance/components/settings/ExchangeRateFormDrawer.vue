<script setup>
import DrawerHeader from '@/components/common/DrawerHeader.vue'
import { computed, onMounted, ref, watch } from 'vue'
import { useApiFormErrors } from '@/composables/useApiFormErrors'
import { useFinanceSettingsStore } from '../../stores/financeSettingsStore'

const props = defineProps({
  isDrawerOpen: {
    type: Boolean,
    required: true,
  },
  rateData: {
    type: Object,
    default: null,
  },
  isSubmitting: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:isDrawerOpen', 'submit'])

const financeStore = useFinanceSettingsStore()
const { errors, generalError, setErrors, clearErrors, clearFieldError } = useApiFormErrors()

const isEditing = computed(() => Boolean(props.rateData?.id))

const today = new Date().toLocaleDateString('en-CA')

const defaultForm = {
  'currency_id': null,
  date: today,
  'buy_rate': '',
  'sell_rate': '',
}

const form = ref({ ...defaultForm })

const foreignCurrencies = computed(() => {
  return financeStore.currenciesOptions
    .filter(c => !c.is_base)
    .map(c => ({
      value: c.id,
      title: `${c.code} - ${c.name} (${c.symbol})`,
    }))
})

onMounted(() => {
  financeStore.fetchCurrenciesOptions()
})

watch(() => [props.isDrawerOpen, props.rateData], () => {
  const val = props.rateData

  form.value = val
    ? {
      'currency_id': val.currency_id || val.currency?.id || null,
      date: val.date || today,
      'buy_rate': val.buy_rate !== undefined ? String(val.buy_rate) : '',
      'sell_rate': val.sell_rate !== undefined ? String(val.sell_rate) : '',
    }
    : {
      ...defaultForm,
      'currency_id': foreignCurrencies.value[0]?.value || null,
    }
  clearErrors()
}, { immediate: true })

const handleClose = () => {
  emit('update:isDrawerOpen', false)
  clearErrors()
}

const handleSubmit = () => {
  clearErrors()
  emit('submit', {
    'currency_id': form.value.currency_id,
    date: form.value.date,
    'buy_rate': parseFloat(form.value.buy_rate),
    'sell_rate': parseFloat(form.value.sell_rate),
  })
}

defineExpose({
  setErrors,
  clearErrors,
})
</script>

<template>
  <VNavigationDrawer
    temporary
    location="end"
    :width="420"
    :model-value="props.isDrawerOpen"
    class="scrollable-content"
    @update:model-value="handleClose"
  >
    <DrawerHeader
      :title="isEditing ? 'Editar Tipo de Cambio' : 'Registrar Tipo de Cambio'"
      icon="tabler-arrows-exchange"
      @close="handleClose"
    />

    <VCard flat>
      <VCardText>
        <VAlert
          v-if="generalError"
          type="error"
          variant="tonal"
          class="mb-4"
        >
          {{ generalError }}
        </VAlert>

        <VForm @submit.prevent="handleSubmit">
          <VRow>
            <VCol cols="12">
              <VSelect
                v-model="form.currency_id"
                label="Moneda *"
                placeholder="USD - Dólar"
                :items="foreignCurrencies"
                :disabled="isEditing"
                :error-messages="errors.currency_id"
                @update:model-value="clearFieldError('currency_id')"
              />
            </VCol>

            <VCol cols="12">
              <VTextField
                v-model="form.date"
                type="date"
                label="Fecha *"
                placeholder="2026-10-19"
                :max="today"
                :disabled="isEditing"
                :error-messages="errors.date"
                @input="clearFieldError('date')"
              />
            </VCol>

            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model="form.buy_rate"
                type="number"
                step="0.0001"
                min="0.0001"
                label="Precio Compra *"
                placeholder="3.7450"
                :error-messages="errors.buy_rate"
                @input="clearFieldError('buy_rate')"
              />
            </VCol>

            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model="form.sell_rate"
                type="number"
                step="0.0001"
                min="0.0001"
                label="Precio Venta *"
                placeholder="3.7550"
                :error-messages="errors.sell_rate"
                @input="clearFieldError('sell_rate')"
              />
            </VCol>

            <VCol
              cols="12"
              class="d-flex gap-4 mt-2"
            >
              <VBtn
                type="submit"
                :loading="props.isSubmitting"
                :disabled="props.isSubmitting"
              >
                Guardar Tipo de Cambio
              </VBtn>
              <VBtn
                variant="tonal"
                color="secondary"
                :disabled="props.isSubmitting"
                @click="handleClose"
              >
                Cancelar
              </VBtn>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
    </VCard>
  </VNavigationDrawer>
</template>
