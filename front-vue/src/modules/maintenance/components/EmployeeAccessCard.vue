<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useAcl } from '@/composables/useAcl'
import { useApiFormErrors } from '@/composables/useApiFormErrors'
import { useConfirm } from '@/composables/useConfirm'
import { useNotify } from '@/composables/useNotify'
import { branchesApi } from '@/modules/maintenance/services/branchesApi'
import { useEmployeesStore } from '@/modules/maintenance/stores/employeesStore'
import { rolesApi } from '@/modules/roles/services/rolesApi'
import { usersApi } from '@/modules/users/services/usersApi'

const props = defineProps({
  employee: {
    type: Object,
    required: true,
  },
})

const emit = defineEmits(['userUpdated'])

const { can } = useAcl()
const { confirm } = useConfirm()
const notify = useNotify()
const employeesStore = useEmployeesStore()
const { errors, generalError, setErrors, clearErrors } = useApiFormErrors()

const activeMode = ref(null) // null | 'create' | 'link'
const isSubmitting = ref(false)

// Catálogos
const availableRoles = ref([])
const availableBranches = ref([])
const availableUsers = ref([])
const isSearchingUsers = ref(false)

// Formulario de creación
const createForm = ref({
  email: '',
  password: '',
  'password_confirmation': '',
  roles: [],
  'branch_ids': [],
})

// Formulario de vinculación
const linkUserId = ref(null)

const hasUser = computed(() => Boolean(props.employee?.user?.id))

const loadRoles = async () => {
  try {
    const res = await rolesApi.list()

    availableRoles.value = (res?.data || []).map(r => ({
      title: r.name,
      value: r.name,
    }))
  }
  catch (e) {
    console.error('Error al cargar roles', e)
  }
}

const loadBranches = async () => {
  try {
    const res = await branchesApi.options()

    availableBranches.value = (res?.data || []).map(b => ({
      title: `${b.code} - ${b.name}`,
      value: b.id,
    }))
  }
  catch (e) {
    console.error('Error al cargar sedes', e)
  }
}

const searchUsers = async (query = '') => {
  isSearchingUsers.value = true
  try {
    const res = await usersApi.list({ search: query, 'without_employee': true, 'per_page': 20 })

    availableUsers.value = (res?.data || []).map(u => ({
      title: `${u.name} (${u.email})`,
      value: u.id,
    }))
  }
  catch (e) {
    console.error('Error al buscar usuarios', e)
  }
  finally {
    isSearchingUsers.value = false
  }
}

watch(
  () => props.employee,
  newEmp => {
    activeMode.value = null
    clearErrors()
    linkUserId.value = null
    createForm.value = {
      email: newEmp?.email || '',
      password: '',
      'password_confirmation': '',
      roles: ['user'],
      'branch_ids': newEmp?.branch_id ? [newEmp.branch_id] : [],
    }
  },
  { immediate: true },
)

onMounted(() => {
  loadRoles()
  loadBranches()
})

const openCreateMode = () => {
  clearErrors()
  activeMode.value = 'create'
  createForm.value.email = props.employee?.email || ''
  createForm.value['branch_ids'] = props.employee?.branch_id ? [props.employee.branch_id] : []
}

const openLinkMode = () => {
  clearErrors()
  activeMode.value = 'link'
  linkUserId.value = null
  searchUsers()
}

const cancelMode = () => {
  clearErrors()
  activeMode.value = null
}

const handleCreateUser = async () => {
  clearErrors()
  isSubmitting.value = true

  try {
    const updated = await employeesStore.createUser(props.employee.id, {
      email: createForm.value.email.trim(),
      password: createForm.value.password,
      'password_confirmation': createForm.value['password_confirmation'],
      roles: createForm.value.roles,
      'branch_ids': createForm.value['branch_ids'].length ? createForm.value['branch_ids'] : undefined,
    })

    activeMode.value = null
    notify.success('Usuario creado correctamente')
    emit('userUpdated', updated)
  }
  catch (err) {
    setErrors(err)
  }
  finally {
    isSubmitting.value = false
  }
}

const handleLinkUser = async () => {
  clearErrors()
  if (!linkUserId.value) {
    errors.value['user_id'] = 'Debe seleccionar un usuario para vincular.'

    return
  }

  isSubmitting.value = true
  try {
    const updated = await employeesStore.linkUser(props.employee.id, {
      'user_id': linkUserId.value,
    })

    activeMode.value = null
    notify.success('Usuario vinculado correctamente')
    emit('userUpdated', updated)
  }
  catch (err) {
    setErrors(err)
  }
  finally {
    isSubmitting.value = false
  }
}

