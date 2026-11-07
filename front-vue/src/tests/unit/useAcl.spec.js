import { beforeEach, describe, expect, it } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useAcl } from '@/composables/useAcl'
import { useAuthStore } from '@/modules/auth/stores/authStore'

describe('useAcl composable', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('permite cualquier acción si no se especifica permiso', () => {
    const { can } = useAcl()

    expect(can(null)).toBe(true)
    expect(can(undefined)).toBe(true)
    expect(can('')).toBe(true)
  })

  it('concede acceso total al rol admin o superadmin', () => {
    const authStore = useAuthStore()

    authStore.roles = ['admin']
    authStore.permissions = []

    const { can, isSuperAdmin } = useAcl()

    expect(isSuperAdmin.value).toBe(true)
    expect(can('users.delete')).toBe(true)
    expect(can('roles.create')).toBe(true)
  })

  it('concede acceso total si posee el comodín * o all', () => {
    const authStore = useAuthStore()

    authStore.roles = ['user']
    authStore.permissions = ['*']

    const { can } = useAcl()

    expect(can('anything.action')).toBe(true)

    authStore.permissions = ['all']
    expect(can('other.action')).toBe(true)
  })

  it('evalúa permisos individuales correctamente para usuarios estándar', () => {
    const authStore = useAuthStore()

    authStore.roles = ['operator']
    authStore.permissions = ['clients.view', 'clients.create']

    const { can } = useAcl()

    expect(can('clients.view')).toBe(true)
    expect(can('clients.create')).toBe(true)
    expect(can('clients.delete')).toBe(false)
  })

  it('canAny evalúa si el usuario posee al menos un permiso de la lista', () => {
    const authStore = useAuthStore()

    authStore.roles = ['operator']
    authStore.permissions = ['clients.view']

    const { canAny } = useAcl()

    expect(canAny(['clients.view', 'clients.delete'])).toBe(true)
    expect(canAny(['clients.delete', 'clients.update'])).toBe(false)
    expect(canAny([])).toBe(true)
  })

  it('canAll verifica que el usuario posea todos los permisos indicados', () => {
    const authStore = useAuthStore()

    authStore.roles = ['operator']
    authStore.permissions = ['clients.view', 'clients.update']

    const { canAll } = useAcl()

    expect(canAll(['clients.view', 'clients.update'])).toBe(true)
    expect(canAll(['clients.view', 'clients.delete'])).toBe(false)
    expect(canAll([])).toBe(true)
  })

  it('hasRole verifica la asignación de roles', () => {
    const authStore = useAuthStore()

    authStore.roles = ['Manager', 'Auditor']

    const { hasRole } = useAcl()

    expect(hasRole('manager')).toBe(true)
    expect(hasRole('Auditor')).toBe(true)
    expect(hasRole('Admin')).toBe(false)
  })
})
