/**
 * Vista previa de totales de documentos comerciales (OC, facturas, cotizaciones, ventas).
 *
 * Reproduce `App\Support\Pricing\DocumentTotalsCalculator` del backend (mismo algoritmo, escala interna 6,
 * truncado en cada operación como bcmath y redondeo HALF_UP a 2 decimales). Es solo una previsualización:
 * lo que se guarda es lo que devuelve el backend, que es la fuente de verdad.
 */

const SCALE = 6
const ONE = 10n ** BigInt(SCALE)

export const TAX_TYPES = ['taxable', 'exempt', 'unaffected']

/** Convierte un número o cadena decimal a BigInt con escala 6 (trunca los decimales extra). */
const parse = value => {
  const text = String(value ?? '0').trim()
  if (text === '' || Number.isNaN(Number(text)))
    return 0n

  const negative = text.startsWith('-')
  const [intPart, fracPart = ''] = text.replace(/^[-+]/, '').split('.')
  const frac = (fracPart + '0'.repeat(SCALE)).slice(0, SCALE)
  const result = BigInt(intPart || '0') * ONE + BigInt(frac || '0')

  return negative ? -result : result
}

const mul = (a, b) => (a * b) / ONE
const div = (a, b) => (b === 0n ? 0n : (a * ONE) / b)

/** Redondea a 2 decimales (HALF_UP, alejándose de cero) y devuelve centavos enteros. */
const roundCents = v => (v >= 0n ? (v + 5000n) / 10000n : (v - 5000n) / 10000n)

const centsToString = cents => {
  const negative = cents < 0n
  const abs = negative ? -cents : cents
  const text = `${abs / 100n}.${String(abs % 100n).padStart(2, '0')}`

  return negative ? `-${text}` : text
}

/**
 * Ajusta la última línea del grupo para que la suma redondeada iguale al redondeo de la suma exacta.
 * `rounded` y `raw` son arreglos paralelos; `indexes` son los elementos del grupo.
 */
const reconcile = (rounded, raw, indexes) => {
  if (!indexes.length)
    return

  const exact = indexes.reduce((acc, i) => acc + raw[i], 0n)
  const sum = indexes.reduce((acc, i) => acc + rounded[i], 0n)
  const diff = roundCents(exact) - sum

  if (diff !== 0n)
    rounded[indexes[indexes.length - 1]] += diff
}

/**
 * @param {Array<{quantity: string|number, unitPrice: string|number, discountPercent?: string|number, discountAmount?: string|number|null, taxType?: string}>} lines
 * @param {{ taxRate: string|number, pricesIncludeTax?: boolean, globalDiscount?: string|number, globalDiscountType?: 'amount'|'percent' }} options
 * @returns {{ taxableBase: string, exemptBase: string, unaffectedBase: string, discountTotal: string, taxTotal: string, total: string, lines: Array<{ taxType: string, discount: string, subtotal: string, tax: string, total: string }> }}
 */
export const calculateDocumentTotals = (lines, { taxRate, pricesIncludeTax = false, globalDiscount = 0, globalDiscountType = 'amount' }) => {
  const rate = div(parse(taxRate), 100n * ONE)
  const types = lines.map(l => (TAX_TYPES.includes(l.taxType) ? l.taxType : 'taxable'))

  // 1. Neto por línea tras su propio descuento.
  const nets = []
  const lineDiscounts = []

  lines.forEach((line, i) => {
    const gross = mul(parse(line.quantity), parse(line.unitPrice))
    let discount = line.discountAmount !== null && line.discountAmount !== undefined && line.discountAmount !== ''
      ? parse(line.discountAmount)
      : div(mul(gross, parse(line.discountPercent ?? 0)), 100n * ONE)

    if (discount > gross)
      discount = gross

    lineDiscounts[i] = discount
    nets[i] = gross - discount
  })

  // 2. Descuento global prorrateado por el neto de cada línea.
  const sumNet = nets.reduce((acc, n) => acc + n, 0n)
  let globalAmount = globalDiscountType === 'percent'
    ? div(mul(sumNet, parse(globalDiscount)), 100n * ONE)
    : parse(globalDiscount)

  if (globalAmount > sumNet)
    globalAmount = sumNet

  let remaining = globalAmount
  const lastIndex = lines.length - 1

  lines.forEach((_, i) => {
    let share = 0n
    if (i === lastIndex)
      share = remaining
    else if (sumNet !== 0n)
      share = div(mul(globalAmount, nets[i]), sumNet)

    remaining -= share
    nets[i] -= share
    lineDiscounts[i] += share
  })

  // 3. Base e impuesto por línea (sin redondear).
  const rawSubtotal = []
  const rawTax = []

  lines.forEach((_, i) => {
    const taxable = types[i] === 'taxable'
    if (taxable && pricesIncludeTax) {
      rawSubtotal[i] = div(nets[i], ONE + rate)
      rawTax[i] = nets[i] - rawSubtotal[i]
    }
    else {
      rawSubtotal[i] = nets[i]
      rawTax[i] = taxable ? mul(nets[i], rate) : 0n
    }
  })

  // 4. Líneas redondeadas (centavos).
  const subtotals = rawSubtotal.map(roundCents)
  const taxes = rawTax.map(roundCents)
  const discounts = lineDiscounts.map(roundCents)

  // 5. Cuadre de redondeo en la última línea de cada grupo.
  TAX_TYPES.forEach(type => {
    const indexes = types.flatMap((t, i) => (t === type ? [i] : []))

    reconcile(subtotals, rawSubtotal, indexes)
    if (type === 'taxable')
      reconcile(taxes, rawTax, indexes)
  })
  reconcile(discounts, lineDiscounts, lines.map((_, i) => i))

  // 6. Resultado.
  const bases = { taxable: 0n, exempt: 0n, unaffected: 0n }
  let taxTotal = 0n
  let discountTotal = 0n

  lines.forEach((_, i) => {
    bases[types[i]] += subtotals[i]
    taxTotal += taxes[i]
    discountTotal += discounts[i]
  })

  return {
    taxableBase: centsToString(bases.taxable),
    exemptBase: centsToString(bases.exempt),
    unaffectedBase: centsToString(bases.unaffected),
    discountTotal: centsToString(discountTotal),
    taxTotal: centsToString(taxTotal),
    total: centsToString(bases.taxable + bases.exempt + bases.unaffected + taxTotal),
    lines: lines.map((_, i) => ({
      taxType: types[i],
      discount: centsToString(discounts[i]),
      subtotal: centsToString(subtotals[i]),
      tax: centsToString(taxes[i]),
      total: centsToString(subtotals[i] + taxes[i]),
    })),
  }
}

/**
 * Formatea un monto decimal ('1180.00') con separador de miles, para mostrarlo en pantalla.
 * `decimals` debe salir de `currencies.decimals` (mantenedor de monedas); nunca asumir 2.
 */
export const formatAmount = (value, symbol = '', decimals = 2) => {
  const number = Number(value ?? 0)
  const text = number.toLocaleString('es-PE', { minimumFractionDigits: decimals, maximumFractionDigits: decimals })

  return symbol ? `${symbol} ${text}` : text
}
