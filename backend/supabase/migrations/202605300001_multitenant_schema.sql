-- Nutri SaaS multi-tenant schema.
-- This migration creates the production-oriented master/tenant data model.

create extension if not exists pgcrypto;

create type public.app_role as enum (
  'platform_admin',
  'owner',
  'admin',
  'nutritionist',
  'assistant',
  'viewer',
  'patient'
);

create type public.member_status as enum (
  'invited',
  'active',
  'suspended',
  'removed'
);

create type public.invitation_status as enum (
  'pending',
  'accepted',
  'expired',
  'revoked'
);

create type public.tenant_status as enum (
  'trial',
  'active',
  'past_due',
  'suspended',
  'cancelled'
);

create type public.subscription_status as enum (
  'trialing',
  'active',
  'past_due',
  'cancelled',
  'unpaid'
);

create type public.patient_status as enum (
  'active',
  'inactive',
  'archived'
);

create type public.appointment_status as enum (
  'scheduled',
  'confirmed',
  'completed',
  'cancelled',
  'no_show'
);

create type public.note_visibility as enum (
  'team',
  'patient_portal',
  'private'
);

create type public.content_status as enum (
  'draft',
  'published',
  'archived'
);

create table public.profiles (
  id uuid primary key references auth.users(id) on delete cascade,
  email text not null,
  full_name text,
  phone text,
  avatar_url text,
  platform_role public.app_role,
  locale text not null default 'es',
  metadata jsonb not null default '{}'::jsonb,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint profiles_platform_role_check check (
    platform_role is null or platform_role = 'platform_admin'
  )
);

create table public.tenants (
  id uuid primary key default gen_random_uuid(),
  name text not null,
  slug text not null unique,
  legal_name text,
  tax_id text,
  billing_email text,
  phone text,
  status public.tenant_status not null default 'trial',
  settings jsonb not null default '{}'::jsonb,
  created_by uuid references public.profiles(id) on delete set null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint tenants_slug_format check (slug ~ '^[a-z0-9]+(?:-[a-z0-9]+)*$')
);

create table public.tenant_members (
  id uuid primary key default gen_random_uuid(),
  tenant_id uuid not null references public.tenants(id) on delete cascade,
  user_id uuid not null references public.profiles(id) on delete cascade,
  role public.app_role not null,
  status public.member_status not null default 'invited',
  invited_by uuid references public.profiles(id) on delete set null,
  joined_at timestamptz,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint tenant_members_no_platform_role check (role <> 'platform_admin'),
  constraint tenant_members_unique_user unique (tenant_id, user_id)
);

create table public.tenant_invitations (
  id uuid primary key default gen_random_uuid(),
  tenant_id uuid not null references public.tenants(id) on delete cascade,
  email text not null,
  role public.app_role not null,
  token_hash text not null,
  status public.invitation_status not null default 'pending',
  invited_by uuid references public.profiles(id) on delete set null,
  expires_at timestamptz not null default (now() + interval '7 days'),
  accepted_at timestamptz,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint tenant_invitations_no_platform_role check (role <> 'platform_admin'),
  constraint tenant_invitations_unique_pending unique (tenant_id, email, status)
);

