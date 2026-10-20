import { useEffect, useRef, useState } from 'react'
import type { FormEvent } from 'react'
import type { User } from './auth'
import { authAction } from './auth'
import type { Invoice } from './invoices'
import { INVOICE_PATTERN, listInvoices, registerInvoice } from './invoices'

const money = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 })
const date = new Intl.DateTimeFormat('es-CO', { dateStyle: 'medium', timeZone: 'UTC' })

function errorMessage(error: unknown, fallback: string) {
  return error instanceof Error ? error.message : fallback
}

export default function InvoiceDashboard({ user, onLogout }: { user: User; onLogout: () => void }) {
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [invoiceNumber, setInvoiceNumber] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [formError, setFormError] = useState('')
  const [success, setSuccess] = useState('')
  const [invoices, setInvoices] = useState<Invoice[] | null>(null)
  const [listError, setListError] = useState('')
  const [reload, setReload] = useState(0)
  const input = useRef<HTMLInputElement>(null)

  useEffect(() => {
    let active = true
    listInvoices().then((items) => {
      if (active) setInvoices(items)
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

  async function submitInvoice(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (submitting) return
    setSubmitting(true)
    setFormError('')
    setSuccess('')
    try {
      const invoice = await registerInvoice(invoiceNumber.trim())
      setInvoices((items) => items ? [invoice, ...items] : items)
      if (!invoices) retryList()
      setInvoiceNumber('')
      setSuccess(`¡Listo! La factura ${invoice.number} quedó registrada.`)
    } catch (error) {
      setFormError(errorMessage(error, 'No se pudo registrar la factura.'))
      input.current?.focus()
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
        </section>
        <div className="invoice-layout">
          <section className="invoice-card" aria-labelledby="register-invoice-title">
            <span className="section-kicker">EL SIGUIENTE PASO</span>
            <h2 id="register-invoice-title">Registra tu factura</h2>
            <p>Escribe el número de documento tal como aparece en tu factura. Lo validamos y queda guardado en tus facturas.</p>
            <form className="invoice-form" onSubmit={(event) => void submitInvoice(event)}>
              <fieldset className="auth-fields" disabled={submitting}>
                <div className="field">
                  <label htmlFor="invoice-number">Número de factura</label>
                  <input
                    ref={input}
                    id="invoice-number"
                    name="invoice_number"
                    value={invoiceNumber}
                    onChange={(event) => { setInvoiceNumber(event.target.value.toUpperCase()); setFormError(''); setSuccess('') }}
                    placeholder="07F-00028641"
                    autoComplete="off"
                    autoCapitalize="characters"
                    spellCheck={false}
                    required
                    maxLength={20}
                    pattern={INVOICE_PATTERN}
                    title="Escribe el número como aparece en tu factura, por ejemplo 07F-00028641."
                    aria-describedby="invoice-help"
                    aria-invalid={formError ? true : undefined}
                  />
                  <small id="invoice-help" className="field-hint">Formato: prefijo, guion y consecutivo. Ejemplo: <strong>07F-00028641</strong></small>
                </div>
                <button className="primary-button" type="submit">{submitting ? 'Validando factura…' : 'Validar y registrar'}</button>
              </fieldset>
              <div aria-live="polite">
                {formError && <p className="form-message error-message" role="alert">{formError}</p>}
                {success && <p className="form-message success-message" role="status">{success}</p>}
              </div>
            </form>
            <p className="invoice-note">Participan facturas de compras entre el 1 de octubre y el 31 de diciembre de 2026. Cada factura se puede registrar una sola vez. Conserva tu factura original.</p>
          </section>
          <section className="invoice-card" aria-labelledby="my-invoices-title" aria-busy={!invoices && !listError}>
            <span className="section-kicker">TUS COMPRAS, EN UN SOLO LUGAR</span>
            <div className="invoice-list-heading">
              <h2 id="my-invoices-title">Mis facturas</h2>
              {invoices && invoices.length > 0 && <span className="invoice-count">{invoices.length} {invoices.length === 1 ? 'registrada' : 'registradas'}</span>}
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
                      <strong>{invoice.number}</strong>
                      <span>{date.format(new Date(`${invoice.issued_on}T00:00:00Z`))}{invoice.point_of_sale ? ` · ${invoice.point_of_sale}` : ''}</span>
                    </div>
                    <div className="invoice-amount">
                      <strong>{money.format(invoice.net_total)}</strong>
                      <span className="invoice-badge">Validada</span>
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
