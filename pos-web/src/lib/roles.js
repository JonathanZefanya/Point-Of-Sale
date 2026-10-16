/** Nilai role sama dengan enum backend App\Enums\Role. */
export const ROLE = {
  OWNER: 'owner',
  MANAGER: 'manager',
  CASHIER: 'cashier',
  WAREHOUSE: 'warehouse',
}

export const ROLE_LABEL = {
  owner: 'Pemilik',
  manager: 'Manajer Outlet',
  cashier: 'Kasir',
  warehouse: 'Staf Gudang',
}

/** Role yang boleh membuka back office (meta.roles default untuk halaman back office). */
export const BACKOFFICE_ROLES = [ROLE.OWNER, ROLE.MANAGER]

/** Role yang boleh membuka layar kasir. */
export const CASHIER_ROLES = [ROLE.OWNER, ROLE.MANAGER, ROLE.CASHIER]

/** Role yang boleh membuka stok, kartu stok, dan penyesuaian. */
export const STOCK_ROLES = [ROLE.OWNER, ROLE.MANAGER, ROLE.WAREHOUSE]
