import { storeToRefs } from 'pinia'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/authStore'

export const useAuth = () => {
  const authStore = useAuthStore()

  let router = null
  try {
    router = useRouter()
  }
  catch {
    // Fuera de contexto de componente con router
  }

  const {
    user,
    accessToken,
    roles,
    permissions,
    branches,
    activeBranchId,
    activeBranch,
    isAuthenticated,
    isSuperAdmin,
  } = storeToRefs(authStore)

  const login = async (credentials, redirectTo = '/') => {
    const data = await authStore.login(credentials)

    if (router && redirectTo) {
      await router.push(redirectTo)
    }

    return data
  }

  const logout = async (redirectTo = '/login') => {
    await authStore.logout()

    if (router && redirectTo) {
      await router.push(redirectTo)
    }
  }

  const setActiveBranch = branchId => authStore.setActiveBranch(branchId)

  const fetchUserProfile = async () => {
    return await authStore.fetchUserProfile()
  }

  return {
    authStore,
    user,
    accessToken,
    roles,
    permissions,
    branches,
    activeBranchId,
    activeBranch,
    isAuthenticated,
    isSuperAdmin,
    login,
    logout,
    setActiveBranch,
    fetchUserProfile,
  }
}
