---
sessionId: session-261109-154441-ev00
---

# Requisitos

## Overview
Implementar las 4 funcionalidades obligatorias que faltan en KeyDeploy para cumplir UT2:
1. **Carrito persistente en BD** – añadir, editar cantidad, quitar líneas; sobrevive al recargar.
2. **Presupuesto como snapshot** – congelar líneas, nombre, tarifa y config JSON dentro de `DB::transaction()`.
3. **Subida de ficheros** – validar, ver, sustituir, eliminar sin huérfanos.
4. **Revisión de seguridad** – `$fillable`, CSRF, escape HTML, sin `.env` ni secretos.

Además se completarán los **10 tests** exigidos (T01–T10) y se ajustará el esquema BD para incluir `providers`, `pricing_model` y `price_cents` en la tabla pivote.

## User Stories
- Como usuario de desarrollo, quiero añadir productos al carrito, modificar cantidades y quitar elementos, de modo que al recargar la página el carrito se conserve.
- Como usuario, quiero emitir un presupuesto que congele las líneas, tarifas y nombres, de modo que si después cambio una tarifa del catálogo el presupuesto anterior no varíe.
- Como usuario, quiero subir un fichero asociado a un proyecto o arquitectura, verlo, sustituirlo y eliminarlo, sin que queden ficheros huérfanos.
- Como revisor, quiero que todos los formularios tengan CSRF, `$fillable` correcto, escape de salida y que no haya secretos en Git.

## Scope
### In Scope
- Migraciones nuevas: `providers`, `cart_items`, `quotes`, `quote_lines`, `project_files`.
- Actualización de `product_region` → `product_availabilities` con `price_cents` y `pricing_model`.
- Modelos Eloquent: `Provider`, `CartItem`, `Quote`, `QuoteLine`, `ProjectFile`.
- Controladores: `CartItemController`, `QuoteController`, `ProjectFileController`.
- Rutas, vistas Blade y FormRequests.
- Tests T01–T10.
- Seeder de usuario de desarrollo.
- Revisión de seguridad en todos los archivos existentes.

### Out of Scope
- Pagos reales, despliegue de infraestructura, IA conectada.
- Permisos multi-rol avanzados (solo usuario de desarrollo).
- API REST o Sanctum.

# Diseño Técnico

### Implementación Actual
- **Modelos existentes**: User, Project (SoftDeletes), Requirement, Architecture, ArchitectureItem, Product, Region.
- **Pivote product_region**: solo product_id + region_id, sin precios.
- **Carrito**: solo existe como borrador en sesión (`architecture_draft`) en `ArchitectureController`.
- **Presupuesto**: no existe. No hay snapshot ni congelación de tarifas.
- **Ficheros**: no hay funcionalidad de subida.
- **Tests existentes**: ArchitectureTest, ProjectTest, RequirementTest, RequirementDynamicFormTest, AuthTest, RequirementRulesTest.
- **Patrones**: FormRequest + Service + Policy + DB::transaction() + Blade + Tailwind.

### Decisiones Clave
1. **Carrito por usuario en BD** (`cart_items`): tabla simple con `user_id`, `product_id`, `quantity`, `config` JSON. El usuario de desarrollo es el usuario autenticado. No se crea tabla `carts` separada para mantenerlo simple.
2. **Presupuesto con dos tablas** (`quotes` + `quote_lines`): cabecera con proyecto, fecha, totales; líneas con nombre congelado, tarifa congelada, cantidad y config JSON. Separación clara.
3. **Ficheros en Project y Architecture**: tabla `project_files` con `attachable_type` (morph) o dos tablas separadas. Se elige una tabla `project_files` con `project_id` FK y `architecture_id` FK nullable para simplicidad.
4. **Schema BD alineado con plantilla**: añadir `providers` table, renombrar `product_region` → `product_availabilities` con `price_cents` (integer) y `pricing_model` (string enum).
5. **Precios en céntimos** para evitar errores de redondeo (DEC-32).
6. **3 modalidades de tarifa**: `mensual` (recurrente), `unico` (pago único), `por_uso` (variable).

