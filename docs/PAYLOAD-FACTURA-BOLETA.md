# Payload exacto: Factura y Boleta (frontend)

El backend **no** asigna valores por defecto. El frontend debe enviar **todos** los datos obligatorios. Si falta `tipoOperacion` o `tipoDoc`, la API responde **400** con la lista de campos requeridos.

---

## Cómo se arma el XML: `tipoDoc` vs `tipoOperacion`

En el XML UBL que se envía a SUNAT el nodo queda así:

```xml
<cbc:InvoiceTypeCode listID="...">...</cbc:InvoiceTypeCode>
```

| Lo que envías desde el frontend | Dónde va en el XML |
|----------------------------------|---------------------|
| **tipoDoc** | Es el **valor interno** del nodo (entre las etiquetas). `"01"` = Factura, `"03"` = Boleta (catálogo SUNAT tipo de comprobante). |
| **tipoOperacion** | Es el **atributo listID**. Si no lo envías, el XML sale con `listID=""` y SUNAT rechaza (error 3205). |

En los XML que **SUNAT acepta** suele verse **listID en 4 dígitos** (ej. `listID="0101"` para factura, `listID="0102"` para boleta). Por eso desde el frontend debes enviar:

- **Factura:** `tipoDoc: "01"`, `tipoOperacion: "0101"`
- **Boleta (venta interna):** `tipoDoc: "03"`, `tipoOperacion: "0101"`  
  (En el catálogo 51, **0101** = Venta interna; **0102** = Exportación. Para boleta por venta interna se usa **0101**.)

Así el XML generado será:
- Factura: `<cbc:InvoiceTypeCode listID="0101">01</cbc:InvoiceTypeCode>`
- Boleta (venta interna): `<cbc:InvoiceTypeCode listID="0101">03</cbc:InvoiceTypeCode>`

Si **no** envías `tipoOperacion`, el backend no puede rellenar `listID` y sale `listID=""` → SUNAT rechaza.

---

## Base oficial: de dónde salen los 4 dígitos

Los valores de **tipo de operación** (los que van en `listID`) están definidos por **SUNAT** en:

- **Guía de elaboración de documentos electrónicos XML - UBL 2.1** (Factura y Boleta electrónica), sección **“4. Tipo de Operación”**.
- **Catálogo N° 51** del **Anexo N° 8** (aprobado por la **Resolución de Superintendencia N° 097-2012/SUNAT** y modificatorias).  
  En la guía también se referencia el **Catálogo N° 17** (en el URN del esquema: `urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo17`); en la práctica, la guía publicada muestra los códigos bajo la tabla **“Catálogo N° 51”**.

En esa guía, los códigos de tipo de operación se publican en **4 dígitos**, por ejemplo:

| Código (4 dígitos) | Concepto |
|--------------------|----------|
| **0101** | Venta interna |
| **0102** | Exportación |
| **0103** | No Domiciliados |
| **0104** | Venta Interna – Anticipos |
| **0105** | Venta Itinerante |
| **0106** | Factura Guía |
| **0107** | Venta Arroz Pilado |
| **0108** | Factura - Comprobante de Percepción |
| **0110** | Factura - Guía remitente |

Por eso en el XML el **listID** usa **4 dígitos** (`0101`, `0102`, etc.): es el formato del **Catálogo N° 51 (Anexo 8)** según la guía UBL 2.1 de SUNAT.  
Desde el frontend debes enviar **`tipoOperacion`** con exactamente ese código de 4 dígitos (ej. `"0101"`, `"0102"`).

