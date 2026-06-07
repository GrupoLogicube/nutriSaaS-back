-- Safe SaaS baseline for remote Supabase.
-- Non-destructive: no DROP, TRUNCATE or DELETE. Does not modify nutrition data.

create extension if not exists pgcrypto;

do $$
begin
  if not exists (select 1 from pg_type where typnamespace = 'public'::regnamespace and typname = 'app_role') then
    create type public.app_role as enum ('platform_admin', 'owner', 'admin', 'nutritionist', 'assistant', 'viewer', 'patient');
  end if;

  if not exists (select 1 from pg_type where typnamespace = 'public'::regnamespace and typname = 'member_status') then
    create type public.member_status as enum ('invited', 'active', 'suspended', 'removed');
  end if;

  if not exists (select 1 from pg_type where typnamespace = 'public'::regnamespace and typname = 'invitation_status') then
    create type public.invitation_status as enum ('pending', 'accepted', 'expired', 'revoked');
  end if;

  if not exists (select 1 from pg_type where typnamespace = 'public'::regnamespace and typname = 'tenant_status') then
    create type public.tenant_status as enum ('trial', 'active', 'past_due', 'suspended', 'cancelled');
  end if;

  if not exists (select 1 from pg_type where typnamespace = 'public'::regnamespace and typname = 'subscription_status') then
    create type public.subscription_status as enum ('trialing', 'active', 'past_due', 'cancelled', 'unpaid');
  end if;

  if not exists (select 1 from pg_type where typnamespace = 'public'::regnamespace and typname = 'patient_status') then
    create type public.patient_status as enum ('active', 'inactive', 'archived');
  end if;

  if not exists (select 1 from pg_type where typnamespace = 'public'::regnamespace and typname = 'appointment_status') then
    create type public.appointment_status as enum ('scheduled', 'confirmed', 'completed', 'cancelled', 'no_show');
  end if;

  if not exists (select 1 from pg_type where typnamespace = 'public'::regnamespace and typname = 'note_visibility') then
    create type public.note_visibility as enum ('team', 'patient_portal', 'private');
  end if;

  if not exists (select 1 from pg_type where typnamespace = 'public'::regnamespace and typname = 'content_status') then
    create type public.content_status as enum ('draft', 'published', 'archived');
  end if;
end $$;

create table if not exists public.profiles (
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
  constraint profiles_platform_role_check check (platform_role is null or platform_role = 'platform_admin')
);

