# Supabase multi-tenant backend

Arquitectura inicial para conectar el frontend React/Vite con Supabase como backend real de un SaaS de nutricion multi-tenant.

## Archivos

- `migrations/202605300001_multitenant_schema.sql`: tipos, tablas, helpers, triggers, indices, vistas limitadas y buckets de Storage.
- `migrations/202605300002_rls_policies.sql`: politicas RLS por rol y politicas de Storage.
- `migrations/202605300005_billing_foundation.sql`: planes comerciales, uso real por RPC, eventos de billing e indices para Stripe/proveedor local.
- `seed.sql`: datos demo para local/staging. No usar en produccion salvo que quieras crear usuarios demo.

## Modelo de seguridad

- Una sola base PostgreSQL.
- `tenants` es el master de clinicas/empresas.
- `tenant_members` relaciona usuario, tenant y rol.
- Las tablas operativas tienen `tenant_id`.
- El frontend nunca decide seguridad con `X-Empresa-ID`; RLS usa `auth.uid()` y membresias activas.
- La `service_role` key queda reservada para Edge Functions, webhooks, jobs internos y scripts controlados. Nunca debe estar en Vite ni en variables `VITE_*`.
- `platform_admin` vive en `profiles.platform_role`; los roles de tenant viven en `tenant_members.role`.

## Helpers RLS

- `is_platform_admin()`: valida administracion global de plataforma.
- `is_tenant_member(p_tenant_id uuid)`: valida membresia activa en un tenant.
- `has_tenant_role(p_tenant_id uuid, p_roles text[])`: valida rol activo dentro de un tenant.
- `is_assigned_nutritionist(...)`, `is_patient_self(...)` y `can_read_patient_record(...)`: helpers adicionales para pacientes, portal y registros clinicos.

## Tablas

- `profiles`: perfil extendido de `auth.users`. No guarda passwords ni claves.
- `tenants`: clinicas/empresas del SaaS.
- `tenant_members`: miembros activos, invitados o suspendidos por tenant.
- `tenant_invitations`: invitaciones con `token_hash`; el token plano debe generarse y enviarse desde Edge Functions.
- `plans`: catalogo global de planes comerciales.
- `subscriptions`: suscripcion actual de cada tenant.
- `subscription_usage`: contadores de uso por periodo.
- `billing_events`: inbox neutral para webhooks/eventos de Stripe, proveedor local o ajustes manuales.
- `patients`: datos basicos del paciente y nutricionista asignado.
- `patient_profiles`: informacion clinica ampliada del paciente.
- `appointments`: agenda y consultas.
- `clinical_notes`: notas clinicas, con visibilidad interna o portal.
- `diets`: planes alimentarios.
- `workout_routines`: rutinas de entrenamiento.
- `measurements`: mediciones corporales.
- `food_recalls`: recordatorios/registros alimentarios.
- `ai_generations`: auditoria funcional de generaciones IA; la llamada sensible debe ocurrir en Edge Functions.
- `audit_logs`: trazabilidad por tenant.

## Vistas limitadas

- `patient_directory`: lectura limitada de pacientes para `viewer`, asistentes, staff clinico y portal cuando corresponde.
- `appointment_calendar`: lectura limitada de agenda sin exponer notas internas.

Las vistas existen porque RLS restringe filas, no columnas. Para datos sensibles, el frontend deberia preferir vistas/RPCs especificas en vez de leer tablas completas.

## Planes y billing

- `plans` define features y limites en JSONB (`patients`, `seats`, `ai_generations`, `storage_mb`).
- `subscriptions` guarda la suscripcion activa por tenant y campos neutrales de proveedor (`provider`, `provider_customer_id`, `provider_subscription_id`).
- `subscription_usage` queda disponible para snapshots por periodo.
- `get_tenant_entitlements(p_tenant_id)` devuelve solo features, limites y uso agregado para miembros activos del tenant. Esta RPC permite bloquear UI sin exponer datos sensibles de facturacion.
- `billing_events` prepara integracion futura con Stripe o proveedor local desde Edge Functions/webhooks. El frontend no debe escribir cobros directo.

