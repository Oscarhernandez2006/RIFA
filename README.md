# Boletas

Frontend React 19 con TypeScript y Vite, API Laravel 13 y PostgreSQL remoto.
Ambas aplicaciones son independientes; Laravel no necesita ejecutar Vite.

## Requisitos

- Node.js 22.12+ y npm.
- PHP 8.3+ con las extensiones de Laravel y `pdo_pgsql`; `pdo_sqlite` para pruebas.
- Composer 2 (instalado localmente en `~/.local/bin/composer` en este equipo).
- Una base PostgreSQL remota y permisos de acceso desde este equipo.

## Preparacion

```bash
cd backend
~/.local/bin/composer install
cp .env.example .env
php artisan key:generate
cd ../frontend
npm ci
cp .env.example .env
```

En esta instalacion las dependencias y los archivos `.env` ya estan preparados.
No sobrescribas un `.env` existente con credenciales.

## PostgreSQL remoto

Completa **solo** `backend/.env` con los datos de tu proveedor:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=tu-servidor-postgres
DB_PORT=5432
DB_DATABASE=tu_base
DB_USERNAME=tu_usuario
DB_PASSWORD="tu_clave"
DB_SSLMODE=require
```

`require` exige cifrado TLS. En produccion usa `DB_SSLMODE=verify-full`
y configura `DB_SSLROOTCERT=/ruta/al/certificado-ca.pem` segun tu proveedor
para verificar tambien la identidad del servidor.
Autoriza tu IP en el firewall del proveedor si es necesario.
Los valores `DB_*` nunca deben ir al frontend ni a variables `VITE_*`,
porque estas se publican en el navegador.
Los archivos `.env` estan excluidos de Git.

Verifica primero la conexion sin modificar la base:

```bash
cd backend
php artisan config:clear
php artisan db:show --database=pgsql
```

Solo despues de confirmar que es la base correcta y revisar las migraciones:

```bash
php artisan migrate
```

La conexion remota fue verificada y las migraciones se ejecutaron con autorizacion.
El entorno local usa `DB_SSLMODE=disable` porque el servidor no admite TLS,
una excepcion autorizada solo para desarrollo. Habilita TLS antes de produccion.
Sesiones/cache usan archivos y la cola es sincrona.
Las pruebas usan configuracion aislada y no acceden a PostgreSQL remoto.

## Desarrollo

En VS Code, ejecuta las tareas **Boletas: backend** y **Boletas: frontend**.
Tambien puedes iniciar dos terminales:

```bash
cd backend
php artisan serve --host=127.0.0.1 --port=8000
```

```bash
cd frontend
npm run dev
```

- Frontend: http://localhost:5173
- API: http://127.0.0.1:8000/api/health

Vite reenvia `/api` a Laravel. El endpoint `/api/health` mantiene el contrato
`{"status":"ok","service":"boletas-api"}`; no verifica PostgreSQL.

## Registro, inicio de sesion y espacio de facturas

La pantalla inicial presenta el registro y el inicio de sesion de Carnes
Santacruz, con el logo suministrado y una paleta roja, dorada, crema y oscura.
El diseno se adapta a movil y escritorio, permite alternar formularios,
mostrar/ocultar contrasenas y validar campos obligatorios, correo y
confirmacion de contrasena.
En escritorio, los espacios y la ilustracion se ajustan a la altura de la
pantalla para evitar desplazamiento en la vista inicial. En movil, ventanas
muy bajas o con mensajes adicionales, se permite desplazar sin recortar campos.

El registro guarda nombre completo, correo normalizado a minusculas, celular
y contrasena con hash en PostgreSQL. Comprueba correo unico, formato de celular,
minimo de 8 caracteres y confirmacion de contrasena. Registro e inicio de sesion
autentican al cliente y muestran `/facturas`. La sesion persiste al recargar.
Se puede cerrar la sesion; no se guardan tokens ni contrasenas en localStorage.

La API usa sesiones de Laravel, cookies HttpOnly y proteccion CSRF en las rutas
de autenticacion del grupo web. Login y registro admiten 5 solicitudes por minuto
por IP. El endpoint de usuario requiere autenticacion y no expone hashes.

| Metodo | Ruta | Funcion |
| --- | --- | --- |
| GET | `/api/auth/csrf` | Preparar cookie de sesion y token CSRF |
| POST | `/api/auth/register` | Crear cliente y abrir sesion |
| POST | `/api/auth/login` | Validar credenciales y abrir sesion |
| GET | `/api/auth/user` | Consultar usuario autenticado |
| POST | `/api/auth/logout` | Invalidar sesion |

Las solicitudes POST requieren `X-CSRF-TOKEN` obtenido de `/api/auth/csrf`
y la misma cookie de sesion. No excluyas estas rutas de la proteccion CSRF.
Las respuestas privadas llevan `Cache-Control: no-store`.

La pantalla privada es la base responsive para el futuro formulario de facturas.
Todavia no guarda facturas ni genera participaciones: falta definir sus campos
y reglas. La recuperacion de contrasena tampoco esta habilitada y lo informa
explicitamente, sin simular el envio de correos.
Los electrodomesticos son ilustraciones, no premios confirmados.
La composicion incluye nevera, lavadora, freidora, televisor, microondas y
licuadora, con movimiento suave continuo, sin boton de pausa. La animacion se
desactiva automaticamente con la preferencia de movimiento reducido.
El reglamento, tratamiento de datos y condiciones de participacion quedan
pendientes para el lanzamiento.

Se recomienda servir frontend y `/api` bajo el mismo origen en produccion,
mediante proxy inverso. Para subdominios del mismo sitio, configura
`VITE_API_BASE_URL=https://api.tudominio.example/api` antes de compilar,
`FRONTEND_URL=https://app.tudominio.example` y `SESSION_DOMAIN=.tudominio.example`.
Dominios de sitios distintos no estan soportados por la configuracion SameSite
predeterminada. Las solicitudes incluyen credenciales y CORS permite solo el origen
configurado. Usa `SESSION_SECURE_COOKIE=true` con HTTPS.
En produccion usa HTTPS, `APP_ENV=production`, `APP_DEBUG=false`,
un servidor web adecuado para Laravel y sirve `frontend/dist` como archivos estaticos.
El proxy de Vite es solo para desarrollo; `npm run preview` no sustituye un despliegue.
La autenticacion esta implementada; las reglas de rifas y facturas estan pendientes.

