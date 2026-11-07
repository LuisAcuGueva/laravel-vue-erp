<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Configuration\Enums\ParameterValueType;
use App\Modules\Configuration\Models\SystemParameter;
use App\Modules\Configuration\Services\SystemParameterService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class SystemParameterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $parameters = [
            [
                'group_key' => 'sales',
                'item_key' => 'max_line_discount_percent',
                'label' => 'Descuento máximo por línea (%)',
                'description' => 'Porcentaje máximo de descuento permitido por ítem en cotizaciones y ventas.',
                'value_type' => ParameterValueType::Decimal,
                'default_value' => 10.0,
                'is_editable' => true,
                'is_active' => true,
            ],
            [
                'group_key' => 'sales',
                'item_key' => 'max_global_discount_percent',
                'label' => 'Descuento máximo global (%)',
                'description' => 'Porcentaje máximo de descuento permitido al total del comprobante.',
                'value_type' => ParameterValueType::Decimal,
                'default_value' => 10.0,
                'is_editable' => true,
                'is_active' => true,
            ],
            [
                'group_key' => 'sales',
                'item_key' => 'quotation_validity_days',
                'label' => 'Días de validez de cotizaciones',
                'description' => 'Plazo estándar en días calendario para la vigencia de cotizaciones emitidas.',
                'value_type' => ParameterValueType::Integer,
                'default_value' => 15,
                'is_editable' => true,
                'is_active' => true,
            ],
            [
                'group_key' => 'sales',
                'item_key' => 'receipt_identity_threshold',
                'label' => 'Tope de boleta para exigir identificación (S/)',
                'description' => 'Importe a partir del cual una boleta de venta exige identificar al cliente.',
                'value_type' => ParameterValueType::Decimal,
                'default_value' => 700.00,
                'is_editable' => true,
                'is_active' => true,
            ],
            [
                'group_key' => 'sales',
                'item_key' => 'max_backdate_days',
                'label' => 'Días máximos de emisión retroactiva',
                'description' => 'Días permitidos hacia atrás para emitir ventas con permiso de fecha retroactiva.',
                'value_type' => ParameterValueType::Integer,
                'default_value' => 3,
                'is_editable' => true,
                'is_active' => true,
            ],
            [
                'group_key' => 'sales',
                'item_key' => 'block_credit_when_overdue',
                'label' => 'Bloquear ventas al crédito por cuotas vencidas',
                'description' => 'Impide realizar nuevas ventas al crédito si el cliente mantiene cuotas vencidas impagas.',
                'value_type' => ParameterValueType::Boolean,
                'default_value' => true,
                'is_editable' => true,
                'is_active' => true,
            ],
            [
                'group_key' => 'inventory',
                'item_key' => 'allow_negative_stock',
                'label' => 'Permitir stock negativo',
                'description' => 'Habilita o restringe las ventas y salidas cuando no hay existencias en almacén.',
                'value_type' => ParameterValueType::Boolean,
                'default_value' => false,
                'is_editable' => true,
                'is_active' => true,
            ],
            [
                'group_key' => 'finance',
                'item_key' => 'cash_difference_tolerance',
                'label' => 'Tolerancia de diferencia de caja',
                'description' => 'Monto máximo admisible de descuadre en arqueos y cierres de caja sin requerir autorización.',
                'value_type' => ParameterValueType::Decimal,
                'default_value' => 0.00,
                'is_editable' => true,
                'is_active' => true,
            ],
            [
                'group_key' => 'finance',
                'item_key' => 'allow_negative_bank_balance',
                'label' => 'Permitir saldo bancario negativo',
                'description' => 'Habilita o restringe los cargos que dejen la cuenta bancaria con saldo negativo.',
                'value_type' => ParameterValueType::Boolean,
                'default_value' => false,
                'is_editable' => true,
                'is_active' => true,
            ],
            [
                'group_key' => 'finance',
                'item_key' => 'require_concept_account',
                'label' => 'Exigir cuenta contable en conceptos',
                'description' => 'Obliga a imputar cada concepto de ingreso o egreso a una cuenta imputable del plan de cuentas.',
                'value_type' => ParameterValueType::Boolean,
                'default_value' => false,
                'is_editable' => true,
                'is_active' => true,
            ],
            [
                'group_key' => 'finance',
                'item_key' => 'cash_denominations',
                'label' => 'Denominaciones de efectivo',
                'description' => 'Valores faciales de billetes y monedas válidos para el arqueo de caja.',
                'value_type' => ParameterValueType::Json,
                'default_value' => [200, 100, 50, 20, 10, 5, 2, 1, 0.5, 0.2, 0.1],
                'is_editable' => true,
                'is_active' => true,
            ],
            [
                'group_key' => 'purchases',
                'item_key' => 'order_approval_threshold',
                'label' => 'Umbral de aprobación de órdenes de compra',
                'description' => 'Monto en moneda base desde el cual una orden de compra requiere aprobación antes de emitirse.',
                'value_type' => ParameterValueType::Decimal,
                'default_value' => 5000.00,
                'is_editable' => true,
                'is_active' => true,
            ],
            [
                'group_key' => 'purchases',
                'item_key' => 'over_receipt_tolerance_percent',
                'label' => 'Tolerancia de sobre-recepción de compras (%)',
                'description' => 'Porcentaje adicional sobre la cantidad pendiente de una orden de compra que se permite recibir en almacén.',
                'value_type' => ParameterValueType::Decimal,
                'default_value' => 0.00,
                'is_editable' => true,
                'is_active' => true,
            ],
            [
                'group_key' => 'purchases',
                'item_key' => 'invoice_match_tolerance_percent',
                'label' => 'Tolerancia de diferencia factura vs. recepción (%)',
                'description' => 'Porcentaje de diferencia entre el total de la factura del proveedor y el valor de lo recibido a partir del cual se muestra una advertencia.',
                'value_type' => ParameterValueType::Decimal,
                'default_value' => 1.00,
                'is_editable' => true,
                'is_active' => true,
            ],
        ];

        foreach ($parameters as $param) {
            $existing = SystemParameter::where('group_key', $param['group_key'])
                ->where('item_key', $param['item_key'])
                ->first();

            if ($existing !== null) {
                $existing->update([
                    'label' => $param['label'],
                    'description' => $param['description'],
                    'value_type' => $param['value_type'],
                    'is_editable' => $param['is_editable'],
                    'is_active' => $param['is_active'],
                ]);
            } else {
                SystemParameter::create([
                    'group_key' => $param['group_key'],
                    'item_key' => $param['item_key'],
                    'label' => $param['label'],
                    'description' => $param['description'],
                    'value_type' => $param['value_type'],
                    'value' => $param['default_value'],
                    'is_editable' => $param['is_editable'],
                    'is_active' => $param['is_active'],
                ]);
            }
        }

        Cache::forget(SystemParameterService::CACHE_KEY);
    }
}
