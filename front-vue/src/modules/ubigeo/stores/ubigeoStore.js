import { defineStore } from 'pinia'
import { markRaw, ref, shallowRef } from 'vue'
import { ubigeoApi } from '../services/ubigeoApi'
import { buildSearchIndex } from '../utils/ubigeoSearch'

const LOAD_ERROR = 'No se pudo cargar el catálogo de ubigeo'

export const useUbigeoStore = defineStore('ubigeo', () => {
  const items = shallowRef([])
  const byCode = shallowRef(new Map())
  const searchIndex = shallowRef([])
  const isLoading = ref(false)
  const error = ref(null)
  const version = ref(null)

  let pending = null

  const build = data => {
    const codes = new Map()

    for (const row of data) {
      codes.set(row.code, {
        code: row.code,
        department: row.department,
        province: row.province,
        district: row.district,
        isActive: row.is_active !== false,
      })
    }

    byCode.value = markRaw(codes)
    searchIndex.value = markRaw(buildSearchIndex(data))
    items.value = markRaw(data)
  }

  /**
   * Carga el catálogo una sola vez; llamadas simultáneas comparten la misma promesa.
   */
  const ensureLoaded = () => {
    if (items.value.length > 0)
      return Promise.resolve(items.value)

    if (pending)
      return pending

    isLoading.value = true
    error.value = null

    pending = ubigeoApi.catalog()
      .then(response => {
        build(response?.data || [])
        version.value = response?.meta?.version || null

        return items.value
      })
      .catch(err => {
        error.value = LOAD_ERROR
        throw err
      })
      .finally(() => {
        isLoading.value = false
        pending = null
      })

    return pending
  }

  const find = code => (code ? byCode.value.get(code) || null : null)

  return {
    items,
    byCode,
    searchIndex,
    isLoading,
    error,
    version,
    ensureLoaded,
    find,
  }
})
