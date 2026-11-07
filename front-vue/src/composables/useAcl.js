import { computed } from 'vue'
import { useAuthStore } from '@/modules/auth/stores/authStore'

export const useAcl = () => {
  const authStore = useAuthStore()

  const roles = computed(() => authStore.roles || [])
  const permissions = computed(() => authStore.permissions || [])

  const isSuperAdmin = computed(() => {
    return roles.value.some(r => {
      const norm = String(r || '').toLowerCase().replace(/[\s_-]+/g, '')
      
      return norm === 'superadmin' || norm === 'admin'
    })
  })

  /**
   * Verifica si el usuario tiene un permiso específico.
   * SuperAdmin y permisos universales ('all', '*') conceden acceso total.
   */
  const can = permission => {
    if (!permission)
      return true
    if (isSuperAdmin.value)
      return true
    if (permissions.value.includes('all') || permissions.value.includes('*'))
      return true

    return permissions.value.includes(permission)
  }

  /**
   * Verifica si el usuario tiene al menos uno de los permisos indicados.
   */
  const canAny = (perms = []) => {
    if (!perms || perms.length === 0)
      return true
    if (isSuperAdmin.value)
      return true

    return perms.some(p => can(p))
  }

  /**
   * Verifica si el usuario tiene todos los permisos indicados.
   */
  const canAll = (perms = []) => {
    if (!perms || perms.length === 0)
      return true
    if (isSuperAdmin.value)
      return true

    return perms.every(p => can(p))
  }

  /**
   * Verifica si el usuario tiene un rol asignado.
   */
  const hasRole = role => {
    if (!role)
      return true

    const target = String(role).toLowerCase()
    
    return roles.value.some(r => String(r).toLowerCase() === target)
  }

  /**
   * Verifica si el usuario tiene al menos uno de los roles indicados.
   */
  const hasAnyRole = (roleList = []) => {
    if (!roleList || roleList.length === 0)
      return true

    return roleList.some(r => hasRole(r))
  }

  return {
    roles,
    permissions,
    isSuperAdmin,
    can,
    canAny,
    canAll,
    hasRole,
    hasAnyRole,
  }
}