const handleUnlinkUser = async () => {
  const confirmed = await confirm({
    title: '¿Desvincular usuario?',
    message: `¿Estás seguro de desvincular el acceso de este empleado? El usuario conservará su cuenta en el sistema.`,
    icon: 'tabler-user-off',
    color: 'warning',
    confirmText: 'Sí, desvincular',
    cancelText: 'Cancelar',
  })

  if (!confirmed) return

  isSubmitting.value = true
  try {
    const updated = await employeesStore.unlinkUser(props.employee.id)

    notify.success('Usuario desvinculado correctamente')
    emit('userUpdated', updated)
  }
  catch (err) {
    setErrors(err)
  }
  finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <VCard
    variant="outlined"
    class="mb-6"
  >
    <VCardItem class="pb-2">
      <template #prepend>
        <VIcon
          icon="tabler-lock-access"
          size="22"
          class="me-2"
        />
      </template>
      <VCardTitle class="text-subtitle-1 font-weight-bold">
        Acceso al Sistema
      </VCardTitle>
    </VCardItem>

    <VCardText>
      <!-- Alerta de error general si aplica -->
      <VAlert
        v-if="generalError"
        type="error"
        density="compact"
        class="mb-3"
      >
        {{ generalError }}
      </VAlert>

      <!-- Estado: Con usuario vinculado -->
      <div
        v-if="hasUser"
        class="d-flex flex-wrap align-center justify-space-between gap-2"
      >
        <div class="d-flex align-center gap-2">
          <VAvatar
            color="primary"
            variant="tonal"
            size="36"
          >
            <VIcon
              icon="tabler-user-check"
              size="20"
            />
          </VAvatar>
          <div>
            <div class="text-body-2 font-weight-medium">
              {{ employee.user.email }}
            </div>
            <VChip
              :color="employee.user.status === 'active' ? 'success' : 'secondary'"
              size="x-small"
              class="text-uppercase"
            >
              {{ employee.user.status === 'active' ? 'Activo' : 'Inactivo' }}
            </VChip>
          </div>
        </div>

        <VBtn
          color="error"
          variant="tonal"
          size="small"
          prepend-icon="tabler-unlink"
          :loading="isSubmitting"
          @click="handleUnlinkUser"
        >
          Desvincular
        </VBtn>
      </div>

      <!-- Estado: Sin usuario vinculado -->
      <div v-else>
        <div
          v-if="!activeMode"
          class="d-flex flex-column gap-2"
        >
          <div class="text-caption text-medium-emphasis mb-1">
            Este empleado no cuenta con usuario de acceso asignado.
          </div>
          <div class="d-flex flex-wrap gap-2">
            <VBtn
              v-if="can('users.create')"
              color="primary"
              variant="tonal"
              size="small"
              prepend-icon="tabler-user-plus"
              @click="openCreateMode"
            >
              Crear acceso
            </VBtn>
            <VBtn
              color="secondary"
              variant="tonal"
              size="small"
              prepend-icon="tabler-link"
              @click="openLinkMode"
            >
              Vincular existente
            </VBtn>
          </div>
        </div>

        <!-- Sub-formulario: Crear nuevo acceso -->
        <div
          v-else-if="activeMode === 'create'"
          class="mt-2"
        >
          <div class="text-subtitle-2 font-weight-bold mb-3">
            Crear credenciales de usuario
          </div>

          <VRow dense>
            <VCol cols="12">
              <VTextField
                v-model="createForm.email"
                label="Correo electrónico *"
                placeholder="juan@empresa.com"
                type="email"
                :error-messages="errors.email"
              />
            </VCol>

            <VCol
              cols="12"
              sm="6"
            >
              <VTextField
                v-model="createForm.password"
                label="Contraseña *"
                placeholder="············"
                type="password"
                :error-messages="errors.password"
              />
            </VCol>

            <VCol
              cols="12"
              sm="6"
            >
              <VTextField
                v-model="createForm.password_confirmation"
                label="Confirmar contraseña *"
                placeholder="············"
                type="password"
                :error-messages="errors.password_confirmation"
              />
            </VCol>

            <VCol cols="12">
              <VSelect
                v-model="createForm.roles"
                label="Roles *"
                placeholder="vendedor"
                :items="availableRoles"
                multiple
                chips
                closable-chips
                :error-messages="errors.roles"
              />
            </VCol>

            <VCol cols="12">
              <VAutocomplete
                v-model="createForm.branch_ids"
                label="Sedes de acceso"
                placeholder="SEDE-01"
                clearable
                :items="availableBranches"
                multiple
                chips
                closable-chips
                hint="Por defecto se asigna la sede del empleado"
                persistent-hint
                :error-messages="errors.branch_ids"
              />
            </VCol>
          </VRow>

          <div class="d-flex gap-2 mt-4">
            <VBtn
              color="primary"
              size="small"
              :loading="isSubmitting"
              @click="handleCreateUser"
            >
              Crear Usuario
            </VBtn>
            <VBtn
              variant="tonal"
              color="secondary"
              size="small"
              :disabled="isSubmitting"
              @click="cancelMode"
            >
              Cancelar
            </VBtn>
          </div>
        </div>

        <!-- Sub-formulario: Vincular usuario existente -->
        <div
          v-else-if="activeMode === 'link'"
          class="mt-2"
        >
          <div class="text-subtitle-2 font-weight-bold mb-3">
            Vincular cuenta de usuario existente
          </div>

          <VAutocomplete
            v-model="linkUserId"
            label="Buscar usuario *"
            :items="availableUsers"
            :loading="isSearchingUsers"
            placeholder="ana@empresa.com"
            :error-messages="errors.user_id"
            @update:search="searchUsers"
          />

          <div class="d-flex gap-2 mt-4">
            <VBtn
              color="primary"
              size="small"
              :loading="isSubmitting"
              @click="handleLinkUser"
            >
              Vincular
            </VBtn>
            <VBtn
              variant="tonal"
              color="secondary"
              size="small"
              :disabled="isSubmitting"
              @click="cancelMode"
            >
              Cancelar
            </VBtn>
          </div>
        </div>
      </div>
    </VCardText>
  </VCard>
</template>
