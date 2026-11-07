<script setup>
import DrawerHeader from '@/components/common/DrawerHeader.vue'
import { computed, ref, watch } from 'vue'
import { useApiFormErrors } from '@/composables/useApiFormErrors'
import { allowedParentOptions } from '../../utils/categoryTree'

const props = defineProps({
  isDrawerOpen: {
    type: Boolean,
    required: true,
  },
  categoryData: {
    type: Object,
    default: null,
  },

  /** Opciones de `/categories/options`: `[{ id, parent_id, code, name, depth }]`. */
  categoryOptions: {
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

const isEditing = computed(() => Boolean(props.categoryData?.id))

// Excluye la propia categoría, sus descendientes y los padres que harían superar los 3 niveles.
// Las opciones solo traen categorías activas: el padre actual se conserva aunque esté inactivo.
const parentOptions = computed(() => {
  const options = allowedParentOptions(props.categoryOptions, props.categoryData?.id)
  const current = props.categoryData?.parent

  if (current && !options.some(o => o.value === current.id))
    options.unshift({ value: current.id, title: current.name })

  return options
})

const defaultForm = {
  name: '',
  code: '',
  'parent_id': null,
  description: '',
  'is_active': true,
}

const form = ref({ ...defaultForm })

watch(() => [props.isDrawerOpen, props.categoryData], () => {
  const val = props.categoryData

  form.value = val
    ? {
      name: val.name || '',
      code: val.code || '',
      'parent_id': val.parent_id || null,
      description: val.description || '',
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
    id: props.categoryData?.id,
    name: form.value.name.trim(),
    code: form.value.code?.trim().toUpperCase() || null,
    'parent_id': form.value.parent_id || null,
    description: form.value.description?.trim() || null,
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
      :title="isEditing ? 'Editar Categoría' : 'Nueva Categoría'"
      icon="tabler-folder"
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
                v-model="form.name"
                label="Nombre *"
                placeholder="Laptops"
                maxlength="120"
                :error-messages="errors.name"
                @input="clearFieldError('name')"
              />
            </VCol>

            <VCol cols="12">
              <VTextField
                v-model="form.code"
                label="Código"
                placeholder="TEC-COM-LAP"
                maxlength="20"
                :error-messages="errors.code"
                @input="clearFieldError('code')"
              />
            </VCol>

            <VCol cols="12">
              <VAutocomplete
                v-model="form.parent_id"
                label="Categoría padre"
                placeholder="Tecnología › Cómputo"
                :items="parentOptions"
                :error-messages="errors.parent_id"
                clearable
                @update:model-value="clearFieldError('parent_id')"
              />
              <span class="text-caption text-disabled">
                Sin padre, la categoría queda en el primer nivel. Se permiten hasta 3 niveles.
              </span>
            </VCol>

            <VCol cols="12">
              <VTextarea
                v-model="form.description"
                label="Descripción"
                placeholder="Computadoras portátiles y ultrabooks"
                rows="3"
                maxlength="500"
                :error-messages="errors.description"
                @input="clearFieldError('description')"
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
                {{ isEditing ? 'Guardar Cambios' : 'Crear Categoría' }}
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
