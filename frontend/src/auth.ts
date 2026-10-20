export type User = {
  id: number
  name: string
  email: string
  phone: string | null
}

const baseUrl = (import.meta.env.VITE_API_BASE_URL ?? '/api').replace(/\/+$/, '')

export class ApiError extends Error {
  status: number

  constructor(message: string, status: number) {
    super(message)
    this.status = status
  }
}

export function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null
}

export async function apiRequest(path: string, init: RequestInit = {}): Promise<unknown> {
  let response: Response
  try {
    response = await fetch(`${baseUrl}/${path}`, {
      ...init,
      credentials: 'include',
      headers: { Accept: 'application/json', ...init.headers },
      signal: AbortSignal.timeout(15000),
    })
  } catch (error) {
    throw new ApiError(error instanceof DOMException && error.name === 'TimeoutError'
      ? 'El servidor tardó demasiado. Revisa tu conexión y vuelve a intentar.'
      : 'No se pudo contactar con el servidor. Revisa tu conexión.', 0)
  }

  const data: unknown = await response.json().catch(() => {
    throw new ApiError('El servidor devolvió una respuesta no válida.', response.status)
  })

  if (!response.ok) {
    let message = response.status === 419
      ? 'La sesión expiró. Vuelve a intentar.'
      : response.status === 401
        ? 'Tu sesión expiró. Vuelve a iniciar sesión.'
        : response.status === 429
        ? 'Demasiados intentos. Espera un minuto y vuelve a intentar.'
        : response.status >= 500
          ? 'Ocurrió un error en el servidor. Inténtalo más tarde.'
          : 'No se pudo completar la solicitud.'
    if (response.status === 422 && isRecord(data) && isRecord(data.errors)) {
      const errors = Object.values(data.errors).flat().filter((value): value is string => typeof value === 'string')
      if (errors.length) message = errors.join(' ')
    } else if (response.status === 503 && isRecord(data) && typeof data.message === 'string') {
      message = data.message
    }
    throw new ApiError(message, response.status)
  }

  return data
}

export async function csrfToken() {
  const csrf = await apiRequest('auth/csrf')
  if (!isRecord(csrf) || typeof csrf.token !== 'string') {
    throw new ApiError('No se pudo preparar la sesión. Recarga la página.', 0)
  }
  return csrf.token
}

export async function postJson(path: string, body: Record<string, string> = {}) {
  return apiRequest(path, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': await csrfToken() },
    body: JSON.stringify(body),
  })
}

export async function authAction(action: 'register' | 'login' | 'logout', body: Record<string, string> = {}) {
  return postJson(`auth/${action}`, body)
}

export function readUser(data: unknown): User {
  if (!isRecord(data) || !isRecord(data.user)) throw new ApiError('La respuesta de usuario no es válida.', 0)
  const user = data.user
  if (typeof user.id !== 'number' || typeof user.name !== 'string' || typeof user.email !== 'string' || !(typeof user.phone === 'string' || user.phone === null)) {
    throw new ApiError('La información del usuario no es válida.', 0)
  }
  return { id: user.id, name: user.name, email: user.email, phone: user.phone }
}

export async function currentUser() {
  try {
    return readUser(await apiRequest('auth/user'))
  } catch (error) {
    if (error instanceof ApiError && error.status === 401) return null
    throw error
  }
}
