/**
 * Utilidades del árbol de navegación (spec 031, design §8.2 y §8.3).
 */

// `action` puede ser un permiso o una lista: basta con tener cualquiera de ellos.
const isVisible = (item, can) => !item.action || [].concat(item.action).some(action => can(action, item.subject))

/**
 * Filtra el menú según los permisos del usuario.
 * - Grupo: conserva los hijos visibles; se descarta si queda vacío.
 * - Link: visible si no declara `action` o si `can(action)` es verdadero (con una lista, basta uno).
 * - Heading: se conserva solo si su bloque (hasta el próximo heading) tiene al menos un ítem visible.
 */
export const filterNavByAcl = (items, can) => {
  const filterItem = item => {
    if (item.children) {
      const children = item.children.map(filterItem).filter(Boolean)

      return children.length ? { ...item, children } : null
    }

    return isVisible(item, can) ? item : null
  }

  const result = []
  let pendingHeading = null

  for (const item of items) {
    if (item.heading) {
      pendingHeading = item
      continue
    }

    const filtered = filterItem(item)
    if (!filtered)
      continue

    if (pendingHeading) {
      result.push(pendingHeading)
      pendingHeading = null
    }

    result.push(filtered)
  }

  return result
}

const routeNameOf = item => (typeof item.to === 'string' ? item.to : item.to?.name)

/**
 * Devuelve la ruta de títulos (grupos + link) hasta el ítem cuya ruta coincide con `routeName`.
 * Los headings nunca forman parte del resultado. Los grupos no llevan `to`.
 * @returns {Array<{ title: string, to?: object|string }>}
 */
export const findNavTrail = (items, routeName) => {
  if (!routeName)
    return []

  for (const item of items) {
    if (item.heading)
      continue

    if (item.children) {
      const trail = findNavTrail(item.children, routeName)
      if (trail.length)
        return [{ title: item.title }, ...trail]
      continue
    }

    if (routeNameOf(item) === routeName)
      return [{ title: item.title, to: item.to }]
  }

  return []
}

const navRouteNames = items => items.flatMap(item => {
  if (item.children)
    return navRouteNames(item.children)

  const name = routeNameOf(item)

  return name ? [name] : []
})

/**
 * Devuelve el nombre de ruta del ítem del menú al que pertenece `routeName`: el mismo nombre si está en el
 * menú o, para subpáginas (`inventory-catalog-id`, `inventory-warehouse-notes-new`), el ítem de prefijo
 * más largo (`inventory-catalog`). Así `finance-cash-sessions-id` resuelve a `finance-cash-sessions`, no a `finance-cash`.
 * @returns {string|null}
 */
export const resolveNavRouteName = (items, routeName) => {
  if (!routeName)
    return null

  return navRouteNames(items)
    .filter(name => routeName === name || routeName.startsWith(`${name}-`))
    .sort((a, b) => b.length - a.length)[0] ?? null
}
