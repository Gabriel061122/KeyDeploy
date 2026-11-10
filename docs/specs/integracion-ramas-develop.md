# Especificación: integración de ramas en `develop`

**Estado:** propuesta pendiente de confirmación.

## Objetivo

Traer todas las ramas `feature/*` de todos los integrantes y fusionarlas en
`develop`, preservando la totalidad de las funcionalidades ya implementadas.
`main` no se modifica. Si una funcionalidad no puede conservarse, se debe
informar en lugar de descartarla silenciosamente.

## Inventario de ramas y su tratamiento

| Rama | Contenido | Tratamiento en `develop` |
|---|---|---|
| `feature/architecture-pricing` | desglose de precios | Ya contenida (ancestro de `architecture-quotes`) |
| `feature/architecture-business-rules` | reglas de negocio + presupuesto | Ya contenida (ancestro de `architecture-quotes`) |
| `feature/architecture-quotes` | precios + reglas + presupuestos | **Fusionar** (avance rápido, ya aplicado) |
| `feature/service-layer-modeled-domains` | capa de servicios (proyectos/requerimientos/arquitecturas) | **Fusionar** (resolución manual, ver abajo) |
| `feature/documentacion-planificacion` | `README.md` + plantilla `.docx` | **Fusionar** (solo documentación) |
| `feature/arquitectura` | `docs/arquitectura.md` (versión antigua) | No aporta contenido nuevo: `develop` ya tiene una versión posterior del mismo documento |
| `feature/aythamireyes`, `feature/catalogo`, `feature/catalogo-Alonso`, `feature/documentacion-analisis-funcional`, `feature/documentacion-entorno` | ya integradas | Ya son ancestros de `develop` |

Las ramas `agent/*-tests` son worktrees intermedios del flujo de agentes, no
ramas `feature` de integrantes; su contenido (tests) ya está incorporado en las
ramas `feature/architecture-*`.

## Conflicto detectado

`ArchitectureController.php` es modificado por dos ramas de forma incompatible:

- `architecture-quotes` inyecta `ArchitectureBusinessRules` y
  `ArchitecturePricingService`, mantiene `review()` + `preview()`, y calcula
  precios/avisos en `check()`, `store()` y `show()`.
- `service-layer-modeled-domains` inyecta `ArchitectureService` (que a su vez
  envuelve `ArchitectureValidator`), elimina `review()` y delega la persistencia
  en `ArchitectureService::createManual()`.

Las ramas `ProjectController`, `RequirementController`, `RequirementFormRequest`
y sus servicios (`ProjectService`, `RequirementService`, `RequirementRules`)
se fusionan sin conflicto.

## Decisión propuesta para resolver el conflicto

Para conservar **ambas** funcionalidades:

1. Mantener el flujo visible de `architecture-quotes` (métodos `review()` /
   `preview()`, cálculo de precios y avisos de presupuesto en `check()`, `store()`
   y `show()`).
2. Conservar la capa de servicios de `service-layer-modeled-domains`: el
   controlador delega el guardado en `ArchitectureService`.
3. Reconciliar `ArchitectureService` para que internamente use
   `ArchitectureBusinessRules` (que ya envuelve `ArchitectureValidator` +
   `ArchitecturePricingService`) en lugar de `ArchitectureValidator` a secas, de
   modo que la validación aplique reglas de negocio, precios y avisos. Esto
   implica ampliar `ArchitectureService::validate()`/`createManual()` para
   recibir el `Project` y devolver el desglose de precios cuando proceda.

Resultado: la capa de servicios sigue existiendo y concentra las reglas
(preservando el contrato de `capa-servicios.md`), y los precios, reglas de
negocio y presupuestos siguen funcionando (preservando el contrato de
`precios-reglas-presupuestos-arquitectura.md`).

## Efectos y límites

- `ProjectService` y `RequirementService` se integran sin cambios de
  comportamiento adicionales.
- La rama `feature/arquitectura` no se fusiona porque su documento ya está
  superado por la versión actual de `docs/arquitectura.md` en `develop`.
- No se añaden migraciones, modelos, rutas ni dependencias nuevas.
- La integración se valida ejecutando la suite de pruebas de
  `proyecto_laravel` (feature tests de arquitectura, precios, reglas,
  presupuestos, proyectos y requerimientos).

## Criterios de aceptación

- `develop` contiene el trabajo de todas las ramas `feature/*`.
- `ArchitectureService` concentra la creación manual de arquitecturas y aplica
  reglas de negocio + precios (no solo validación).
- `ArchitectureController` conserva `review()`/`preview()` y los resultados
  visibles de precios/avisos.
- Las pruebas existentes pertinentes pasan y no se pierde cobertura de
  funcionalidad ya existente.
