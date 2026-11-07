import { describe, expect, it } from 'vitest'
import { accountCodeRule, directionAllowsAccount, isValidAccountCode } from '@/modules/finance/utils/accountRules'

describe('isValidAccountCode', () => {
  it('acepta raíces de 2 dígitos y rechaza otras longitudes', () => {
    expect(isValidAccountCode('10')).toBe(true)
    expect(isValidAccountCode('101')).toBe(false)
    expect(isValidAccountCode('1')).toBe(false)
  })

  it('rechaza puntos, letras y más de 8 dígitos', () => {
    expect(isValidAccountCode('10.1')).toBe(false)
    expect(isValidAccountCode('1a')).toBe(false)
    expect(isValidAccountCode('123456789', '12')).toBe(false)
  })

  it('exige que la hija empiece con el código del padre y sea más larga', () => {
    expect(isValidAccountCode('6361', '636')).toBe(true)
    expect(isValidAccountCode('701', '636')).toBe(false)
    expect(isValidAccountCode('636', '636')).toBe(false)
  })
})

describe('accountCodeRule', () => {
  it('devuelve true o el mensaje correspondiente', () => {
    expect(accountCodeRule('6361', '636')).toBe(true)
    expect(accountCodeRule('701', '636')).toContain('636')
    expect(accountCodeRule('101')).toContain('2 dígitos')
    expect(accountCodeRule('10.1')).toContain('sin puntos')
  })
})

describe('directionAllowsAccount', () => {
  const account = (type, code = '999') => ({ type, code })

  it.each([
    ['income', 'asset', true],
    ['income', 'liability', true],
    ['income', 'equity', true],
    ['income', 'income', true],
    ['income', 'expense', false],
    ['expense', 'asset', true],
    ['expense', 'liability', true],
    ['expense', 'equity', false],
    ['expense', 'income', false],
    ['expense', 'expense', true],
    ['transfer', 'liability', false],
    ['transfer', 'equity', false],
    ['transfer', 'income', false],
    ['transfer', 'expense', false],
  ])('%s con cuenta %s => %s', (direction, type, expected) => {
    expect(directionAllowsAccount(direction, account(type))).toBe(expected)
  })

  it('transfer solo acepta activos bajo la cuenta 10', () => {
    expect(directionAllowsAccount('transfer', account('asset', '103'))).toBe(true)
    expect(directionAllowsAccount('transfer', account('asset', '1212'))).toBe(false)
  })

  it('acepta el tipo como objeto enum', () => {
    expect(directionAllowsAccount('income', { type: { value: 'expense' }, code: '659' })).toBe(false)
  })
})
