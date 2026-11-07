<script setup>
import DrawerHeader from '@/components/common/DrawerHeader.vue'
import { computed, ref, watch } from 'vue'
import { useApiFormErrors } from '@/composables/useApiFormErrors'
import { useFinanceSettingsStore } from '../../stores/financeSettingsStore'
import { directionAllowsAccount } from '../../utils/accountRules'
import { CONCEPT_DIRECTIONS } from '../../utils/constants'

const props = defineProps({
  isDrawerOpen: {
    type: Boolean,
    required: true,
  },
  conceptData: {
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

const isEditing = computed(() => Boolean(props.conceptData?.id))
const isSystem = computed(() => Boolean(props.conceptData?.is_system))

const defaultForm = {
  code: '',
  name: '',
  direction: 'expense',
  'financial_account_id': null,
  'is_active': true,
}

const form = ref({ ...defaultForm })
const postableAccounts = ref([])
const isLoadingAccounts = ref(false)

// Con params el store no cachea: la lista imputable se guarda localmente.
const loadPostableAccounts = async () => {
  isLoadingAccounts.value = true
  try {
    postableAccounts.value = await financeStore.fetchFinancialAccountsOptions({ postable: true })
  }
  catch {
    postableAccounts.value = []
  }
  finally {
    isLoadingAccounts.value = false
  }
}

const requireAccount = computed(() => financeStore.requireConceptAccount)

const filteredAccountOptions = computed(() => postableAccounts.value
  .filter(acc => directionAllowsAccount(form.value.direction, acc))
  .map(acc => ({
    value: acc.id,
    title: `${acc.code} - ${acc.name}`,
  })))

// Si al cambiar la dirección la cuenta elegida deja de ser válida, se limpia.
watch(() => form.value.direction, () => {
  const selected = form.value['financial_account_id']
  if (selected && postableAccounts.value.length > 0 && !filteredAccountOptions.value.some(opt => opt.value === selected))
    form.value['financial_account_id'] = null
})

watch(() => [props.isDrawerOpen, props.conceptData], () => {
  const val = props.conceptData

  form.value = val
    ? {
      code: val.code || '',
      name: val.name || '',
      direction: val.direction?.value || val.direction || 'expense',
      'financial_account_id': val.financial_account_id || val.financial_account?.id || null,
      'is_active': val.is_active !== undefined ? Boolean(val.is_active) : true,
    }
    : { ...defaultForm }
  clearErrors()
  if (props.isDrawerOpen) {
    loadPostableAccounts()
    financeStore.fetchHealth().catch(() => {})
  }
}, { immediate: true })

const handleClose = () => {
  emit('update:isDrawerOpen', false)
  clearErrors()
}

const handleSubmit = () => {
  clearErrors()
  emit('submit', {
    id: props.conceptData?.id,
    code: form.value.code.trim().toUpperCase(),
    name: form.value.name.trim(),
    direction: form.value.direction,
    'financial_account_id': form.value['financial_account_id'] || null,
    'is_active': Boolean(form.value['is_active']),
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
    :width="440"
    :model-value="props.isDrawerOpen"
    class="scrollable-content"
    @update:model-value="handleClose"
  >
    <DrawerHeader
      :title="isEditing ? 'Editar Concepto Financiero' : 'Nuevo Concepto Financiero'"
      icon="tabler-receipt"
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
              <VTextField
                v-model="form.code"
                label="Código *"
                placeholder="OTHER_EXPENSE"
                maxlength="40"
                :disabled="isSystem"
                :error-messages="errors.code"
                @input="clearFieldError('code')"
              />
            </VCol>

            <VCol cols="12">
              <VTextField
                v-model="form.name"
                label="Nombre del Concepto *"
                placeholder="Gastos Notariales y Registrales"
                maxlength="120"
                :error-messages="errors.name"
                @input="clearFieldError('name')"
              />
            </VCol>

            <VCol cols="12">
              <VSelect
                v-model="form.direction"
                label="Dirección de Flujo *"
                placeholder="Egreso"
                :items="CONCEPT_DIRECTIONS"
                :disabled="isSystem"
                :error-messages="errors.direction"
                @update:model-value="clearFieldError('direction')"
              />
            </VCol>

            <VCol cols="12">
              <VAutocomplete
                v-model="form.financial_account_id"
                :label="requireAccount ? 'Cuenta Contable *' : 'Cuenta Contable'"
                placeholder="6391 - Gastos bancarios"
                :items="filteredAccountOptions"
                :loading="isLoadingAccounts"
                no-data-text="No hay cuentas imputables para esta dirección"
                :clearable="!requireAccount"
                :error-messages="errors.financial_account_id"
                @update:model-value="clearFieldError('financial_account_id')"
              />
            </VCol>

            <VCol cols="12">
              <VSwitch
                v-model="form.is_active"
                label="Activo"
                color="success"
                :error-messages="errors.is_active"
                @update:model-value="clearFieldError('is_active')"
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
                {{ isEditing ? 'Guardar Cambios' : 'Crear Concepto' }}
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
