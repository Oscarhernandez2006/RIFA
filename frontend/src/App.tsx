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

function Prizes() {
  return (
    <svg className="prize-art" viewBox="0 0 440 250" fill="none" aria-hidden="true">
      <ellipse cx="230" cy="229" rx="183" ry="13" fill="#490c11" opacity=".22" />
      <g stroke="#f6d3a4" strokeWidth="2">
        <g className="appliance appliance-tv">
          <rect x="222" y="21" width="145" height="78" rx="6" fill="#302c2b" stroke="#d4ae7c" />
          <rect x="229" y="28" width="131" height="62" rx="2" fill="#4c6867" stroke="none" />
          <path d="m230 89 70-60h60v19l-47 42Z" fill="#8da7a0" opacity=".4" stroke="none" />
          <path d="m278 100-7 10m43-10 7 10" stroke="#d4ae7c" strokeWidth="4" strokeLinecap="round" />
        </g>
        <g className="appliance appliance-fridge">
        <rect x="91" y="19" width="108" height="204" rx="10" fill="#fff4df" />
        <path d="M92 88h106" stroke="#d5b799" />
        <path d="M181 47v23m0 39v42" stroke="#7c5a4a" strokeWidth="5" strokeLinecap="round" />
        <rect x="105" y="30" width="16" height="6" rx="2" fill="#d8b689" stroke="none" />
        <path d="M103 223v6m85-6v6" stroke="#55352d" strokeWidth="5" />
        </g>
        <g className="appliance appliance-washer">
        <rect x="210" y="91" width="130" height="132" rx="9" fill="#fdf4e4" />
        <path d="M211 120h128" stroke="#d5b799" />
        <circle cx="277" cy="172" r="39" fill="#d4b59a" stroke="#e8d3b6" strokeWidth="7" />
        <circle cx="277" cy="172" r="29" fill="#453834" stroke="#796356" strokeWidth="3" />
        <path d="M259 176c9-13 18 12 36-5" stroke="#b4c6c0" strokeWidth="6" />
        <circle cx="226" cy="106" r="4" fill="#a37b5a" stroke="none" />
        <path d="M240 106h13m54 0h19" stroke="#a37b5a" strokeWidth="3" />
        </g>
        <g className="appliance appliance-fryer">
        <rect x="316" y="164" width="66" height="59" rx="12" fill="#34302d" stroke="#c89a60" />
        <path d="M319 188h60m-30 0v15" stroke="#b99b75" strokeWidth="3" />
        <rect x="333" y="171" width="32" height="8" rx="4" fill="#d6b477" stroke="none" />
        </g>
        <g className="appliance appliance-microwave">
          <rect x="13" y="163" width="113" height="61" rx="8" fill="#e4ccab" />
          <rect x="21" y="172" width="74" height="42" rx="4" fill="#383936" stroke="#a88d70" />
          <path d="m28 205 43-27h16v8l-43 26H28Z" fill="#71817a" opacity=".6" stroke="none" />
          <path d="M89 182v20" stroke="#e4ccab" strokeWidth="3" strokeLinecap="round" />
          <rect x="102" y="175" width="16" height="8" rx="2" fill="#46564c" stroke="none" />
          <circle cx="110" cy="198" r="5" fill="#795b43" stroke="none" />
        </g>
        <g className="appliance appliance-blender">
          <path d="m43 87 5 53h32l5-53Z" fill="#c5ded0" fillOpacity=".85" />
          <path d="M85 96h8c12 0 11 28-10 28" stroke="#e4ccab" strokeWidth="5" />
          <path d="m52 124 12 7 12-7M64 112v19" stroke="#6f8374" strokeWidth="2" />
          <rect x="40" y="81" width="48" height="8" rx="4" fill="#36322e" stroke="#bb945c" />
          <path d="m49 140-7 19a4 4 0 0 0 4 5h36a4 4 0 0 0 4-5l-7-19Z" fill="#36322e" stroke="#bb945c" />
          <circle cx="64" cy="153" r="4" fill="#edcc83" stroke="none" />
        </g>
      </g>
      <g className="prize-sparkles" fill="#f5cc69">
        <path d="m60 76 3 9 9 3-9 3-3 9-3-9-9-3 9-3Zm300-46 4 12 12 4-12 4-4 12-4-12-12-4 12-4Z" />
        <circle cx="389" cy="121" r="3" /><circle cx="48" cy="179" r="3" />
        <path d="m228 40 4-9m-191 97 7 4m334 60 8-5" stroke="#f5cc69" strokeWidth="3" strokeLinecap="round" />
      </g>
    </svg>
  )
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
        <section className="campaign" aria-labelledby="campaign-title">
          <div className="campaign-topline"><span className="campaign-badge"><span /> COMPRAR. PARTICIPAR. SOÑAR.</span><span className="edition">SANTACRUZ</span></div>
          <div className="campaign-copy">
            <p className="campaign-eyebrow">TÚ PONES LA CONFIANZA</p>
            <h1 id="campaign-title">Nosotros ponemos<br />la <em>emoción de ganar.</em></h1>
            <p className="campaign-description">Tus compras pueden traer algo más a casa.<br />Registra tus facturas y participa por<br className="desktop-break" /> electrodomésticos para tu hogar.</p>
          </div>

          <div className="prize-display">
            <span className="prize-stamp"><Icon name="gift" /> Premios para<br /><strong>tu hogar</strong></span>
            <Prizes />
          </div>

          <div className="participation">
            <p>ASÍ DE FÁCIL ES PARTICIPAR</p>
            <ol>
              <li><span className="step-icon"><Icon name="user" /></span><span><small>PASO 01</small>Crea tu cuenta</span></li>
              <li><span className="step-icon"><Icon name="receipt" /></span><span><small>PASO 02</small>Ingresa tu factura</span></li>
              <li><span className="step-icon"><Icon name="gift" /></span><span><small>PASO 03</small>¡Participa!</span></li>
            </ol>
          </div>
          <p className="campaign-footnote">Conserva tu factura. Las condiciones de participación se publicarán al lanzamiento.</p>
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
