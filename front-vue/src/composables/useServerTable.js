/* eslint-disable camelcase */
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useNotify } from '@/composables/useNotify'
import { downloadFile } from '@/utils/download'

export const SEARCH_DEBOUNCE_MS = 350

const isEmpty = value => value === null || value === undefined || value === ''
  || (Array.isArray(value) && value.length === 0)

// Los `List*Request` esperan booleanos como '1' / '0' (STATUS_OPTIONS usa true/false).
const serialize = value => {
  if (value === true)
    return '1'
  if (value === false)
    return '0'

  return value
}

/**
 * Estado y carga de un listado paginado en servidor (spec 031, design §8.6).
 * Se usa junto a `ServerDataTable`:
 *   v-model:options="table.options" v-model:search="table.search" :has-filters="table.hasFilters"
 *
 * @param {object} config
 * @param {(params: object) => Promise<any>} config.fetch recibe { page, per_page, sort_by, sort_dir, search, ...filters }
 * @param {object} [config.filters] filtros iniciales (null = sin filtro)
 * @param {number} [config.defaultItemsPerPage]
 * @param {boolean} [config.immediate] carga inicial en onMounted
 * @param {(params: object) => object} [config.mapParams] adapta los parámetros a la API (listado y exportación)
 *
 * Si `fetch` resuelve la respuesta paginada de la API (`{ data: [], meta: { total } }`), la tabla la expone
 * en `items`, `total` y `loading`; las páginas que guardan el listado en su store pueden ignorarlos.
 */
export function useServerTable({
  fetch,
  filters = {},
  defaultItemsPerPage = 10,
  immediate = true,
  mapParams = params => params,
} = {}) {
  const notify = useNotify()

  const initialFilters = { ...filters }

  const options = ref({ page: 1, itemsPerPage: defaultItemsPerPage, sortBy: [] })
  const search = ref('')
  const filterState = reactive({ ...filters })
  const exporting = ref(false)
  const items = ref([])
  const total = ref(0)
  const loading = ref(false)

  const hasFilters = computed(() => Object.values(filterState).some(value => !isEmpty(value)))

  /** Parámetros de búsqueda y filtros, sin paginación y sin valores vacíos. */
  const queryParams = () => {
    const params = { search: search.value?.trim?.() ?? search.value }
    const [sort] = options.value.sortBy || []

    if (sort?.key) {
      params.sort_by = sort.key
      params.sort_dir = sort.order === 'desc' ? 'desc' : 'asc'
    }

    Object.entries(filterState).forEach(([key, value]) => {
      params[key] = serialize(value)
    })

    const mapped = mapParams(Object.fromEntries(Object.entries(params).filter(([, value]) => !isEmpty(value))))

    return Object.fromEntries(Object.entries(mapped).filter(([, value]) => !isEmpty(value)))
  }

  const currentParams = () => ({
    page: options.value.page,
    per_page: options.value.itemsPerPage,
    ...queryParams(),
  })

  const reload = async () => {
    loading.value = true
    try {
      const response = await fetch(currentParams())

      if (Array.isArray(response?.data)) {
        items.value = response.data
        total.value = response.meta?.total ?? response.data.length
      }

      return response
    }
    catch (err) {
      notify.error(err?.data?.message || 'No se pudo cargar el listado.')
    }
    finally {
      loading.value = false
    }
  }

  let debounceTimer = null

  const scheduleReload = () => {
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => {
      if (options.value.page !== 1)
        options.value = { ...options.value, page: 1 } // el watch de options recarga
      else
        reload()
    }, SEARCH_DEBOUNCE_MS)
  }

  watch(search, scheduleReload)
  watch(() => ({ ...filterState }), scheduleReload, { deep: true })

  watch(options, (next, prev) => {
    if (JSON.stringify(next) !== JSON.stringify(prev))
      reload()
  }, { deep: true })

  const reset = () => {
    search.value = ''
    Object.keys(filterState).forEach(key => {
      filterState[key] = initialFilters[key] ?? null
    })
  }

  /**
   * Descarga el listado filtrado completo (sin page / per_page) vía `utils/download.js`.
   * @param {string} url ej. '/v1/branches/export'
   * @param {string} baseName nombre por defecto si la API no envía Content-Disposition
   */
  const exportFile = async (url, baseName = 'exportacion') => {
    if (exporting.value)
      return

    exporting.value = true
    try {
      await downloadFile(url, queryParams(), `${baseName}.csv`)
    }
    catch (err) {
      notify.error(err?.data?.message || 'No se pudo exportar el listado.')
    }
    finally {
      exporting.value = false
    }
  }

  if (immediate)
    onMounted(reload)

  return reactive({
    options,
    search,
    filters: filterState,
    hasFilters,
    exporting,
    items,
    total,
    loading,
    reload,
    reset,
    params: currentParams,
    export: exportFile,
  })
}
