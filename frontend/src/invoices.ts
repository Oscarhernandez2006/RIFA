import { ApiError, apiRequest, isRecord, postJson } from './auth'

export type Invoice = {
  id: number
  number: string
  consecutive: number
  issued_on: string
  point_of_sale: string | null
  net_total: number
  tickets: number
  registered_at: string
}

export type PointOfSale = {
  id: number
  name: string
}

export type InvoiceList = {
  invoices: Invoice[]
  ticketsTotal: number
  ticketValue: number
  pointsOfSale: PointOfSale[]
}

// Solo los dígitos que siguen a la letra C del tiquete (931C25533 → 25533).
export const INVOICE_PATTERN = '[0-9]{1,10}'

function readInvoice(value: unknown): Invoice {
  if (
    !isRecord(value) ||
    typeof value.id !== 'number' ||
    typeof value.number !== 'string' ||
    typeof value.consecutive !== 'number' ||
    typeof value.issued_on !== 'string' ||
    !(typeof value.point_of_sale === 'string' || value.point_of_sale === null) ||
    typeof value.net_total !== 'number' ||
    typeof value.tickets !== 'number' ||
    typeof value.registered_at !== 'string'
  ) {
    throw new ApiError('La información de la factura no es válida.', 0)
  }
  return {
    id: value.id,
    number: value.number,
    consecutive: value.consecutive,
    issued_on: value.issued_on,
    point_of_sale: value.point_of_sale,
    net_total: value.net_total,
    tickets: value.tickets,
    registered_at: value.registered_at,
  }
}

function readPointOfSale(value: unknown): PointOfSale {
  if (!isRecord(value) || typeof value.id !== 'number' || typeof value.name !== 'string') {
    throw new ApiError('La lista de puntos de venta no es válida.', 0)
  }
  return { id: value.id, name: value.name }
}

export async function listInvoices(): Promise<InvoiceList> {
  const data = await apiRequest('invoices')
  if (
    !isRecord(data) ||
    !Array.isArray(data.invoices) ||
    typeof data.tickets_total !== 'number' ||
    typeof data.ticket_value !== 'number' ||
    !Array.isArray(data.points_of_sale)
  ) {
    throw new ApiError('No se pudieron leer tus facturas.', 0)
  }
  return {
    invoices: data.invoices.map(readInvoice),
    ticketsTotal: data.tickets_total,
    ticketValue: data.ticket_value,
    pointsOfSale: data.points_of_sale.map(readPointOfSale),
  }
}

export async function registerInvoice(invoiceNumber: string, pointOfSale: number) {
  const data = await postJson('invoices', { invoice_number: invoiceNumber, point_of_sale: String(pointOfSale) })
  if (!isRecord(data)) throw new ApiError('La respuesta del servidor no es válida.', 0)
  return readInvoice(data.invoice)
}
