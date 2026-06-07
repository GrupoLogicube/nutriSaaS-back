-- Demo seed for local/staging environments.
-- Do not apply this file to production unless you intentionally want demo users/data.

insert into auth.users (
  instance_id,
  id,
  aud,
  role,
  email,
  encrypted_password,
  email_confirmed_at,
  raw_app_meta_data,
  raw_user_meta_data,
  created_at,
  updated_at
)
values
  (
    '00000000-0000-0000-0000-000000000000',
    '00000000-0000-4000-8000-000000000100',
    'authenticated',
    'authenticated',
    'platform.demo@nutrisaas.local',
    crypt('Demo12345!', gen_salt('bf')),
    now(),
    '{"provider": "email", "providers": ["email"]}'::jsonb,
    '{"full_name": "Admin Plataforma"}'::jsonb,
    now(),
    now()
  ),
  (
    '00000000-0000-0000-0000-000000000000',
    '00000000-0000-4000-8000-000000000101',
    'authenticated',
    'authenticated',
    'owner.demo@nutrisaas.local',
    crypt('Demo12345!', gen_salt('bf')),
    now(),
    '{"provider": "email", "providers": ["email"]}'::jsonb,
    '{"full_name": "Dra. Valeria Owner"}'::jsonb,
    now(),
    now()
  ),
  (
    '00000000-0000-0000-0000-000000000000',
    '00000000-0000-4000-8000-000000000102',
    'authenticated',
    'authenticated',
    'nutri.demo@nutrisaas.local',
    crypt('Demo12345!', gen_salt('bf')),
    now(),
    '{"provider": "email", "providers": ["email"]}'::jsonb,
    '{"full_name": "Nta. Camila Rojas"}'::jsonb,
    now(),
    now()
  ),
  (
    '00000000-0000-0000-0000-000000000000',
    '00000000-0000-4000-8000-000000000103',
    'authenticated',
    'authenticated',
    'paciente.demo@nutrisaas.local',
    crypt('Demo12345!', gen_salt('bf')),
    now(),
    '{"provider": "email", "providers": ["email"]}'::jsonb,
    '{"full_name": "Mateo Paciente"}'::jsonb,
    now(),
    now()
  )
on conflict (id) do update
set
  email = excluded.email,
  raw_app_meta_data = excluded.raw_app_meta_data,
  raw_user_meta_data = excluded.raw_user_meta_data,
  updated_at = now();

insert into auth.identities (
  user_id,
  provider_id,
  identity_data,
  provider,
  last_sign_in_at,
  created_at,
  updated_at
)
select
  seeded_users.id,
  seeded_users.id::text,
  jsonb_build_object(
    'sub', seeded_users.id::text,
    'email', seeded_users.email,
    'email_verified', true,
    'phone_verified', false
  ),
  'email',
  now(),
  now(),
  now()
from (
  values
    ('00000000-0000-4000-8000-000000000100'::uuid, 'platform.demo@nutrisaas.local'),
    ('00000000-0000-4000-8000-000000000101'::uuid, 'owner.demo@nutrisaas.local'),
    ('00000000-0000-4000-8000-000000000102'::uuid, 'nutri.demo@nutrisaas.local'),
    ('00000000-0000-4000-8000-000000000103'::uuid, 'paciente.demo@nutrisaas.local')
) as seeded_users(id, email)
on conflict (provider, provider_id) do nothing;

insert into public.profiles (id, email, full_name, platform_role)
values
  (
    '00000000-0000-4000-8000-000000000100',
    'platform.demo@nutrisaas.local',
    'Admin Plataforma',
    'platform_admin'
  ),
  (
    '00000000-0000-4000-8000-000000000101',
    'owner.demo@nutrisaas.local',
    'Dra. Valeria Owner',
    null
  ),
  (
    '00000000-0000-4000-8000-000000000102',
    'nutri.demo@nutrisaas.local',
    'Nta. Camila Rojas',
    null
  ),
  (
    '00000000-0000-4000-8000-000000000103',
    'paciente.demo@nutrisaas.local',
    'Mateo Paciente',
    null
  )
on conflict (id) do update
set
  email = excluded.email,
  full_name = excluded.full_name,
  platform_role = excluded.platform_role,
  updated_at = now();

