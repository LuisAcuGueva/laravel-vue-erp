/* eslint-disable camelcase */
import { describe, expect, it } from 'vitest'
import { buildSearchIndex, formatName, formatUbigeo, normalize, searchUbigeo } from '@/modules/ubigeo/utils/ubigeoSearch'

const catalog = [
  { code: '090710', department: 'HUANCAVELICA', province: 'TAYACAJA', district: 'ÑAHUIMPUQUIO', is_active: true },
  { code: '100101', department: 'HUÁNUCO', province: 'HUÁNUCO', district: 'HUÁNUCO', is_active: true },
  { code: '100102', department: 'HUÁNUCO', province: 'HUÁNUCO', district: 'AMARILIS', is_active: true },
  { code: '150101', department: 'LIMA', province: 'LIMA', district: 'LIMA', is_active: true },
  { code: '150102', department: 'LIMA', province: 'LIMA', district: 'ANCÓN', is_active: true },
  { code: '150122', department: 'LIMA', province: 'LIMA', district: 'MIRAFLORES', is_active: true },
  { code: '150123', department: 'LIMA', province: 'LIMA', district: 'PACHACAMAC', is_active: true },
  { code: '150130', department: 'LIMA', province: 'LIMA', district: 'SAN BORJA', is_active: true },
  { code: '150140', department: 'LIMA', province: 'LIMA', district: 'SANTIAGO DE SURCO', is_active: true },
  { code: '150199', department: 'LIMA', province: 'LIMA', district: 'RETIRADO', is_active: false },
  { code: '150501', department: 'LIMA', province: 'CAÑETE', district: 'SAN VICENTE DE CAÑETE', is_active: true },
  { code: '040101', department: 'AREQUIPA', province: 'AREQUIPA', district: 'AREQUIPA', is_active: true },
  { code: '040122', department: 'AREQUIPA', province: 'AREQUIPA', district: 'JOSE LUIS BUSTAMANTE Y RIVERO', is_active: true },
  { code: '040208', department: 'AREQUIPA', province: 'CAMANÁ', district: 'MIRAFLORES', is_active: true },
]

const index = buildSearchIndex(catalog)
const codes = query => searchUbigeo(index, query).map(e => e.code)

describe('ubigeoSearch', () => {
  it('normaliza tildes, diéresis y eñe', () => {
    expect(normalize('Ancón')).toBe('ancon')
    expect(normalize(' Güeppí ')).toBe('gueppi')
    expect(normalize('Ñahuimpuquio')).toBe('nahuimpuquio')
    expect(normalize(null)).toBe('')
  })

  it('formatea nombres y ubigeos para mostrar', () => {
    expect(formatName('SAN JUAN DE LURIGANCHO')).toBe('San Juan de Lurigancho')
    expect(formatName('JOSE LUIS BUSTAMANTE Y RIVERO')).toBe('Jose Luis Bustamante y Rivero')
    expect(formatUbigeo(catalog[5])).toBe('Lima, Lima, Miraflores')
  })

  it('indexa un distrito activo por entrada', () => {
    expect(index).toHaveLength(catalog.length - 1)
    expect(index.some(e => e.code === '150199')).toBe(false)
    expect(index.find(e => e.code === '150122').label).toBe('Lima, Lima, Miraflores')
  })

  it('ignora tildes al buscar', () => {
    expect(codes('ancon')).toEqual(['150102'])
    expect(codes('nahui')).toEqual(['090710'])
  })

  it('encuentra distritos por provincia o departamento', () => {
    expect(codes('huanuco')).toEqual(['100101', '100102'])
    expect(codes('canete')).toEqual(['150501'])
  })

  it('ordena: distrito que empieza → lo contiene → provincia → departamento', () => {
    // "lima": distrito LIMA primero; luego el resto por provincia, en orden alfabético de distrito.
    expect(codes('lima')).toEqual(['150101', '150102', '150122', '150123', '150130', '150140', '150501'])
    expect(codes('san')).toEqual(['150130', '150501', '150140'])
  })

  it('combina palabras de distrito, provincia y departamento', () => {
    expect(codes('miraflores')).toEqual(['040208', '150122'])
    expect(codes('miraflores lima')).toEqual(['150122'])
    expect(codes('Miraflores, Arequipa')).toEqual(['040208'])
  })

  it('busca por prefijo de código', () => {
    expect(codes('15')).toHaveLength(7)
    expect(codes('1501')).toEqual(['150101', '150102', '150122', '150123', '150130', '150140'])
    expect(codes('15012')).toEqual(['150122', '150123'])
    expect(codes('150122')).toEqual(['150122'])
  })

  it('no busca con menos de 2 caracteres y respeta el límite', () => {
    expect(searchUbigeo(index, 'a')).toEqual([])
    expect(searchUbigeo(index, '')).toEqual([])

    const big = buildSearchIndex(Array.from({ length: 120 }, (_, i) => ({
      code: `1501${String(i).padStart(2, '0')}`,
      department: 'LIMA',
      province: 'LIMA',
      district: `DISTRITO ${i}`,
      is_active: true,
    })))

    expect(searchUbigeo(big, 'distrito')).toHaveLength(50)
    expect(searchUbigeo(big, 'distrito', 10)).toHaveLength(10)
  })
})
