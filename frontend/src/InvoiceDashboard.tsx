import { useState } from 'react'
import type { User } from './auth'
import { authAction } from './auth'

export default function InvoiceDashboard({ user, onLogout }: { user: User; onLogout: () => void }) {
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')

  async function logout() {
    setBusy(true)
    setError('')
    try {
      await authAction('logout')
      onLogout()
    } catch (error) {
      setError(error instanceof Error ? error.message : 'No se pudo cerrar la sesión.')
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
          <section className="invoice-card">
            <span className="section-kicker">EL SIGUIENTE PASO</span>
            <h2>Registra tu factura</h2>
            <p>Aquí podrás ingresar los datos de tus compras para participar.</p>
            <div className="invoice-coming" role="status">Estamos preparando el registro de facturas. Tu cuenta ya está lista.</div>
            <p className="invoice-note">Conserva tu factura original. Los campos y las condiciones de participación se definirán en la siguiente etapa.</p>
          </section>
          <section className="invoice-card">
            <span className="section-kicker">TUS COMPRAS, EN UN SOLO LUGAR</span>
            <h2>Mis facturas</h2>
            <div className="invoice-empty"><span aria-hidden="true">↗</span><h3>Este será tu historial</h3><p>Cuando habilitemos el registro, aquí podrás consultar tus facturas y su estado.</p></div>
          </section>
        </div>
      </main>
    </div>
  )
}
