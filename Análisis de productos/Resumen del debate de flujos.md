# Resumen del debate de flujos

Documento de trabajo. Resume las decisiones tomadas al debatir los flujos, para revisión y comentarios. No sustituye todavía a `Actores y flujos.md`; es la base para reescribirlo.

Categorías: **Confirmado**, **Propuesta**, **Supuesto**, **Pregunta abierta**.

## 1. Alcance del sistema

- La aplicación **recomienda** arquitectura y presupuesto con IA. *(Confirmado)*
- Además **opera infraestructura propia**, pero **no despliega la aplicación del cliente**: despliega su infraestructura (contenedores de soporte) y da apoyo técnico para que el cliente suba su app. *(Confirmado)*
- La IA **recomienda**; el **sistema decide**. La validación y las habilitaciones no se confían a la IA. *(Propuesta)*

## 2. Actores

| Actor | Tipo | Cambio |
|---|---|---|
| Cliente | Usuario | sin cambios |
| Administrador de catálogo | Usuario interno | sin cambios |
| Equipo técnico/soporte | Usuario interno | gana peso: participa en el alta, no solo en incidencias |
| Proveedor de IA | Sistema externo | sin cambios |
| Proveedor de pagos | Sistema externo | sin cambios |
| **Proveedor de notificaciones/correo** | Sistema externo | **nuevo** (por el canal de correo) |
| Plataforma de infraestructura | ~~Sistema externo~~ | **pasa a componente interno** (host Docker propio) |

La aplicación sigue siendo el sistema bajo diseño, no un actor.

## 3. Flujo principal — solicitar una propuesta

Se pasa de **síncrono** a **asíncrono** y se añade **consentimiento del coste**.

Estados de `PeticiónCliente`:
`borrador` → `validada` → (`pendiente_de_creditos`) → `créditos_reservados` → `procesando` → `completada` / `fallida`

1. Cliente crea requisitos → `borrador`.
2. **Solicitar propuesta** (comando idempotente). El sistema valida datos y `tecnologías ⊆ catálogo`.
   - inválida → devuelve errores, **no toca créditos**.
   - válida → `validada`.
3. El sistema calcula el coste con `ModeloIA` + `Tarifa` y **lo muestra al cliente**.
4. Cliente **confirma el gasto**. *(Propuesta: hoy falta)*
5. Comprueba saldo:
   - insuficiente → `pendiente_de_creditos`, ofrece paquetes.
   - suficiente → **reserva atómica** (`MovimientoCrédito` tipo `reserva`, cubo `reservado`).
6. `procesando`: trabajo asíncrono encolado.
7. Worker llama a la IA con prompt + esquema. Timeout/reintento con backoff.
8. Respuesta:
   - válida → guarda `Propuesta`, **consume** la reserva, `completada`, notifica.
   - inválida o fallo técnico → **libera** la reserva, `fallida` con motivo, permite reintento. Notifica.
9. Cliente ve propuesta: arquitectura, presupuesto y `recomendaciones` (soporte/despliegue) **separadas** de las decisiones.

Decisiones asociadas:

- **Coste:** tarifa fija por tramo (modelo + complejidad). Precio exacto antes de gastar; sin liquidación ni reembolsos parciales. *(Confirmado)*
- **Fallo:** no se cobra; la reserva se libera. *(Confirmado)*
- **Reintento técnico:** misma petición, gratis. *(Confirmado)*
- **Regeneración** (el cliente quiere otra): **petición nueva, se cobra de nuevo**. *(Confirmado)*
- **Notificación:** en la app **y** por correo. *(Confirmado)*
- **Sesgo del prompt:** la IA puede recomendar servicios; el sistema valida y decide. Falta política de transparencia. *(Pregunta abierta)*

## 4. Créditos y movimientos

Libro mayor **inmutable** (`MovimientoCrédito`); el saldo es la suma de movimientos, nunca un campo sobrescrito. Dos cubos:

- `disponible` — gastable.
- `reservado` — comprometido por una petición en curso.

Transiciones:

| Movimiento | Efecto |
|---|---|
| `compra` | + disponible |
| `reserva` | disponible → reservado |
| `consumo` | reservado → gastado |
| `liberación` | reservado → disponible |
| `ajuste` | ± disponible (admin, con motivo) |
| `reembolso` | + disponible (si aplica) |

- Reservar exige `disponible ≥ coste`, atómico. *(Propuesta)*
- `consumo` **exactamente una vez** por petición. *(Regla)*
- Los créditos **no caducan**. *(Confirmado)*
- **Ajustes manuales** permitidos, con motivo y auditoría. *(Confirmado)*
- Un solo paquete (200 créditos) es **supuesto**, no restricción.

