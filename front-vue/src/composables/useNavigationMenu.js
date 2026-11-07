import { computed } from 'vue'
import rawNavItems from '@/navigation/vertical'
import { useAcl } from '@/composables/useAcl'
import { filterNavByAcl } from '@/utils/navigation'

/**
 * Menú vertical filtrado por permisos. Reactivo a cambios de permisos del usuario.
 */
export const useNavigationMenu = (customNavItems = null) => {
  const { can } = useAcl()

  const navItems = computed(() => filterNavByAcl(customNavItems || rawNavItems, can))

  return {
    navItems,
  }
}