## Fuentes nutricionales

Migracion:

```bash
npx supabase db query --linked --file supabase/migrations/202605300006_nutrition_multi_source.sql
```

Importar INCAP:

```bash
node scripts/build-nutrition-source-import-sql.mjs incap
npx supabase db query --linked --file supabase/sql/20260530_import_incap_foods.generated.sql
npx supabase db query --linked --file supabase/sql/20260530_validate_nutrition_sources.sql -o json
```

El importador usa un mapa por fuente y genera SQL idempotente con `on conflict`. Para agregar otra fuente, crea una entrada en `sourceConfigs` con `csvPath`, metadatos y mapeo de columnas hacia `foods`. Los campos vacios o `NaN` se generan como `NULL`, y la fila original se conserva en `foods.raw_data`.

Tablas:

- `nutrition_sources`: catalogo global de fuentes (`incap`, `usfq`).
- `food_categories`: categorias por fuente.
- `foods`: alimentos normalizados por fuente, con `raw_data` para trazabilidad.
- `tenant_nutrition_settings`: preferencia por tenant; se crea cuando existe el esquema multi-tenant (`tenants`).

El selector del frontend lee `tenant_nutrition_settings`; si aun no existe, usa una configuracion fallback de solo lectura con la fuente activa disponible. Al guardar una dieta via Edge Function se registran `nutrition_source_id` y `nutrition_source_code` cuando las columnas existen.

## Storage

- Bucket privado `tenant-documents`: ruta esperada `{tenant_id}/...` o `{tenant_id}/patients/{patient_id}/...`.
- Bucket publico `profile-avatars`: ruta esperada `{user_id}/archivo`.
- Staff del tenant puede subir documentos; owner/admin pueden borrar.
- Pacientes solo pueden leer archivos ubicados bajo su ruta de paciente.

## Aplicar en Supabase

1. Instala o usa Supabase CLI:

```bash
npx supabase --version
```

2. Inicia sesion y enlaza el proyecto remoto:

```bash
npx supabase login
npx supabase link --project-ref pzxylqaeevzchceewjrs
```

3. Aplica migraciones:

```bash
npx supabase db push
```

4. Opcional para local/staging: aplicar tambien la semilla demo.

```bash
npx supabase db push --include-seed
```

Para desarrollo local, `supabase/seed.sql` tambien se ejecuta con `supabase db reset`.

## Siguiente fase recomendada

- Crear Edge Functions para `create-tenant`, `invite-member`, `accept-invitation`, `billing-webhook` y `generate-ai-plan`.
- Crear variables frontend solo con `VITE_SUPABASE_URL` y `VITE_SUPABASE_ANON_KEY`.
- Generar tipos de base de datos para el cliente y adaptar `src/services/api.js` sin cambiar el diseno.
- Rotar claves si fueron compartidas en un canal no seguro.

## Edge Functions IA

Funciones creadas:

- `generate-diet`: genera un plan alimentario, registra auditoria en `ai_generations` y guarda borrador en `diets`.
- `generate-workout`: genera rutina de entrenamiento, registra auditoria en `ai_generations` y guarda borrador en `workout_routines`.

Secrets requeridos:

```bash
npx supabase secrets set GITHUB_MODELS_TOKEN="tu_token" --project-ref pzxylqaeevzchceewjrs
npx supabase secrets set GITHUB_MODELS_MODEL="openai/gpt-4.1-mini" --project-ref pzxylqaeevzchceewjrs
```

Deploy:

```bash
npx supabase functions deploy generate-diet --project-ref pzxylqaeevzchceewjrs
npx supabase functions deploy generate-workout --project-ref pzxylqaeevzchceewjrs
```

Payload esperado:

```json
{
  "tenant_id": "uuid",
  "patient_id": "uuid",
  "title": "Plan opcional",
  "starts_on": "2026-05-30",
  "ends_on": "2026-06-13",
  "context": {},
  "preferences": {}
}
```

Las funciones validan JWT, membresia activa, rol (`owner`, `admin`, `nutritionist`), paciente dentro del tenant y limite `plans.limits.ai_generations`.
