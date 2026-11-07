<script setup>
import DrawerHeader from '@/components/common/DrawerHeader.vue'
import { computed, ref, watch } from 'vue'
import { useApiFormErrors } from '@/composables/useApiFormErrors'

const props = defineProps({
  isDrawerOpen: {
    type: Boolean,
    required: true,
  },
  currencyData: {
    type: Object,
    default: null,
  },
  isSubmitting: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:isDrawerOpen', 'submit'])

const { errors, generalError, setErrors, clearErrors, clearFieldError } = useApiFormErrors()

const isEditing = computed(() => Boolean(props.currencyData?.id))
const isCurrentBase = computed(() => Boolean(props.currencyData?.is_base))

const defaultForm = {
  code: '',
  name: '',
  symbol: '',
  'sunat_code': '',
  decimals: 2,
  'is_base': false,
  'is_active': true,
}

const form = ref({ ...defaultForm })

watch(() => [props.isDrawerOpen, props.currencyData], () => {
  const val = props.currencyData

  form.value = val
    ? {
      code: val.code || '',
      name: val.name || '',
      symbol: val.symbol || '',
      'sunat_code': val.sunat_code || '',
      decimals: val.decimals !== undefined ? Number(val.decimals) : 2,
      'is_base': Boolean(val.is_base),
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
    id: props.currencyData?.id,
    code: form.value.code.trim().toUpperCase(),
    name: form.value.name.trim(),
    symbol: form.value.symbol.trim(),
    'sunat_code': form.value.sunat_code?.trim() || null,
    decimals: Number(form.value.decimals),
    'is_base': Boolean(form.value.is_base),
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
      :title="isEditing ? 'Editar Moneda' : 'Nueva Moneda'"
      icon="tabler-currency-dollar"
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
                label="Código ISO *"
                placeholder="USD"
                maxlength="3"
                :disabled="isEditing && isCurrentBase"
                :error-messages="errors.code"
                @input="clearFieldError('code')"
              />
            </VCol>

            <VCol cols="12">
              <VTextField
                v-model="form.name"
                label="Nombre *"
                placeholder="Dólar Estadounidense"
                maxlength="40"
                :error-messages="errors.name"
                @input="clearFieldError('name')"
              />
            </VCol>

            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model="form.symbol"
                label="Símbolo *"
                placeholder="$"
                maxlength="5"
                :error-messages="errors.symbol"
                @input="clearFieldError('symbol')"
              />
            </VCol>

            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model="form.sunat_code"
                label="Código SUNAT"
                placeholder="USD"
                maxlength="5"
                :error-messages="errors.sunat_code"
                @input="clearFieldError('sunat_code')"
              />
            </VCol>

            <VCol cols="12">
              <VTextField
                v-model="form.decimals"
                type="number"
                min="0"
                max="4"
                label="Decimales *"
                placeholder="2"
                :error-messages="errors.decimals"
                @input="clearFieldError('decimals')"
              />
            </VCol>

            <VCol cols="12">
              <VSwitch
                v-model="form.is_base"
                label="Moneda Base"
                color="primary"
                :disabled="isEditing && isCurrentBase"
                :error-messages="errors.is_base"
                messages="La moneda base define la unidad contable principal del sistema"
                @update:model-value="clearFieldError('is_base')"
              />
            </VCol>

            <VCol cols="12">
              <VSwitch
                v-model="form.is_active"
                label="Activo"
                color="success"
                :disabled="isEditing && isCurrentBase"
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
                {{ isEditing ? 'Guardar Cambios' : 'Crear Moneda' }}
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
