/**
 * Utilidades del árbol de categorías (máximo 3 niveles: depth 0, 1 y 2).
 * Trabajan sobre la lista plana de `/categories/options`: `[{ id, parent_id, name, depth }]`.
 */

export const MAX_DEPTH = 2

/** Ruta legible desde la raíz, ej. "Tecnología › Cómputo". */
export const categoryPath = (category, byId) => {
  const names = []
  let node = category

  while (node) {
    names.unshift(node.name)
    node = node.parent_id ? byId.get(node.parent_id) : null
  }

  return names.join(' › ')
}

/** Ids de todos los descendientes de `rootId`. */
export const descendantIds = (categories, rootId) => {
  const ids = new Set()
  const pending = [rootId]

  while (pending.length) {
    const current = pending.pop()

    categories
      .filter(c => c.parent_id === current && !ids.has(c.id))
      .forEach(c => {
        ids.add(c.id)
        pending.push(c.id)
      })
  }

  return ids
}

/**
 * Padres válidos para `categoryId` (o para una categoría nueva si es null):
 * excluye la propia categoría, sus descendientes y los padres con los que su subárbol superaría el nivel máximo.
 * @returns {Array<{ value: string, title: string }>}
 */
export const allowedParentOptions = (categories, categoryId = null) => {
  const byId = new Map(categories.map(c => [c.id, c]))
  const excluded = categoryId ? descendantIds(categories, categoryId) : new Set()

  if (categoryId)
    excluded.add(categoryId)

  const self = categoryId ? byId.get(categoryId) : null

  const subtreeHeight = self
    ? Math.max(0, ...categories.filter(c => excluded.has(c.id)).map(c => c.depth - self.depth))
    : 0

  return categories
    .filter(c => !excluded.has(c.id) && c.depth + 1 + subtreeHeight <= MAX_DEPTH)
    .map(c => ({ value: c.id, title: categoryPath(c, byId) }))
    .sort((a, b) => a.title.localeCompare(b.title, 'es'))
}
