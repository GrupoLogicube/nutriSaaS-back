alter table public.plans
  add column if not exists sort_order integer not null default 0,
  add column if not exists provider_price_ids jsonb not null default '{}'::jsonb,
  add column if not exists metadata jsonb not null default '{}'::jsonb;

create unique index if not exists subscriptions_provider_subscription_uidx
on public.subscriptions (provider, provider_subscription_id)
where provider is not null and provider_subscription_id is not null;

create unique index if not exists subscriptions_one_current_per_tenant_uidx
on public.subscriptions (tenant_id)
where status in ('trialing', 'active', 'past_due');

create unique index if not exists subscription_usage_subscription_period_uidx
on public.subscription_usage (subscription_id, period_start, period_end);

create table if not exists public.billing_events (
  id uuid primary key default gen_random_uuid(),
  tenant_id uuid not null references public.tenants(id) on delete cascade,
  subscription_id uuid references public.subscriptions(id) on delete set null,
  provider text not null check (provider in ('stripe', 'local', 'manual')),
  event_type text not null,
  provider_event_id text,
  status text not null default 'pending' check (status in ('pending', 'processed', 'failed', 'ignored')),
  payload jsonb not null default '{}'::jsonb,
  processed_at timestamptz,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create unique index if not exists billing_events_provider_event_uidx
on public.billing_events (provider, provider_event_id)
where provider_event_id is not null;

create index if not exists billing_events_tenant_id_idx on public.billing_events (tenant_id);
create index if not exists billing_events_subscription_id_idx on public.billing_events (subscription_id);
create index if not exists billing_events_status_idx on public.billing_events (status);

drop trigger if exists set_billing_events_updated_at on public.billing_events;
create trigger set_billing_events_updated_at before update on public.billing_events
for each row execute function public.set_updated_at();

alter table public.billing_events enable row level security;
alter table public.billing_events force row level security;

drop policy if exists "Tenant owners read billing events" on public.billing_events;
create policy "Tenant owners read billing events"
on public.billing_events for select
to authenticated
using (public.has_tenant_role(tenant_id, array['owner', 'admin']));

drop policy if exists "Platform admins manage billing events" on public.billing_events;
create policy "Platform admins manage billing events"
on public.billing_events for all
to authenticated
using (public.is_platform_admin())
with check (public.is_platform_admin());

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
  v_ai_count integer;
  v_patient_count integer;
  v_seats_count integer;
  v_notes_count integer;
  v_appointments_count integer;
begin
  if not (public.is_platform_admin() or public.is_tenant_member(p_tenant_id)) then
    raise exception 'Forbidden';
  end if;

  select
    s.id as subscription_id,
    s.tenant_id,
    s.plan_id,
    s.status,
    s.current_period_start,
    s.current_period_end,
    p.code as plan_code,
    p.name as plan_name,
    p.features,
    p.limits
  into v_subscription
  from public.subscriptions s
  join public.plans p on p.id = s.plan_id
  where s.tenant_id = p_tenant_id
    and s.status in ('trialing', 'active', 'past_due')
  order by s.created_at desc
  limit 1;

  if v_subscription.subscription_id is null then
    return;
  end if;

  v_period_start := coalesce(v_subscription.current_period_start::date, date_trunc('month', now())::date);
  v_period_end := coalesce(
    v_subscription.current_period_end::date,
    (date_trunc('month', now()) + interval '1 month - 1 day')::date
  );

  select count(*)::integer into v_patient_count
  from public.patients
  where patients.tenant_id = p_tenant_id and patients.status <> 'archived';

  select count(*)::integer into v_seats_count
  from public.tenant_members
  where tenant_members.tenant_id = p_tenant_id
    and tenant_members.status = 'active'
    and tenant_members.role <> 'patient';

  select count(*)::integer into v_ai_count
  from public.ai_generations
  where ai_generations.tenant_id = p_tenant_id
    and ai_generations.status = 'completed'
    and ai_generations.created_at >= v_period_start::timestamptz
    and ai_generations.created_at < (v_period_end + 1)::timestamptz;

  select count(*)::integer into v_notes_count
  from public.clinical_notes
  where clinical_notes.tenant_id = p_tenant_id;

  select count(*)::integer into v_appointments_count
  from public.appointments
  where appointments.tenant_id = p_tenant_id
    and appointments.starts_at >= v_period_start::timestamptz
    and appointments.starts_at < (v_period_end + 1)::timestamptz;

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
      'patients', v_patient_count,
      'seats', v_seats_count,
      'ai_generations', v_ai_count,
      'clinical_notes', v_notes_count,
      'appointments', v_appointments_count,
      'storage_mb', 0
    );
end;
$$;

grant execute on function public.get_tenant_entitlements(uuid) to authenticated;

insert into public.plans (
  code,
  name,
  description,
  price_cents,
  currency,
  billing_interval,
  limits,
  features,
  sort_order,
  provider_price_ids,
  metadata,
  is_active
)
values
  (
    'starter',
    'Starter',
    'Plan inicial para consultorios pequenos.',
    2900,
    'USD',
    'month',
    '{"seats": 1, "patients": 50, "ai_generations": 40, "storage_mb": 512}'::jsonb,
    '{"appointments": true, "clinical_notes": true, "ai_diets": true, "workout_routines": false, "analytics": false, "team": false, "api_access": false}'::jsonb,
    10,
    '{"stripe": null, "local": null}'::jsonb,
    '{"checkout_enabled": false}'::jsonb,
    true
  ),
  (
    'pro',
    'Pro',
    'Plan para equipos clinicos con IA avanzada.',
    7900,
    'USD',
    'month',
    '{"seats": 5, "patients": null, "ai_generations": 500, "storage_mb": 2048}'::jsonb,
    '{"appointments": true, "clinical_notes": true, "ai_diets": true, "workout_routines": true, "analytics": true, "team": true, "api_access": false}'::jsonb,
    20,
    '{"stripe": null, "local": null}'::jsonb,
    '{"checkout_enabled": false}'::jsonb,
    true
  ),
  (
    'enterprise',
    'Enterprise',
    'Plan avanzado con limites personalizados e integraciones.',
    19900,
    'USD',
    'month',
    '{"seats": null, "patients": null, "ai_generations": null, "storage_mb": 10240}'::jsonb,
    '{"appointments": true, "clinical_notes": true, "ai_diets": true, "workout_routines": true, "analytics": true, "team": true, "api_access": true, "white_label": true}'::jsonb,
    30,
    '{"stripe": null, "local": null}'::jsonb,
    '{"checkout_enabled": false, "sales_contact_required": true}'::jsonb,
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
  sort_order = excluded.sort_order,
  provider_price_ids = excluded.provider_price_ids,
  metadata = excluded.metadata,
  is_active = excluded.is_active,
  updated_at = now();
