<script setup>
import DrawerHeader from '@/components/common/DrawerHeader.vue'
import { computed, ref, watch } from 'vue'
import { useApiFormErrors } from '@/composables/useApiFormErrors'

const props = defineProps({
  isDrawerOpen: {
    type: Boolean,
    required: true,
  },
  warehouseData: {
    type: Object,
    default: null,
  },

  /** Sedes accesibles: `[{ value, title }]` */
  branchOptions: {
    type: Array,
    default: () => [],
  },

  /** Sede preseleccionada al crear (la sede activa). */
  defaultBranchId: {
    type: String,
    default: null,
  },
  isSubmitting: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:isDrawerOpen', 'submit'])

const { errors, generalError, setErrors, clearErrors, clearFieldError } = useApiFormErrors()

const isEditing = computed(() => Boolean(props.warehouseData?.id))

const defaultForm = {
  code: '',
  name: '',
  'branch_id': null,
  address: '',
  'is_default': false,
  'is_active': true,
}

const form = ref({ ...defaultForm })

watch(() => [props.isDrawerOpen, props.warehouseData], () => {
  const val = props.warehouseData

  form.value = val
    ? {
      code: val.code || '',
      name: val.name || '',
      'branch_id': val.branch_id,
      address: val.address || '',
      'is_default': Boolean(val.is_default),
      'is_active': val.is_active !== undefined ? Boolean(val.is_active) : true,
    }
    : { ...defaultForm, 'branch_id': props.defaultBranchId ?? props.branchOptions[0]?.value ?? null }
  clearErrors()
}, { immediate: true })

const handleClose = () => {
  emit('update:isDrawerOpen', false)
  clearErrors()
}

const handleSubmit = () => {
  clearErrors()
  emit('submit', {
    id: props.warehouseData?.id,
    code: form.value.code.trim().toUpperCase(),
    name: form.value.name.trim(),
    'branch_id': form.value.branch_id,
    address: form.value.address?.trim() || null,
    'is_default': Boolean(form.value.is_default),
    'is_active': Boolean(form.value.is_active),
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
      :title="isEditing ? 'Editar Almacén' : 'Nuevo Almacén'"
      icon="tabler-building-warehouse"
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
                placeholder="ALM01"
                maxlength="10"
                :error-messages="errors.code"
                @input="clearFieldError('code')"
              />
            </VCol>

            <VCol cols="12">
              <VTextField
                v-model="form.name"
                label="Nombre *"
                placeholder="Almacén Principal"
                maxlength="120"
                :error-messages="errors.name"
                @input="clearFieldError('name')"
              />
            </VCol>

            <VCol cols="12">
              <VAutocomplete
                v-model="form.branch_id"
                label="Sede *"
                placeholder="Sede Principal"
                :items="props.branchOptions"
                :error-messages="errors.branch_id"
                @update:model-value="clearFieldError('branch_id')"
              />
            </VCol>

            <VCol cols="12">
              <VTextField
                v-model="form.address"
                label="Dirección"
                placeholder="Av. Principal 123"
                maxlength="255"
                :error-messages="errors.address"
                @input="clearFieldError('address')"
              />
            </VCol>

            <VCol cols="12">
              <VSwitch
                v-model="form.is_default"
                label="Almacén por defecto de la sede"
                color="primary"
                :error-messages="errors.is_default"
                @update:model-value="clearFieldError('is_default')"
              />
              <span class="text-caption text-disabled">
                Cada sede tiene un solo almacén por defecto; al marcar este, se desmarca el anterior.
              </span>
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
                {{ isEditing ? 'Guardar Cambios' : 'Crear Almacén' }}
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
