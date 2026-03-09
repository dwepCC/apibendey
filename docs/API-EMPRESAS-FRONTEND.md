# API Empresas – Guía para el frontend (multitenant)

Documentación para integrar desde tu frontend la gestión de múltiples empresas con Lycet. Las empresas se almacenan en `data/empresas.json` y se usan en el resto de la API enviando el parámetro `ruc`.

---

## Autenticación

Todas las peticiones a `/api/*` requieren el token configurado en el backend (variable `CLIENT_TOKEN` en `.env`).

**Forma de envío:** parámetro de query `token`.

```
?token=tu_token
```

Ejemplo base URL con token:

```
https://tu-dominio.com/api/v1/empresas?token=greenter
```

---

## Base URL

Ajusta según tu entorno:

| Entorno   | Base URL                    |
|----------|-----------------------------|
| Local    | `http://localhost:8000`     |
| Producción | `https://tu-dominio.com`  |

Prefijo de la API: `/api/v1`.

---

## Endpoints

### 1. Listar empresas

Obtiene todas las empresas registradas en el servidor.

| Método | URL | Descripción        |
|--------|-----|--------------------|
| GET    | `/api/v1/empresas` | Lista todas las empresas |

**Query params**

| Parámetro | Tipo   | Obligatorio | Descripción      |
|----------|--------|-------------|------------------|
| token    | string | Sí          | Token de acceso  |

**Respuesta exitosa (200)**

Body: objeto donde cada clave es un **RUC** y el valor es la configuración de esa empresa (sin contraseñas en claro en la lógica actual; el backend devuelve lo que hay en `empresas.json`).

```json
{
  "20161515648": {
    "SOL_USER": "20161515648MODDATOS",
    "SOL_PASS": "MODDATOS",
    "certificate": "20161515648-cert.pem",
    "logo": "20161515648-logo.png"
  },
  "20000000002": {
    "SOL_USER": "20000000002MODDATOS",
    "SOL_PASS": "moddatos",
    "certificate": "20000000002-cert.pem",
    "logo": "20000000002-logo.png",
    "FE_URL": "https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService",
    "RE_URL": "https://e-beta.sunat.gob.pe/ol-ti-itemision-otroscpe-gem-beta/billService",
    "GUIA_URL": "https://e-beta.sunat.gob.pe/ol-ti-itemision-guia-gem-beta/billService"
  }
}
```

Si no hay empresas, se devuelve `{}`.

**Ejemplo (fetch)**

