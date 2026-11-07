/* eslint-disable camelcase */
import { describe, expect, it } from 'vitest'
import { allowedParentOptions, categoryPath, descendantIds } from '@/modules/inventory/utils/categoryTree'

const categories = [
  { id: 'tec', parent_id: null, name: 'Tecnología', depth: 0 },
  { id: 'com', parent_id: 'tec', name: 'Cómputo', depth: 1 },
  { id: 'lap', parent_id: 'com', name: 'Laptops', depth: 2 },
  { id: 'tel', parent_id: 'tec', name: 'Telefonía', depth: 1 },
  { id: 'ali', parent_id: null, name: 'Alimentos', depth: 0 },
]

const byId = new Map(categories.map(c => [c.id, c]))
const values = options => options.map(o => o.value)

describe('categoryTree', () => {
  it('arma la ruta desde la raíz', () => {
    expect(categoryPath(byId.get('lap'), byId)).toBe('Tecnología › Cómputo › Laptops')
  })

  it('obtiene todos los descendientes', () => {
    expect([...descendantIds(categories, 'tec')].sort()).toEqual(['com', 'lap', 'tel'])
  })

  it('una categoría nueva puede colgar de los niveles 1 y 2, ordenados por ruta', () => {
    const options = allowedParentOptions(categories)

    expect(values(options)).toEqual(['ali', 'tec', 'com', 'tel'])
    expect(options[2].title).toBe('Tecnología › Cómputo')
  })

  it('excluye la propia categoría, sus descendientes y los padres que superan 3 niveles', () => {
    // Cómputo tiene un hijo: solo puede quedar bajo una raíz.
    expect(values(allowedParentOptions(categories, 'com'))).toEqual(['ali', 'tec'])

    // Tecnología tiene dos niveles debajo: no puede tener padre.
    expect(allowedParentOptions(categories, 'tec')).toEqual([])
  })
})
