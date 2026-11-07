import { beforeEach, describe, expect, it } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { resolveInitialBranchId, useAuthStore } from '@/modules/auth/stores/authStore'

describe('Resolución de sede activa (branchResolution)', () => {
  const mockBranches = [
    { id: 'branch-1', code: 'SED1', name: 'Sede Central', 'is_default': false },
    { id: 'branch-2', code: 'SED2', name: 'Sede Norte', 'is_default': true },
    { id: 'branch-3', code: 'SED3', name: 'Sede Sur', 'is_default': false },
  ]

  beforeEach(() => {
    setActivePinia(createPinia())
    window.sessionStorage.clear()
    window.localStorage.clear()
  })

  it('retorna null cuando la lista de sedes está vacía o es inválida', () => {
    expect(resolveInitialBranchId([])).toBeNull()
    expect(resolveInitialBranchId(null)).toBeNull()
    expect(resolveInitialBranchId(undefined)).toBeNull()
  })

  it('prioridad 1: resuelve desde sessionStorage si la sede es válida para el usuario', () => {
    window.sessionStorage.setItem('active_branch_id', 'branch-3')
    window.localStorage.setItem('last_branch_id', 'branch-1')

    const resolved = resolveInitialBranchId(mockBranches)

    expect(resolved).toBe('branch-3')
  })

  it('prioridad 2: si sessionStorage está vacío, resuelve desde localStorage y sincroniza a sessionStorage', () => {
    window.localStorage.setItem('last_branch_id', 'branch-1')

    const resolved = resolveInitialBranchId(mockBranches)

    expect(resolved).toBe('branch-1')
    expect(window.sessionStorage.getItem('active_branch_id')).toBe('branch-1')
  })

  it('prioridad 3: si ni sessionStorage ni localStorage tienen sede válida, usa la sede con is_default', () => {
    const resolved = resolveInitialBranchId(mockBranches)

    expect(resolved).toBe('branch-2')
    expect(window.sessionStorage.getItem('active_branch_id')).toBe('branch-2')
    expect(window.localStorage.getItem('last_branch_id')).toBe('branch-2')
  })

  it('prioridad 3b: si no hay ninguna con is_default, usa la primera sede disponible', () => {
    const noDefaultBranches = [
      { id: 'branch-a', code: 'SEDA', name: 'Sede A', 'is_default': false },
      { id: 'branch-b', code: 'SEDB', name: 'Sede B', 'is_default': false },
    ]

    const resolved = resolveInitialBranchId(noDefaultBranches)

    expect(resolved).toBe('branch-a')
    expect(window.sessionStorage.getItem('active_branch_id')).toBe('branch-a')
    expect(window.localStorage.getItem('last_branch_id')).toBe('branch-a')
  })

  it('ignora sedes en storage que no pertenezcan al usuario y recurre a default', () => {
    window.sessionStorage.setItem('active_branch_id', 'branch-invalida')
    window.localStorage.setItem('last_branch_id', 'otra-invalida')

    const resolved = resolveInitialBranchId(mockBranches)

    expect(resolved).toBe('branch-2')
  })

  it('setActiveBranch actualiza el estado y persiste en sessionStorage y localStorage', () => {
    const store = useAuthStore()

    store.branches = mockBranches
    store.activeBranchId = 'branch-2'

    const success = store.setActiveBranch('branch-3')

    expect(success).toBe(true)
    expect(store.activeBranchId).toBe('branch-3')
    expect(window.sessionStorage.getItem('active_branch_id')).toBe('branch-3')
    expect(window.localStorage.getItem('last_branch_id')).toBe('branch-3')
  })

  it('setActiveBranch rechaza sedes no asignadas al usuario', () => {
    const store = useAuthStore()

    store.branches = mockBranches
    store.activeBranchId = 'branch-2'

    const success = store.setActiveBranch('sede-ajena')

    expect(success).toBe(false)
    expect(store.activeBranchId).toBe('branch-2')
  })
})
