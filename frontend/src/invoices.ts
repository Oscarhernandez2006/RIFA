import { ApiError, apiRequest, isRecord, postJson } from './auth'

export type Invoice = {
  id: number
  number: string
  issued_on: string
  point_of_sale: string | null
  net_total: number
  registered_at: string
}

export const INVOICE_PATTERN = '[0-9A-Za-z]{2,4}-[0-9]{1,10}'

function readInvoice(value: unknown): Invoice {
  if (
    !isRecord(value) ||
    typeof value.id !== 'number' ||
    typeof value.number !== 'string' ||
    typeof value.issued_on !== 'string' ||
    !(typeof value.point_of_sale === 'string' || value.point_of_sale === null) ||
    typeof value.net_total !== 'number' ||
    typeof value.registered_at !== 'string'
  ) {
    throw new ApiError('La información de la factura no es válida.', 0)
  }
  return {
    id: value.id,
    number: value.number,
    issued_on: value.issued_on,
    point_of_sale: value.point_of_sale,
    net_total: value.net_total,
    registered_at: value.registered_at,
  }
}

export async function listInvoices() {
  const data = await apiRequest('invoices')
  if (!isRecord(data) || !Array.isArray(data.invoices)) {
    throw new ApiError('No se pudieron leer tus facturas.', 0)
  }
  return data.invoices.map(readInvoice)
}

export async function registerInvoice(invoiceNumber: string) {
  const data = await postJson('invoices', { invoice_number: invoiceNumber })
  if (!isRecord(data)) throw new ApiError('La respuesta del servidor no es válida.', 0)
  return readInvoice(data.invoice)
}
