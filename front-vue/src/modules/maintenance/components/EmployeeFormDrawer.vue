<script setup>
import DrawerHeader from '@/components/common/DrawerHeader.vue'
import { computed, onMounted, ref, watch } from 'vue'
import { useApiFormErrors } from '@/composables/useApiFormErrors'
import EmployeeAccessCard from '@/modules/maintenance/components/EmployeeAccessCard.vue'
import { areasApi } from '@/modules/maintenance/services/areasApi'
import { branchesApi } from '@/modules/maintenance/services/branchesApi'

const props = defineProps({
  isDrawerOpen: {
    type: Boolean,
    required: true,
  },
  employeeData: {
    type: Object,
    default: null,
  },
  isSubmitting: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:isDrawerOpen', 'submit', 'userUpdated'])

const { errors, generalError, setErrors, clearErrors, clearFieldError } = useApiFormErrors()

const isEditing = computed(() => Boolean(props.employeeData?.id))
const currentEmployee = ref(null)

const documentTypes = [
  { title: 'DNI', value: 'DNI' },
  { title: 'Carné de Extranjería (CE)', value: 'CE' },
  { title: 'Pasaporte', value: 'PASSPORT' },
]

const areaOptions = ref([])
const branchOptions = ref([])

const defaultForm = {
  documentType: 'DNI',
  documentNumber: '',
  firstName: '',
  lastName: '',
  email: '',
  phone: '',
  address: '',
  birthDate: '',
  hireDate: '',
  position: '',
  areaId: null,
  branchId: null,
  isActive: true,
}

const form = ref({ ...defaultForm })

const loadCatalogs = async () => {
  try {
    const [areasRes, branchesRes] = await Promise.all([
      areasApi.options(),
      branchesApi.options(),
    ])

    areaOptions.value = (areasRes?.data || []).map(a => ({
      title: a.name,
      value: a.id,
    }))

    branchOptions.value = (branchesRes?.data || []).map(b => ({
      title: `${b.code} - ${b.name}`,
      value: b.id,
    }))
  }
  catch (e) {
    console.error('Error al cargar catálogos', e)
  }
}

onMounted(() => {
  loadCatalogs()
})

watch(
  () => props.employeeData,
  val => {
    if (val) {
      currentEmployee.value = { ...val }
      form.value = {
        documentType: val.document_type || 'DNI',
        documentNumber: val.document_number || '',
        firstName: val.first_name || '',
        lastName: val.last_name || '',
        email: val.email || '',
        phone: val.phone || '',
        address: val.address || '',
        birthDate: val.birth_date || '',
        hireDate: val.hire_date || '',
        position: val.position || '',
        areaId: val.area_id || val.area?.id || null,
        branchId: val.branch_id || val.branch?.id || null,
        isActive: val.is_active !== undefined ? Boolean(val.is_active) : true,
      }
    }
    else {
      currentEmployee.value = null
      form.value = { ...defaultForm }
    }
    clearErrors()
  },
  { immediate: true },
)

const handleClose = () => {
  emit('update:isDrawerOpen', false)
  form.value = { ...defaultForm }
  clearErrors()
}

const handleSubmit = () => {
  clearErrors()

  if (!form.value.documentNumber || !form.value.documentNumber.trim()) {
    errors.value['document_number'] = 'El número de documento es obligatorio.'

    return
  }

  if (form.value.documentType === 'DNI' && form.value.documentNumber.trim().length !== 8) {
    errors.value['document_number'] = 'El DNI debe tener 8 dígitos.'

    return
  }

  if (!form.value.firstName || !form.value.firstName.trim()) {
    errors.value['first_name'] = 'El nombre es obligatorio.'

    return
  }

  if (!form.value.lastName || !form.value.lastName.trim()) {
    errors.value['last_name'] = 'El apellido es obligatorio.'

    return
  }

  emit('submit', {
    'document_type': form.value.documentType,
    'document_number': form.value.documentNumber.trim(),
    'first_name': form.value.firstName.trim(),
    'last_name': form.value.lastName.trim(),
    email: form.value.email?.trim() || null,
    phone: form.value.phone?.trim() || null,
    address: form.value.address?.trim() || null,
    'birth_date': form.value.birthDate || null,
    'hire_date': form.value.hireDate || null,
    position: form.value.position?.trim() || null,
    'area_id': form.value.areaId || null,
    'branch_id': form.value.branchId || null,
    'is_active': form.value.isActive,
    id: props.employeeData?.id,
  })
}

const onUserUpdated = updatedEmp => {
  currentEmployee.value = { ...updatedEmp }
  emit('userUpdated', updatedEmp)
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
    <!-- Cabecera -->
    <DrawerHeader
      :title="isEditing ? 'Editar Empleado' : 'Nuevo Empleado'"
      icon="tabler-id-badge-2"
      @close="handleClose"
    />

    <VCardText class="pb-6">
      <!-- Error General -->
      <VAlert
        v-if="generalError"
        type="error"
        density="compact"
        class="mb-4"
      >
        {{ generalError }}
      </VAlert>

      <!-- Tarjeta de Acceso al Sistema (solo modo edición) -->
      <EmployeeAccessCard
        v-if="isEditing && currentEmployee"
        :employee="currentEmployee"
        @user-updated="onUserUpdated"
      />

      <!-- Formulario de Ficha del Empleado -->
      <VForm @submit.prevent="handleSubmit">
        <VRow>
          <!-- Documento -->
          <VCol
            cols="12"
            sm="5"
          >
            <VSelect
              v-model="form.documentType"
              label="Tipo de documento *"
              :items="documentTypes"
              :error-messages="errors['document_type']"
              @update:model-value="clearFieldError('document_type')"
            />
          </VCol>

          <VCol
            cols="12"
            sm="7"
          >
            <VTextField
              v-model="form.documentNumber"
              label="Nro. documento *"
              placeholder="12345678"
              :maxlength="form.documentType === 'DNI' ? 8 : 15"
              :error-messages="errors['document_number']"
              @input="clearFieldError('document_number')"
            />
          </VCol>

          <!-- Nombres y Apellidos -->
          <VCol
            cols="12"
            sm="6"
          >
            <VTextField
              v-model="form.firstName"
              label="Nombres *"
              placeholder="Juan Carlos"
              :error-messages="errors['first_name']"
              @input="clearFieldError('first_name')"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <VTextField
              v-model="form.lastName"
              label="Apellidos *"
              placeholder="Pérez Gómez"
              :error-messages="errors['last_name']"
              @input="clearFieldError('last_name')"
            />
          </VCol>

          <!-- Contacto -->
          <VCol
            cols="12"
            sm="6"
          >
            <VTextField
              v-model="form.email"
              label="Correo personal"
              placeholder="juan.perez@gmail.com"
              type="email"
              :error-messages="errors.email"
              @input="clearFieldError('email')"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <VTextField
              v-model="form.phone"
              label="Teléfono / celular"
              placeholder="987 654 321"
              :error-messages="errors.phone"
              @input="clearFieldError('phone')"
            />
          </VCol>

          <VCol cols="12">
            <VTextField
              v-model="form.address"
              label="Dirección"
              placeholder="Av. Los Olivos 456"
              :error-messages="errors.address"
              @input="clearFieldError('address')"
            />
          </VCol>

          <!-- Datos Laborales -->
          <VCol cols="12">
            <VDivider class="mb-2" />
            <div class="text-caption font-weight-bold text-uppercase text-medium-emphasis">
              Datos Laborales
            </div>
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <VAutocomplete
              v-model="form.branchId"
              label="Sede de trabajo"
              placeholder="SEDE-01"
              :items="branchOptions"
              clearable
              :error-messages="errors['branch_id']"
              @update:model-value="clearFieldError('branch_id')"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <VAutocomplete
              v-model="form.areaId"
              label="Área"
              placeholder="Ventas"
              :items="areaOptions"
              clearable
              :error-messages="errors['area_id']"
              @update:model-value="clearFieldError('area_id')"
            />
          </VCol>

          <VCol cols="12">
            <VTextField
              v-model="form.position"
              label="Cargo / puesto"
              placeholder="Vendedor"
              :error-messages="errors.position"
              @input="clearFieldError('position')"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <VTextField
              v-model="form.birthDate"
              label="Fecha de nacimiento"
              type="date"
              :error-messages="errors['birth_date']"
              @input="clearFieldError('birth_date')"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <VTextField
              v-model="form.hireDate"
              label="Fecha de ingreso"
              type="date"
              :error-messages="errors['hire_date']"
              @input="clearFieldError('hire_date')"
            />
          </VCol>

          <VCol cols="12">
            <VSwitch
              v-model="form.isActive"
              label="Empleado activo"
              density="compact"
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
              {{ isEditing ? 'Guardar Cambios' : 'Crear Empleado' }}
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