create table public.plans (
  id uuid primary key default gen_random_uuid(),
  code text not null unique,
  name text not null,
  description text,
  price_cents integer not null default 0 check (price_cents >= 0),
  currency text not null default 'USD',
  billing_interval text not null default 'month' check (billing_interval in ('month', 'year')),
  limits jsonb not null default '{}'::jsonb,
  features jsonb not null default '{}'::jsonb,
  is_active boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.subscriptions (
  id uuid primary key default gen_random_uuid(),
  tenant_id uuid not null references public.tenants(id) on delete cascade,
  plan_id uuid not null references public.plans(id) on delete restrict,
  status public.subscription_status not null default 'trialing',
  provider text,
  provider_customer_id text,
  provider_subscription_id text,
  current_period_start timestamptz,
  current_period_end timestamptz,
  trial_ends_at timestamptz,
  cancel_at timestamptz,
  cancelled_at timestamptz,
  metadata jsonb not null default '{}'::jsonb,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.subscription_usage (
  id uuid primary key default gen_random_uuid(),
  tenant_id uuid not null references public.tenants(id) on delete cascade,
  subscription_id uuid not null references public.subscriptions(id) on delete cascade,
  period_start date not null,
  period_end date not null,
  patients_count integer not null default 0 check (patients_count >= 0),
  seats_count integer not null default 0 check (seats_count >= 0),
  ai_generations_count integer not null default 0 check (ai_generations_count >= 0),
  storage_mb numeric(12, 2) not null default 0 check (storage_mb >= 0),
  metadata jsonb not null default '{}'::jsonb,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint subscription_usage_period_check check (period_end >= period_start)
);

create table public.patients (
  id uuid primary key default gen_random_uuid(),
  tenant_id uuid not null references public.tenants(id) on delete cascade,
  user_id uuid references public.profiles(id) on delete set null,
  assigned_nutritionist_id uuid references public.profiles(id) on delete set null,
  first_name text not null,
  last_name text not null,
  email text,
  phone text,
  birth_date date,
  gender text check (gender in ('female', 'male', 'other', 'prefer_not_to_say') or gender is null),
  status public.patient_status not null default 'active',
  tags text[] not null default '{}'::text[],
  notes text,
  created_by uuid references public.profiles(id) on delete set null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.patient_profiles (
  id uuid primary key default gen_random_uuid(),
  tenant_id uuid not null references public.tenants(id) on delete cascade,
  patient_id uuid not null references public.patients(id) on delete cascade,
  height_cm numeric(5, 2),
  target_weight_kg numeric(5, 2),
  activity_level text,
  goals text[] not null default '{}'::text[],
  allergies text[] not null default '{}'::text[],
  medical_conditions text[] not null default '{}'::text[],
  medications text[] not null default '{}'::text[],
  dietary_preferences text[] not null default '{}'::text[],
  emergency_contact jsonb not null default '{}'::jsonb,
  clinical_summary text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint patient_profiles_unique_patient unique (tenant_id, patient_id)
);

create table public.appointments (
  id uuid primary key default gen_random_uuid(),
  tenant_id uuid not null references public.tenants(id) on delete cascade,
  patient_id uuid not null references public.patients(id) on delete cascade,
  nutritionist_id uuid references public.profiles(id) on delete set null,
  title text not null default 'Consulta',
  starts_at timestamptz not null,
  ends_at timestamptz not null,
  status public.appointment_status not null default 'scheduled',
  location text,
  meeting_url text,
  notes text,
  created_by uuid references public.profiles(id) on delete set null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint appointments_time_check check (ends_at > starts_at)
);

create table public.clinical_notes (
  id uuid primary key default gen_random_uuid(),
  tenant_id uuid not null references public.tenants(id) on delete cascade,
  patient_id uuid not null references public.patients(id) on delete cascade,
  author_id uuid references public.profiles(id) on delete set null,
  appointment_id uuid references public.appointments(id) on delete set null,
  title text not null,
  body text not null,
  visibility public.note_visibility not null default 'team',
  locked_at timestamptz,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.diets (
  id uuid primary key default gen_random_uuid(),
  tenant_id uuid not null references public.tenants(id) on delete cascade,
  patient_id uuid not null references public.patients(id) on delete cascade,
  nutritionist_id uuid references public.profiles(id) on delete set null,
  title text not null,
  status public.content_status not null default 'draft',
  starts_on date,
  ends_on date,
  content jsonb not null default '{}'::jsonb,
  notes text,
  published_at timestamptz,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint diets_date_check check (ends_on is null or starts_on is null or ends_on >= starts_on)
);

create table public.workout_routines (
  id uuid primary key default gen_random_uuid(),
  tenant_id uuid not null references public.tenants(id) on delete cascade,
  patient_id uuid not null references public.patients(id) on delete cascade,
  nutritionist_id uuid references public.profiles(id) on delete set null,
  title text not null,
  status public.content_status not null default 'draft',
  starts_on date,
  ends_on date,
  content jsonb not null default '{}'::jsonb,
  notes text,
  published_at timestamptz,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint workout_routines_date_check check (ends_on is null or starts_on is null or ends_on >= starts_on)
);

create table public.measurements (
  id uuid primary key default gen_random_uuid(),
  tenant_id uuid not null references public.tenants(id) on delete cascade,
  patient_id uuid not null references public.patients(id) on delete cascade,
  recorded_by uuid references public.profiles(id) on delete set null,
  recorded_at timestamptz not null default now(),
  weight_kg numeric(5, 2),
  body_fat_percentage numeric(5, 2),
  waist_cm numeric(5, 2),
  hip_cm numeric(5, 2),
  chest_cm numeric(5, 2),
  arm_cm numeric(5, 2),
  thigh_cm numeric(5, 2),
  metrics jsonb not null default '{}'::jsonb,
  notes text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.food_recalls (
  id uuid primary key default gen_random_uuid(),
  tenant_id uuid not null references public.tenants(id) on delete cascade,
  patient_id uuid not null references public.patients(id) on delete cascade,
  recorded_by uuid references public.profiles(id) on delete set null,
  recall_date date not null default current_date,
  meals jsonb not null default '[]'::jsonb,
  water_intake_ml integer check (water_intake_ml is null or water_intake_ml >= 0),
  notes text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.ai_generations (
  id uuid primary key default gen_random_uuid(),
  tenant_id uuid not null references public.tenants(id) on delete cascade,
  patient_id uuid references public.patients(id) on delete set null,
  requested_by uuid references public.profiles(id) on delete set null,
  generation_type text not null,
  model text not null,
  input jsonb not null default '{}'::jsonb,
  output jsonb not null default '{}'::jsonb,
  prompt_tokens integer check (prompt_tokens is null or prompt_tokens >= 0),
  completion_tokens integer check (completion_tokens is null or completion_tokens >= 0),
  status text not null default 'completed' check (status in ('queued', 'running', 'completed', 'failed')),
  error_message text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.audit_logs (
  id uuid primary key default gen_random_uuid(),
  tenant_id uuid not null references public.tenants(id) on delete cascade,
  actor_user_id uuid references public.profiles(id) on delete set null,
  action text not null,
  entity_table text,
  entity_id uuid,
  metadata jsonb not null default '{}'::jsonb,
  ip_address inet,
  user_agent text,
  created_at timestamptz not null default now()
);

create or replace function public.set_updated_at()
returns trigger
language plpgsql
as $$
begin
  new.updated_at = now();
  return new;
end;
$$;

create or replace function public.handle_new_user()
returns trigger
language plpgsql
security definer
set search_path = public
as $$
begin
  insert into public.profiles (id, email, full_name, avatar_url)
  values (
    new.id,
    new.email,
    coalesce(new.raw_user_meta_data ->> 'full_name', new.raw_user_meta_data ->> 'name'),
    new.raw_user_meta_data ->> 'avatar_url'
  )
  on conflict (id) do update
  set
    email = excluded.email,
    full_name = coalesce(public.profiles.full_name, excluded.full_name),
    avatar_url = coalesce(public.profiles.avatar_url, excluded.avatar_url),
    updated_at = now();

  return new;
end;
$$;

create or replace function public.is_platform_admin()
returns boolean
language sql
stable
security definer
set search_path = public
as $$
  select exists (
    select 1
    from public.profiles p
    where p.id = auth.uid()
      and p.platform_role = 'platform_admin'
  );
$$;

create or replace function public.is_tenant_member(p_tenant_id uuid)
returns boolean
language sql
stable
security definer
set search_path = public
as $$
  select public.is_platform_admin()
    or exists (
      select 1
      from public.tenant_members tm
      where tm.tenant_id = p_tenant_id
        and tm.user_id = auth.uid()
        and tm.status = 'active'
    );
$$;

create or replace function public.has_tenant_role(p_tenant_id uuid, p_roles text[])
returns boolean
language sql
stable
security definer
set search_path = public
as $$
  select public.is_platform_admin()
    or exists (
      select 1
      from public.tenant_members tm
      where tm.tenant_id = p_tenant_id
        and tm.user_id = auth.uid()
        and tm.status = 'active'
        and tm.role::text = any (p_roles)
    );
$$;

create or replace function public.is_assigned_nutritionist(p_tenant_id uuid, p_patient_id uuid)
returns boolean
language sql
stable
security definer
set search_path = public
as $$
  select exists (
    select 1
    from public.patients p
    where p.id = p_patient_id
      and p.tenant_id = p_tenant_id
      and p.assigned_nutritionist_id = auth.uid()
      and public.has_tenant_role(p_tenant_id, array['nutritionist'])
  );
$$;

create or replace function public.is_patient_self(p_tenant_id uuid, p_patient_id uuid)
returns boolean
language sql
stable
security definer
set search_path = public
as $$
  select exists (
    select 1
    from public.patients p
    where p.id = p_patient_id
      and p.tenant_id = p_tenant_id
      and p.user_id = auth.uid()
  );
$$;

create or replace function public.can_read_patient_record(p_tenant_id uuid, p_patient_id uuid)
returns boolean
language sql
stable
security definer
set search_path = public
as $$
  select public.has_tenant_role(p_tenant_id, array['owner', 'admin'])
    or public.is_assigned_nutritionist(p_tenant_id, p_patient_id)
    or public.is_patient_self(p_tenant_id, p_patient_id);
$$;

create or replace function public.storage_tenant_id(object_name text)
returns uuid
language plpgsql
immutable
as $$
declare
  first_segment text;
begin
  first_segment := split_part(object_name, '/', 1);
  return first_segment::uuid;
exception
  when invalid_text_representation then
    return null;
end;
$$;

create or replace function public.storage_patient_id(object_name text)
returns uuid
language plpgsql
immutable
as $$
declare
  parts text[];
begin
  parts := string_to_array(object_name, '/');

  if array_length(parts, 1) >= 3 and parts[2] = 'patients' then
    return parts[3]::uuid;
  end if;

  return null;
exception
  when invalid_text_representation then
    return null;
end;
$$;

create or replace view public.patient_directory as
select
  p.id,
  p.tenant_id,
  p.assigned_nutritionist_id,
  p.first_name,
  p.last_name,
  p.email,
  p.phone,
  p.birth_date,
  p.gender,
  p.status,
  p.tags,
  p.created_at,
  p.updated_at
from public.patients p
where
  public.has_tenant_role(p.tenant_id, array['owner', 'admin', 'assistant', 'viewer'])
  or (
    public.has_tenant_role(p.tenant_id, array['nutritionist'])
    and p.assigned_nutritionist_id = auth.uid()
  )
  or p.user_id = auth.uid();

create or replace view public.appointment_calendar as
select
  a.id,
  a.tenant_id,
  a.patient_id,
  a.nutritionist_id,
  a.title,
  a.starts_at,
  a.ends_at,
  a.status,
  a.location,
  a.meeting_url,
  a.created_at,
  a.updated_at
from public.appointments a
where
  public.has_tenant_role(a.tenant_id, array['owner', 'admin', 'assistant', 'viewer'])
  or (
    public.has_tenant_role(a.tenant_id, array['nutritionist'])
    and (a.nutritionist_id = auth.uid() or public.is_assigned_nutritionist(a.tenant_id, a.patient_id))
  )
  or public.is_patient_self(a.tenant_id, a.patient_id);

drop trigger if exists on_auth_user_created on auth.users;
create trigger on_auth_user_created
  after insert on auth.users
  for each row execute function public.handle_new_user();

drop trigger if exists set_profiles_updated_at on public.profiles;
create trigger set_profiles_updated_at before update on public.profiles
  for each row execute function public.set_updated_at();

drop trigger if exists set_tenants_updated_at on public.tenants;
create trigger set_tenants_updated_at before update on public.tenants
  for each row execute function public.set_updated_at();

drop trigger if exists set_tenant_members_updated_at on public.tenant_members;
create trigger set_tenant_members_updated_at before update on public.tenant_members
  for each row execute function public.set_updated_at();

drop trigger if exists set_tenant_invitations_updated_at on public.tenant_invitations;
create trigger set_tenant_invitations_updated_at before update on public.tenant_invitations
  for each row execute function public.set_updated_at();

drop trigger if exists set_plans_updated_at on public.plans;
create trigger set_plans_updated_at before update on public.plans
  for each row execute function public.set_updated_at();

drop trigger if exists set_subscriptions_updated_at on public.subscriptions;
create trigger set_subscriptions_updated_at before update on public.subscriptions
  for each row execute function public.set_updated_at();

drop trigger if exists set_subscription_usage_updated_at on public.subscription_usage;
create trigger set_subscription_usage_updated_at before update on public.subscription_usage
  for each row execute function public.set_updated_at();

drop trigger if exists set_patients_updated_at on public.patients;
create trigger set_patients_updated_at before update on public.patients
  for each row execute function public.set_updated_at();

drop trigger if exists set_patient_profiles_updated_at on public.patient_profiles;
create trigger set_patient_profiles_updated_at before update on public.patient_profiles
  for each row execute function public.set_updated_at();

drop trigger if exists set_appointments_updated_at on public.appointments;
create trigger set_appointments_updated_at before update on public.appointments
  for each row execute function public.set_updated_at();

drop trigger if exists set_clinical_notes_updated_at on public.clinical_notes;
create trigger set_clinical_notes_updated_at before update on public.clinical_notes
  for each row execute function public.set_updated_at();

drop trigger if exists set_diets_updated_at on public.diets;
create trigger set_diets_updated_at before update on public.diets
  for each row execute function public.set_updated_at();

drop trigger if exists set_workout_routines_updated_at on public.workout_routines;
create trigger set_workout_routines_updated_at before update on public.workout_routines
  for each row execute function public.set_updated_at();

drop trigger if exists set_measurements_updated_at on public.measurements;
create trigger set_measurements_updated_at before update on public.measurements
  for each row execute function public.set_updated_at();

drop trigger if exists set_food_recalls_updated_at on public.food_recalls;
create trigger set_food_recalls_updated_at before update on public.food_recalls
  for each row execute function public.set_updated_at();

drop trigger if exists set_ai_generations_updated_at on public.ai_generations;
create trigger set_ai_generations_updated_at before update on public.ai_generations
  for each row execute function public.set_updated_at();

create index profiles_email_idx on public.profiles (lower(email));
create index profiles_platform_role_idx on public.profiles (platform_role);

create index tenants_slug_idx on public.tenants (slug);
create index tenants_status_idx on public.tenants (status);

create index tenant_members_tenant_id_idx on public.tenant_members (tenant_id);
create index tenant_members_user_id_idx on public.tenant_members (user_id);
create index tenant_members_role_status_idx on public.tenant_members (tenant_id, role, status);

create index tenant_invitations_tenant_id_idx on public.tenant_invitations (tenant_id);
create index tenant_invitations_email_idx on public.tenant_invitations (lower(email));
create index tenant_invitations_status_idx on public.tenant_invitations (status);

create index subscriptions_tenant_id_idx on public.subscriptions (tenant_id);
create index subscriptions_plan_id_idx on public.subscriptions (plan_id);
create index subscriptions_status_idx on public.subscriptions (status);

create index subscription_usage_tenant_id_idx on public.subscription_usage (tenant_id);
create index subscription_usage_subscription_id_idx on public.subscription_usage (subscription_id);
create index subscription_usage_period_idx on public.subscription_usage (tenant_id, period_start, period_end);

create index patients_tenant_id_idx on public.patients (tenant_id);
create index patients_user_id_idx on public.patients (user_id);
create index patients_assigned_nutritionist_id_idx on public.patients (assigned_nutritionist_id);
create index patients_status_idx on public.patients (tenant_id, status);
create index patients_email_idx on public.patients (tenant_id, lower(email));

create index patient_profiles_tenant_id_idx on public.patient_profiles (tenant_id);
create index patient_profiles_patient_id_idx on public.patient_profiles (patient_id);

create index appointments_tenant_id_idx on public.appointments (tenant_id);
create index appointments_patient_id_idx on public.appointments (patient_id);
create index appointments_nutritionist_id_idx on public.appointments (nutritionist_id);
create index appointments_starts_at_idx on public.appointments (tenant_id, starts_at);
create index appointments_status_idx on public.appointments (tenant_id, status);

create index clinical_notes_tenant_id_idx on public.clinical_notes (tenant_id);
create index clinical_notes_patient_id_idx on public.clinical_notes (patient_id);
create index clinical_notes_author_id_idx on public.clinical_notes (author_id);

create index diets_tenant_id_idx on public.diets (tenant_id);
create index diets_patient_id_idx on public.diets (patient_id);
create index diets_nutritionist_id_idx on public.diets (nutritionist_id);
create index diets_status_idx on public.diets (tenant_id, status);

create index workout_routines_tenant_id_idx on public.workout_routines (tenant_id);
create index workout_routines_patient_id_idx on public.workout_routines (patient_id);
create index workout_routines_nutritionist_id_idx on public.workout_routines (nutritionist_id);
create index workout_routines_status_idx on public.workout_routines (tenant_id, status);

create index measurements_tenant_id_idx on public.measurements (tenant_id);
create index measurements_patient_id_idx on public.measurements (patient_id);
create index measurements_recorded_by_idx on public.measurements (recorded_by);
create index measurements_recorded_at_idx on public.measurements (tenant_id, recorded_at);

create index food_recalls_tenant_id_idx on public.food_recalls (tenant_id);
create index food_recalls_patient_id_idx on public.food_recalls (patient_id);
create index food_recalls_recorded_by_idx on public.food_recalls (recorded_by);
create index food_recalls_recall_date_idx on public.food_recalls (tenant_id, recall_date);

create index ai_generations_tenant_id_idx on public.ai_generations (tenant_id);
create index ai_generations_patient_id_idx on public.ai_generations (patient_id);
create index ai_generations_requested_by_idx on public.ai_generations (requested_by);
create index ai_generations_type_idx on public.ai_generations (tenant_id, generation_type);

create index audit_logs_tenant_id_idx on public.audit_logs (tenant_id);
create index audit_logs_actor_user_id_idx on public.audit_logs (actor_user_id);
create index audit_logs_entity_idx on public.audit_logs (entity_table, entity_id);
create index audit_logs_created_at_idx on public.audit_logs (tenant_id, created_at desc);

alter table public.profiles enable row level security;
alter table public.tenants enable row level security;
alter table public.tenant_members enable row level security;
alter table public.tenant_invitations enable row level security;
alter table public.plans enable row level security;
alter table public.subscriptions enable row level security;
alter table public.subscription_usage enable row level security;
alter table public.patients enable row level security;
alter table public.patient_profiles enable row level security;
alter table public.appointments enable row level security;
alter table public.clinical_notes enable row level security;
alter table public.diets enable row level security;
alter table public.workout_routines enable row level security;
alter table public.measurements enable row level security;
alter table public.food_recalls enable row level security;
alter table public.ai_generations enable row level security;
alter table public.audit_logs enable row level security;

alter table public.profiles force row level security;
alter table public.tenants force row level security;
alter table public.tenant_members force row level security;
alter table public.tenant_invitations force row level security;
alter table public.plans force row level security;
alter table public.subscriptions force row level security;
alter table public.subscription_usage force row level security;
alter table public.patients force row level security;
alter table public.patient_profiles force row level security;
alter table public.appointments force row level security;
alter table public.clinical_notes force row level security;
alter table public.diets force row level security;
alter table public.workout_routines force row level security;
alter table public.measurements force row level security;
alter table public.food_recalls force row level security;
alter table public.ai_generations force row level security;
alter table public.audit_logs force row level security;

insert into storage.buckets (id, name, public, file_size_limit, allowed_mime_types)
values
  ('tenant-documents', 'tenant-documents', false, 52428800, null),
  ('profile-avatars', 'profile-avatars', true, 5242880, array['image/png', 'image/jpeg', 'image/webp'])
on conflict (id) do update
set
  public = excluded.public,
  file_size_limit = excluded.file_size_limit,
  allowed_mime_types = excluded.allowed_mime_types;

grant usage on schema public to anon, authenticated;
grant select, insert, update, delete on all tables in schema public to authenticated;
grant usage, select on all sequences in schema public to authenticated;
revoke execute on all functions in schema public from public;
grant execute on all functions in schema public to authenticated;
