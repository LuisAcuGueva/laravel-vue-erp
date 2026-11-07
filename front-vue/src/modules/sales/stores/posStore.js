/* eslint-disable camelcase */
import { defineStore } from 'pinia'
import { calculateDocumentTotals } from '@/utils/documentTotals'
import { currenciesApi } from '@/modules/finance/services/financeSettingsApi'
import { useCashStore } from '@/modules/finance/stores/cashStore'
import { warehousesApi } from '@/modules/inventory/services/inventorySettingsApi'
import { documentSeriesApi } from '../services/documentSeriesApi'
import { salesApi } from '../services/salesApi'

const STORAGE_KEY = 'erp_pos_cart_v1'

export const usePosStore = defineStore('salesPos', {
  state: () => ({
    cart: [],
    client: null,
    documentType: 'receipt', // 'receipt' | 'invoice'
    documentSeriesId: null,
    availableSeries: [],
    priceType: 'retail', // 'retail' | 'wholesale'
    currencies: [],
    currency: null,
    exchangeRate: '1',
    activeBox: null,
    activeCashSession: null,
    warehouses: [],
    selectedWarehouseId: null,
    sellerId: null,
    quotationId: null,
    isInitialized: false,
    isLoading: false,
    lastIssuedSale: null,
  }),

  getters: {
    totals(state) {
      if (!state.cart.length) {
        return {
          taxableBase: '0.00',
          exemptBase: '0.00',
          unaffectedBase: '0.00',
          discountTotal: '0.00',
          taxTotal: '0.00',
          total: '0.00',
          lines: [],
        }
      }

      const linesInput = state.cart.map(line => ({
        quantity: String(line.quantity || '1'),
        unitPrice: String(line.unit_price || '0'),
        discountPercent: String(line.discount_percent || '0'),
        taxType: line.tax_type || 'taxable',
      }))

      return calculateDocumentTotals(linesInput, '18.00', true, '0.00')
    },

    totalAmount() {
      return this.totals.total
    },

    linesCount(state) {
      return state.cart.length
    },

    itemsCount(state) {
      return state.cart.reduce((sum, item) => sum + Number(item.quantity || 0), 0)
    },

    filteredSeries(state) {
      return state.availableSeries.filter(s => s.document_type === state.documentType)
    },

    currentSeries(state) {
      return state.availableSeries.find(s => s.id === state.documentSeriesId) || null
    },

    canCheckout(state) {
      return (
        state.cart.length > 0 &&
        Boolean(state.documentSeriesId) &&
        Boolean(state.selectedWarehouseId) &&
        Boolean(state.client)
      )
    },

    isForeignCurrency(state) {
      return Boolean(state.currency && !state.currency.is_base)
    },
  },

  actions: {
    persistCart() {
      try {
        sessionStorage.setItem(
          STORAGE_KEY,
          JSON.stringify({
            cart: this.cart,
            client: this.client,
            documentType: this.documentType,
            documentSeriesId: this.documentSeriesId,
            priceType: this.priceType,
            selectedWarehouseId: this.selectedWarehouseId,
            sellerId: this.sellerId,
            quotationId: this.quotationId,
          }),
        )
      }
      catch {
        // Ignorar errores de cuota de sessionStorage
      }
    },

    loadPersistedCart() {
      try {
        const raw = sessionStorage.getItem(STORAGE_KEY)
        if (!raw)
          return false

        const data = JSON.parse(raw)
        if (data && Array.isArray(data.cart) && data.cart.length > 0) {
          this.cart = data.cart
          if (data.client) this.client = data.client
          if (data.documentType) this.documentType = data.documentType
          if (data.documentSeriesId) this.documentSeriesId = data.documentSeriesId
          if (data.priceType) this.priceType = data.priceType
          if (data.selectedWarehouseId) this.selectedWarehouseId = data.selectedWarehouseId
          if (data.sellerId) this.sellerId = data.sellerId
          if (data.quotationId) this.quotationId = data.quotationId

          return true
        }
      }
      catch {
        // Fallback al estado vacío
      }

      return false
    },

    async initPos(quotationId = null) {
      this.isLoading = true
      try {
        const cashStore = useCashStore()

        const [seriesRes, warehousesRes, currenciesRes] = await Promise.all([
          documentSeriesApi.available(),
          warehousesApi.options(),
          currenciesApi.options(),
          cashStore.availableBoxes.length ? null : cashStore.fetchAvailableBoxes(),
        ])

        this.availableSeries = seriesRes?.data || []
        this.warehouses = warehousesRes?.data || []
        this.currencies = currenciesRes?.data || []
        this.currency = this.currencies.find(c => c.is_base) || null
        this.exchangeRate = '1'
        this.activeBox = cashStore.selectedBox
        this.activeCashSession = cashStore.hasOpenSession ? cashStore.currentSession : null

        if (!this.selectedWarehouseId && this.warehouses.length > 0) {
          const mainWarehouse = this.warehouses.find(w => w.is_default) || this.warehouses[0]

          this.selectedWarehouseId = mainWarehouse.id
        }

        if (quotationId) {
          const res = await salesApi.prefill({ quotation_id: quotationId })

          this.applyQuotationData(res?.data || {})
        }
        else {
          const restored = this.loadPersistedCart()
          if (!restored)
            this.autoSelectSeries()
        }

        this.isInitialized = true
      }
      finally {
        this.isLoading = false
      }
    },

    applyQuotationData(q) {
      this.quotationId = q.quotation_id
      this.client = q.client
      this.sellerId = q.seller_id || this.sellerId
      this.priceType = q.price_type || 'retail'

      // Si cliente tiene RUC, cambiar a factura
      if (q.client?.document_type === 'RUC' || (q.client?.document_number && String(q.client.document_number).length === 11))
        this.documentType = 'invoice'
      else
        this.documentType = 'receipt'

      this.autoSelectSeries()

      this.cart = (q.items || []).map((line, idx) => ({
        key: `line_${Date.now()}_${idx}`,
        catalog_item_id: line.catalog_item_id,
        item_presentation_id: line.item_presentation_id,
        item_name: line.item?.name || line.description,
        presentation_name: line.presentation?.name || '',
        code: line.item?.code || '',
        unit_symbol: line.item?.unit_symbol || 'UND',
        quantity: Number(line.quantity || 1),
        list_price: line.list_price || line.unit_price || '0.00',
        unit_price: line.unit_price || '0.00',
        discount_percent: Number(line.discount_percent || 0),
        tax_type: line.tax_type || 'taxable',
        prices: [],
      }))

      this.persistCart()
    },

    autoSelectSeries() {
      const match = this.filteredSeries[0]
      if (match)
        this.documentSeriesId = match.id
      else
        this.documentSeriesId = null
    },

    setDocumentType(type) {
      this.documentType = type
      this.autoSelectSeries()
      this.persistCart()
    },

    setClient(client) {
      this.client = client
      if (client?.document_type === 'RUC' || (client?.document_number && String(client.document_number).length === 11))
        this.setDocumentType('invoice')

      this.persistCart()
    },

    setPriceType(type) {
      this.priceType = type

      // Recalcular precios de lista para líneas existentes
      for (const line of this.cart)
        this.recalculateLinePrice(line)

      this.persistCart()
    },

    /**
     * Cambia la moneda de la venta. Si no es la moneda base, el tipo de cambio
     * debe fijarse aparte con `setExchangeRate()` (se obtiene de forma asíncrona
     * desde la página que orquesta el POS).
     */
    setCurrency(currencyId) {
      const found = this.currencies.find(c => c.id === currencyId)
      if (!found) return

      this.currency = found
      this.exchangeRate = found.is_base ? '1' : ''

      for (const line of this.cart)
        this.recalculateLinePrice(line)

      this.persistCart()
    },

    setExchangeRate(rate) {
      this.exchangeRate = rate ? String(rate) : ''

      for (const line of this.cart)
        this.recalculateLinePrice(line)

      this.persistCart()
    },

    recalculateLinePrice(line) {
      if (!line.prices || !line.prices.length)
        return

      const matched = this.priceType === 'wholesale'
        ? line.prices
          .filter(p => p.price_type === 'wholesale' && Number(p.min_quantity || 1) <= line.quantity)
          .sort((a, b) => Number(b.min_quantity || 0) - Number(a.min_quantity || 0))[0] ||
          line.prices.find(p => p.price_type === 'retail')
        : line.prices.find(p => p.price_type === 'retail')

      if (!matched || !(Number(matched.price) > 0))
        return

      // Si la moneda activa no es la base, convertir con la misma fórmula que
      // `Sales\Services\SalePricingService::listPrice()`: precio base / tipo de cambio.
      if (this.isForeignCurrency && Number(this.exchangeRate) > 0) {
        const converted = (Number(matched.price) / Number(this.exchangeRate)).toFixed(4)

        line.list_price = converted
        line.unit_price = converted

        return
      }

      line.list_price = String(matched.price)
      line.unit_price = String(matched.price)
    },

    // Valida una cantidad deseada contra el stock disponible de la línea (si controla stock).
    // Devuelve null si es válida, o un mensaje de error si no alcanza.
    stockErrorFor(line, desiredQuantity) {
      if (!line.track_stock || line.available_stock === null || line.available_stock === undefined)
        return null

      if (desiredQuantity > line.available_stock) {
        return `Stock insuficiente de "${line.item_name}". Disponible: ${line.available_stock} ${line.unit_symbol || ''}.`.trim()
      }

      return null
    },

    addItem(catalogItem, presentation = null, quantity = 1) {
      const pres = presentation || catalogItem.default_presentation || catalogItem.presentations?.[0]
      const presId = pres?.id || null

      const existingIndex = this.cart.findIndex(
        l => l.catalog_item_id === catalogItem.id && l.item_presentation_id === presId,
      )

      if (existingIndex >= 0) {
        const line = this.cart[existingIndex]
        const desiredQuantity = line.quantity + quantity
        const error = this.stockErrorFor(line, desiredQuantity)
        if (error) return { ok: false, message: error }

        line.quantity = desiredQuantity
        this.recalculateLinePrice(line)
      }
      else {
        const prices = pres?.prices || catalogItem.prices || []
        const retailPriceObj = prices.find(p => p.price_type === 'retail') || prices[0]
        const unitPrice = retailPriceObj?.price ? String(retailPriceObj.price) : '0.00'
        const trackStock = Boolean(catalogItem.track_stock)

        const availableStock = trackStock && pres?.stock
          ? Number(pres.stock.available_presentation ?? pres.stock.available ?? 0)
          : null

        const line = {
          key: `line_${Date.now()}_${Math.random().toString(36).substring(2, 7)}`,
          catalog_item_id: catalogItem.id,
          item_presentation_id: presId,
          item_name: catalogItem.name,
          presentation_name: pres?.name || 'Unidad',
          code: catalogItem.code || '',
          barcode: pres?.barcode || '',
          unit_symbol: pres?.unit?.symbol || catalogItem.base_unit?.symbol || 'UND',
          track_stock: trackStock,
          available_stock: availableStock,
          quantity,
          list_price: unitPrice,
          unit_price: unitPrice,
          discount_percent: 0,
          tax_type: catalogItem.tax_type || 'taxable',
          prices,
        }

        const error = this.stockErrorFor(line, quantity)
        if (error) return { ok: false, message: error }

        this.recalculateLinePrice(line)
        this.cart.unshift(line)
      }

      this.persistCart()

      return { ok: true }
    },

    addLookupItem(item, quantity = 1) {
      if (!item) return { ok: true }

      const existingIndex = this.cart.findIndex(
        l => l.catalog_item_id === item.catalog_item_id && l.item_presentation_id === item.presentation_id,
      )

      if (existingIndex >= 0) {
        const line = this.cart[existingIndex]
        const desiredQuantity = line.quantity + quantity
        const error = this.stockErrorFor(line, desiredQuantity)
        if (error) return { ok: false, message: error }

        line.quantity = desiredQuantity
        this.recalculateLinePrice(line)
      }
      else {
        // Normalizar precios tanto si vienen como array [{ price_type, price, min_quantity }]
        // o como objeto { retail, wholesale, wholesale_min_quantity } desde CatalogLookupResource
        let pricesList = []
        if (Array.isArray(item.prices)) {
          pricesList = item.prices
        }
        else if (item.prices && typeof item.prices === 'object') {
          if (item.prices.retail !== undefined && item.prices.retail !== null) {
            pricesList.push({
              price_type: 'retail',
              price: item.prices.retail,
              min_quantity: 1,
            })
          }
          if (item.prices.wholesale !== undefined && item.prices.wholesale !== null) {
            pricesList.push({
              price_type: 'wholesale',
              price: item.prices.wholesale,
              min_quantity: item.prices.wholesale_min_quantity || 1,
            })
          }
        }

        const retailPriceObj = pricesList.find(p => p.price_type === 'retail') || pricesList[0]

        const unitPrice = item.retail_price != null
          ? String(item.retail_price)
          : (retailPriceObj?.price != null ? String(retailPriceObj.price) : '0.00')

        const trackStock = Boolean(item.track_stock)

        const availableStock = trackStock && item.stock
          ? Number(item.stock.available_presentation ?? item.stock.available ?? 0)
          : null

        const line = {
          key: `line_${Date.now()}_${Math.random().toString(36).substring(2, 7)}`,
          catalog_item_id: item.catalog_item_id,
          item_presentation_id: item.presentation_id,
          item_name: item.name,
          presentation_name: item.presentation_name || 'Unidad',
          code: item.code || '',
          barcode: item.barcode || '',
          unit_symbol: item.unit?.symbol || 'UND',
          track_stock: trackStock,
          available_stock: availableStock,
          quantity,
          list_price: unitPrice,
          unit_price: unitPrice,
          discount_percent: 0,
          tax_type: item.tax_type || 'taxable',
          prices: pricesList,
        }

        const error = this.stockErrorFor(line, quantity)
        if (error) return { ok: false, message: error }

        this.recalculateLinePrice(line)
        this.cart.unshift(line)
      }

      this.persistCart()

      return { ok: true }
    },

    updateQuantity(lineKey, quantity) {
      const line = this.cart.find(l => l.key === lineKey)
      if (!line) return { ok: true }

      const desiredQuantity = Math.max(1, Number(quantity) || 1)
      const error = this.stockErrorFor(line, desiredQuantity)
      if (error) return { ok: false, message: error }

      line.quantity = desiredQuantity
      this.recalculateLinePrice(line)
      this.persistCart()

      return { ok: true }
    },

    updateUnitPrice(lineKey, price) {
      const line = this.cart.find(l => l.key === lineKey)
      if (!line) return

      line.unit_price = String(price || '0.00')
      this.persistCart()
    },

    updateDiscount(lineKey, percent) {
      const line = this.cart.find(l => l.key === lineKey)
      if (!line) return

      line.discount_percent = Math.min(100, Math.max(0, Number(percent) || 0))
      this.persistCart()
    },

    removeLine(lineKey) {
      this.cart = this.cart.filter(l => l.key !== lineKey)
      this.persistCart()
    },

    clearCart() {
      this.cart = []
      this.quotationId = null
      sessionStorage.removeItem(STORAGE_KEY)
    },

    resetForNewSale() {
      this.cart = []
      this.quotationId = null
      this.client = null
      this.documentType = 'receipt'
      this.priceType = 'retail'
      this.autoSelectSeries()
      sessionStorage.removeItem(STORAGE_KEY)
    },
  },
})