- Guía oficial (ej. Boleta UBL 2.1): [cpe.sunat.gob.pe – Guías y manuales](https://cpe.sunat.gob.pe/guias-y-manuales).

---

## Endpoint

- **Enviar a SUNAT:** `POST /api/v1/invoice/send?token=TU_TOKEN`
- **Solo XML:** `POST /api/v1/invoice/xml?token=TU_TOKEN`
- **Solo PDF:** `POST /api/v1/invoice/pdf?token=TU_TOKEN`

El **PDF** no viene en la respuesta de `/send`. Para obtenerlo hay que llamar a **`POST /api/v1/invoice/pdf`** con el **mismo body** (mismo JSON de la factura/boleta). La respuesta es el archivo PDF en binario (`Content-Type: application/pdf`).

El **RUC** de la empresa se toma del objeto **`company.ruc`** del body (debe coincidir con una empresa registrada en `empresas.json`).

---

## Campos obligatorios comunes

| Campo | Tipo | Descripción |
|-------|------|-------------|
| **tipoOperacion** | string | **Obligatorio.** Valor que va al **atributo listID** del XML (Catálogo N° 51, 4 dígitos). Venta interna: `"0101"`. Exportación: `"0102"`. Si no lo envías, listID sale vacío y SUNAT rechaza. |
| **tipoDoc** | string | **Obligatorio.** Valor que va **dentro** del nodo InvoiceTypeCode (catálogo tipo comprobante): `"01"` Factura, `"03"` Boleta. |
| **serie** | string | Serie del comprobante (ej. `"F001"`, `"B001"`). |
| **correlativo** | string | Número correlativo. |
| **fechaEmision** | string | Fecha y hora ISO 8601 (ej. `"2026-03-06T00:41:04-05:00"`). |
| **company** | object | Emisor. Debe incluir `ruc`, `razonSocial`, `nombreComercial`, `address`. |
| **client** | object | Cliente. Debe incluir `tipoDoc`, `numDoc`, `rznSocial`, `address`. |
| **tipoMoneda** | string | Moneda (ej. `"PEN"`). |
| **formaPago** | object | Al menos `tipo` (ej. `"Contado"`). |
| **details** | array | Ítems (al menos uno). Cada ítem con los campos indicados abajo. |
| **legends** | array | Leyendas (código 1000 = monto en letras). |
| **mtoOperGravadas** | number | Total operaciones gravadas. |
| **mtoIGV** | number | IGV. |
| **totalImpuestos** | number | Total tributos. |
| **valorVenta** | number | Valor de venta. |
| **subTotal** | number | Subtotal. |
| **mtoImpVenta** | number | Monto total a pagar. |
| **fecVencimiento** | string (opcional) | Solo **factura**. Fecha de vencimiento del pago en ISO 8601 (ej. `"2026-03-12"`). Si lo envías, en el XML se genera `<cbc:DueDate>`. |

---

## Payload completo FACTURA (tipoDoc 01)

```json
{
  "ublVersion": "2.1",
  "tipoOperacion": "0101",
  "tipoDoc": "01",
  "serie": "F001",
  "correlativo": "1",
  "fechaEmision": "2026-03-06T12:00:00-05:00",
  "formaPago": {
    "tipo": "Contado"
  },
  "company": {
    "ruc": "20161515648",
    "razonSocial": "MI EMPRESA S.A.C.",
    "nombreComercial": "MI EMPRESA S.A.C.",
    "address": {
      "ubigueo": "150131",
      "codigoPais": "PE",
      "departamento": "LIMA",
      "provincia": "LIMA",
      "distrito": "SAN ISIDRO",
      "urbanizacion": "-",
      "direccion": "AV. EJEMPLO 123"
    }
  },
  "client": {
    "tipoDoc": "6",
    "numDoc": "20100000001",
    "rznSocial": "CLIENTE EJEMPLO S.A.C.",
    "address": {
      "ubigueo": "150101",
      "codigoPais": "PE",
      "departamento": "LIMA",
      "provincia": "LIMA",
      "distrito": "LIMA",
      "direccion": "AV. CLIENTE 456"
    }
  },
  "tipoMoneda": "PEN",
  "mtoOperGravadas": 84.75,
  "mtoIGV": 15.25,
  "totalImpuestos": 15.25,
  "valorVenta": 84.75,
  "subTotal": 100.00,
  "mtoImpVenta": 100.00,
  "details": [
    {
      "unidad": "NIU",
      "cantidad": 1,
      "codProducto": "P001",
      "descripcion": "Producto ejemplo",
      "mtoValorUnitario": 84.75,
      "mtoValorVenta": 84.75,
      "tipAfeIgv": "10",
      "mtoBaseIgv": 84.75,
      "porcentajeIgv": 18,
      "igv": 15.25,
      "totalImpuestos": 15.25,
      "mtoPrecioUnitario": 100.00
    }
  ],
  "legends": [
    {
      "code": "1000",
      "value": "SON CIEN CON 00/100 SOLES"
    }
  ]
}
```

---

## Payload completo BOLETA (tipoDoc 03)

```json
{
  "ublVersion": "2.1",
  "tipoOperacion": "0101",
  "tipoDoc": "03",
  "serie": "B001",
  "correlativo": "30",
  "fechaEmision": "2026-03-06T00:41:04-05:00",
  "formaPago": {
    "tipo": "Contado"
  },
  "company": {
    "ruc": "20161515648",
    "razonSocial": "MI EMPRESA S.A.C.",
    "nombreComercial": "MI EMPRESA S.A.C.",
    "address": {
      "ubigueo": "150131",
      "codigoPais": "PE",
      "departamento": "LIMA",
      "provincia": "LIMA",
      "distrito": "SAN ISIDRO",
      "urbanizacion": "-",
      "direccion": "AV. EJEMPLO 123"
    }
  },
  "client": {
    "tipoDoc": "1",
    "numDoc": "12345678",
    "rznSocial": "JUAN PEREZ",
    "address": {
      "ubigueo": "150101",
      "codigoPais": "PE",
      "departamento": "LIMA",
      "provincia": "LIMA",
      "distrito": "LIMA",
      "direccion": "JR. CLIENTE 789"
    }
  },
  "tipoMoneda": "PEN",
  "mtoOperGravadas": 63.56,
  "mtoIGV": 11.44,
  "totalImpuestos": 11.44,
  "valorVenta": 63.56,
  "subTotal": 75.00,
  "mtoImpVenta": 75.00,
  "details": [
    {
      "unidad": "NIU",
      "cantidad": 1,
      "codProducto": "P002",
      "descripcion": "Producto boleta",
      "mtoValorUnitario": 63.56,
      "mtoValorVenta": 63.56,
      "tipAfeIgv": "10",
      "mtoBaseIgv": 63.56,
      "porcentajeIgv": 18,
      "igv": 11.44,
      "totalImpuestos": 11.44,
      "mtoPrecioUnitario": 75.00
    }
  ],
  "legends": [
    {
      "code": "1000",
      "value": "MONTO: PEN 75.00"
    }
  ]
}
```

---

## Valores clave que debes enviar siempre

| Concepto | Factura | Boleta |
|----------|---------|--------|
| **tipoOperacion** | `"0101"` (va a **listID** en el XML; venta interna) | `"0101"` (va a **listID**; venta interna) |
| **tipoDoc** | `"01"` (va **dentro** del nodo) | `"03"` (va **dentro** del nodo) |
| **serie** | Ej. `"F001"` | Ej. `"B001"` |

- **tipoOperacion** es lo que rellena el atributo **listID**; si no lo envías, listID queda vacío y SUNAT rechaza. Usa `"0101"` factura, `"0102"` boleta (venta interna).
- **tipoDoc** es el código del comprobante (01 factura, 03 boleta) y va como contenido del nodo.
- **company.ruc** debe ser un RUC registrado en `GET /api/v1/empresas` (o en `data/empresas.json`).
- **client.tipoDoc**: `"0"` Sin documento (no documentado; en el XML sale `schemeID="0"`), `"1"` DNI, `"6"` RUC, `"4"` Carnet de extranjería, etc. (catálogo SUNAT N° 06).
- **details[].tipAfeIgv**: `"10"` Gravado, `"20"` Exonerado, `"30"` Inafecto, etc. (catálogo 07). **Si usas exonerado o inafecto**, el XML debe incluir el total de ese tributo en el resumen; ver sección *Tributos e IGV: productos gravados, exonerados e inafectos* más abajo.
- **legends[].code**: `"1000"` para el monto en letras.

---

## Datos a enviar para observaciones frecuentes del XML

Estos son los campos que debes enviar desde el frontend para que el XML generado cumpla con SUNAT y no rechace por nodos vacíos o valores incorrectos.

### 1. Fecha de vencimiento → `<cbc:DueDate>`

Para que en el XML aparezca el tag **`<cbc:DueDate>YYYY-MM-DD</cbc:DueDate>`** (fecha de vencimiento del pago), debes enviar en el body:

| Campo | Tipo | Ejemplo | Nota |
|-------|------|---------|------|
| **fecVencimiento** | string (fecha ISO 8601) | `"2026-03-12"` o `"2026-03-12T00:00:00-05:00"` | **Solo aplica a factura** (Invoice). Si no lo envías, el nodo `cbc:DueDate` no se genera. |

Inclúyelo a nivel raíz del JSON de la factura, al mismo nivel que `fechaEmision`, `serie`, etc.

### 2. Leyenda del monto en letras (LanguageLocaleID / monto en texto)

El backend **no** genera automáticamente el texto "Cuarenta y cinco con 00/100". Ese texto debe enviarlo el **frontend** en las **leyendas**.

| Dónde | Campo | Valor |
|-------|--------|--------|
| **legends** | **code** | `"1000"` (código SUNAT para monto en letras). |
| **legends** | **value** | El texto del monto en letras, ej. `"SON CUARENTA Y CINCO CON 00/100 SOLES"` o `"Cuarenta y cinco con 00/100"` según lo que exija la guía UBL. |

Ejemplo:

```json
"legends": [
  {
    "code": "1000",
    "value": "SON CUARENTA Y CINCO CON 00/100 SOLES"
  }
]
```

El frontend debe armar este texto a partir de `mtoImpVenta` y `tipoMoneda` (por ejemplo con una función "número a letras") y enviarlo en **`legends[].value`**.

### 3. Cliente no documentado → `schemeID="0"` en `<cbc:ID>`

En el XML del cliente, el atributo **`schemeID`** del nodo `<cbc:ID>` corresponde al **tipo de documento de identidad** (Catálogo SUNAT N° 06). Si el cliente es **no documentado**, debe salir **`schemeID="0"`**.

| Situación | Qué enviar en **client** |
|-----------|---------------------------|
| Cliente **no documentado** | **tipoDoc**: `"0"`, **numDoc**: p. ej. `"99999999999"` o el número que uses para sin documento. |
| DNI | **tipoDoc**: `"1"` |
| RUC | **tipoDoc**: `"6"` |
| Carnet de extranjería | **tipoDoc**: `"4"` |
| Otros (catálogo 06) | El código que corresponda. |

Si hoy envías `tipoDoc: "1"` para un cliente sin documento, en el XML saldrá `schemeID="1"` y SUNAT puede rechazar. Para no documentados debes enviar **`client.tipoDoc`: `"0"`**.

### 4. Productos exonerados / inafectos → `<cbc:Percent>` del IGV

Para que en el XML aparezca el **porcentaje de IGV** en las líneas (p. ej. `<cbc:Percent>10.50</cbc:Percent>` o `18` según corresponda), cada ítem de **details** debe llevar el campo **porcentajeIgv** con el valor numérico correcto:

| Situación | Qué enviar en **details[].porcentajeIgv** |
|-----------|------------------------------------------|
| Gravado (18% IGV) | `18` |
| Exonerado / inafecto (tasa 0% o tasa especial) | Según la guía SUNAT y el régimen de la empresa: a menudo **0**, o la tasa que corresponda (p. ej. **10.5** o **18** si SUNAT exige declarar la tasa de referencia). |

El backend mapea **porcentajeIgv** al nodo `<cbc:Percent>` del XML. Para productos exonerados, envía el porcentaje que deba figurar (0, 10.5, 18, etc.) en cada **details[]** según el **tipAfeIgv** y la normativa vigente.

---

## Tributos e IGV: productos gravados, exonerados e inafectos (rechazo SUNAT 2638 / 3105)

SUNAT exige que **por cada tipo de tributo/afectación usado en las líneas del comprobante exista un total de ese tributo en el resumen del XML** (bloques `cac:TaxTotal` / `cac:TaxSubtotal`). Si en alguna línea usas un tipo de afectación distinto del gravado (IGV 17%), debes declarar el monto total de ese tributo a nivel de resumen.

### Qué significa el error de SUNAT

- **"El XML debe contener al menos un tributo por línea de afectación por IGV"** o **código 2638 / 3105**: indica que en el XML hay líneas con `tipAfeIgv` exonerado (`"20"`), inafecto (`"30"`), etc., pero **falta el nodo (tag) con el monto total de ese tributo** en la sección de resumen de impuestos. SUNAT espera, además del `cac:TaxTotal` para IGV gravado (código 17), **otro bloque `cac:TaxTotal`** (o el correspondiente `cac:TaxSubtotal`) para el tributo exonerado/inafecto.

### Tipos de afectación del IGV (Catálogo N° 07)

| Código | Descripción |
|--------|-------------|
| **10** | Gravado - Operación Onerosa |
| **20** | Exonerado - Operación Onerosa |
| **30** | Inafecto - Operación Onerosa |
| **40** | Exportación |
| Otros | Según catálogo SUNAT vigente |

### Qué debe enviar el frontend cuando hay exonerados o inafectos

1. **Por línea (`details[]`):** cada ítem debe llevar el `tipAfeIgv` correcto (`"10"`, `"20"`, `"30"`, etc.) y los montos coherentes con esa afectación:
   - **Gravado (10):** `mtoBaseIgv`, `porcentajeIgv`, `igv`, `totalImpuestos` con valores correspondientes.
   - **Exonerado (20) / Inafecto (30):** según la guía UBL 2.1 de SUNAT, suele enviarse base y monto de impuesto (a menudo 0 o el valor que corresponda); el detalle debe ser consistente con el total declarado para ese tributo.

2. **Totales a nivel de comprobante:** además de `mtoOperGravadas` y `mtoIGV` (para gravado), cuando existan operaciones exoneradas o inafectas el comprobante debe declarar los **totales por tipo de operación** que la librería que genera el XML use para armar los `cac:TaxTotal`:
   - Si el backend/librería acepta campos como **`mtoOperExoneradas`**, **`mtoOperInafectas`** (u equivalentes), hay que enviarlos cuando haya líneas con `tipAfeIgv` 20 o 30.
   - Los totales de totales (`valorVenta`, `subTotal`, `mtoImpVenta`, `totalImpuestos`) deben cuadrar con la suma de gravado + exonerado + inafecto según corresponda.

3. **Verificación del XML generado:** si SUNAT rechaza con 2638/3105, revisar el XML firmado que se envía y comprobar que existan **tantos bloques `cac:TaxTotal` (o `cac:TaxSubtotal`) como tipos de tributo/afectación** usados en las líneas (por ejemplo: uno para IGV gravado y otro para exonerado/inafecto). El nodo que SUNAT indica vacío o inexistente es el que debe contener el monto total de ese tributo.

En resumen: **si alguna línea tiene `tipAfeIgv` exonerado o inafecto, el XML debe incluir el tag del total de ese tributo en el resumen; de lo contrario SUNAT rechaza el comprobante.**

---

## Respuesta al enviar a SUNAT

Estructura exacta del JSON que devuelve el backend cuando envías a SUNAT, y qué pasa si SUNAT **acepta**, **rechaza** o **no hay conexión**: ver **[docs/RESPUESTA-SUNAT-BACKEND.md](RESPUESTA-SUNAT-BACKEND.md)**.