insert into public.tenants (
  id,
  name,
  slug,
  legal_name,
  billing_email,
  status,
  settings,
  created_by
)
values (
  '10000000-0000-4000-8000-000000000001',
  'Clinica Nutricion Demo',
  'clinica-nutricion-demo',
  'Clinica Nutricion Demo S.A.',
  'owner.demo@nutrisaas.local',
  'trial',
  '{"timezone": "America/Guayaquil", "default_locale": "es"}'::jsonb,
  '00000000-0000-4000-8000-000000000100'
)
on conflict (id) do update
set
  name = excluded.name,
  slug = excluded.slug,
  legal_name = excluded.legal_name,
  billing_email = excluded.billing_email,
  status = excluded.status,
  settings = excluded.settings,
  updated_at = now();

insert into public.tenant_members (tenant_id, user_id, role, status, invited_by, joined_at)
values
  (
    '10000000-0000-4000-8000-000000000001',
    '00000000-0000-4000-8000-000000000101',
    'owner',
    'active',
    '00000000-0000-4000-8000-000000000100',
    now()
  ),
  (
    '10000000-0000-4000-8000-000000000001',
    '00000000-0000-4000-8000-000000000102',
    'nutritionist',
    'active',
    '00000000-0000-4000-8000-000000000101',
    now()
  ),
  (
    '10000000-0000-4000-8000-000000000001',
    '00000000-0000-4000-8000-000000000103',
    'patient',
    'active',
    '00000000-0000-4000-8000-000000000101',
    now()
  )
on conflict (tenant_id, user_id) do update
set
  role = excluded.role,
  status = excluded.status,
  invited_by = excluded.invited_by,
  joined_at = excluded.joined_at,
  updated_at = now();

insert into public.plans (
  id,
  code,
  name,
  description,
  price_cents,
  currency,
  billing_interval,
  limits,
  features,
  is_active
)
values (
  '20000000-0000-4000-8000-000000000001',
  'demo-growth',
  'Demo Growth',
  'Plan demo para clinicas pequenas de nutricion.',
  4900,
  'USD',
  'month',
  '{"seats": 5, "patients": 200, "ai_generations": 500, "storage_mb": 2048}'::jsonb,
  '{"appointments": true, "clinical_notes": true, "ai_diets": true, "workout_routines": true}'::jsonb,
  true
)
on conflict (code) do update
set
  name = excluded.name,
  description = excluded.description,
  price_cents = excluded.price_cents,
  currency = excluded.currency,
  billing_interval = excluded.billing_interval,
  limits = excluded.limits,
  features = excluded.features,
  is_active = excluded.is_active,
  updated_at = now();

insert into public.subscriptions (
  id,
  tenant_id,
  plan_id,
  status,
  current_period_start,
  current_period_end,
  trial_ends_at
)
values (
  '20000000-0000-4000-8000-000000000101',
  '10000000-0000-4000-8000-000000000001',
  '20000000-0000-4000-8000-000000000001',
  'trialing',
  now(),
  now() + interval '30 days',
  now() + interval '14 days'
)
on conflict (id) do update
set
  status = excluded.status,
  current_period_start = excluded.current_period_start,
  current_period_end = excluded.current_period_end,
  trial_ends_at = excluded.trial_ends_at,
  updated_at = now();

insert into public.subscription_usage (
  tenant_id,
  subscription_id,
  period_start,
  period_end,
  patients_count,
  seats_count,
  ai_generations_count,
  storage_mb
)
values (
  '10000000-0000-4000-8000-000000000001',
  '20000000-0000-4000-8000-000000000101',
  current_date,
  current_date + 30,
  3,
  3,
  2,
  12.5
)
on conflict do nothing;

insert into public.patients (
  id,
  tenant_id,
  user_id,
  assigned_nutritionist_id,
  first_name,
  last_name,
  email,
  phone,
  birth_date,
  gender,
  status,
  tags,
  created_by
)
values
  (
    '30000000-0000-4000-8000-000000000001',
    '10000000-0000-4000-8000-000000000001',
    '00000000-0000-4000-8000-000000000103',
    '00000000-0000-4000-8000-000000000102',
    'Mateo',
    'Paciente',
    'paciente.demo@nutrisaas.local',
    '+593999000001',
    '1992-04-15',
    'male',
    'active',
    array['portal', 'demo'],
    '00000000-0000-4000-8000-000000000101'
  ),
  (
    '30000000-0000-4000-8000-000000000002',
    '10000000-0000-4000-8000-000000000001',
    null,
    '00000000-0000-4000-8000-000000000102',
    'Lucia',
    'Andrade',
    'lucia.andrade@example.test',
    '+593999000002',
    '1987-09-21',
    'female',
    'active',
    array['hipertension'],
    '00000000-0000-4000-8000-000000000102'
  ),
  (
    '30000000-0000-4000-8000-000000000003',
    '10000000-0000-4000-8000-000000000001',
    null,
    '00000000-0000-4000-8000-000000000102',
    'Diego',
    'Mora',
    'diego.mora@example.test',
    '+593999000003',
    '1979-12-03',
    'male',
    'active',
    array['deporte'],
    '00000000-0000-4000-8000-000000000102'
  )
