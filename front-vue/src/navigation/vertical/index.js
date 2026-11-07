/**
 * Menú vertical por secciones (spec 031, design §8.2).
 * Orden fijo de secciones: INVENTARIO · COMERCIAL · OPERACIONES · FINANZAS · TALLER · MANTENIMIENTO.
 * Las secciones sin ítems no se declaran. Todo ítem (link, grupo e hijo) declara su ícono explícito.
 */
export default [
  {
    title: 'Inicio',
    to: { name: 'root' },
    icon: { icon: 'tabler-smart-home' },
  },
  { heading: 'INVENTARIO' },
  {
    title: 'Stock',
    to: { name: 'inventory-stock' },
    icon: { icon: 'tabler-stack-2' },
    action: 'stock.view',
  },
  {
    title: 'Kardex',
    to: { name: 'inventory-kardex' },
    icon: { icon: 'tabler-report-analytics' },
    action: 'kardex.view',
  },
  {
    title: 'Movimientos',
    icon: { icon: 'tabler-arrows-exchange' },
    children: [
      {
        title: 'Notas de almacén',
        to: { name: 'inventory-warehouse-notes' },
        icon: { icon: 'tabler-file-invoice' },
        action: 'warehouse_notes.view',
      },
      {
        title: 'Toma de inventario',
        to: { name: 'inventory-counts' },
        icon: { icon: 'tabler-clipboard-check' },
        action: 'inventory_counts.view',
      },
    ],
  },
  {
    title: 'Catálogo',
    to: { name: 'inventory-catalog' },
    icon: { icon: 'tabler-box' },
    action: 'catalog.view',
  },
  {
    title: 'Configuración',
    to: { name: 'inventory-settings' },
    icon: { icon: 'tabler-adjustments' },
    action: ['categories.view', 'brands.view', 'units.view', 'warehouses.view', 'warehouse_reasons.view'],
  },
  { heading: 'COMERCIAL' },
  {
    title: 'Clientes',
    to: { name: 'clients' },
    icon: { icon: 'tabler-users' },
    action: 'clients.view',
  },
  {
    title: 'Ventas',
    icon: { icon: 'tabler-receipt' },
    children: [
      {
        title: 'Punto de venta',
        to: { name: 'sales-pos' },
        icon: { icon: 'tabler-scan' },
        action: 'sales.create',
      },
      {
        title: 'Cotizaciones',
        to: { name: 'sales-quotations' },
        icon: { icon: 'tabler-file-dollar' },
        action: 'quotations.view',
      },
      {
        title: 'Historial de ventas',
        to: { name: 'sales-history' },
        icon: { icon: 'tabler-file-text' },
        action: 'sales.view',
      },
      {
        title: 'Notas de crédito',
        to: { name: 'sales-credit-notes' },
        icon: { icon: 'tabler-receipt-refund' },
        action: 'credit_notes.view',
      },
      {
        title: 'Series',
        to: { name: 'sales-document-series' },
        icon: { icon: 'tabler-numbers' },
        action: 'document_series.view',
      },
      {
        title: 'Cuentas por cobrar',
        to: { name: 'sales-receivables' },
        icon: { icon: 'tabler-calendar-dollar' },
        action: 'receivables.view',
      },
    ],
  },
  {
    title: 'Compras',
    icon: { icon: 'tabler-shopping-cart' },
    children: [
      {
        title: 'Proveedores',
        to: { name: 'purchases-providers' },
        icon: { icon: 'tabler-truck-delivery' },
        action: 'providers.view',
      },
      {
        title: 'Requerimientos',
        to: { name: 'purchases-requests' },
        icon: { icon: 'tabler-clipboard-list' },
        action: 'purchase_requests.view',
      },
      {
        title: 'Órdenes de compra',
        to: { name: 'purchases-orders' },
        icon: { icon: 'tabler-receipt-2' },
        action: 'purchase_orders.view',
      },
      {
        title: 'Recepciones',
        to: { name: 'purchases-receipts' },
        icon: { icon: 'tabler-truck-loading' },
        action: 'purchase_receipts.view',
      },
      {
        title: 'Facturas de proveedor',
        to: { name: 'purchases-supplier-invoices' },
        icon: { icon: 'tabler-receipt-tax' },
        action: 'supplier_invoices.view',
      },
      {
        title: 'Cuentas por pagar',
        to: { name: 'purchases-accounts-payable' },
        icon: { icon: 'tabler-calendar-due' },
        action: ['supplier_invoices.view', 'supplier_payments.view'],
      },
    ],
  },
  { heading: 'FINANZAS' },
  {
    title: 'Caja',
    icon: { icon: 'tabler-cash' },
    children: [
      {
        title: 'Operar caja',
        to: { name: 'finance-cash' },
        icon: { icon: 'tabler-cash-register' },
        action: 'cash_sessions.operate',
      },
      {
        title: 'Historial de caja',
        to: { name: 'finance-cash-sessions' },
        icon: { icon: 'tabler-report-money' },
        action: 'cash_sessions.view',
      },
      {
        title: 'Transferencias',
        to: { name: 'finance-cash-transfers' },
        icon: { icon: 'tabler-arrows-left-right' },
        action: ['cash_transfers.view', 'cash_transfers.create'],
      },
      {
        title: 'Cajas registradoras',
        to: { name: 'finance-boxes' },
        icon: { icon: 'tabler-device-desktop' },
        action: 'boxes.view',
      },
    ],
  },
  {
    title: 'Bancos',
    to: { name: 'finance-banks' },
    icon: { icon: 'tabler-building-bank' },
    action: 'bank_movements.view',
  },
  {
    title: 'Configuración',
    to: { name: 'finance-settings' },
    icon: { icon: 'tabler-settings-dollar' },
    action: 'finance_settings.view',
  },
  { heading: 'MANTENIMIENTO' },
  {
    title: 'Sedes',
    to: { name: 'maintenance-branches' },
    icon: { icon: 'tabler-building-store' },
    action: 'branches.view',
  },
  {
    title: 'Áreas',
    to: { name: 'maintenance-areas' },
    icon: { icon: 'tabler-sitemap' },
    action: 'areas.view',
  },
  {
    title: 'Empleados',
    to: { name: 'maintenance-employees' },
    icon: { icon: 'tabler-id-badge-2' },
    action: 'employees.view',
  },
  {
    title: 'Seguridad',
    icon: { icon: 'tabler-shield-lock' },
    children: [
      {
        title: 'Usuarios',
        to: { name: 'users' },
        icon: { icon: 'tabler-user' },
        action: 'users.view',
      },
      {
        title: 'Roles',
        to: { name: 'roles' },
        icon: { icon: 'tabler-shield' },
        action: 'roles.view',
      },
      {
        title: 'Permisos',
        to: { name: 'permissions' },
        icon: { icon: 'tabler-key' },
        action: 'permissions.view',
      },
    ],
  },
  {
    title: 'Auditoría',
    to: { name: 'audit' },
    icon: { icon: 'tabler-history' },
    action: 'audit.view',
  },
  {
    title: 'Configuración',
    to: { name: 'configuration' },
    icon: { icon: 'tabler-settings' },
    action: 'configuration.view',
  },
]