### Modelo de Datos (nuevas tablas)
```
providers
  id (PK)
  name (string, unique, NOT NULL)

product_availabilities (renombrado desde product_region)
  id (PK)
  product_id (FK → products, CASCADE)
  region_id (FK → regions, RESTRICT)
  provider_id (FK → providers, RESTRICT)
  available (boolean, default true)
  price_cents (unsignedInt)
  pricing_model (string: 'mensual'|'unico'|'por_uso')
  unique(product_id, region_id)

cart_items
  id (PK)
  user_id (FK → users, CASCADE)
  product_id (FK → products, RESTRICT)
  quantity (unsignedInt, min 1)
  config (JSON, default {})
  created_at, updated_at
  unique(user_id, product_id)

quotes
  id (PK)
  project_id (FK → projects, RESTRICT)
  user_id (FK → users, RESTRICT)
  status (string: 'emitido')
  subtotal_monthly_cents (unsignedInt)
  subtotal_one_time_cents (unsignedInt)
  total_cents (unsignedInt)
  issued_at (datetime)
  created_at, updated_at

quote_lines
  id (PK)
  quote_id (FK → quotes, CASCADE)
  product_name (string, 150)  -- congelado
  product_type (string, 50)   -- congelado
  pricing_model (string)      -- congelado
  quantity (unsignedInt)
  unit_price_cents (unsignedInt)  -- congelado
  line_total_cents (unsignedInt)
  config (JSON)  -- congelado
  created_at, updated_at

project_files
  id (PK)
  project_id (FK → projects, CASCADE)
  architecture_id (FK → architectures, nullable, CASCADE)
  original_name (string, 255)
  storage_path (string, 500)
  mime_type (string, 100)
  size_bytes (unsignedInt)
  created_at, updated_at
```

### Relaciones Eloquent
- `User::cartItems()` → HasMany(CartItem)
- `CartItem::product()` → BelongsTo(Product)
- `Project::quotes()` → HasMany(Quote)
- `Quote::lines()` → HasMany(QuoteLine)
- `Quote::project()` → BelongsTo(Project)
- `Project::files()` → HasMany(ProjectFile)
- `Architecture::files()` → HasMany(ProjectFile)
- `Product::availabilities()` → belongsToMany(Region)->withPivot(...)
- `Product::provider()` → BelongsTo(Provider) (nuevo)

### Flujo de Emisión de Presupuesto
1. Usuario ve el carrito (`cart_items`) con líneas del proyecto.
2. Valida reglas (mínimo 3 elementos, 2 tipos, productos disponibles en región).
3. `DB::transaction()`:
   - Crear `Quote` (cabecera con totales).
   - Para cada `CartItem`, crear `QuoteLine` con nombre, tipo, tarifa y config congelados.
   - Si todo OK, commit. Si falla, rollback (no quedan datos incompletos).
4. Vaciar `cart_items` del usuario.
5. Redirigir a vista del presupuesto emitido.

### Flujo de Subida de Ficheros
1. Formulario `multipart/form-data` en proyecto o arquitectura.
2. Validación: tipo MIME (pdf, png, jpg, docx, md), tamaño máx 10MB.
3. `Storage::disk('public')->put()` con nombre único.
4. Guardar metadatos en `project_files`.
5. Ver: `Storage::response()` o enlace público.
6. Sustituir: nuevo upload → borrar fichero antiguo → actualizar registro.
7. Eliminar: borrar fichero de Storage + eliminar registro BD.
8. Sin huérfanos: `onDelete('CASCADE')` en FKs; al borrar proyecto/arquitectura se borran los registros, y un job o evento limpia ficheros si queda algo.

# Delivery Steps

### ✓ Step 1: Migraciones y esquema BD actualizado
Crear y ejecutar las migraciones necesarias para alinear el esquema con la plantilla UT2.
- Migración `create_providers_table`: tabla providers con id, name(unique).
- Migración `rename_product_region_to_availabilities`: renombrar product_region → product_availabilities, añadir columns provider_id(FK), available(boolean), price_cents(unsignedInt), pricing_model(string).
- Migración `create_cart_items_table`: user_id(FK CASCADE), product_id(FK RESTRICT), quantity, config(JSON), unique(user_id, product_id).
- Migración `create_quotes_table`: project_id(FK), user_id(FK), status, subtotal_monthly_cents, subtotal_one_time_cents, total_cents, issued_at.
- Migración `create_quote_lines_table`: quote_id(FK CASCADE), product_name, product_type, pricing_model, quantity, unit_price_cents, line_total_cents, config(JSON).
- Migración `create_project_files_table`: project_id(FK CASCADE), architecture_id(FK nullable CASCADE), original_name, storage_path, mime_type, size_bytes.
- Migración `add_provider_id_to_products_table`: FK provider_id en products.
- Ejecutar `migrate:fresh` para verificar que todo compila. **COMPLETADO - 18 migraciones sin errores.**

