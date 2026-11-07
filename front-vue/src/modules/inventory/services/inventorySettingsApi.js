import { $api } from '@/utils/api'

export const categoriesApi = {
  tree() {
    return $api('/v1/inventory/categories/tree', {
      method: 'GET',
    })
  },

  options() {
    return $api('/v1/inventory/categories/options', {
      method: 'GET',
    })
  },

  list(params = {}) {
    return $api('/v1/inventory/categories', {
      method: 'GET',
      params,
    })
  },

  get(id) {
    return $api(`/v1/inventory/categories/${id}`, {
      method: 'GET',
    })
  },

  create(payload) {
    return $api('/v1/inventory/categories', {
      method: 'POST',
      body: payload,
    })
  },

  update(id, payload) {
    return $api(`/v1/inventory/categories/${id}`, {
      method: 'PUT',
      body: payload,
    })
  },

  delete(id) {
    return $api(`/v1/inventory/categories/${id}`, {
      method: 'DELETE',
    })
  },
}

export const brandsApi = {
  options() {
    return $api('/v1/inventory/brands/options', {
      method: 'GET',
    })
  },

  list(params = {}) {
    return $api('/v1/inventory/brands', {
      method: 'GET',
      params,
    })
  },

  get(id) {
    return $api(`/v1/inventory/brands/${id}`, {
      method: 'GET',
    })
  },

  create(payload) {
    return $api('/v1/inventory/brands', {
      method: 'POST',
      body: payload,
    })
  },

  update(id, payload) {
    return $api(`/v1/inventory/brands/${id}`, {
      method: 'PUT',
      body: payload,
    })
  },

  delete(id) {
    return $api(`/v1/inventory/brands/${id}`, {
      method: 'DELETE',
    })
  },
}

export const unitCategoriesApi = {
  options() {
    return $api('/v1/inventory/unit-categories/options', {
      method: 'GET',
    })
  },

  list(params = {}) {
    return $api('/v1/inventory/unit-categories', {
      method: 'GET',
      params,
    })
  },

  get(id) {
    return $api(`/v1/inventory/unit-categories/${id}`, {
      method: 'GET',
    })
  },

  create(payload) {
    return $api('/v1/inventory/unit-categories', {
      method: 'POST',
      body: payload,
    })
  },

  update(id, payload) {
    return $api(`/v1/inventory/unit-categories/${id}`, {
      method: 'PUT',
      body: payload,
    })
  },

  delete(id) {
    return $api(`/v1/inventory/unit-categories/${id}`, {
      method: 'DELETE',
    })
  },
}

export const unitsApi = {
  options(params = {}) {
    return $api('/v1/inventory/units/options', {
      method: 'GET',
      params,
    })
  },

  list(params = {}) {
    return $api('/v1/inventory/units', {
      method: 'GET',
      params,
    })
  },

  get(id) {
    return $api(`/v1/inventory/units/${id}`, {
      method: 'GET',
    })
  },

  create(payload) {
    return $api('/v1/inventory/units', {
      method: 'POST',
      body: payload,
    })
  },

  update(id, payload) {
    return $api(`/v1/inventory/units/${id}`, {
      method: 'PUT',
      body: payload,
    })
  },

  delete(id) {
    return $api(`/v1/inventory/units/${id}`, {
      method: 'DELETE',
    })
  },

  convert(payload) {
    return $api('/v1/inventory/units/convert', {
      method: 'POST',
      body: payload,
    })
  },
}

export const warehousesApi = {
  options(params = {}) {
    return $api('/v1/inventory/warehouses/options', {
      method: 'GET',
      params,
    })
  },

  list(params = {}) {
    return $api('/v1/inventory/warehouses', {
      method: 'GET',
      params,
    })
  },

  get(id) {
    return $api(`/v1/inventory/warehouses/${id}`, {
      method: 'GET',
    })
  },

  create(payload) {
    return $api('/v1/inventory/warehouses', {
      method: 'POST',
      body: payload,
    })
  },

  update(id, payload) {
    return $api(`/v1/inventory/warehouses/${id}`, {
      method: 'PUT',
      body: payload,
    })
  },

  delete(id) {
    return $api(`/v1/inventory/warehouses/${id}`, {
      method: 'DELETE',
    })
  },
}

export const warehouseReasonsApi = {
  options(params = {}) {
    return $api('/v1/inventory/warehouse-reasons/options', {
      method: 'GET',
      params,
    })
  },

  list(params = {}) {
    return $api('/v1/inventory/warehouse-reasons', {
      method: 'GET',
      params,
    })
  },

  get(id) {
    return $api(`/v1/inventory/warehouse-reasons/${id}`, {
      method: 'GET',
    })
  },

  create(payload) {
    return $api('/v1/inventory/warehouse-reasons', {
      method: 'POST',
      body: payload,
    })
  },

  update(id, payload) {
    return $api(`/v1/inventory/warehouse-reasons/${id}`, {
      method: 'PUT',
      body: payload,
    })
  },

  delete(id) {
    return $api(`/v1/inventory/warehouse-reasons/${id}`, {
      method: 'DELETE',
    })
  },
}

export default {
  categories: categoriesApi,
  brands: brandsApi,
  unitCategories: unitCategoriesApi,
  units: unitsApi,
  warehouses: warehousesApi,
  warehouseReasons: warehouseReasonsApi,
}
