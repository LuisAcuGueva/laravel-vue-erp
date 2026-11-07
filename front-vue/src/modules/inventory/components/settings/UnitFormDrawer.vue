<script setup>
import DrawerHeader from '@/components/common/DrawerHeader.vue'
import { computed, ref, watch } from 'vue'
import { useApiFormErrors } from '@/composables/useApiFormErrors'

const props = defineProps({
  isDrawerOpen: {
    type: Boolean,
    required: true,
  },
  unitData: {
    type: Object,
    default: null,
  },

  /** `[{ value, title, is_packaging }]` */
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

const isEditing = computed(() => Boolean(props.unitData?.id))

const defaultForm = {
  'unit_category_id': null,
  name: '',
  symbol: '',
  'is_base': false,
  'conversion_factor': '1.000000',
  'allow_decimals': true,
  'sunat_code': '',
  'is_active': true,
}

const form = ref({ ...defaultForm })

// Las unidades de empaque no tienen base ni factor: el factor lo define la presentación de cada producto.
const isPackaging = computed(() => Boolean(
  props.categoryOptions.find(c => c.value === form.value.unit_category_id)?.is_packaging,
))

watch(() => [props.isDrawerOpen, props.unitData], () => {
  const val = props.unitData

  form.value = val
    ? {
      'unit_category_id': val.unit_category_id,
      name: val.name || '',
      symbol: val.symbol || '',
      'is_base': Boolean(val.is_base),
      'conversion_factor': val.conversion_factor || '1.000000',
      'allow_decimals': val.allow_decimals !== undefined ? Boolean(val.allow_decimals) : true,
      'sunat_code': val.sunat_code || '',
      'is_active': val.is_active !== undefined ? Boolean(val.is_active) : true,
    }
    : { ...defaultForm, 'unit_category_id': props.categoryOptions[0]?.value ?? null }
  clearErrors()
}, { immediate: true })

const handleClose = () => {
  emit('update:isDrawerOpen', false)
  clearErrors()
}

const handleSubmit = () => {
  clearErrors()

  const isBase = !isPackaging.value && form.value.is_base

  emit('submit', {
    id: props.unitData?.id,
    'unit_category_id': form.value.unit_category_id,
    name: form.value.name.trim(),
    symbol: form.value.symbol.trim(),
    'is_base': isBase,
    'conversion_factor': isBase || isPackaging.value ? '1.000000' : form.value.conversion_factor,
    'allow_decimals': Boolean(form.value.allow_decimals),
    'sunat_code': form.value.sunat_code?.trim().toUpperCase() || null,
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
      :title="isEditing ? 'Editar Unidad' : 'Nueva Unidad'"
      icon="tabler-scale"
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
              <VAutocomplete
                v-model="form.unit_category_id"
                label="Categoría *"
                placeholder="Masa"
                :items="props.categoryOptions"
                :error-messages="errors.unit_category_id"
                @update:model-value="clearFieldError('unit_category_id')"
              />
            </VCol>

            <VCol cols="12">
              <VTextField
                v-model="form.name"
                label="Nombre *"
                placeholder="Kilogramo"
                maxlength="60"
                :error-messages="errors.name"
                @input="clearFieldError('name')"
              />
            </VCol>

            <VCol cols="12">
              <VTextField
                v-model="form.symbol"
                label="Símbolo *"
                placeholder="KG"
                maxlength="10"
                :error-messages="errors.symbol"
                @input="clearFieldError('symbol')"
              />
            </VCol>

            <VCol
              v-if="isPackaging"
              cols="12"
            >
              <VAlert
                type="info"
                variant="tonal"
                density="compact"
              >
                Unidad de empaque: no tiene factor fijo. Cuántas unidades contiene se define en la presentación de cada producto.
              </VAlert>
            </VCol>

            <template v-else>
              <VCol cols="12">
                <VSwitch
                  v-model="form.is_base"
                  label="Unidad base de la categoría"
                  color="primary"
                  :error-messages="errors.is_base"
                  @update:model-value="clearFieldError('is_base')"
                />
              </VCol>

              <VCol
                v-if="!form.is_base"
                cols="12"
              >
                <VTextField
                  v-model="form.conversion_factor"
                  label="Factor de conversión *"
                  placeholder="0.001"
                  type="number"
                  step="0.000001"
                  min="0.000001"
                  :error-messages="errors.conversion_factor"
                  @input="clearFieldError('conversion_factor')"
                />
                <span class="text-caption text-disabled">
                  Cuántas unidades base equivale una de esta unidad (1 g = 0.001 kg).
                </span>
              </VCol>
            </template>

            <VCol cols="12">
              <VSwitch
                v-model="form.allow_decimals"
                label="Permite cantidades con decimales"
                color="primary"
                :error-messages="errors.allow_decimals"
                @update:model-value="clearFieldError('allow_decimals')"
              />
            </VCol>

            <VCol cols="12">
              <VTextField
                v-model="form.sunat_code"
                label="Código SUNAT"
                placeholder="KGM"
                maxlength="5"
                :error-messages="errors.sunat_code"
                @input="clearFieldError('sunat_code')"
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
                {{ isEditing ? 'Guardar Cambios' : 'Crear Unidad' }}
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
