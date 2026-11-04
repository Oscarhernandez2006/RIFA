import { useEffect, useId, useState } from 'react'
import type { FormEvent, ReactNode } from 'react'
import { authAction, currentUser, readUser } from './auth'
import type { User } from './auth'
import InvoiceDashboard from './InvoiceDashboard'
import './App.css'

type IconName = 'arrow' | 'gift' | 'receipt' | 'user' | 'lock' | 'eye' | 'eye-off' | 'check'

function Icon({ name }: { name: IconName }) {
  const paths: Record<IconName, ReactNode> = {
    arrow: <path d="M4 12h16m-6-6 6 6-6 6" />,
    gift: <><path d="M3 8h18v4H3zM5 12v9h14v-9M12 8v13" /><path d="M12 8C5 8 5 2 8 3c3 0 4 5 4 5s1-5 4-5c3-1 3 5-4 5Z" /></>,
    receipt: <><path d="M6 3v18l3-2 3 2 3-2 3 2V3l-3 2-3-2-3 2-3-2Z" /><path d="M9 9h6m-6 4h6" /></>,
    user: <><circle cx="12" cy="8" r="4" /><path d="M4 21v-2a8 8 0 0 1 16 0v2" /></>,
    lock: <><rect x="5" y="10" width="14" height="11" rx="2" /><path d="M8 10V7a4 4 0 0 1 8 0v3m-4 5v2" /></>,
    eye: <><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z" /><circle cx="12" cy="12" r="3" /></>,
    'eye-off': <><path d="m3 3 18 18M10 5c7-2 12 7 12 7s-1 2-3 4M6 6c-3 2-4 6-4 6s4 7 10 7c2 0 3 0 4-1" /><path d="M10 10a3 3 0 0 0 4 4" /></>,
    check: <path d="m5 12 4 4L19 6" />,
  }

  return <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">{paths[name]}</svg>
}

function PasswordField({ confirm = false, newAccount = false }: { confirm?: boolean; newAccount?: boolean }) {
  const [visible, setVisible] = useState(false)
  const id = useId()

  return (
    <div className="field">
      <label htmlFor={id}>{confirm ? 'Confirmar contraseña' : 'Contraseña'}</label>
      <div className="password-input">
        <input
          id={id}
          name={confirm ? 'password_confirmation' : 'password'}
          type={visible ? 'text' : 'password'}
          autoComplete={newAccount || confirm ? 'new-password' : 'current-password'}
          placeholder={confirm ? 'Repite tu contraseña' : 'Ingresa tu contraseña'}
          required
          minLength={8}
          maxLength={128}
        />
        <button type="button" className="password-toggle" aria-label={`${visible ? 'Ocultar' : 'Mostrar'} ${confirm ? 'confirmación de contraseña' : 'contraseña'}`} aria-pressed={visible} onClick={() => setVisible(!visible)}>
          <Icon name={visible ? 'eye-off' : 'eye'} />
        </button>
      </div>
    </div>
  )
}

function AuthForm({ mode, onSwitch, onAuthenticated }: { mode: 'register' | 'login'; onSwitch: () => void; onAuthenticated: (user: User) => void }) {
  const register = mode === 'register'
  const [notice, setNotice] = useState('')
  const [passwordError, setPasswordError] = useState('')
  const [busy, setBusy] = useState(false)

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (busy) return
    const form = event.currentTarget
    const password = form.elements.namedItem('password')
    const confirmation = form.elements.namedItem('password_confirmation')

    if (register && password instanceof HTMLInputElement && confirmation instanceof HTMLInputElement && password.value !== confirmation.value) {
      setPasswordError('Las contraseñas no coinciden. Revisa los dos campos.')
      confirmation.focus()
      return
    }

    setPasswordError('')
    setNotice('')
    setBusy(true)
    try {
      const fields = new FormData(form)
      const payload: Record<string, string> = {}
      fields.forEach((value, key) => { if (typeof value === 'string') payload[key] = value })
      const user = readUser(await authAction(mode, payload))
      form.reset()
      onAuthenticated(user)
    } catch (error) {
      setPasswordError(error instanceof Error ? error.message : 'No se pudo completar la solicitud.')
    } finally {
      setBusy(false)
    }
  }

  return (
    <>
      <div className="form-heading">
        <span className="section-kicker">{register ? 'TU PRÓXIMA SORPRESA EMPIEZA AQUÍ' : 'QUÉ BUENO TENERTE DE VUELTA'}</span>
        <h2>{register ? 'Crea tu cuenta' : 'Bienvenido de nuevo'}</h2>
        <p>{register ? 'Un pequeño paso para empezar a participar.' : 'Ingresa a tu cuenta y sigue participando.'}</p>
      </div>

      <form onSubmit={(event) => void handleSubmit(event)} onInput={() => { setPasswordError(''); setNotice('') }} aria-busy={busy}>
        <fieldset className="auth-fields" disabled={busy}>
        {register && (
          <div className="field">
            <label htmlFor="full-name">Nombre completo</label>
            <input id="full-name" name="name" autoComplete="name" placeholder="Como aparece en tu documento" required maxLength={120} pattern=".*\S.*" />
          </div>
        )}
        <div className="field">
          <label htmlFor="email">Correo electrónico</label>
          <input id="email" name="email" type="email" autoComplete="email" placeholder="tucorreo@ejemplo.com" required maxLength={254} />
        </div>
        {register && (
          <div className="field">
            <label htmlFor="phone">Celular</label>
            <input id="phone" name="phone" type="tel" autoComplete="tel" placeholder="Tu número de contacto" required pattern="\+?[0-9 \(\)\-]{7,20}" title="Ingresa entre 7 y 20 caracteres: números, espacios, paréntesis o guiones, y un + opcional al inicio." />
          </div>
        )}
        <div className={register ? 'password-grid' : undefined}>
          <PasswordField key={mode} newAccount={register} />
          {register && <PasswordField confirm />}
        </div>
        {register && <p className="field-hint">Usa al menos 8 caracteres para tu contraseña.</p>}
        {passwordError && <p className="form-message error-message" role="alert">{passwordError}</p>}
        {!register && (
          <div className="login-help">
            <span><Icon name="lock" /> Acceso a tu cuenta</span>
            <button type="button" className="text-button" onClick={() => setNotice('La recuperación de contraseña todavía no está habilitada. No se ha enviado ningún correo.')}>¿Olvidaste tu contraseña?</button>
          </div>
        )}
        <button className="primary-button" type="submit">{busy ? 'Procesando…' : register ? 'Crear mi cuenta' : 'Iniciar sesión'}<Icon name="arrow" /></button>
        {notice && <p className="form-message" role="status">{notice}</p>}
        </fieldset>
      </form>

      <p className="switch-copy">{register ? '¿Ya tienes una cuenta?' : '¿Aún no tienes una cuenta?'} <button type="button" className="text-button" onClick={onSwitch}>{register ? 'Inicia sesión' : 'Regístrate aquí'}</button></p>
    </>
  )
}