```javascript
const token = 'tu_token';
const response = await fetch(
  `https://tu-dominio.com/api/v1/empresas?token=${token}`,
  { method: 'GET', headers: { 'Accept': 'application/json' } }
);
const empresas = await response.json();
// empresas['20161515648'], empresas['20000000002'], ...
```

---

### 2. Crear o actualizar empresas

Registra una o varias empresas. Si el RUC ya existe, se actualiza (merge); si no, se crea. Opcionalmente se envían certificado y logo en base64 y el backend los guarda en archivos.

| Método | URL | Descripción              |
|--------|-----|--------------------------|
| POST   | `/api/v1/empresas` | Crear/actualizar empresas |

**Query params**

| Parámetro | Tipo   | Obligatorio | Descripción     |
|----------|--------|-------------|-----------------|
| token    | string | Sí          | Token de acceso |

**Body (JSON)**

Dos formatos admitidos:

**Opción A – Objeto anidado bajo `empresas`**

```json
{
  "empresas": {
    "RUC_1": { ... },
    "RUC_2": { ... }
  }
}
```

**Opción B – Objeto directo (clave = RUC)**

```json
{
  "RUC_1": { ... },
  "RUC_2": { ... }
}
```

**Campos por empresa (RUC)**

| Campo                | Tipo   | Obligatorio | Descripción |
|----------------------|--------|-------------|-------------|
| SOL_USER             | string | Sí          | RUC + usuario SOL (ej: `20161515648MODDATOS`) |
| SOL_PASS             | string | Sí          | Clave SOL |
| certificate_base64   | string | No          | Contenido del certificado `.pem` en base64. Se guarda como `{RUC}-cert.pem` |
| logo_base64          | string | No          | Imagen del logo en base64. Se guarda como `{RUC}-logo.png` |
| certificateBase64    | string | No          | Alternativa en camelCase a `certificate_base64` |
| logoBase64           | string | No          | Alternativa en camelCase a `logo_base64` |
| FE_URL               | string | No          | URL del servicio de facturación electrónica |
| RE_URL               | string | No          | URL del servicio de retenciones/percepciones |
| GUIA_URL             | string | No          | URL del servicio de guías de remisión |
| AUTH_URL             | string | No          | URL de autenticación (API) |
| API_URL              | string | No          | URL del API CPE |
| CLIENT_ID            | string | No          | Client ID (API) |
| CLIENT_SECRET        | string | No          | Client secret (API) |

**Ejemplo mínimo (solo usuario y clave SOL)**

```json
{
  "empresas": {
    "20161515648": {
      "SOL_USER": "20161515648MODDATOS",
      "SOL_PASS": "MODDATOS"
    }
  }
}
```

**Ejemplo con certificado y logo en base64**

```json
{
  "empresas": {
    "20161515648": {
      "SOL_USER": "20161515648MODDATOS",
      "SOL_PASS": "MODDATOS",
      "certificate_base64": "LS0tLS1CRUdJTi...",
      "logo_base64": "iVBORw0KGgoAAAANSUhEUgAA..."
    }
  }
}
```

**Ejemplo con URLs personalizadas (OSE)**

```json
{
  "empresas": {
    "20000000002": {
      "SOL_USER": "20000000002MODDATOS",
      "SOL_PASS": "moddatos",
      "certificate_base64": "...",
      "logo_base64": "...",
      "FE_URL": "https://mi-ose.com/billService",
      "RE_URL": "https://mi-ose.com/billService",
      "GUIA_URL": "https://mi-ose.com/billService"
    }
  }
}
```

**Respuesta exitosa (200)**

```json
{
  "ok": true,
  "message": "Empresas actualizadas"
}
```

**Errores posibles**

| Código | Body típico | Motivo |
|--------|-------------|--------|
| 400 | `{ "error": "JSON inválido" }` | Body no es JSON válido |
| 400 | `{ "error": "Se esperaba un objeto de empresas (RUC => config)" }` | `empresas` (o el objeto raíz) no es un objeto |
| 403 | — | Token ausente o incorrecto |

**Ejemplo (fetch)**

```javascript
const token = 'tu_token';
const empresas = {
  empresas: {
    '20161515648': {
      SOL_USER: '20161515648MODDATOS',
      SOL_PASS: 'MODDATOS',
      certificate_base64: pemBase64,  // opcional
      logo_base64: logoBase64         // opcional
    }
  }
};

const response = await fetch(
  `https://tu-dominio.com/api/v1/empresas?token=${token}`,
  {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    },
    body: JSON.stringify(empresas)
  }
);

