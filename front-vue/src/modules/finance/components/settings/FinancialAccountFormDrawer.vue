<script setup>
import DrawerHeader from '@/components/common/DrawerHeader.vue'
import { computed, ref, watch } from 'vue'
import { useApiFormErrors } from '@/composables/useApiFormErrors'
import { accountCodeRule } from '../../utils/accountRules'
import { FINANCIAL_ACCOUNT_TYPES } from '../../utils/constants'

const props = defineProps({
  isDrawerOpen: {
    type: Boolean,
    required: true,
  },
  accountData: {
    type: Object,
    default: null,
  },
  accountOptions: {
    type: Array,
    default: () => [],
  },
  isSubmitting: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:isDrawerOpen', 'submit'])

const { errors, generalError, setErrors, clearErrors, clearFieldError } = useApiFormErrors()

const isEditing = computed(() => Boolean(props.accountData?.id))
const hasChildren = computed(() => Boolean(props.accountData?.children?.length > 0))

const defaultForm = {
  code: '',
  name: '',
  type: 'asset',
  'parent_id': null,
  'is_postable': true,
  'is_active': true,
}

const form = ref({ ...defaultForm })

const parentOptions = computed(() => {
  return props.accountOptions
    .filter(a => !props.accountData?.id || a.id !== props.accountData.id)
    .map(a => ({
      value: a.id,
      title: `${a.code} - ${a.name}`,
      type: a.type?.value || a.type,
      level: a.level,
    }))
})

const selectedParent = computed(() => {
  if (!form.value['parent_id'])
    return null

  return parentOptions.value.find(p => p.value === form.value['parent_id']) || null
})

const selectedParentCode = computed(() => {
  const parent = props.accountOptions.find(a => a.id === form.value['parent_id'])

  return parent?.code || null
})

watch(() => selectedParent.value, parent => {
  if (parent) {
    form.value.type = parent.type
  }
})

watch(() => [props.isDrawerOpen, props.accountData], () => {
  const val = props.accountData

  form.value = val
    ? {
      code: val.code || '',
      name: val.name || '',
      type: val.type?.value || val.type || 'asset',
      'parent_id': val.parent_id || val.parent?.id || null,
      'is_postable': val.is_postable !== undefined ? Boolean(val.is_postable) : true,
      'is_active': val.is_active !== undefined ? Boolean(val.is_active) : true,
    }
    : { ...defaultForm }
  clearErrors()
}, { immediate: true })

const handleClose = () => {
  emit('update:isDrawerOpen', false)
  clearErrors()
}

const handleSubmit = () => {
  clearErrors()
  emit('submit', {
    id: props.accountData?.id,
    code: form.value.code.trim(),
    name: form.value.name.trim(),
    type: form.value['parent_id'] ? undefined : form.value.type,
    'parent_id': form.value['parent_id'] || null,
    'is_postable': Boolean(form.value['is_postable']),
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
      :title="isEditing ? 'Editar Cuenta Contable' : 'Nueva Cuenta Contable'"
      icon="tabler-list-numbers"
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
                label="Código Contable *"
                placeholder="6361"
                maxlength="8"
                :rules="[v => accountCodeRule(v, selectedParentCode)]"
                :error-messages="errors.code"
                @input="clearFieldError('code')"
              />
            </VCol>

            <VCol cols="12">
              <VTextField
                v-model="form.name"
                label="Nombre de la Cuenta *"
                placeholder="Caja General"
                maxlength="150"
                :error-messages="errors.name"
                @input="clearFieldError('name')"
              />
            </VCol>

            <VCol cols="12">
              <VSelect
                v-model="form.parent_id"
                label="Cuenta Padre (Opcional)"
                placeholder="10 - Efectivo y equivalentes"
                :items="parentOptions"
                clearable
                :error-messages="errors.parent_id"
                @update:model-value="clearFieldError('parent_id')"
              />
            </VCol>

            <VCol cols="12">
              <VSelect
                v-model="form.type"
                label="Tipo de Cuenta *"
                placeholder="Activo"
                :items="FINANCIAL_ACCOUNT_TYPES"
                :disabled="Boolean(form.parent_id)"
                :messages="form.parent_id ? 'El tipo se hereda automáticamente de la cuenta padre' : ''"
                :error-messages="errors.type"
                @update:model-value="clearFieldError('type')"
              />
            </VCol>

            <VCol cols="12">
              <VSwitch
                v-model="form.is_postable"
                label="Cuenta Imputable (acepta asientos / conceptos)"
                color="primary"
                :disabled="hasChildren"
                :messages="hasChildren ? 'Las cuentas que tienen subcuentas no pueden ser imputables' : ''"
                :error-messages="errors.is_postable"
                @update:model-value="clearFieldError('is_postable')"
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
                {{ isEditing ? 'Guardar Cambios' : 'Crear Cuenta Contable' }}
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
