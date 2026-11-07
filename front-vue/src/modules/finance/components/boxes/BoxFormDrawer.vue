<script setup>
import DrawerHeader from '@/components/common/DrawerHeader.vue'
import { computed, ref, watch } from 'vue'
import { useApiFormErrors } from '@/composables/useApiFormErrors'

const props = defineProps({
  isDrawerOpen: {
    type: Boolean,
    required: true,
  },
  boxData: {
    type: Object,
    default: null,
  },
  rolesList: {
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

const isEditing = computed(() => Boolean(props.boxData?.id))

const defaultForm = {
  code: '',
  name: '',
  description: '',
  'role_ids': [],
  'is_active': true,
}

const form = ref({ ...defaultForm })

watch(() => [props.isDrawerOpen, props.boxData], () => {
  const val = props.boxData

  form.value = val
    ? {
      code: val.code || '',
      name: val.name || '',
      description: val.description || '',
      'role_ids': val.role_ids ? [...val.role_ids] : (val.roles ? val.roles.map(r => r.id) : []),
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
    id: props.boxData?.id,
    code: form.value.code.trim().toUpperCase(),
    name: form.value.name.trim(),
    description: form.value.description ? form.value.description.trim() : null,
    'role_ids': form.value.role_ids || [],
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
    :width="460"
    :model-value="props.isDrawerOpen"
    class="scrollable-content"
    @update:model-value="handleClose"
  >
    <DrawerHeader
      :title="isEditing ? 'Editar Caja' : 'Nueva Caja'"
      icon="tabler-device-desktop"
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
                placeholder="CAJA01"
                maxlength="10"
                :disabled="isEditing"
                :error-messages="errors.code"
                @input="clearFieldError('code')"
              />
            </VCol>

            <VCol cols="12">
              <VTextField
                v-model="form.name"
                label="Nombre *"
                placeholder="Caja Principal"
                maxlength="80"
                :error-messages="errors.name"
                @input="clearFieldError('name')"
              />
            </VCol>

            <VCol cols="12">
              <VTextarea
                v-model="form.description"
                label="Descripción"
                placeholder="Caja para cobranza en salón de ventas"
                maxlength="255"
                rows="2"
                :error-messages="errors.description"
                @input="clearFieldError('description')"
              />
            </VCol>

            <VCol cols="12">
              <VAutocomplete
                v-model="form.role_ids"
                :items="props.rolesList"
                item-title="name"
                item-value="id"
                label="Roles Autorizados"
                placeholder="Seleccionar roles con acceso"
                multiple
                chips
                closable-chips
                clearable
                :error-messages="errors.role_ids"
                @update:model-value="clearFieldError('role_ids')"
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
                {{ isEditing ? 'Guardar Cambios' : 'Crear Caja' }}
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