### ✓ Step 2: Modelos Eloquent y relaciones
Crear los nuevos modelos y actualizar los existentes.
- Modelos nuevos: Provider, CartItem, Quote, QuoteLine, ProjectFile con $fillable seguro, casts y relaciones. **COMPLETADO**
- Actualizar Product::regions() → belongsToMany(Region)->withPivot('available','price_cents','pricing_model'). **COMPLETADO**
- Actualizar Product para incluir provider_id y relación provider(). **COMPLETADO**
- Actualizar User para incluir cartItems() HasMany. **COMPLETADO**
- Actualizar Project para incluir quotes() y files(). **COMPLETADO**
- Actualizar Architecture para incluir files(). **COMPLETADO**
- Factory para cada nuevo modelo. **COMPLETADO**

### ✓ Step 3: Carrito persistente: controlador, rutas y vistas
Implementar el carrito funcional con persistencia en BD.
- CartItemController: index, store, update, destroy. **COMPLETADO**
- Rutas: GET/POST/PATCH/DELETE /carrito. **COMPLETADO**
- StoreCartItemRequest y UpdateCartItemRequest. **COMPLETADO**
- Vista carrito/index.blade.php. **COMPLETADO**
- Enlace al carrito en layout. **COMPLETADO**

### ✓ Step 4: Presupuesto como snapshot: servicio, controlador y vistas
Emitir presupuesto congelando líneas dentro de DB::transaction().
- QuoteService::emitFromCart con DB::transaction(). **COMPLETADO**
- QuoteController: show, emit. **COMPLETADO**
- Rutas: POST /presupuesto/emitir, GET /presupuestos/{quote}. **COMPLETADO**
- Vista quote/show.blade.php. **COMPLETADO**

### ✓ Step 5: Subida de ficheros: controlador, validación y vistas
Implementar subida, visualización, sustitución y eliminación de ficheros.
- ProjectFileController: upload, show, replace, destroy. **COMPLETADO**
- StoreProjectFileRequest con validación MIME y tamaño. **COMPLETADO**
- Rutas de ficheros. **COMPLETADO**
- Sección de ficheros en project/show.blade.php. **COMPLETADO**
- Limpieza compensatoria en catch. **COMPLETADO**

### * Step 6: Seeder de catálogo completo y usuario de desarrollo
Completar seeders para datos de prueba coherentes.
- ProviderSeeder: 2 proveedores (NimbusCloud, Atlantic Hosting).
- Actualizar CatalogDemoSeeder: 5 regiones, 15 productos con 6 tipos, asignados a proveedores y regiones con price_cents y pricing_model.
- DatabaseSeeder: orquestar todo con migrate:fresh --seed.
- UserSeeder: usuario de desarrollo (dev@keydeploy.test / password) con rol cliente.
- Verificar que migrate:fresh --seed funciona y crea los datos mínimos.

### ✓ Step 7: Tests T01–T10 y revisión de seguridad
Completar la batería de tests y revisar seguridad en todo el código.
- T01: Test precio modalidad mensual (Unit, PriceCalculationTest).
- T02: Test precio modalidad único con límite (Unit).
- T03: Test regla bloqueante (Feature, regla que impide emitir presupuesto).
- T04: Test regla de aviso (Feature, permite continuar).
- T05: Test alta válida que persiste (Feature, CartItem o Quote).
- T06: Test alta inválida que NO persiste (Feature).
- T07: Test CRUD o relación Eloquent con datos reales (Feature).
- T08: Test Storage::fake + UploadedFile::fake (Feature, subida/sustitución/rechazo).
- T09: Test presupuesto histórico inmutable (Feature: emitir, cambiar tarifa, verificar quote_lines sin cambios).
- T10: Test transacción con fallo controlado (Feature: forzar excepción, verificar rollback).
- Revisión seguridad: verificar $fillable en todos los modelos (user_id nunca fillable), @csrf en todos los formularios, escape de salida HTML en Blade, .gitignore correcto (sin .env), sin secretos en código.