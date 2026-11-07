<script setup>
import DrawerHeader from '@/components/common/DrawerHeader.vue'
import { computed, ref, watch } from 'vue'
import { useApiFormErrors } from '@/composables/useApiFormErrors'

const props = defineProps({
  isDrawerOpen: {
    type: Boolean,
    required: true,
  },
  branchData: {
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

const isEditing = computed(() => Boolean(props.branchData?.id))

const defaultForm = {
  code: '',
  name: '',
  address: '',
  ubigeo: null,
  phone: '',
  email: '',
  'is_main': false,
  'is_active': true,
}

const form = ref({ ...defaultForm })

watch(() => props.branchData, val => {
  if (val) {
    form.value = {
      code: val.code || '',
      name: val.name || '',
      address: val.address || '',
      ubigeo: val.ubigeo || null,
      phone: val.phone || '',
      email: val.email || '',
      'is_main': Boolean(val.is_main),
      'is_active': val.is_active !== undefined ? Boolean(val.is_active) : true,
    }
  }
  else {
    form.value = { ...defaultForm }
  }
  clearErrors()
}, { immediate: true })

const handleClose = () => {
  emit('update:isDrawerOpen', false)
  form.value = { ...defaultForm }
  clearErrors()
}

const handleSubmit = () => {
  clearErrors()
  if (!form.value.code || !form.value.code.trim()) {
    errors.value.code = 'El código es obligatorio.'

    return
  }
  if (!form.value.name || !form.value.name.trim()) {
    errors.value.name = 'El nombre de la sede es obligatorio.'

    return
  }

  emit('submit', {
    ...form.value,
    code: form.value.code.trim().toUpperCase(),
    name: form.value.name.trim(),
    address: form.value.address?.trim() || null,
    ubigeo: form.value.ubigeo || null,
    phone: form.value.phone?.trim() || null,
    email: form.value.email?.trim() || null,
    id: props.branchData?.id,
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
    <!-- Header -->
    <DrawerHeader
      :title="isEditing ? 'Editar Sede' : 'Nueva Sede'"
      icon="tabler-building-store"
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
            <!-- Código -->
            <VCol cols="12">
              <VTextField
                v-model="form.code"
                label="Código *"
                placeholder="SEDE-01"
                maxlength="10"
                :error-messages="errors.code"
                @input="clearFieldError('code')"
              />
            </VCol>

            <!-- Nombre -->
            <VCol cols="12">
              <VTextField
                v-model="form.name"
                label="Nombre *"
                placeholder="Sede Central"
                maxlength="150"
                :error-messages="errors.name"
                @input="clearFieldError('name')"
              />
            </VCol>

            <!-- Dirección -->
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

            <!-- Ubigeo -->
            <VCol cols="12">
              <UbigeoSelect
                v-model="form.ubigeo"
                :error-messages="errors.ubigeo"
                @update:model-value="clearFieldError('ubigeo')"
              />
            </VCol>

            <!-- Teléfono -->
            <VCol cols="12">
              <VTextField
                v-model="form.phone"
                label="Teléfono"
                placeholder="01-1234567"
                maxlength="30"
                :error-messages="errors.phone"
                @input="clearFieldError('phone')"
              />
            </VCol>

            <!-- Email -->
            <VCol cols="12">
              <VTextField
                v-model="form.email"
                label="Correo electrónico"
                placeholder="sede@empresa.com"
                type="email"
                maxlength="150"
                :error-messages="errors.email"
                @input="clearFieldError('email')"
              />
            </VCol>

            <!-- Es Principal -->
            <VCol cols="12">
              <VSwitch
                v-model="form.is_main"
                label="Sede principal"
                color="primary"
                :error-messages="errors.is_main"
                @update:model-value="clearFieldError('is_main')"
              />
              <span class="text-caption text-disabled">
                La sede principal se asigna por defecto a nuevos usuarios.
              </span>
            </VCol>

            <!-- Estado -->
            <VCol cols="12">
              <VSwitch
                v-model="form.is_active"
                label="Activo"
                color="success"
                :error-messages="errors.is_active"
                @update:model-value="clearFieldError('is_active')"
              />
            </VCol>

            <!-- Acciones -->
            <VCol
              cols="12"
              class="d-flex gap-4 mt-2"
            >
              <VBtn
                type="submit"
                :loading="props.isSubmitting"
                :disabled="props.isSubmitting"
              >
                {{ isEditing ? 'Guardar Cambios' : 'Crear Sede' }}
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