function App() {
  const [mode, setMode] = useState<'register' | 'login'>('register')
  const [user, setUser] = useState<User | null>(null)
  const [loading, setLoading] = useState(true)
  const [sessionError, setSessionError] = useState('')
  const [retry, setRetry] = useState(0)

  useEffect(() => {
    let active = true
    currentUser().then((user) => {
      if (active) {
        setUser(user)
        if (user) history.replaceState(null, '', '/facturas')
        else if (location.pathname === '/facturas') {
          history.replaceState(null, '', '/')
          setMode('login')
        }
      }
    }).catch((error: unknown) => {
      if (active) setSessionError(error instanceof Error ? error.message : 'No se pudo verificar tu sesión.')
    }).finally(() => {
      if (active) setLoading(false)
    })
    return () => { active = false }
  }, [retry])

  function authenticated(user: User) {
    setUser(user)
    history.replaceState(null, '', '/facturas')
  }

  if (loading || sessionError) return (
    <main className="session-state" aria-live="polite">
      <img src="/logo-santacruz-transparente.png" width="80" height="80" alt="Carnes Santacruz" />
      {loading ? <p>Verificando tu sesión…</p> : <><p role="alert">{sessionError}</p><button className="primary-button" onClick={() => { setLoading(true); setSessionError(''); setRetry(retry + 1) }}>Volver a intentar</button></>}
    </main>
  )

  if (user) return <InvoiceDashboard user={user} onLogout={() => { setUser(null); history.replaceState(null, '', '/'); setMode('login') }} />

  return (
    <div className="site-shell">
      <header className="site-header">
        <a className="brand" href="/" aria-label="Carnes Santacruz, inicio">
          <img src="/logo-santacruz-transparente.png" width="56" height="56" alt="" />
          <span>Carnes <strong>Santacruz</strong></span>
        </a>
        <span className="header-label"><Icon name="gift" /> Club de premios</span>
      </header>

      <main className="auth-layout">
        <section className="campaign" aria-label="Viaje de premios: por tus compras Santacruz te lleva a San Andrés">
          <img
            src="/evento-aniversario.jpeg"
            alt="Por tus compras Santacruz te lleva a San Andrés. Por compras iguales o superiores a $70.000, regístrate e ingresa el número de tu factura. Válido del 17 de noviembre al 17 de diciembre de 2026."
          />
        </section>

        <section className={`auth-panel auth-panel--${mode}`} aria-label="Acceso de clientes">
          <div className="auth-content">
            <div className="auth-tabs" aria-label="Elige cómo acceder">
              <button type="button" aria-pressed={mode === 'register'} onClick={() => setMode('register')}>Crear cuenta</button>
              <button type="button" aria-pressed={mode === 'login'} onClick={() => setMode('login')}>Iniciar sesión</button>
            </div>
            <AuthForm key={mode} mode={mode} onSwitch={() => setMode(mode === 'register' ? 'login' : 'register')} onAuthenticated={authenticated} />
          </div>
        </section>
      </main>

    </div>
  )
}

export default App