## Despliegue con Dokploy (Docker)

El `Dockerfile` de la raiz genera una sola imagen: compila React con Node 22
y sirve Laravel con PHP 8.5 y Apache en el puerto **80**. Frontend y `/api`
comparten dominio, por lo que no hace falta `VITE_API_BASE_URL` ni CORS.
Laravel entrega la app React en `/` y `/facturas`; `/api/*` y `/up` siguen
siendo de Laravel. `/up` se usa como comprobacion de salud del contenedor.

En Dokploy: tipo de compilacion **Dockerfile**, ruta `Dockerfile`, contexto `.`
y puerto del dominio **80**. Configura estas variables en *Environment*
(nunca en el repositorio):

```dotenv
APP_NAME=Boletas
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...            # genera una con: php artisan key:generate --show
APP_URL=https://tu-dominio
FRONTEND_URL=https://tu-dominio
APP_LOCALE=es
LOG_CHANNEL=stderr
LOG_LEVEL=warning
DB_CONNECTION=pgsql
DB_HOST=...
DB_PORT=...
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD="..."
DB_SSLMODE=disable            # el servidor actual no admite TLS
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=file
QUEUE_CONNECTION=sync
INVOICE_API_QUERY_TOKEN=...
```

Si PostgreSQL tambien esta en Dokploy, usa su host interno de la red Docker en
lugar de la IP publica, asi la conexion sin TLS no sale a internet.

Al arrancar, el contenedor exige `APP_KEY`, genera las caches de configuracion,
rutas y vistas, y ejecuta `php artisan migrate --force`, que solo aplica
migraciones pendientes y no borra datos. Para desactivarlo usa
`RUN_MIGRATIONS=false`. Laravel confia en los encabezados del proxy de Dokploy
para detectar HTTPS y marcar las cookies como seguras.

## Validacion

```bash
cd frontend
npm run lint
npm run build
cd ../backend
php artisan test
vendor/bin/pint --test
```

Las pruebas de autenticacion usan SQLite en memoria y nunca las tablas remotas.
Si falta el driver, instala la extension `pdo_sqlite` para la version de PHP usada.
Se verifican registro, hash, duplicados, validacion, credenciales invalidas,
sesiones, rutas privadas, cierre de sesion y limite de intentos.