const result = await response.json();
if (result.ok) {
  console.log('Empresas guardadas');
}
```

### 3. Configuración por RUC (certificado y logo)

Si usas el endpoint **POST `/api/v1/configuration`** para subir certificado o logo, **debes enviar siempre el campo `ruc`**. Así se guardan como **`{ruc}-cert.pem`** y **`{ruc}-logo.png`** en `data/` y se actualiza `data/empresas.json` (igual que en ese archivo).  
Si envías `certificate` o `logo` **sin** `ruc`, la API responde **400** y no se guarda en `cert.pem` ni `logo.png`.

| Método | URL | Descripción |
|--------|-----|-------------|
| POST | `/api/v1/configuration` | Subir certificado/logo; **ruc obligatorio** → se guarda como `{ruc}-cert.pem` y `{ruc}-logo.png` |

**Body:** debe incluir `ruc` y, opcionalmente, usuario/clave SOL y URLs.

```json
{
  "ruc": "20161515648",
  "SOL_USER": "20161515648MODDATOS",
  "SOL_PASS": "MODDATOS",
  "certificate": "<base64 del .pem>",
  "logo": "<base64 del .png>",
  "FE_URL": "https://...",
  "RE_URL": "https://...",
  "GUIA_URL": "https://..."
}
```

- Con **`ruc`**: se guardan `data/{ruc}-cert.pem` y `data/{ruc}-logo.png` y se actualiza la entrada de esa empresa en `empresas.json`.
- Sin **`ruc`** pero con certificate/logo: respuesta **400** (no se usa `cert.pem` ni `logo.png`).

---

## Uso del RUC en el resto de la API

Para **facturas, notas, guías, anulaciones, resúmenes, etc.**, se debe indicar con qué empresa se opera usando el parámetro de query **`ruc`**.

Ejemplos:

- Enviar factura para la empresa `20161515648`:
  - `POST /api/v1/invoice/send?token=...&ruc=20161515648`
- Estado de CDR:
  - `GET /api/v1/invoice/status?token=...&ruc=20161515648&tipo=01&serie=F001&numero=1`
- Guía de remisión:
  - `POST /api/v1/despatch/send?token=...&ruc=20161515648`
- Anulación:
  - `POST /api/v1/voided/send?token=...&ruc=20161515648`

El backend busca en `data/empresas.json` la configuración del RUC indicado (certificado, usuario SOL, URLs, etc.) y usa esa empresa para esa petición.

### Factura y Boleta: datos obligatorios

Para **factura** y **boleta** el frontend debe enviar **siempre** en el body:

- **tipoOperacion** — Valor que se escribe en el **atributo listID** del XML (`"0101"` factura, `"0102"` boleta). Si no lo envías, listID sale vacío y SUNAT rechaza. Ver [PAYLOAD-FACTURA-BOLETA.md](PAYLOAD-FACTURA-BOLETA.md).
- **tipoDoc** — Tipo de comprobante: `"01"` (factura), `"03"` (boleta).

Si falta alguno, la API responde **400** y no envía a SUNAT. No hay valores por defecto.

**Payload completo** (todos los campos): ver **[docs/PAYLOAD-FACTURA-BOLETA.md](PAYLOAD-FACTURA-BOLETA.md)**.

**Estructura de la respuesta** al enviar a SUNAT (aceptado, rechazado, sin conexión): ver **[docs/RESPUESTA-SUNAT-BACKEND.md](RESPUESTA-SUNAT-BACKEND.md)**.

---

## Flujo recomendado en el frontend

1. **Al cargar la app (o el módulo multitenant)**  
   - `GET /api/v1/empresas?token=...`  
   - Guardar el objeto en estado (por ejemplo lista/selector de empresas).

2. **Al dar de alta o editar una empresa**  
   - Si el usuario sube certificado/logo, convertir archivos a base64.  
   - `POST /api/v1/empresas?token=...` con el objeto `empresas` (uno o varios RUC).  
   - Tras éxito, opcionalmente volver a llamar a `GET /api/v1/empresas` para refrescar la lista.

3. **En cada operación de comprobantes**  
   - Enviar siempre `ruc=<RUC_EMPRESA>` en la URL de la petición correspondiente (invoice, note, voided, despatch, etc.).

---

## Conversión de archivos a base64 (referencia)

```javascript
// Certificado .pem
function fileToBase64(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => {
      const base64 = reader.result.split(',')[1];
      resolve(base64 || reader.result);
    };
    reader.onerror = reject;
    reader.readAsDataURL(file);
  });
}

// Uso
const certBase64 = await fileToBase64(certFileInput.files[0]);
const logoBase64 = await fileToBase64(logoFileInput.files[0]);
```

---

## Resumen de URLs

| Acción              | Método | URL                        |
|---------------------|--------|----------------------------|
| Listar empresas     | GET    | `/api/v1/empresas`         |
| Crear/actualizar    | POST   | `/api/v1/empresas`         |
| Config (con `ruc`)  | POST   | `/api/v1/configuration`   |

Todas con `?token=<CLIENT_TOKEN>`. El resto de la API sigue usando `?token=...&ruc=<RUC>`.

### Logs para depurar guardado

Si los archivos no se guardan en `data/` o `empresas.json` no se escribe, revisa los logs del backend:

- **Symfony (dev):** `var/log/dev.log`
- Busca líneas `[ConfigurationController]` y `[EmpresasService]`.

Ahí verás:
- Qué claves llegan en el body (`body_keys`, `has_ruc`, `has_certificate`, `has_logo`, longitudes).
- Si el base64 se decodifica bien (`cert_decoded_ok`, `logo_decoded_ok`).
- La ruta donde se escribe (`path`), si el directorio es escribible (`data_dir_writable`) y si el guardado tuvo éxito (`success`).
- Si se llama a `addOrUpdateEmpresas` y si `empresas.json` se escribe (`empresas_count`, `file_exists_after`).

Así puedes saber si el fallo es: body sin `ruc`, base64 inválido, permisos en `data/` o que no se está llamando al endpoint correcto.
