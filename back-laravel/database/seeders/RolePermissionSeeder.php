<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Permissions\Models\Permission;
use App\Modules\Roles\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Eliminar permiso obsoleto roles.manage si existiera
        Permission::where('slug', 'roles.manage')->forceDelete();

        // 1. Crear Permisos del Sistema
        $permissions = [
            // Usuarios
            [
                'name' => 'Ver usuarios',
                'slug' => 'users.view',
                'module' => 'users',
                'description' => 'Permite listar y ver el detalle de usuarios.',
            ],
            [
                'name' => 'Crear usuarios',
                'slug' => 'users.create',
                'module' => 'users',
                'description' => 'Permite registrar nuevos usuarios.',
            ],
            [
                'name' => 'Editar usuarios',
                'slug' => 'users.update',
                'module' => 'users',
                'description' => 'Permite actualizar datos de usuarios.',
            ],
            [
                'name' => 'Eliminar usuarios',
                'slug' => 'users.delete',
                'module' => 'users',
                'description' => 'Permite eliminar usuarios.',
            ],
            [
                'name' => 'Exportar usuarios',
                'slug' => 'users.export',
                'module' => 'users',
                'description' => 'Permite descargar en CSV el listado filtrado de usuarios.',
            ],

            // Roles (Spec 002)
            [
                'name' => 'Ver roles',
                'slug' => 'roles.view',
                'module' => 'roles',
                'description' => 'Permite listar y ver el detalle de roles.',
            ],
            [
                'name' => 'Crear roles',
                'slug' => 'roles.create',
                'module' => 'roles',
                'description' => 'Permite registrar nuevos roles en el sistema.',
            ],
            [
                'name' => 'Editar roles',
                'slug' => 'roles.update',
                'module' => 'roles',
                'description' => 'Permite modificar roles y sus permisos asignados.',
            ],
            [
                'name' => 'Eliminar roles',
                'slug' => 'roles.delete',
                'module' => 'roles',
                'description' => 'Permite eliminar roles que no tengan usuarios asignados.',
            ],

            // Permisos (Spec 001)
            [
                'name' => 'Ver permisos',
                'slug' => 'permissions.view',
                'module' => 'permissions',
                'description' => 'Permite listar y ver el catálogo de permisos.',
            ],
            [
                'name' => 'Crear permisos',
                'slug' => 'permissions.create',
                'module' => 'permissions',
                'description' => 'Permite crear nuevos permisos en el sistema.',
            ],
            [
                'name' => 'Editar permisos',
                'slug' => 'permissions.update',
                'module' => 'permissions',
                'description' => 'Permite modificar permisos existentes.',
            ],
            [
                'name' => 'Eliminar permisos',
                'slug' => 'permissions.delete',
                'module' => 'permissions',
                'description' => 'Permite eliminar permisos no asignados.',
            ],

            // Clientes (Módulo de ejemplo)
            [
                'name' => 'Ver clientes',
                'slug' => 'clients.view',
                'module' => 'clients',
                'description' => 'Permite listar y ver el detalle de clientes.',
            ],
            [
                'name' => 'Crear clientes',
                'slug' => 'clients.create',
                'module' => 'clients',
                'description' => 'Permite crear nuevos clientes.',
            ],
            [
                'name' => 'Editar clientes',
                'slug' => 'clients.update',
                'module' => 'clients',
                'description' => 'Permite editar información de clientes.',
            ],
            [
                'name' => 'Eliminar clientes',
                'slug' => 'clients.delete',
                'module' => 'clients',
                'description' => 'Permite eliminar clientes.',
            ],
            [
                'name' => 'Exportar clientes',
                'slug' => 'clients.export',
                'module' => 'clients',
                'description' => 'Permite descargar en CSV el listado filtrado de clientes.',
            ],
            [
                'name' => 'Gestionar crédito de clientes',
                'slug' => 'clients.manage_credit',
                'module' => 'clients',
                'description' => 'Permite fijar y modificar el límite y días de crédito de clientes.',
            ],

            // Reportes y Dashboard
            [
                'name' => 'Ver reportes',
                'slug' => 'reports.view',
                'module' => 'reports',
                'description' => 'Permite visualizar reportes y dashboards.',
            ],

            // Auditoría (Spec 007)
            [
                'name' => 'Ver auditoría',
                'slug' => 'audit.view',
                'module' => 'audit',
                'description' => 'Permite consultar los registros y pistas de auditoría del sistema.',
            ],

            // Configuración (Spec 010)
            [
                'name' => 'Ver configuración',
                'slug' => 'configuration.view',
                'module' => 'configuration',
                'description' => 'Permite ver la configuración general y los parámetros del sistema.',
            ],
            [
                'name' => 'Editar configuración',
                'slug' => 'configuration.update',
                'module' => 'configuration',
                'description' => 'Permite modificar la configuración de la empresa y parámetros del sistema.',
            ],

            // Módulo Maintenance — Sedes y Áreas (Spec 011)
            [
                'name' => 'Ver sedes',
                'slug' => 'branches.view',
                'module' => 'branches',
                'description' => 'Permite listar y ver el detalle de sedes.',
            ],
            [
                'name' => 'Crear sedes',
                'slug' => 'branches.create',
                'module' => 'branches',
                'description' => 'Permite registrar nuevas sedes.',
            ],
            [
                'name' => 'Editar sedes',
                'slug' => 'branches.update',
                'module' => 'branches',
                'description' => 'Permite actualizar datos de sedes.',
            ],
            [
                'name' => 'Eliminar sedes',
                'slug' => 'branches.delete',
                'module' => 'branches',
                'description' => 'Permite eliminar sedes sin usuarios o registros asociados.',
            ],
            [
                'name' => 'Exportar sedes',
                'slug' => 'branches.export',
                'module' => 'branches',
                'description' => 'Permite descargar en CSV el listado filtrado de sedes.',
            ],
            [
                'name' => 'Ver áreas',
                'slug' => 'areas.view',
                'module' => 'areas',
                'description' => 'Permite listar y ver el detalle de áreas.',
            ],
            [
                'name' => 'Crear áreas',
                'slug' => 'areas.create',
                'module' => 'areas',
                'description' => 'Permite registrar nuevas áreas.',
            ],
            [
                'name' => 'Editar áreas',
                'slug' => 'areas.update',
                'module' => 'areas',
                'description' => 'Permite actualizar datos de áreas.',
            ],
            [
                'name' => 'Eliminar áreas',
                'slug' => 'areas.delete',
                'module' => 'areas',
                'description' => 'Permite eliminar áreas.',
            ],
            [
                'name' => 'Exportar áreas',
                'slug' => 'areas.export',
                'module' => 'areas',
                'description' => 'Permite descargar en CSV el listado filtrado de áreas.',
            ],

            // Módulo Maintenance — Empleados (Spec 012)
            [
                'name' => 'Ver empleados',
                'slug' => 'employees.view',
                'module' => 'employees',
                'description' => 'Permite listar y ver el detalle de empleados.',
            ],
            [
                'name' => 'Crear empleados',
                'slug' => 'employees.create',
                'module' => 'employees',
                'description' => 'Permite registrar nuevos empleados.',
            ],
            [
                'name' => 'Editar empleados',
                'slug' => 'employees.update',
                'module' => 'employees',
                'description' => 'Permite actualizar datos de empleados y gestionar su acceso al sistema.',
            ],
            [
                'name' => 'Eliminar empleados',
                'slug' => 'employees.delete',
                'module' => 'employees',
                'description' => 'Permite eliminar empleados.',
            ],
            [
                'name' => 'Exportar empleados',
                'slug' => 'employees.export',
                'module' => 'employees',
                'description' => 'Permite descargar en CSV el listado filtrado de empleados.',
            ],

            // Módulo Purchases — Proveedores (Spec 021)
            [
                'name' => 'Ver proveedores',
                'slug' => 'providers.view',
                'module' => 'providers',
                'description' => 'Permite listar proveedores y ver su detalle, contactos, cuentas y precios.',
            ],
            [
                'name' => 'Crear proveedores',
                'slug' => 'providers.create',
                'module' => 'providers',
                'description' => 'Permite registrar nuevos proveedores.',
            ],
            [
                'name' => 'Editar proveedores',
                'slug' => 'providers.update',
                'module' => 'providers',
                'description' => 'Permite actualizar proveedores y gestionar sus contactos, cuentas bancarias y precios.',
            ],
            [
                'name' => 'Eliminar proveedores',
                'slug' => 'providers.delete',
                'module' => 'providers',
                'description' => 'Permite eliminar proveedores.',
            ],

            // Módulo Purchases — Requerimientos y órdenes de compra (Spec 022)
            [
                'name' => 'Ver requerimientos de compra',
                'slug' => 'purchase_requests.view',
                'module' => 'purchase_requests',
                'description' => 'Permite listar y ver requerimientos de compra.',
            ],
            [
                'name' => 'Crear requerimientos de compra',
                'slug' => 'purchase_requests.create',
                'module' => 'purchase_requests',
                'description' => 'Permite crear, editar y enviar requerimientos de compra propios.',
            ],
            [
                'name' => 'Aprobar requerimientos de compra',
                'slug' => 'purchase_requests.approve',
                'module' => 'purchase_requests',
                'description' => 'Permite aprobar, rechazar y anular requerimientos de compra.',
            ],
            [
                'name' => 'Ver órdenes de compra',
                'slug' => 'purchase_orders.view',
                'module' => 'purchase_orders',
                'description' => 'Permite listar, ver y descargar en PDF las órdenes de compra.',
            ],
            [
                'name' => 'Crear órdenes de compra',
                'slug' => 'purchase_orders.create',
                'module' => 'purchase_orders',
                'description' => 'Permite emitir órdenes de compra y enviarlas a aprobación.',
            ],
            [
                'name' => 'Editar órdenes de compra',
                'slug' => 'purchase_orders.update',
                'module' => 'purchase_orders',
                'description' => 'Permite editar órdenes de compra en borrador o rechazadas.',
            ],
            [
                'name' => 'Aprobar órdenes de compra',
                'slug' => 'purchase_orders.approve',
                'module' => 'purchase_orders',
                'description' => 'Permite aprobar o rechazar órdenes de compra que superan el umbral.',
            ],
            [
                'name' => 'Anular y cerrar órdenes de compra',
                'slug' => 'purchase_orders.cancel',
                'module' => 'purchase_orders',
                'description' => 'Permite anular o cerrar órdenes de compra.',
            ],

            // Recepciones de compra (Spec 023)
            [
                'name' => 'Ver recepciones de compra',
                'slug' => 'purchase_receipts.view',
                'module' => 'purchase_receipts',
                'description' => 'Permite listar y ver el detalle de recepciones de compra.',
            ],
            [
                'name' => 'Crear recepciones de compra',
                'slug' => 'purchase_receipts.create',
                'module' => 'purchase_receipts',
                'description' => 'Permite registrar recepciones de compra en borrador y consultar ítems pendientes.',
            ],
            [
                'name' => 'Confirmar recepciones de compra',
                'slug' => 'purchase_receipts.confirm',
                'module' => 'purchase_receipts',
                'description' => 'Permite confirmar recepciones de compra ingresando el stock al inventario.',
            ],
            [
                'name' => 'Anular recepciones de compra',
                'slug' => 'purchase_receipts.cancel',
                'module' => 'purchase_receipts',
                'description' => 'Permite anular recepciones de compra y revertir movimientos de kardex asociados.',
            ],

            // Facturas de proveedor, pagos y cuentas por pagar (Spec 024)
            [
                'name' => 'Ver facturas de proveedor',
                'slug' => 'supplier_invoices.view',
                'module' => 'supplier_invoices',
                'description' => 'Permite listar facturas de proveedor, cuentas por pagar, antigüedad de saldos y estados de cuenta.',
            ],
            [
                'name' => 'Registrar facturas de proveedor',
                'slug' => 'supplier_invoices.create',
                'module' => 'supplier_invoices',
                'description' => 'Permite registrar facturas de proveedor por compras y gastos directos.',
            ],
            [
                'name' => 'Anular facturas de proveedor',
                'slug' => 'supplier_invoices.cancel',
                'module' => 'supplier_invoices',
                'description' => 'Permite anular facturas de proveedor sin pagos vigentes.',
            ],
            [
                'name' => 'Ver pagos a proveedores',
                'slug' => 'supplier_payments.view',
                'module' => 'supplier_payments',
                'description' => 'Permite listar y ver el detalle de pagos a proveedores.',
            ],
            [
                'name' => 'Registrar pagos a proveedores',
                'slug' => 'supplier_payments.create',
                'module' => 'supplier_payments',
                'description' => 'Permite registrar pagos a proveedores desde caja o banco.',
            ],
            [
                'name' => 'Anular pagos a proveedores',
                'slug' => 'supplier_payments.cancel',
                'module' => 'supplier_payments',
                'description' => 'Permite anular pagos a proveedores revirtiendo el movimiento de caja o banco.',
            ],

            // Módulo Inventory — Maestros (Spec 013)
            [
                'name' => 'Ver categorías',
                'slug' => 'categories.view',
                'module' => 'inventory',
                'description' => 'Permite listar y ver categorías de productos.',
            ],
            [
                'name' => 'Crear categorías',
                'slug' => 'categories.create',
                'module' => 'inventory',
                'description' => 'Permite registrar nuevas categorías.',
            ],
            [
                'name' => 'Editar categorías',
                'slug' => 'categories.update',
                'module' => 'inventory',
                'description' => 'Permite actualizar categorías.',
            ],
            [
                'name' => 'Eliminar categorías',
                'slug' => 'categories.delete',
                'module' => 'inventory',
                'description' => 'Permite eliminar categorías.',
            ],
            [
                'name' => 'Exportar categorías',
                'slug' => 'categories.export',
                'module' => 'inventory',
                'description' => 'Permite descargar en CSV el listado filtrado de categorías.',
            ],
            [
                'name' => 'Ver marcas',
                'slug' => 'brands.view',
                'module' => 'inventory',
                'description' => 'Permite listar y ver marcas.',
            ],
            [
                'name' => 'Crear marcas',
                'slug' => 'brands.create',
                'module' => 'inventory',
                'description' => 'Permite registrar nuevas marcas.',
            ],
            [
                'name' => 'Editar marcas',
                'slug' => 'brands.update',
                'module' => 'inventory',
                'description' => 'Permite actualizar marcas.',
            ],
            [
                'name' => 'Eliminar marcas',
                'slug' => 'brands.delete',
                'module' => 'inventory',
                'description' => 'Permite eliminar marcas.',
            ],
            [
                'name' => 'Exportar marcas',
                'slug' => 'brands.export',
                'module' => 'inventory',
                'description' => 'Permite descargar en CSV el listado filtrado de marcas.',
            ],
            [
                'name' => 'Ver unidades',
                'slug' => 'units.view',
                'module' => 'inventory',
                'description' => 'Permite listar unidades y categorías de unidades.',
            ],
            [
                'name' => 'Crear unidades',
                'slug' => 'units.create',
                'module' => 'inventory',
                'description' => 'Permite registrar nuevas unidades y categorías de unidades.',
            ],
            [
                'name' => 'Editar unidades',
                'slug' => 'units.update',
                'module' => 'inventory',
                'description' => 'Permite actualizar unidades y categorías de unidades.',
            ],
            [
                'name' => 'Eliminar unidades',
                'slug' => 'units.delete',
                'module' => 'inventory',
                'description' => 'Permite eliminar unidades y categorías de unidades.',
            ],
            [
                'name' => 'Exportar unidades',
                'slug' => 'units.export',
                'module' => 'inventory',
                'description' => 'Permite descargar en CSV el listado filtrado de unidades de medida.',
            ],
            [
                'name' => 'Ver almacenes',
                'slug' => 'warehouses.view',
                'module' => 'inventory',
                'description' => 'Permite listar y ver almacenes.',
            ],
            [
                'name' => 'Crear almacenes',
                'slug' => 'warehouses.create',
                'module' => 'inventory',
                'description' => 'Permite registrar nuevos almacenes.',
            ],
            [
                'name' => 'Editar almacenes',
                'slug' => 'warehouses.update',
                'module' => 'inventory',
                'description' => 'Permite actualizar datos de almacenes.',
            ],
            [
                'name' => 'Eliminar almacenes',
                'slug' => 'warehouses.delete',
                'module' => 'inventory',
                'description' => 'Permite eliminar almacenes.',
            ],
            [
                'name' => 'Exportar almacenes',
                'slug' => 'warehouses.export',
                'module' => 'inventory',
                'description' => 'Permite descargar en CSV el listado filtrado de almacenes.',
            ],
            [
                'name' => 'Ver motivos de almacén',
                'slug' => 'warehouse_reasons.view',
                'module' => 'inventory',
                'description' => 'Permite listar y ver motivos de almacén.',
            ],
            [
                'name' => 'Crear motivos de almacén',
                'slug' => 'warehouse_reasons.create',
                'module' => 'inventory',
                'description' => 'Permite registrar nuevos motivos de almacén.',
            ],
            [
                'name' => 'Editar motivos de almacén',
                'slug' => 'warehouse_reasons.update',
                'module' => 'inventory',
                'description' => 'Permite actualizar motivos de almacén.',
            ],
            [
                'name' => 'Eliminar motivos de almacén',
                'slug' => 'warehouse_reasons.delete',
                'module' => 'inventory',
                'description' => 'Permite eliminar motivos de almacén.',
            ],
            [
                'name' => 'Exportar motivos de almacén',
                'slug' => 'warehouse_reasons.export',
                'module' => 'inventory',
                'description' => 'Permite descargar en CSV el listado filtrado de motivos de almacén.',
            ],

            // Catálogo maestro (Spec 014)
            [
                'name' => 'Ver catálogo',
                'slug' => 'catalog.view',
                'module' => 'catalog',
                'description' => 'Permite listar y ver el detalle de bienes y servicios del catálogo.',
            ],
            [
                'name' => 'Crear ítems de catálogo',
                'slug' => 'catalog.create',
                'module' => 'catalog',
                'description' => 'Permite registrar nuevos bienes y servicios en el catálogo.',
            ],
            [
                'name' => 'Editar ítems de catálogo',
                'slug' => 'catalog.update',
                'module' => 'catalog',
                'description' => 'Permite actualizar datos y presentaciones de ítems del catálogo.',
            ],
            [
                'name' => 'Eliminar ítems de catálogo',
                'slug' => 'catalog.delete',
                'module' => 'catalog',
                'description' => 'Permite eliminar ítems y presentaciones del catálogo.',
            ],
            [
                'name' => 'Actualizar precios de catálogo',
                'slug' => 'catalog.update_prices',
                'module' => 'catalog',
                'description' => 'Permite modificar la matriz de precios minoristas y mayoristas.',
            ],

            // Módulo Finance — Maestros (Spec 017)
            [
                'name' => 'Ver configuración financiera',
                'slug' => 'finance_settings.view',
                'module' => 'finance_settings',
                'description' => 'Permite consultar monedas, tipos de cambio, bancos, cuentas, métodos de pago y plan de cuentas.',
            ],
            [
                'name' => 'Gestionar configuración financiera',
                'slug' => 'finance_settings.manage',
                'module' => 'finance_settings',
                'description' => 'Permite crear, modificar y eliminar monedas, bancos, cuentas, métodos de pago y plan de cuentas.',
            ],
            [
                'name' => 'Registrar tipos de cambio',
                'slug' => 'exchange_rates.manage',
                'module' => 'exchange_rates',
                'description' => 'Permite registrar y actualizar el tipo de cambio del día.',
            ],

            // Cajas y sesiones de caja (Spec 018)
            [
                'name' => 'Ver cajas',
                'slug' => 'boxes.view',
                'module' => 'finance',
                'description' => 'Permite listar y consultar cajas de la empresa.',
            ],
            [
                'name' => 'Gestionar cajas',
                'slug' => 'boxes.manage',
                'module' => 'finance',
                'description' => 'Permite crear, modificar, desactivar y asignar roles a las cajas.',
            ],
            [
                'name' => 'Ver sesiones de caja',
                'slug' => 'cash_sessions.view',
                'module' => 'finance',
                'description' => 'Permite consultar el historial, detalle y resumen de sesiones de caja.',
            ],
            [
                'name' => 'Operar caja',
                'slug' => 'cash_sessions.operate',
                'module' => 'finance',
                'description' => 'Permite abrir, registrar movimientos y cerrar sesiones de cajas asignadas.',
            ],
            [
                'name' => 'Revisar cierres de caja',
                'slug' => 'cash_sessions.review',
                'module' => 'finance',
                'description' => 'Permite aprobar o rechazar cierres de caja con diferencias de arqueo.',
            ],
            [
                'name' => 'Anular movimientos de caja',
                'slug' => 'cash_movements.cancel',
                'module' => 'finance',
                'description' => 'Permite anular movimientos manuales de caja en sesiones abiertas.',
            ],

            // Transferencias entre cajas (Spec 019)
            [
                'name' => 'Ver transferencias de caja',
                'slug' => 'cash_transfers.view',
                'module' => 'finance',
                'description' => 'Permite listar y consultar transferencias de efectivo entre cajas.',
            ],
            [
                'name' => 'Registrar transferencias de caja',
                'slug' => 'cash_transfers.create',
                'module' => 'finance',
                'description' => 'Permite solicitar transferencias de efectivo y cancelar las propias.',
            ],
            [
                'name' => 'Aprobar transferencias de caja',
                'slug' => 'cash_transfers.approve',
                'module' => 'finance',
                'description' => 'Permite aprobar o rechazar transferencias de efectivo hacia la caja de destino.',
            ],

            // Bancos y periodos (Spec 020)
            [
                'name' => 'Ver bancos y movimientos',
                'slug' => 'bank_movements.view',
                'module' => 'finance',
                'description' => 'Permite consultar cuentas bancarias, resúmenes, movimientos y comprobantes.',
            ],
            [
                'name' => 'Registrar movimientos bancarios manuales',
                'slug' => 'bank_movements.create',
                'module' => 'finance',
                'description' => 'Permite registrar movimientos manuales de abono o cargo en cuentas bancarias.',
            ],
            [
                'name' => 'Registrar operaciones bancarias',
                'slug' => 'bank_operations.create',
                'module' => 'finance',
                'description' => 'Permite realizar depósitos, retiros y transferencias entre cuentas bancarias.',
            ],
            [
                'name' => 'Anular operaciones y movimientos bancarios',
                'slug' => 'bank_operations.cancel',
                'module' => 'finance',
                'description' => 'Permite anular operaciones bancarias y movimientos manuales del periodo abierto.',
            ],
            [
                'name' => 'Abrir y cerrar periodos bancarios',
                'slug' => 'bank_periods.close',
                'module' => 'finance',
                'description' => 'Permite abrir cuentas bancarias y cerrar periodos de conciliación con el estado de cuenta.',
            ],

            // Stock y Kardex (Spec 015)
            [
                'name' => 'Ver stock',
                'slug' => 'stock.view',
                'module' => 'stock',
                'description' => 'Permite consultar las existencias valorizadas por almacén e ítem.',
            ],
            [
                'name' => 'Actualizar límites de stock',
                'slug' => 'stock.update_limits',
                'module' => 'stock',
                'description' => 'Permite configurar los stocks mínimo y máximo de cada artículo por almacén.',
            ],
            [
                'name' => 'Ver kardex',
                'slug' => 'kardex.view',
                'module' => 'kardex',
                'description' => 'Permite consultar y exportar los movimientos valorizados de kardex.',
            ],

            // Notas de Almacén y Toma de Inventario (Spec 016)
            [
                'name' => 'Ver notas de almacén',
                'slug' => 'warehouse_notes.view',
                'module' => 'warehouse_notes',
                'description' => 'Permite consultar el listado y detalle de notas de almacén.',
            ],
            [
                'name' => 'Crear notas de almacén',
                'slug' => 'warehouse_notes.create',
                'module' => 'warehouse_notes',
                'description' => 'Permite registrar notas de almacén en borrador.',
            ],
            [
                'name' => 'Modificar notas de almacén',
                'slug' => 'warehouse_notes.update',
                'module' => 'warehouse_notes',
                'description' => 'Permite editar notas de almacén en estado borrador.',
            ],
            [
                'name' => 'Aprobar notas de almacén',
                'slug' => 'warehouse_notes.approve',
                'module' => 'warehouse_notes',
                'description' => 'Permite aprobar notas de almacén y aplicar sus movimientos al stock.',
            ],
            [
                'name' => 'Anular notas de almacén',
                'slug' => 'warehouse_notes.cancel',
                'module' => 'warehouse_notes',
                'description' => 'Permite anular notas de almacén y revertir movimientos de kardex.',
            ],
            [
                'name' => 'Ver tomas de inventario',
                'slug' => 'inventory_counts.view',
                'module' => 'inventory_counts',
                'description' => 'Permite consultar el listado y detalle de tomas de inventario físico.',
            ],
            [
                'name' => 'Gestionar tomas de inventario',
                'slug' => 'inventory_counts.manage',
                'module' => 'inventory_counts',
                'description' => 'Permite crear tomas de inventario y registrar conteos físicos.',
            ],
            [
                'name' => 'Cerrar y anular tomas de inventario',
                'slug' => 'inventory_counts.close',
                'module' => 'inventory_counts',
                'description' => 'Permite cerrar tomas de inventario generando notas de ajuste o anularlas.',
            ],

            // Ventas y Cotizaciones (Spec 026)
            [
                'name' => 'Ver cotizaciones',
                'slug' => 'quotations.view',
                'module' => 'quotations',
                'description' => 'Permite consultar y listar cotizaciones.',
            ],
            [
                'name' => 'Ver todas las cotizaciones',
                'slug' => 'quotations.view_all',
                'module' => 'quotations',
                'description' => 'Permite consultar cotizaciones de todos los vendedores.',
            ],
            [
                'name' => 'Crear cotizaciones',
                'slug' => 'quotations.create',
                'module' => 'quotations',
                'description' => 'Permite registrar y duplicar cotizaciones.',
            ],
            [
                'name' => 'Editar cotizaciones',
                'slug' => 'quotations.update',
                'module' => 'quotations',
                'description' => 'Permite actualizar cotizaciones en borrador y cambiar su estado comercial.',
            ],
            [
                'name' => 'Anular cotizaciones',
                'slug' => 'quotations.cancel',
                'module' => 'quotations',
                'description' => 'Permite cancelar cotizaciones vigentes o aceptadas.',
            ],
            [
                'name' => 'Superar tope de descuento',
                'slug' => 'sales.discount_override',
                'module' => 'sales',
                'description' => 'Permite emitir cotizaciones y ventas con descuentos superiores a los topes máximos.',
            ],

            // Ventas y Comprobantes (Spec 027)
            [
                'name' => 'Ver series de comprobantes',
                'slug' => 'document_series.view',
                'module' => 'sales',
                'description' => 'Permite consultar y listar series de comprobantes.',
            ],
            [
                'name' => 'Administrar series de comprobantes',
                'slug' => 'document_series.manage',
                'module' => 'sales',
                'description' => 'Permite crear, actualizar y eliminar series de comprobantes.',
            ],
            [
                'name' => 'Ver ventas',
                'slug' => 'sales.view',
                'module' => 'sales',
                'description' => 'Permite consultar y listar ventas emitidas.',
            ],
            [
                'name' => 'Ver todas las ventas',
                'slug' => 'sales.view_all',
                'module' => 'sales',
                'description' => 'Permite consultar ventas de todas las cajas y vendedores.',
            ],
            [
                'name' => 'Ver costos y márgenes de venta',
                'slug' => 'sales.view_costs',
                'module' => 'sales',
                'description' => 'Permite ver costos y márgenes de ganancia en el detalle de ventas.',
            ],
            [
                'name' => 'Emitir ventas',
                'slug' => 'sales.create',
                'module' => 'sales',
                'description' => 'Permite emitir comprobantes de venta.',
            ],
            [
                'name' => 'Anular ventas',
                'slug' => 'sales.cancel',
                'module' => 'sales',
                'description' => 'Permite anular comprobantes de venta emitidos.',
            ],
            [
                'name' => 'Vender al crédito',
                'slug' => 'sales.sell_on_credit',
                'module' => 'sales',
                'description' => 'Permite realizar ventas al crédito a clientes con línea aprobada.',
            ],
            [
                'name' => 'Emitir con fecha retroactiva',
                'slug' => 'sales.backdate',
                'module' => 'sales',
                'description' => 'Permite emitir comprobantes con fecha retroactiva dentro del plazo configurado.',
            ],

            // Cuentas por Cobrar y Cobranzas (Spec 028)
            [
                'name' => 'Ver cuentas por cobrar',
                'slug' => 'receivables.view',
                'module' => 'sales',
                'description' => 'Permite consultar cuentas por cobrar, cuotas y reportes de antigüedad.',
            ],
            [
                'name' => 'Exportar cuentas por cobrar',
                'slug' => 'receivables.export',
                'module' => 'sales',
                'description' => 'Permite exportar cuotas por cobrar y cobranzas a CSV.',
            ],
            [
                'name' => 'Reprogramar cuotas',
                'slug' => 'receivables.reschedule',
                'module' => 'sales',
                'description' => 'Permite reprogramar cronogramas de cuotas pendientes.',
            ],
            [
                'name' => 'Registrar cobranzas',
                'slug' => 'collections.create',
                'module' => 'sales',
                'description' => 'Permite registrar cobranzas y amortizaciones de clientes.',
            ],
            [
                'name' => 'Anular cobranzas',
                'slug' => 'collections.cancel',
                'module' => 'sales',
                'description' => 'Permite anular cobranzas de clientes y revertir movimientos de caja/banco.',
            ],

            // Notas de Crédito (Spec 029)
            [
                'name' => 'Ver notas de crédito',
                'slug' => 'credit_notes.view',
                'module' => 'sales',
                'description' => 'Permite consultar notas de crédito emitidas y sus detalles.',
            ],
            [
                'name' => 'Emitir notas de crédito',
                'slug' => 'credit_notes.create',
                'module' => 'sales',
                'description' => 'Permite emitir notas de crédito por devolución, descuento o anulación.',
            ],
            [
                'name' => 'Anular notas de crédito',
                'slug' => 'credit_notes.cancel',
                'module' => 'sales',
                'description' => 'Permite anular notas de crédito y revertir stock y dinero.',
            ],
        ];

        foreach ($permissions as $data) {
            Permission::updateOrCreate(
                ['slug' => $data['slug']],
                $data
            );
        }

        // 2. Crear Roles y Asignar Permisos
        // Rol: Super Administrador (Acceso total)
        $adminRole = Role::updateOrCreate(
            ['name' => 'admin'],
            [
                'name' => 'admin',
                'description' => 'Super Administrador con acceso total al sistema.',
            ]
        );
        $adminRole->syncPermissions(Permission::all());

        // Rol: Gerente / Manager (Gestión de usuarios y clientes)
        $managerRole = Role::updateOrCreate(
            ['name' => 'manager'],
            [
                'name' => 'manager',
                'description' => 'Gerente con gestión de usuarios y clientes.',
            ]
        );
        $managerRole->syncPermissions([
            'users.view',
            'clients.view',
            'clients.create',
            'clients.update',
            'clients.manage_credit',
            'reports.view',
            'branches.view',
            'areas.view',
            'employees.view',
            'employees.create',
            'employees.update',
            'categories.view',
            'categories.create',
            'categories.update',
            'categories.export',
            'brands.view',
            'brands.create',
            'brands.update',
            'brands.export',
            'units.view',
            'units.create',
            'units.update',
            'units.export',
            'warehouses.view',
            'warehouses.create',
            'warehouses.update',
            'warehouses.export',
            'warehouse_reasons.view',
            'warehouse_reasons.create',
            'warehouse_reasons.update',
            'warehouse_reasons.export',
            'catalog.view',
            'catalog.create',
            'catalog.update',
            'catalog.update_prices',
            'finance_settings.view',
            'finance_settings.manage',
            'exchange_rates.manage',
            'stock.view',
            'stock.update_limits',
            'kardex.view',
            'warehouse_notes.view',
            'warehouse_notes.create',
            'warehouse_notes.update',
            'warehouse_notes.approve',
            'warehouse_notes.cancel',
            'inventory_counts.view',
            'inventory_counts.manage',
            'inventory_counts.close',
            'boxes.view',
            'boxes.manage',
            'cash_sessions.view',
            'cash_sessions.operate',
            'cash_sessions.review',
            'cash_movements.cancel',
            'cash_transfers.view',
            'cash_transfers.create',
            'cash_transfers.approve',
            'bank_movements.view',
            'bank_movements.create',
            'bank_operations.create',
            'bank_operations.cancel',
            'bank_periods.close',
            'providers.view',
            'providers.create',
            'providers.update',
            'purchase_requests.view',
            'purchase_requests.create',
            'purchase_requests.approve',
            'purchase_orders.view',
            'purchase_orders.create',
            'purchase_orders.update',
            'purchase_orders.approve',
            'purchase_orders.cancel',
            'purchase_receipts.view',
            'purchase_receipts.create',
            'purchase_receipts.confirm',
            'purchase_receipts.cancel',
            'supplier_invoices.view',
            'supplier_invoices.create',
            'supplier_invoices.cancel',
            'supplier_payments.view',
            'supplier_payments.create',
            'supplier_payments.cancel',
            'quotations.view',
            'quotations.view_all',
            'quotations.create',
            'quotations.update',
            'quotations.cancel',
            'sales.discount_override',
            'document_series.view',
            'document_series.manage',
            'sales.view',
            'sales.view_all',
            'sales.view_costs',
            'sales.create',
            'sales.cancel',
            'sales.sell_on_credit',
            'sales.backdate',
            'receivables.view',
            'receivables.export',
            'receivables.reschedule',
            'collections.create',
            'collections.cancel',
            'credit_notes.view',
            'credit_notes.create',
            'credit_notes.cancel',
        ]);

        // Rol: Operador / Usuario Estándar (Acceso operativo a clientes)
        $userRole = Role::updateOrCreate(
            ['name' => 'user'],
            [
                'name' => 'user',
                'description' => 'Usuario estándar con acceso operativo a clientes.',
            ]
        );
        $userRole->syncPermissions([
            'clients.view',
            'clients.create',
            'clients.update',
            'catalog.view',
            'exchange_rates.manage',
            'stock.view',
            'warehouse_notes.view',
            'warehouse_notes.create',
            'cash_sessions.operate',
            'cash_transfers.create',
            'bank_operations.create',
            'purchase_requests.view',
            'purchase_requests.create',
            'purchase_receipts.view',
            'purchase_receipts.create',
            'supplier_invoices.create',
            'supplier_payments.create',
            'quotations.view',
            'quotations.create',
            'quotations.update',
            'sales.view',
            'sales.create',
            'receivables.view',
            'collections.create',
            'credit_notes.view',
        ]);
    }
}