create table if not exists public.tenants (
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

create table if not exists public.tenant_members (
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

create table if not exists public.tenant_invitations (
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

create table if not exists public.plans (
  id uuid primary key default gen_random_uuid(),
  code text not null unique,
  name text not null,
  description text,
  price_cents integer not null default 0 check (price_cents >= 0),
  currency text not null default 'USD',
  billing_interval text not null default 'month' check (billing_interval in ('month', 'year')),
  limits jsonb not null default '{}'::jsonb,
  features jsonb not null default '{}'::jsonb,
  sort_order integer not null default 0,
  provider_price_ids jsonb not null default '{}'::jsonb,
  metadata jsonb not null default '{}'::jsonb,
  is_active boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table if not exists public.subscriptions (
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

create table if not exists public.subscription_usage (
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

create table if not exists public.patients (
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

create table if not exists public.patient_profiles (
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
  extended_data jsonb not null default '{}'::jsonb,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint patient_profiles_unique_patient unique (tenant_id, patient_id)
);

create table if not exists public.appointments (
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

create table if not exists public.clinical_notes (
  id uuid primary key default gen_random_uuid(),
  tenant_id uuid not null references public.tenants(id) on delete cascade,
  patient_id uuid not null references public.patients(id) on delete cascade,
  author_id uuid references public.profiles(id) on delete set null,
  appointment_id uuid references public.appointments(id) on delete set null,
  title text not null,
  body text not null,
  visibility public.note_visibility not null default 'team',
  tags text[] not null default '{}'::text[],
  pinned boolean not null default false,
  locked_at timestamptz,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table if not exists public.diets (
  id uuid primary key default gen_random_uuid(),
  tenant_id uuid not null references public.tenants(id) on delete cascade,
  patient_id uuid not null references public.patients(id) on delete cascade,
  nutritionist_id uuid references public.profiles(id) on delete set null,
  created_by uuid references public.profiles(id) on delete set null,
  title text not null,
  objective text,
  status public.content_status not null default 'draft',
  starts_on date,
  ends_on date,
  content jsonb not null default '{}'::jsonb,
  notes text,
  nutrition_source_id uuid references public.nutrition_sources(id),
  nutrition_source_code text,
  generated_by_ai boolean not null default false,
  published_at timestamptz,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint diets_date_check check (ends_on is null or starts_on is null or ends_on >= starts_on)
);

create table if not exists public.workout_routines (
  id uuid primary key default gen_random_uuid(),
  tenant_id uuid not null references public.tenants(id) on delete cascade,
  patient_id uuid not null references public.patients(id) on delete cascade,
  nutritionist_id uuid references public.profiles(id) on delete set null,
  created_by uuid references public.profiles(id) on delete set null,
  title text not null,
  objective text,
  status public.content_status not null default 'draft',
  starts_on date,
  ends_on date,
  content jsonb not null default '{}'::jsonb,
  notes text,
  generated_by_ai boolean not null default false,
  published_at timestamptz,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint workout_routines_date_check check (ends_on is null or starts_on is null or ends_on >= starts_on)
);

create table if not exists public.ai_generations (
  id uuid primary key default gen_random_uuid(),
  tenant_id uuid not null references public.tenants(id) on delete cascade,
  patient_id uuid references public.patients(id) on delete set null,
  user_id uuid references public.profiles(id) on delete set null,
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

create table if not exists public.audit_logs (
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

create table if not exists public.tenant_nutrition_settings (
  id uuid primary key default gen_random_uuid(),
  tenant_id uuid not null references public.tenants(id) on delete cascade,
  default_source_id uuid references public.nutrition_sources(id),
  allow_source_switching boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint tenant_nutrition_settings_tenant_unique unique (tenant_id)
);

create table if not exists public.billing_events (
  id uuid primary key default gen_random_uuid(),
  tenant_id uuid not null references public.tenants(id) on delete cascade,
  subscription_id uuid references public.subscriptions(id) on delete set null,
  provider text not null default 'manual',
  event_type text not null,
  provider_event_id text,
  status text not null default 'pending' check (status in ('pending', 'processed', 'failed', 'ignored')),
  payload jsonb not null default '{}'::jsonb,
  processed_at timestamptz,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
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
    coalesce(new.raw_user_meta_data ->> 'full_name', new.raw_user_meta_data ->> 'name', new.email),
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
    select 1 from public.profiles p
    where p.id = auth.uid() and p.platform_role = 'platform_admin'
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
      select 1 from public.tenant_members tm
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
      select 1 from public.tenant_members tm
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
    select 1 from public.patients p
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
    select 1 from public.patients p
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
  select public.has_tenant_role(p_tenant_id, array['owner', 'admin', 'assistant', 'viewer'])
    or public.is_assigned_nutritionist(p_tenant_id, p_patient_id)
    or public.is_patient_self(p_tenant_id, p_patient_id);
$$;

create or replace function public.slugify(value text)
returns text
language sql
immutable
as $$
  select trim(both '-' from regexp_replace(lower(coalesce(value, 'tenant')), '[^a-z0-9]+', '-', 'g'));
$$;

create or replace function public.bootstrap_current_user_demo_tenant(p_tenant_name text default 'Clinica Demo')
returns uuid
language plpgsql
security definer
set search_path = public
as $$
declare
  v_user_id uuid := auth.uid();
  v_email text;
  v_tenant_id uuid;
  v_slug text;
  v_plan_id uuid;
  v_subscription_id uuid;
  v_source_id uuid;
begin
  if v_user_id is null then
    raise exception 'Authentication required';
  end if;

  select email into v_email from auth.users where id = v_user_id;

  insert into public.profiles (id, email, full_name)
  values (v_user_id, coalesce(v_email, 'usuario@demo.local'), coalesce(v_email, 'Usuario Demo'))
  on conflict (id) do update
  set email = excluded.email, updated_at = now();

  select tm.tenant_id into v_tenant_id
  from public.tenant_members tm
  where tm.user_id = v_user_id and tm.status = 'active'
  order by tm.created_at asc
  limit 1;

  if v_tenant_id is not null then
    return v_tenant_id;
  end if;

  v_slug := public.slugify(p_tenant_name) || '-' || substring(v_user_id::text from 1 for 8);

  insert into public.tenants (name, slug, billing_email, status, created_by, settings)
  values (p_tenant_name, v_slug, v_email, 'trial', v_user_id, '{"demo": true}'::jsonb)
  returning id into v_tenant_id;

  insert into public.tenant_members (tenant_id, user_id, role, status, joined_at)
  values (v_tenant_id, v_user_id, 'owner', 'active', now());

  select id into v_plan_id from public.plans where code = 'pro' limit 1;
  if v_plan_id is not null then
    insert into public.subscriptions (
      tenant_id,
      plan_id,
      status,
      provider,
      current_period_start,
      current_period_end,
      trial_ends_at,
      metadata
    )
    values (
      v_tenant_id,
      v_plan_id,
      'trialing',
      'manual',
      date_trunc('day', now()),
      date_trunc('day', now()) + interval '30 days',
      date_trunc('day', now()) + interval '30 days',
      '{"bootstrap": true}'::jsonb
    )
    returning id into v_subscription_id;

    insert into public.subscription_usage (tenant_id, subscription_id, period_start, period_end, patients_count, seats_count)
    values (v_tenant_id, v_subscription_id, current_date, current_date + 30, 0, 1);
  end if;

  select id into v_source_id from public.nutrition_sources where code = 'usfq' and is_active = true limit 1;
  if v_source_id is null then
    select id into v_source_id from public.nutrition_sources where is_active = true order by code limit 1;
  end if;

  if v_source_id is not null then
    insert into public.tenant_nutrition_settings (tenant_id, default_source_id, allow_source_switching)
    values (v_tenant_id, v_source_id, true)
    on conflict (tenant_id) do nothing;
  end if;

  return v_tenant_id;
end;
$$;

create or replace function public.get_tenant_entitlements(p_tenant_id uuid)
returns table (
  tenant_id uuid,
  subscription_id uuid,
  plan_id uuid,
  plan_code text,
  plan_name text,
  status text,
  current_period_start date,
  current_period_end date,
  features jsonb,
  limits jsonb,
  usage jsonb
)
language plpgsql
security definer
set search_path = public
as $$
declare
  v_subscription record;
  v_period_start date;
  v_period_end date;
begin
  if not (public.is_platform_admin() or public.is_tenant_member(p_tenant_id)) then
    raise exception 'Forbidden';
  end if;

  select s.id as subscription_id, s.tenant_id, s.plan_id, s.status,
    s.current_period_start, s.current_period_end,
    p.code as plan_code, p.name as plan_name, p.features, p.limits
  into v_subscription
  from public.subscriptions s
  join public.plans p on p.id = s.plan_id
  where s.tenant_id = p_tenant_id and s.status in ('trialing', 'active', 'past_due')
  order by s.created_at desc
  limit 1;

  if v_subscription.subscription_id is null then
    return;
  end if;

  v_period_start := coalesce(v_subscription.current_period_start::date, date_trunc('month', now())::date);
  v_period_end := coalesce(v_subscription.current_period_end::date, (date_trunc('month', now()) + interval '1 month - 1 day')::date);

  return query select
    v_subscription.tenant_id,
    v_subscription.subscription_id,
    v_subscription.plan_id,
    v_subscription.plan_code,
    v_subscription.plan_name,
    v_subscription.status::text,
    v_period_start,
    v_period_end,
    v_subscription.features,
    v_subscription.limits,
    jsonb_build_object(
      'patients', (select count(*) from public.patients p where p.tenant_id = p_tenant_id and p.status <> 'archived'),
      'seats', (select count(*) from public.tenant_members tm where tm.tenant_id = p_tenant_id and tm.status = 'active' and tm.role <> 'patient'),
      'ai_generations', (select count(*) from public.ai_generations ag where ag.tenant_id = p_tenant_id and ag.status = 'completed' and ag.created_at >= v_period_start::timestamptz and ag.created_at < (v_period_end + 1)::timestamptz),
      'clinical_notes', (select count(*) from public.clinical_notes cn where cn.tenant_id = p_tenant_id),
      'appointments', (select count(*) from public.appointments a where a.tenant_id = p_tenant_id and a.starts_at >= v_period_start::timestamptz and a.starts_at < (v_period_end + 1)::timestamptz),
      'storage_mb', 0
    );
end;
$$;

do $$
declare
  table_name text;
begin
  foreach table_name in array array[
    'profiles','tenants','tenant_members','tenant_invitations','plans','subscriptions',
    'subscription_usage','patients','patient_profiles','appointments','clinical_notes',
    'diets','workout_routines','ai_generations','audit_logs','tenant_nutrition_settings','billing_events'
  ] loop
    execute format('alter table public.%I enable row level security', table_name);
  end loop;
end $$;

do $$
begin
  if not exists (select 1 from pg_trigger where tgname = 'on_auth_user_created') then
    create trigger on_auth_user_created
      after insert on auth.users
      for each row execute function public.handle_new_user();
  end if;
end $$;

do $$
declare
  table_name text;
  trigger_name text;
begin
  foreach table_name in array array[
    'profiles','tenants','tenant_members','tenant_invitations','plans','subscriptions',
    'subscription_usage','patients','patient_profiles','appointments','clinical_notes',
    'diets','workout_routines','ai_generations','tenant_nutrition_settings','billing_events'
  ] loop
    trigger_name := 'set_' || table_name || '_updated_at';
    if not exists (select 1 from pg_trigger where tgname = trigger_name) then
      execute format('create trigger %I before update on public.%I for each row execute function public.set_updated_at()', trigger_name, table_name);
    end if;
  end loop;
end $$;

create index if not exists profiles_email_idx on public.profiles (lower(email));
create index if not exists tenant_members_tenant_id_idx on public.tenant_members (tenant_id);
create index if not exists tenant_members_user_id_idx on public.tenant_members (user_id);
create index if not exists tenant_members_role_status_idx on public.tenant_members (tenant_id, role, status);
create index if not exists subscriptions_tenant_id_idx on public.subscriptions (tenant_id);
create unique index if not exists subscriptions_one_current_per_tenant_uidx on public.subscriptions (tenant_id) where status in ('trialing', 'active', 'past_due');
create unique index if not exists subscription_usage_subscription_period_uidx on public.subscription_usage (subscription_id, period_start, period_end);
create index if not exists patients_tenant_id_idx on public.patients (tenant_id);
create index if not exists patients_user_id_idx on public.patients (user_id);
create index if not exists patients_assigned_nutritionist_id_idx on public.patients (assigned_nutritionist_id);
create index if not exists patient_profiles_patient_id_idx on public.patient_profiles (patient_id);
create index if not exists appointments_tenant_id_idx on public.appointments (tenant_id);
create index if not exists appointments_patient_id_idx on public.appointments (patient_id);
create index if not exists appointments_starts_at_idx on public.appointments (tenant_id, starts_at);
create index if not exists clinical_notes_tenant_id_idx on public.clinical_notes (tenant_id);
create index if not exists clinical_notes_patient_id_idx on public.clinical_notes (patient_id);
create index if not exists diets_tenant_id_idx on public.diets (tenant_id);
create index if not exists diets_patient_id_idx on public.diets (patient_id);
create index if not exists diets_nutrition_source_id_idx on public.diets (nutrition_source_id);
create index if not exists workout_routines_tenant_id_idx on public.workout_routines (tenant_id);
create index if not exists workout_routines_patient_id_idx on public.workout_routines (patient_id);
create index if not exists ai_generations_tenant_id_idx on public.ai_generations (tenant_id);
create index if not exists ai_generations_patient_id_idx on public.ai_generations (patient_id);
create index if not exists ai_generations_requested_by_idx on public.ai_generations (requested_by);
create index if not exists tenant_nutrition_settings_tenant_id_idx on public.tenant_nutrition_settings (tenant_id);
create index if not exists billing_events_tenant_id_idx on public.billing_events (tenant_id);

insert into public.plans (code, name, description, price_cents, currency, billing_interval, limits, features, sort_order, provider_price_ids, metadata, is_active)
values
  ('starter','Starter','Plan inicial para consultorios pequenos.',2900,'USD','month','{"seats":1,"patients":50,"ai_generations":40,"storage_mb":512}'::jsonb,'{"appointments":true,"clinical_notes":true,"ai_diets":true,"workout_routines":false,"analytics":false,"team":false,"api_access":false}'::jsonb,10,'{"stripe":null,"local":null}'::jsonb,'{"checkout_enabled":false}'::jsonb,true),
  ('pro','Pro','Plan para equipos clinicos con IA avanzada.',7900,'USD','month','{"seats":5,"patients":null,"ai_generations":500,"storage_mb":2048}'::jsonb,'{"appointments":true,"clinical_notes":true,"ai_diets":true,"workout_routines":true,"analytics":true,"team":true,"api_access":false}'::jsonb,20,'{"stripe":null,"local":null}'::jsonb,'{"checkout_enabled":false}'::jsonb,true),
  ('enterprise','Enterprise','Plan avanzado con limites personalizados e integraciones.',19900,'USD','month','{"seats":null,"patients":null,"ai_generations":null,"storage_mb":10240}'::jsonb,'{"appointments":true,"clinical_notes":true,"ai_diets":true,"workout_routines":true,"analytics":true,"team":true,"api_access":true,"white_label":true}'::jsonb,30,'{"stripe":null,"local":null}'::jsonb,'{"checkout_enabled":false,"sales_contact_required":true}'::jsonb,true)
on conflict (code) do update
set name = excluded.name,
  description = excluded.description,
  price_cents = excluded.price_cents,
  currency = excluded.currency,
  billing_interval = excluded.billing_interval,
  limits = excluded.limits,
  features = excluded.features,
  sort_order = excluded.sort_order,
  provider_price_ids = excluded.provider_price_ids,
  metadata = excluded.metadata,
  is_active = excluded.is_active,
  updated_at = now();

do $$
begin
  if not exists (select 1 from pg_policies where schemaname = 'public' and tablename = 'profiles' and policyname = 'profiles_select_self_or_shared_tenant') then
    create policy profiles_select_self_or_shared_tenant on public.profiles for select to authenticated
    using (
      id = auth.uid()
      or public.is_platform_admin()
      or exists (
        select 1
        from public.tenant_members mine
        join public.tenant_members other_member on other_member.tenant_id = mine.tenant_id
        where mine.user_id = auth.uid()
          and mine.status = 'active'
          and other_member.user_id = profiles.id
          and other_member.status = 'active'
      )
    );
  end if;

  if not exists (select 1 from pg_policies where schemaname = 'public' and tablename = 'profiles' and policyname = 'profiles_insert_own') then
    create policy profiles_insert_own on public.profiles for insert to authenticated with check (id = auth.uid());
  end if;

  if not exists (select 1 from pg_policies where schemaname = 'public' and tablename = 'profiles' and policyname = 'profiles_update_own') then
    create policy profiles_update_own on public.profiles for update to authenticated using (id = auth.uid()) with check (id = auth.uid());
  end if;

  if not exists (select 1 from pg_policies where schemaname = 'public' and tablename = 'tenants' and policyname = 'tenants_select_members') then
    create policy tenants_select_members on public.tenants for select to authenticated using (public.is_tenant_member(id));
  end if;

  if not exists (select 1 from pg_policies where schemaname = 'public' and tablename = 'tenants' and policyname = 'tenants_insert_authenticated') then
    create policy tenants_insert_authenticated on public.tenants for insert to authenticated with check (created_by = auth.uid() or public.is_platform_admin());
  end if;

  if not exists (select 1 from pg_policies where schemaname = 'public' and tablename = 'tenants' and policyname = 'tenants_update_owner_admin') then
    create policy tenants_update_owner_admin on public.tenants for update to authenticated using (public.has_tenant_role(id, array['owner','admin'])) with check (public.has_tenant_role(id, array['owner','admin']));
  end if;

  if not exists (select 1 from pg_policies where schemaname = 'public' and tablename = 'tenant_members' and policyname = 'tenant_members_select_members') then
    create policy tenant_members_select_members on public.tenant_members for select to authenticated using (public.is_tenant_member(tenant_id));
  end if;

  if not exists (select 1 from pg_policies where schemaname = 'public' and tablename = 'tenant_members' and policyname = 'tenant_members_manage_owner_admin') then
    create policy tenant_members_manage_owner_admin on public.tenant_members for all to authenticated using (public.has_tenant_role(tenant_id, array['owner','admin'])) with check (public.has_tenant_role(tenant_id, array['owner','admin']));
  end if;

  if not exists (select 1 from pg_policies where schemaname = 'public' and tablename = 'plans' and policyname = 'plans_select_authenticated') then
    create policy plans_select_authenticated on public.plans for select to authenticated using (is_active = true or public.is_platform_admin());
  end if;

  if not exists (select 1 from pg_policies where schemaname = 'public' and tablename = 'subscriptions' and policyname = 'subscriptions_select_members') then
    create policy subscriptions_select_members on public.subscriptions for select to authenticated using (public.is_tenant_member(tenant_id));
  end if;

  if not exists (select 1 from pg_policies where schemaname = 'public' and tablename = 'subscription_usage' and policyname = 'subscription_usage_select_members') then
    create policy subscription_usage_select_members on public.subscription_usage for select to authenticated using (public.is_tenant_member(tenant_id));
  end if;

  if not exists (select 1 from pg_policies where schemaname = 'public' and tablename = 'patients' and policyname = 'patients_select_by_access') then
    create policy patients_select_by_access on public.patients for select to authenticated using (public.can_read_patient_record(tenant_id, id));
  end if;

  if not exists (select 1 from pg_policies where schemaname = 'public' and tablename = 'patients' and policyname = 'patients_insert_staff') then
    create policy patients_insert_staff on public.patients for insert to authenticated with check (public.has_tenant_role(tenant_id, array['owner','admin','nutritionist','assistant']));
  end if;

  if not exists (select 1 from pg_policies where schemaname = 'public' and tablename = 'patients' and policyname = 'patients_update_staff') then
    create policy patients_update_staff on public.patients for update to authenticated using (public.has_tenant_role(tenant_id, array['owner','admin','nutritionist','assistant'])) with check (public.has_tenant_role(tenant_id, array['owner','admin','nutritionist','assistant']));
  end if;

  if not exists (select 1 from pg_policies where schemaname = 'public' and tablename = 'patient_profiles' and policyname = 'patient_profiles_select_by_access') then
    create policy patient_profiles_select_by_access on public.patient_profiles for select to authenticated using (public.can_read_patient_record(tenant_id, patient_id));
  end if;

  if not exists (select 1 from pg_policies where schemaname = 'public' and tablename = 'patient_profiles' and policyname = 'patient_profiles_write_clinical_staff') then
    create policy patient_profiles_write_clinical_staff on public.patient_profiles for all to authenticated using (public.has_tenant_role(tenant_id, array['owner','admin','nutritionist'])) with check (public.has_tenant_role(tenant_id, array['owner','admin','nutritionist']));
  end if;

  if not exists (select 1 from pg_policies where schemaname = 'public' and tablename = 'appointments' and policyname = 'appointments_select_members_or_patient') then
    create policy appointments_select_members_or_patient on public.appointments for select to authenticated using (public.is_tenant_member(tenant_id) or public.is_patient_self(tenant_id, patient_id));
  end if;

  if not exists (select 1 from pg_policies where schemaname = 'public' and tablename = 'appointments' and policyname = 'appointments_write_staff') then
    create policy appointments_write_staff on public.appointments for all to authenticated using (public.has_tenant_role(tenant_id, array['owner','admin','nutritionist','assistant'])) with check (public.has_tenant_role(tenant_id, array['owner','admin','nutritionist','assistant']));
  end if;

  if not exists (select 1 from pg_policies where schemaname = 'public' and tablename = 'clinical_notes' and policyname = 'clinical_notes_select_clinical') then
    create policy clinical_notes_select_clinical on public.clinical_notes for select to authenticated using (public.has_tenant_role(tenant_id, array['owner','admin','nutritionist','viewer']) or (visibility = 'patient_portal' and public.is_patient_self(tenant_id, patient_id)));
  end if;

  if not exists (select 1 from pg_policies where schemaname = 'public' and tablename = 'clinical_notes' and policyname = 'clinical_notes_write_clinical') then
    create policy clinical_notes_write_clinical on public.clinical_notes for all to authenticated using (public.has_tenant_role(tenant_id, array['owner','admin','nutritionist'])) with check (public.has_tenant_role(tenant_id, array['owner','admin','nutritionist']));
  end if;

  if not exists (select 1 from pg_policies where schemaname = 'public' and tablename = 'diets' and policyname = 'diets_select_clinical_or_patient') then
    create policy diets_select_clinical_or_patient on public.diets for select to authenticated using (public.has_tenant_role(tenant_id, array['owner','admin','nutritionist','viewer']) or public.is_patient_self(tenant_id, patient_id));
  end if;

  if not exists (select 1 from pg_policies where schemaname = 'public' and tablename = 'diets' and policyname = 'diets_write_clinical') then
    create policy diets_write_clinical on public.diets for all to authenticated using (public.has_tenant_role(tenant_id, array['owner','admin','nutritionist'])) with check (public.has_tenant_role(tenant_id, array['owner','admin','nutritionist']));
  end if;

  if not exists (select 1 from pg_policies where schemaname = 'public' and tablename = 'workout_routines' and policyname = 'workout_routines_select_clinical_or_patient') then
    create policy workout_routines_select_clinical_or_patient on public.workout_routines for select to authenticated using (public.has_tenant_role(tenant_id, array['owner','admin','nutritionist','viewer']) or public.is_patient_self(tenant_id, patient_id));
  end if;

  if not exists (select 1 from pg_policies where schemaname = 'public' and tablename = 'workout_routines' and policyname = 'workout_routines_write_clinical') then
    create policy workout_routines_write_clinical on public.workout_routines for all to authenticated using (public.has_tenant_role(tenant_id, array['owner','admin','nutritionist'])) with check (public.has_tenant_role(tenant_id, array['owner','admin','nutritionist']));
  end if;

  if not exists (select 1 from pg_policies where schemaname = 'public' and tablename = 'ai_generations' and policyname = 'ai_generations_select_clinical') then
    create policy ai_generations_select_clinical on public.ai_generations for select to authenticated using (public.has_tenant_role(tenant_id, array['owner','admin','nutritionist']));
  end if;

  if not exists (select 1 from pg_policies where schemaname = 'public' and tablename = 'ai_generations' and policyname = 'ai_generations_insert_clinical') then
    create policy ai_generations_insert_clinical on public.ai_generations for insert to authenticated with check (public.has_tenant_role(tenant_id, array['owner','admin','nutritionist']));
  end if;

  if not exists (select 1 from pg_policies where schemaname = 'public' and tablename = 'tenant_nutrition_settings' and policyname = 'tenant_nutrition_settings_select_members') then
    create policy tenant_nutrition_settings_select_members on public.tenant_nutrition_settings for select to authenticated using (public.is_tenant_member(tenant_id));
  end if;

  if not exists (select 1 from pg_policies where schemaname = 'public' and tablename = 'tenant_nutrition_settings' and policyname = 'tenant_nutrition_settings_manage_owner_admin') then
    create policy tenant_nutrition_settings_manage_owner_admin on public.tenant_nutrition_settings for all to authenticated using (public.has_tenant_role(tenant_id, array['owner','admin'])) with check (public.has_tenant_role(tenant_id, array['owner','admin']));
  end if;

  if not exists (select 1 from pg_policies where schemaname = 'public' and tablename = 'billing_events' and policyname = 'billing_events_select_owner_admin') then
    create policy billing_events_select_owner_admin on public.billing_events for select to authenticated using (public.has_tenant_role(tenant_id, array['owner','admin']));
  end if;
end $$;

grant usage on schema public to anon, authenticated;
grant select, insert, update, delete on all tables in schema public to authenticated;
grant usage, select on all sequences in schema public to authenticated;
grant execute on function public.is_platform_admin() to authenticated;
grant execute on function public.is_tenant_member(uuid) to authenticated;
grant execute on function public.has_tenant_role(uuid, text[]) to authenticated;
grant execute on function public.bootstrap_current_user_demo_tenant(text) to authenticated;
grant execute on function public.get_tenant_entitlements(uuid) to authenticated;