## 5. Contratación de despliegue

- Host Docker **propio**; orquestación interna. *(Confirmado)*
- Se aprovisiona **infraestructura** (stack multi-contenedor: BD, servidor, runtime, volúmenes, red/TLS, cuotas CPU/GPU/RAM/disco). *(Propuesta)*
- **Habilitación:** `deploy_habilitado = tecnologías(propuesta) ⊆ tecnologías_soportadas`. Lo decide el sistema. `deploy_recomendado` (IA) y `deploy_habilitado` (catálogo) son hechos distintos. *(Confirmado)*
- **Apoyo técnico para subir la app del cliente:** incluido en la suscripción de despliegue y **rastreado como ticket**. *(Confirmado)*
- **Fin del apoyo inicial = aceptación explícita del cliente.** Estado intermedio `pendiente_de_aceptación` tras `activa`. *(Confirmado)*
- **Al terminar la suscripción:** ventana de gracia → exportación de datos → destruir infraestructura. *(Confirmado)*

Estados del despliegue (más amplios que los actuales):
`pendiente_de_aprobación` → `aprobada` → `aprovisionando` → `activa` → (`pendiente_de_aceptación`) → `activo` / `fallido` → `suspendido` / `detenido` / `destruido`.

## 6. Mantenimiento y tickets

Entidad única `SolicitudApoyo`:

- Campos: `origen` (`despliegue_inicial` | `mantenimiento`), `suscripción`, `cliente`, `tecnología`, `prioridad`, descripción, adjuntos, `responsable`, marcas de tiempo.
- Estados: `abierta` → `en_progreso` → (`esperando_cliente`) → `resuelta` → `cerrada`; con `reabierta`.
- **Invariante de cobertura:** `tecnología ∈ catálogo` **y** `suscripción` activa. *(Regla)*
- **SLA por prioridad** (alta/media/baja): primera respuesta y resolución. *(Confirmado)*
- Mantenimiento **independiente** del despliegue (sirve para infra propia del cliente). *(Confirmado)*
- Cada cambio de estado notifica al cliente (correo). *(Propuesta)*

## 7. Facturación, renovación y cancelación

- **Ambas suscripciones**: anuales con **12 cuotas mensuales**. *(Confirmado)*
- Aparece `Cuota`/`Factura` con estado: `pendiente` → `pagada` / `fallida`. *(Propuesta)*
- **Fallo de pago:** gracia → **suspensión** (no se destruyen datos). *(Confirmado)*
- **Renovación:** automática al año, con preaviso para no renovar. *(Confirmado)*
- **Cancelación:** dentro de **15 días** → libre con **reembolso** (`SolicitudReembolso`). Después → **indemnización = 10% × días restantes × (precio anual / 365)**. *(Confirmado)*
- **Fin de suscripción:** gracia → exportación → destruir (ver §5).

Estados de `Suscripción`:
`pendiente_de_pago` → `activa` → (`en_mora` → `suspendida`) → `cancelación_solicitada` → `cancelada` / `vencida`.

## 8. Notificaciones

- Actor externo nuevo: **proveedor de notificaciones/correo**.
- Entidad `Notificacion`: destinatario, canal, evento, estado, idempotencia (evitar dobles envíos).
- Eventos: propuesta lista, pago confirmado, cuota fallida, cancelación, ticket actualizado/resuelto.

## 9. Entidades nuevas o modificadas

- `MovimientoCrédito` — libro mayor (tipo, cantidad, cubo, referencia).
- `Notificacion` — evento, canal, destinatario, estado.
- `SolicitudApoyo` — ticket unificado.
- `Cuota` / `Factura` — cuota recurrente con estado.
- `OrdenDespliegue` / `RecursoAprovisionado` — si se modela el aprovisionamiento explícito.
- `Suscripción` — añadir `tipo` (mantenimiento / despliegue).
- `Propuesta` — sin versionado: cada regeneración es una `PeticiónCliente` nueva.

## 10. Preguntas abiertas

1. Onboarding y administración de catálogo: flujo aún no debatido.
2. Tramos/tarifas concretas y precio del paquete (200 es supuesto).
3. Días exactos de preaviso de renovación.
4. Días de gracia para mora y para destrucción de infraestructura.
5. SLA concretos por prioridad.
6. Qué ocurre si el cliente **no** acepta el despliegue.
7. Política de transparencia sobre el sesgo del prompt.
8. Reembolso exacto en los 15 días (¿incluye cuotas ya cobradas?).

## 11. Siguiente paso

Reescribir `Actores y flujos.md` con estas decisiones y actualizar `Lista Entidades.md`, dejando las preguntas abiertas de §10 como pendientes explícitos.
