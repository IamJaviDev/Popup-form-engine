# Popup Form Engine

Plugin WordPress para mostrar formularios y popups de descarga de PDF con integración de newsletter.

Versión: 2.0 · Requiere PHP 8.1+ · WordPress 6.0+

---

## Dos flujos

### 1. Flujo PDF

El usuario hace clic en un enlace PDF marcado con la clase `pdf-popup` o que contenga una imagen `<img>`:

```html
<a href="/documentos/mi-guia.pdf" class="pdf-popup">Descargar guía</a>
```

El popup pide nombre, email, país y teléfono. Tras el envío, el plugin:

1. Selecciona la plantilla de email por el **slug de la página** (modo PAGE_SLUG_TEMPLATE).
2. Envía el email con la guía al usuario.
3. Si el usuario consiente y la newsletter PDF está activa, suscribe al usuario.
4. Registra el log.

**Modo PAGE_SLUG_TEMPLATE (legado):** La plantilla se resuelve por el slug de la página donde está el PDF, no por el PDF clicado. Esto reproduce exactamente el comportamiento del plugin original `Tota-form-detecterv2`. Los mappings se configuran en Admin → PDF / Templates.

### 2. Flujo genérico (visual / HTML)

Un botón o enlace con la clase `form-popup-trigger` y `data-form-slug="SLUG"` abre un popup con el formulario configurado en Admin → Formularios:

```html
<a href="#pfe-form:mi-formulario" class="form-popup-trigger">Contactar</a>
<!-- o bien: -->
<button class="form-popup-trigger" data-form-slug="mi-formulario">Contactar</button>
```

El formulario puede ser:

- **Modo visual:** campos declarados en el admin (tipo, nombre, label, placeholder, required). Siempre se require un campo `email`.
- **Modo HTML:** HTML libre pegado en el admin. El plugin recoge todos los campos enviados excepto los reservados internos. Requiere que el HTML incluya un campo `name="email"`.

#### Newsletter genérica

Para que se procese la suscripción, **deben estar activos** tanto el toggle de newsletter del formulario concreto como el toggle global (Admin → Newsletter).

El campo de consentimiento es `pfe_newsletter_consent` con valor `1`.

---

## Admin → Newsletter (endpoint)

Solo se configura el **host** del servidor de newsletter. El plugin construye siempre la URL como `https://{host}/newsletter/subscribe`.

| Campo | Descripción |
|---|---|
| Host | Dominio del servidor de newsletter (ej. `nl.ejemplo.com`) |
| Timeout | Segundos máximos de espera |

**Retro-compat:** instalaciones que guardaron el endpoint como URL completa bajo la clave `endpoint` siguen funcionando sin migración.

---

## Seguridad

- **Honeypot:** campo oculto `_pfe_hp`; si viene relleno, la petición se descarta y se loga.
- **Time trap:** campo `_pfe_ts` con timestamp; si el formulario se envía en menos de 2 segundos, se descarta.
- **Rate limit:** configurable por IP/hora en Admin → General (default: 5 envíos/hora).
- **Nonce REST:** los endpoints de submit requieren que la petición venga del mismo origen (`isValidOrigin`). El endpoint `/form-config` es público de solo lectura.

---

## Known limitations

### PDF (PAGE_SLUG_TEMPLATE)

- Las plantillas de email legacy contienen **enlaces PDF hardcodeados** y branding RO-DES. No son multisite-friendly.
- La plantilla se selecciona por el slug de la **página** donde está el enlace PDF, no por el PDF en sí.
- El modo CLICKED_PDF_TEMPLATE (resolución por URL del PDF) está documentado en el código pero no implementado.

### Newsletter

- Si el servidor de newsletter no está disponible, el log registra el error pero el formulario devuelve éxito (el email ya fue enviado).
- El plugin no reintenta envíos fallidos de newsletter.

### General

- Los endpoints REST no usan nonce de autenticación WP para usuarios anónimos (incompatible con formularios públicos). La seguridad se basa en honeypot, time trap y rate limit.
- El plugin no gestiona RGPD ni almacenamiento de datos personales más allá de los logs internos.

---

## Logs

Los logs se almacenan en `{prefix}_popup_form_engine_logs` con los campos:

| Campo | Descripción |
|---|---|
| `flow_type` | `pdf`, `generic`, `honeypot`, `invalid` |
| `form_identifier` | Slug del formulario |
| `email` | Email del usuario |
| `payload_json` | Campos enviados (sanitizados) |
| `consent_status` | `no_aplica`, `true`, `false` |
| `newsletter_sent` | `0` / `1` |
| `newsletter_response` | Respuesta del servidor de newsletter |
| `email_sent` | `0` / `1` |
| `status` | `ok`, `error`, `honeypot`, `invalid`, `invalid_email` |
| `error_message` | Detalle del error si aplica |

Desde Admin → Logs puedes filtrar, exportar CSV y limpiar registros por antigüedad.

---

## Instalación

1. Copiar `popup-form-engine/` a `/wp-content/plugins/`.
2. Activar el plugin desde WordPress.
3. La tabla de logs y las opciones con valores por defecto se crean automáticamente.
4. Configurar en Admin → Popup Forms → General (remitente, rate limit, emails internos).
5. Configurar en Admin → Newsletter el endpoint de suscripción.
6. Crear formularios en Admin → Formularios.
7. Añadir triggers en el editor de páginas.
