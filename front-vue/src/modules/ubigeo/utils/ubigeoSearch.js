// Funciones puras del catálogo de ubigeo (sin dependencias de Vue).

const LOWERCASE_WORDS = new Set(['de', 'del', 'la', 'las', 'los', 'y', 'el'])

/**
 * Normaliza un texto para comparar: sin tildes ni diéresis (ñ → n), en minúsculas y sin espacios extremos.
 */
export function normalize(text) {
  return String(text ?? '')
    .normalize('NFD')
    .replace(/\p{M}/gu, '')
    .toLowerCase()
    .trim()
}

/**
 * Formato título para mostrar: "SAN JUAN DE LURIGANCHO" → "San Juan de Lurigancho".
 */
export function formatName(name) {
  return String(name ?? '')
    .toLocaleLowerCase('es')
    .split(' ')
    .filter(Boolean)
    .map((word, index) => (index > 0 && LOWERCASE_WORDS.has(word))
      ? word
      : word.charAt(0).toLocaleUpperCase('es') + word.slice(1))
    .join(' ')
}

/**
 * Texto legible de un ubigeo (departamento, provincia, distrito): "Lima, Lima, Miraflores".
 */
export function formatUbigeo(ubigeo) {
  return [ubigeo.department, ubigeo.province, ubigeo.district].map(formatName).join(', ')
}

const tokenize = text => normalize(text).split(/[^a-z0-9]+/).filter(Boolean)

/**
 * Genera una entrada de búsqueda por distrito activo, con los nombres ya normalizados.
 */
export function buildSearchIndex(items) {
  return items
    .filter(item => item.is_active !== false && item.isActive !== false)
    .map(item => ({
      code: item.code,
      department: item.department,
      province: item.province,
      district: item.district,
      label: formatUbigeo(item),
      districtKey: normalize(item.district),
      provinceKey: normalize(item.province),
      departmentKey: normalize(item.department),
      haystack: `${normalize(item.district)} ${normalize(item.province)} ${normalize(item.department)} ${item.code}`,
    }))
}

const rankOf = (entry, q) => {
  if (entry.districtKey.startsWith(q))
    return 0
  if (entry.districtKey.includes(q))
    return 1
  if (entry.provinceKey.startsWith(q))
    return 2
  if (entry.departmentKey.startsWith(q))
    return 3

  return 4
}

/**
 * Busca distritos por nombre de distrito, provincia o departamento (cada palabra debe coincidir)
 * o por prefijo de código. Orden: distrito que empieza con el texto → lo contiene → provincia → departamento.
 */
export function searchUbigeo(index, query, limit = 50) {
  const q = normalize(query)

  if (q.length < 2)
    return []

  if (/^\d+$/.test(q)) {
    return index
      .filter(entry => entry.code.startsWith(q))
      .slice(0, limit)
  }

  const tokens = tokenize(q)
  const ranked = []

  for (const entry of index) {
    if (tokens.every(token => entry.haystack.includes(token)))
      ranked.push({ rank: rankOf(entry, q), entry })
  }

  return ranked
    .sort((a, b) => a.rank - b.rank
      || a.entry.district.localeCompare(b.entry.district, 'es')
      || a.entry.code.localeCompare(b.entry.code))
    .slice(0, limit)
    .map(({ entry }) => entry)
}
