<script setup>
/* eslint-disable camelcase */
import DrawerHeader from '@/components/common/DrawerHeader.vue'
import { useApiFormErrors } from '@/composables/useApiFormErrors'
import { computed, ref, watch } from 'vue'

const props = defineProps({
  isDrawerOpen: {
    type: Boolean,
    required: true,
  },
  presentationData: {
    type: Object,
    default: null,
  },
  units: {
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

const isEditing = computed(() => Boolean(props.presentationData?.id))

const defaultForm = {
  name: '',
  unit_id: null,
  factor: 1,
  barcode: '',
  is_default: false,
  is_active: true,
}

const form = ref({ ...defaultForm })

watch(() => [props.isDrawerOpen, props.presentationData], () => {
  const val = props.presentationData

  form.value = val
    ? {
      name: val.name || '',
      unit_id: val.unit_id || null,
      factor: val.factor ?? 1,
      barcode: val.barcode || '',
      is_default: Boolean(val.is_default),
      is_active: val.is_active !== undefined ? Boolean(val.is_active) : true,
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
    id: props.presentationData?.id,
    name: form.value.name.trim(),
    unit_id: form.value.unit_id,
    factor: Number(form.value.factor),
    barcode: form.value.barcode?.trim() || null,
    is_default: Boolean(form.value.is_default),
    is_active: Boolean(form.value.is_active),
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
    :width="480"
    :model-value="props.isDrawerOpen"
    class="scrollable-content"
    @update:model-value="handleClose"
  >
    <DrawerHeader
      :title="isEditing ? 'Editar Presentación' : 'Nueva Presentación'"
      icon="tabler-box-seam"
      @close="handleClose"
    />

    <VCardText class="pb-6">
      <VAlert
        v-if="generalError"
        type="error"
        density="compact"
        class="mb-4"
      >
        {{ generalError }}
      </VAlert>

      <VForm @submit.prevent="handleSubmit">
        <VRow>
          <VCol cols="12">
            <VTextField
              v-model="form.name"
              label="Nombre de la Presentación *"
              placeholder="Caja x 12 unidades"
              maxlength="80"
              :error-messages="errors.name"
              @input="clearFieldError('name')"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <VAutocomplete
              v-model="form.unit_id"
              :items="props.units"
              :item-title="u => `${u.name} (${u.symbol})`"
              item-value="id"
              label="Unidad *"
              placeholder="Caja (CJA)"
              clearable
              :error-messages="errors.unit_id"
              @update:model-value="clearFieldError('unit_id')"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <VTextField
              v-model="form.factor"
              type="number"
              step="0.0001"
              min="0.0001"
              label="Factor de Conversión *"
              placeholder="12"
              :error-messages="errors.factor"
              @input="clearFieldError('factor')"
            />
          </VCol>

          <VCol cols="12">
            <VTextField
              v-model="form.barcode"
              label="Código de Barras"
              placeholder="7751234567890"
              maxlength="50"
              :error-messages="errors.barcode"
              @input="clearFieldError('barcode')"
            />
          </VCol>

          <VCol cols="12">
            <VDivider class="my-2" />
            <div class="d-flex flex-wrap gap-4">
              <VSwitch
                v-model="form.is_default"
                label="Predeterminada"
                color="warning"
                density="compact"
              />
              <VSwitch
                v-model="form.is_active"
                label="Activa"
                color="success"
                density="compact"
              />
            </div>
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
              {{ isEditing ? 'Guardar Cambios' : 'Registrar Presentación' }}
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
  </VNavigationDrawer>
</template>
