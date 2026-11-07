import { defineStore } from 'pinia'
import {
  brandsApi,
  categoriesApi,
  unitCategoriesApi,
  unitsApi,
  warehouseReasonsApi,
  warehousesApi,
} from '../services/inventorySettingsApi'

export const useInventorySettingsStore = defineStore('inventorySettings', {
  state: () => ({
    categoryTree: [],
    categoriesOptions: [],
    brandsOptions: [],
    unitCategoriesOptions: [],
    unitsOptions: [],
    warehousesOptions: [],
    warehouseReasonsOptions: [],
    isLoading: false,
    error: null,
  }),

  actions: {
    async fetchCategoryTree() {
      this.isLoading = true
      this.error = null
      try {
        const response = await categoriesApi.tree()

        this.categoryTree = response?.data || []

        return this.categoryTree
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al cargar el árbol de categorías'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },

    async fetchCategoryOptions(force = false) {
      if (!force && this.categoriesOptions.length > 0)
        return this.categoriesOptions

      try {
        const response = await categoriesApi.options()

        this.categoriesOptions = response?.data || []

        return this.categoriesOptions
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al cargar opciones de categorías'
        throw err
      }
    },

    async fetchBrandOptions(force = false) {
      if (!force && this.brandsOptions.length > 0)
        return this.brandsOptions

      try {
        const response = await brandsApi.options()

        this.brandsOptions = response?.data || []

        return this.brandsOptions
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al cargar opciones de marcas'
        throw err
      }
    },

    async fetchUnitCategoryOptions(force = false) {
      if (!force && this.unitCategoriesOptions.length > 0)
        return this.unitCategoriesOptions

      try {
        const response = await unitCategoriesApi.options()

        this.unitCategoriesOptions = response?.data || []

        return this.unitCategoriesOptions
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al cargar opciones de categorías de unidades'
        throw err
      }
    },

    async fetchUnitOptions(params = {}, force = false) {
      if (!force && this.unitsOptions.length > 0 && Object.keys(params).length === 0)
        return this.unitsOptions

      try {
        const response = await unitsApi.options(params)

        if (Object.keys(params).length === 0)
          this.unitsOptions = response?.data || []

        return response?.data || []
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al cargar opciones de unidades'
        throw err
      }
    },

    async fetchWarehouseOptions(params = {}, force = false) {
      if (!force && this.warehousesOptions.length > 0 && Object.keys(params).length === 0)
        return this.warehousesOptions

      try {
        const response = await warehousesApi.options(params)

        if (Object.keys(params).length === 0)
          this.warehousesOptions = response?.data || []

        return response?.data || []
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al cargar opciones de almacenes'
        throw err
      }
    },

    async fetchWarehouseReasonOptions(params = {}, force = false) {
      if (!force && this.warehouseReasonsOptions.length > 0 && Object.keys(params).length === 0)
        return this.warehouseReasonsOptions

      try {
        const response = await warehouseReasonsApi.options(params)

        if (Object.keys(params).length === 0)
          this.warehouseReasonsOptions = response?.data || []

        return response?.data || []
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al cargar opciones de motivos'
        throw err
      }
    },

    invalidateCategories() {
      this.categoriesOptions = []
      this.categoryTree = []
    },

    invalidateBrands() {
      this.brandsOptions = []
    },

    invalidateUnits() {
      this.unitCategoriesOptions = []
      this.unitsOptions = []
    },

    invalidateWarehouses() {
      this.warehousesOptions = []
    },

    invalidateWarehouseReasons() {
      this.warehouseReasonsOptions = []
    },
  },
})

export default useInventorySettingsStore