on conflict (id) do update
set
  user_id = excluded.user_id,
  assigned_nutritionist_id = excluded.assigned_nutritionist_id,
  first_name = excluded.first_name,
  last_name = excluded.last_name,
  email = excluded.email,
  phone = excluded.phone,
  birth_date = excluded.birth_date,
  gender = excluded.gender,
  status = excluded.status,
  tags = excluded.tags,
  updated_at = now();

insert into public.patient_profiles (
  tenant_id,
  patient_id,
  height_cm,
  target_weight_kg,
  activity_level,
  goals,
  allergies,
  medical_conditions,
  dietary_preferences,
  clinical_summary
)
values
  (
    '10000000-0000-4000-8000-000000000001',
    '30000000-0000-4000-8000-000000000001',
    178,
    78,
    'moderate',
    array['mejorar composicion corporal', 'energia estable'],
    array['mani'],
    array['ninguna relevante'],
    array['alto en proteina'],
    'Paciente demo con acceso a portal.'
  ),
  (
    '10000000-0000-4000-8000-000000000001',
    '30000000-0000-4000-8000-000000000002',
    164,
    62,
    'light',
    array['control de presion arterial'],
    array[]::text[],
    array['hipertension'],
    array['bajo sodio'],
    'Seguimiento mensual recomendado.'
  )
on conflict (tenant_id, patient_id) do update
set
  height_cm = excluded.height_cm,
  target_weight_kg = excluded.target_weight_kg,
  activity_level = excluded.activity_level,
  goals = excluded.goals,
  allergies = excluded.allergies,
  medical_conditions = excluded.medical_conditions,
  dietary_preferences = excluded.dietary_preferences,
  clinical_summary = excluded.clinical_summary,
  updated_at = now();

insert into public.appointments (
  id,
  tenant_id,
  patient_id,
  nutritionist_id,
  title,
  starts_at,
  ends_at,
  status,
  location,
  notes,
  created_by
)
values
  (
    '40000000-0000-4000-8000-000000000001',
    '10000000-0000-4000-8000-000000000001',
    '30000000-0000-4000-8000-000000000001',
    '00000000-0000-4000-8000-000000000102',
    'Consulta inicial',
    now() + interval '1 day',
    now() + interval '1 day 45 minutes',
    'confirmed',
    'Online',
    'Revisar objetivos y habitos actuales.',
    '00000000-0000-4000-8000-000000000102'
  ),
  (
    '40000000-0000-4000-8000-000000000002',
    '10000000-0000-4000-8000-000000000001',
    '30000000-0000-4000-8000-000000000002',
    '00000000-0000-4000-8000-000000000102',
    'Control nutricional',
    now() + interval '3 days',
    now() + interval '3 days 30 minutes',
    'scheduled',
    'Consultorio',
    'Control de peso y adherencia.',
    '00000000-0000-4000-8000-000000000102'
  )
on conflict (id) do update
set
  starts_at = excluded.starts_at,
  ends_at = excluded.ends_at,
  status = excluded.status,
  notes = excluded.notes,
  updated_at = now();

insert into public.clinical_notes (
  id,
  tenant_id,
  patient_id,
  author_id,
  appointment_id,
  title,
  body,
  visibility
)
values
  (
    '50000000-0000-4000-8000-000000000001',
    '10000000-0000-4000-8000-000000000001',
    '30000000-0000-4000-8000-000000000001',
    '00000000-0000-4000-8000-000000000102',
    '40000000-0000-4000-8000-000000000001',
    'Nota inicial',
    'Paciente busca mejorar composicion corporal sin restricciones medicas relevantes. Se inicia plan gradual.',
    'team'
  ),
  (
    '50000000-0000-4000-8000-000000000002',
    '10000000-0000-4000-8000-000000000001',
    '30000000-0000-4000-8000-000000000001',
    '00000000-0000-4000-8000-000000000102',
    null,
    'Resumen para portal',
    'Mantener hidratacion, registrar comidas y completar mediciones semanales.',
    'patient_portal'
  )
