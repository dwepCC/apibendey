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
- **client.tipoDoc**: `"1"` DNI, `"6"` RUC, `"4"` Carnet de extranjería, etc. (catálogo SUNAT tipo documento).
- **details[].tipAfeIgv**: `"10"` Gravado, `"20"` Exonerado, `"30"` Inafecto, etc. (catálogo 07).
- **legends[].code**: `"1000"` para el monto en letras.

Si falta **tipoOperacion** o **tipoDoc**, la API responde **400** con `campos_requeridos` y no envía a SUNAT.

---

## Respuesta al enviar a SUNAT

Estructura exacta del JSON que devuelve el backend cuando envías a SUNAT, y qué pasa si SUNAT **acepta**, **rechaza** o **no hay conexión**: ver **[docs/RESPUESTA-SUNAT-BACKEND.md](RESPUESTA-SUNAT-BACKEND.md)**.
