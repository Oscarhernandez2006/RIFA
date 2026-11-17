import { useEffect, useRef, useState } from 'react'
import type { FormEvent } from 'react'
import type { User } from './auth'
import { authAction } from './auth'
import type { Invoice, PointOfSale } from './invoices'
import { INVOICE_PATTERN, listInvoices, registerInvoice } from './invoices'

const money = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 })
const date = new Intl.DateTimeFormat('es-CO', { dateStyle: 'medium', timeZone: 'UTC' })
const DEFAULT_TICKET_VALUE = 70000

function errorMessage(error: unknown, fallback: string) {
  return error instanceof Error ? error.message : fallback
}

function plural(count: number, singular: string, pluralWord: string) {
  return `${count} ${count === 1 ? singular : pluralWord}`
}

export default function InvoiceDashboard({ user, onLogout }: { user: User; onLogout: () => void }) {
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [invoiceNumber, setInvoiceNumber] = useState('')
  const [storeOpen, setStoreOpen] = useState(false)
  const [storeError, setStoreError] = useState('')
  const [pointOfSale, setPointOfSale] = useState<number | null>(null)
  const [submitting, setSubmitting] = useState(false)
  const [formError, setFormError] = useState('')
  const [success, setSuccess] = useState('')
  const [invoices, setInvoices] = useState<Invoice[] | null>(null)
  const [pointsOfSale, setPointsOfSale] = useState<PointOfSale[]>([])
  const [ticketValue, setTicketValue] = useState(DEFAULT_TICKET_VALUE)
  const [listError, setListError] = useState('')
  const [reload, setReload] = useState(0)
  const input = useRef<HTMLInputElement>(null)
  const dialog = useRef<HTMLDialogElement>(null)
  const ticketsTotal = invoices?.reduce((sum, invoice) => sum + invoice.tickets, 0) ?? 0

  // El <dialog> nativo se abre/cierra de forma imperativa para obtener foco atrapado, fondo y Escape.
  useEffect(() => {
    const element = dialog.current
    if (!element) return
    if (storeOpen && !element.open) element.showModal()
    if (!storeOpen && element.open) element.close()
  }, [storeOpen])

  useEffect(() => {
    let active = true
    listInvoices().then((list) => {
      if (!active) return
      setInvoices(list.invoices)
      setPointsOfSale(list.pointsOfSale)
      setTicketValue(list.ticketValue)
    }).catch((error: unknown) => {
      if (active) setListError(errorMessage(error, 'No se pudieron cargar tus facturas.'))
    })
    return () => { active = false }
  }, [reload])

  function retryList() {
    setListError('')
    setInvoices(null)
    setReload(reload + 1)
  }

  function openStorePicker(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (submitting) return
    setFormError('')
    setSuccess('')
    if (pointsOfSale.length === 0) {
      setFormError('No se pudo cargar la lista de puntos de venta. Recarga la página e inténtalo de nuevo.')
      return
    }
    setPointOfSale(null)
    setStoreError('')
    setStoreOpen(true)
  }

  function closeStorePicker() {
    if (submitting) return
    setStoreOpen(false)
    setTimeout(() => input.current?.focus(), 0)
  }

  async function submitInvoice(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (submitting) return
    if (pointOfSale === null) {
      setStoreError('Selecciona el punto de venta donde hiciste la compra.')
      return
    }
    setStoreError('')
    setSubmitting(true)
    try {
      const invoice = await registerInvoice(invoiceNumber.trim(), pointOfSale)
      setInvoices((items) => items ? [invoice, ...items] : items)
      if (!invoices) retryList()
      setInvoiceNumber('')
      setStoreOpen(false)
      setSuccess(`¡Listo! La factura ${invoice.consecutive} de ${invoice.point_of_sale ?? 'tu tienda'} quedó registrada y te da ${plural(invoice.tickets, 'boleta', 'boletas')}.`)
    } catch (error) {
      setStoreError(errorMessage(error, 'No se pudo registrar la factura.'))
    } finally {
      setSubmitting(false)
    }
  }

  async function logout() {
    setBusy(true)
    setError('')
    try {
      await authAction('logout')
      onLogout()
    } catch (error) {
      setError(errorMessage(error, 'No se pudo cerrar la sesión.'))
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="customer-shell">
      <header className="site-header">
        <a className="brand" href="/"><img src="/logo-santacruz-transparente.png" width="56" height="56" alt="" /><span>Carnes <strong>Santacruz</strong></span></a>
        <button className="logout-button" type="button" disabled={busy} onClick={() => void logout()}>{busy ? 'Cerrando…' : 'Cerrar sesión'}</button>
      </header>
      <main className="customer-main">
        {error && <p className="form-message error-message" role="alert">{error}</p>}
        <section className="customer-welcome">
          <span className="section-kicker">MI ESPACIO SANTACRUZ</span>
          <h1>¡Hola, {user.name}!</h1>
          <p>Bienvenido a tu espacio de facturas y participación.</p>
          <span className="customer-email">{user.email}</span>
          {invoices && (
            <p className="tickets-summary" role="status">
              <strong>{ticketsTotal}</strong> {ticketsTotal === 1 ? 'boleta acumulada' : 'boletas acumuladas'}
            </p>
          )}
        </section>
        <div className="invoice-layout">
          <section className="invoice-card" aria-labelledby="register-invoice-title">
            <span className="section-kicker">EL SIGUIENTE PASO</span>
            <h2 id="register-invoice-title">Registra tu factura</h2>
            <p>Escribe solo los números que aparecen después de la letra <strong>C</strong> en «Factura electrónica de venta» de tu tiquete. Luego te preguntamos en qué tienda compraste para validarla.</p>
            <form className="invoice-form" onSubmit={openStorePicker}>
              <fieldset className="auth-fields" disabled={submitting}>
                <div className="field">
                  <label htmlFor="invoice-number">Número de factura</label>
                  <input
                    ref={input}
                    id="invoice-number"
                    name="invoice_number"
                    value={invoiceNumber}
                    onChange={(event) => { setInvoiceNumber(event.target.value.replace(/\D/g, '')); setFormError(''); setSuccess('') }}
                    placeholder="25533"
                    inputMode="numeric"
                    autoComplete="off"
                    spellCheck={false}
                    required
                    maxLength={10}
                    pattern={INVOICE_PATTERN}
                    title="Escribe solo los dígitos que van después de la letra C, por ejemplo 25533."
                    aria-describedby="invoice-help"
                    aria-invalid={formError ? true : undefined}
                  />
                  <small id="invoice-help" className="field-hint">Si tu tiquete dice <strong>931C25533</strong>, escribe solo <strong>25533</strong>.</small>
                </div>
                <button className="primary-button" type="submit">Validar y registrar</button>
              </fieldset>
              <div aria-live="polite">
                {formError && <p className="form-message error-message" role="alert">{formError}</p>}
                {success && <p className="form-message success-message" role="status">{success}</p>}
              </div>
            </form>
            <dialog ref={dialog} className="store-dialog" aria-labelledby="store-dialog-title" onClose={() => setStoreOpen(false)} onCancel={(event) => { if (submitting) event.preventDefault() }}>
              <form className="store-dialog-body" onSubmit={(event) => void submitInvoice(event)}>
                <span className="section-kicker">ÚLTIMO PASO</span>
                <h3 id="store-dialog-title">¿En qué punto de venta compraste?</h3>
                <p className="store-dialog-number">Factura N.º <strong>{invoiceNumber}</strong></p>
                <fieldset className="store-field" disabled={submitting}>
                  <legend className="sr-only">Punto de venta</legend>
                  <div className="store-options">
                    {pointsOfSale.map((store) => (
                      <label key={store.id} className={`store-option${pointOfSale === store.id ? ' selected' : ''}`}>
                        <input
                          type="radio"
                          name="point_of_sale"
                          value={store.id}
                          checked={pointOfSale === store.id}
                          onChange={() => { setPointOfSale(store.id); setStoreError('') }}
                        />
                        <span>PDV {store.name}</span>
                      </label>
                    ))}
                  </div>
                </fieldset>
                <small className="field-hint">La tienda aparece en la parte superior del tiquete, por ejemplo «PDV LA 93».</small>
                <div aria-live="polite">
                  {storeError && <p className="form-message error-message" role="alert">{storeError}</p>}
                </div>
                <div className="store-dialog-actions">
                  <button className="logout-button" type="button" disabled={submitting} onClick={closeStorePicker}>Cambiar número</button>
                  <button className="primary-button" type="submit" disabled={submitting}>{submitting ? 'Validando factura…' : 'Validar y registrar'}</button>
                </div>
              </form>
            </dialog>
            <p className="invoice-note">Recibes <strong>1 boleta por cada {money.format(ticketValue)}</strong> del valor de tu factura (por ejemplo, {money.format(ticketValue * 3)} = 3 boletas). Las compras menores a {money.format(ticketValue)} no otorgan boletas y el excedente no se acumula. Participan facturas de compras entre el 1 de octubre y el 31 de diciembre de 2026. Cada factura se puede registrar una sola vez. Conserva tu factura original.</p>
          </section>
          <section className="invoice-card" aria-labelledby="my-invoices-title" aria-busy={!invoices && !listError}>
            <span className="section-kicker">TUS COMPRAS, EN UN SOLO LUGAR</span>
            <div className="invoice-list-heading">
              <h2 id="my-invoices-title">Mis facturas</h2>
              {invoices && invoices.length > 0 && <span className="invoice-count">{plural(invoices.length, 'registrada', 'registradas')} · {plural(ticketsTotal, 'boleta', 'boletas')}</span>}
            </div>
            {listError ? (
              <div className="invoice-empty" role="alert">
                <p>{listError}</p>
                <button className="logout-button" type="button" onClick={retryList}>Volver a intentar</button>
              </div>
            ) : !invoices ? (
              <p className="invoice-loading" role="status">Cargando tus facturas…</p>
            ) : invoices.length === 0 ? (
              <div className="invoice-empty"><span aria-hidden="true">↗</span><h3>Aún no tienes facturas</h3><p>Cuando registres una factura válida aparecerá aquí.</p></div>
            ) : (
              <ul className="invoice-list">
                {invoices.map((invoice) => (
                  <li key={invoice.id}>
                    <div>
                      <strong>N.º {invoice.consecutive}</strong>
                      <span>{date.format(new Date(`${invoice.issued_on}T00:00:00Z`))}{invoice.point_of_sale ? ` · ${invoice.point_of_sale}` : ''}</span>
                    </div>
                    <div className="invoice-amount">
                      <strong>{money.format(invoice.net_total)}</strong>
                      <span className="invoice-badge invoice-badge-tickets">{plural(invoice.tickets, 'boleta', 'boletas')}</span>
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </section>
        </div>
      </main>
    </div>
  )
}