on conflict (id) do update
set
  body = excluded.body,
  visibility = excluded.visibility,
  updated_at = now();

insert into public.measurements (
  tenant_id,
  patient_id,
  recorded_by,
  recorded_at,
  weight_kg,
  body_fat_percentage,
  waist_cm,
  metrics,
  notes
)
values
  (
    '10000000-0000-4000-8000-000000000001',
    '30000000-0000-4000-8000-000000000001',
    '00000000-0000-4000-8000-000000000102',
    now() - interval '7 days',
    84.2,
    23.5,
    91,
    '{"muscle_mass_kg": 39.4}'::jsonb,
    'Medicion inicial demo.'
  )
on conflict do nothing;

insert into public.diets (
  tenant_id,
  patient_id,
  nutritionist_id,
  title,
  status,
  starts_on,
  ends_on,
  content,
  notes,
  published_at
)
values (
  '10000000-0000-4000-8000-000000000001',
  '30000000-0000-4000-8000-000000000001',
  '00000000-0000-4000-8000-000000000102',
  'Plan demo inicial',
  'published',
  current_date,
  current_date + 14,
  '{"meals": [{"name": "Desayuno", "items": ["yogur griego", "fruta", "avena"]}, {"name": "Almuerzo", "items": ["pollo", "arroz integral", "ensalada"]}]}'::jsonb,
  'Plan de ejemplo para validar permisos.',
  now()
)
on conflict do nothing;

insert into public.workout_routines (
  tenant_id,
  patient_id,
  nutritionist_id,
  title,
  status,
  starts_on,
  ends_on,
  content,
  notes,
  published_at
)
values (
  '10000000-0000-4000-8000-000000000001',
  '30000000-0000-4000-8000-000000000001',
  '00000000-0000-4000-8000-000000000102',
  'Rutina demo de movilidad',
  'published',
  current_date,
  current_date + 14,
  '{"days": [{"day": "lunes", "exercises": ["caminata 30 min", "movilidad 10 min"]}, {"day": "miercoles", "exercises": ["fuerza basica 30 min"]}]}'::jsonb,
  'Rutina ligera de demostracion.',
  now()
)
on conflict do nothing;

insert into public.food_recalls (
  tenant_id,
  patient_id,
  recorded_by,
  recall_date,
  meals,
  water_intake_ml,
  notes
)
values (
  '10000000-0000-4000-8000-000000000001',
  '30000000-0000-4000-8000-000000000001',
  '00000000-0000-4000-8000-000000000103',
  current_date,
  '[{"meal": "desayuno", "description": "cafe, pan integral y huevos"}, {"meal": "almuerzo", "description": "arroz, pollo y ensalada"}]'::jsonb,
  1800,
  'Registro demo enviado por paciente.'
)
on conflict do nothing;

insert into public.ai_generations (
  tenant_id,
  patient_id,
  requested_by,
  generation_type,
  model,
  input,
  output,
  prompt_tokens,
  completion_tokens,
  status
)
values (
  '10000000-0000-4000-8000-000000000001',
  '30000000-0000-4000-8000-000000000001',
  '00000000-0000-4000-8000-000000000102',
  'diet_plan',
  'demo-model',
  '{"goal": "mejorar composicion corporal"}'::jsonb,
  '{"summary": "Plan demo generado para pruebas de permisos."}'::jsonb,
  120,
  80,
  'completed'
)
on conflict do nothing;

insert into public.audit_logs (
  tenant_id,
  actor_user_id,
  action,
  entity_table,
  entity_id,
  metadata
)
values
  (
    '10000000-0000-4000-8000-000000000001',
    '00000000-0000-4000-8000-000000000101',
    'tenant.demo_seeded',
    'tenants',
    '10000000-0000-4000-8000-000000000001',
    '{"source": "supabase/seed.sql"}'::jsonb
  ),
  (
    '10000000-0000-4000-8000-000000000001',
    '00000000-0000-4000-8000-000000000102',
    'patient.created',
    'patients',
    '30000000-0000-4000-8000-000000000001',
    '{"source": "supabase/seed.sql"}'::jsonb
  )
on conflict do nothing;
