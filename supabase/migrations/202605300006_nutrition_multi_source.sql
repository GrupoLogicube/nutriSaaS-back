create extension if not exists pg_trgm with schema extensions;
create extension if not exists unaccent with schema extensions;

create or replace function public.set_updated_at()
returns trigger
language plpgsql
as $$
begin
  new.updated_at = now();
  return new;
end;
$$;

create table if not exists public.nutrition_sources (
  id uuid primary key default gen_random_uuid(),
  code text unique not null,
  name text not null,
  country text,
  version text,
  description text,
  is_active boolean not null default true,
  metadata jsonb not null default '{}'::jsonb,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table if not exists public.food_categories (
  id uuid primary key default gen_random_uuid(),
  source_id uuid not null references public.nutrition_sources(id),
  code text,
  name text not null,
  sort_order integer,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint food_categories_source_name_unique unique (source_id, name)
);

create table if not exists public.foods (
  id uuid primary key default gen_random_uuid(),
  source_id uuid not null references public.nutrition_sources(id),
  category_id uuid references public.food_categories(id),
  external_code text not null,
  name text not null,
  normalized_name text not null,
  portion_label text,
  weight_g numeric,
  energy_kcal numeric,
  carbohydrates_g numeric,
  protein_g numeric,
  fat_g numeric,
  fiber_g numeric,
  calcium_mg numeric,
  iron_mg numeric,
  cholesterol_mg numeric,
  sodium_mg numeric,
  vitamin_c_mg numeric,
  zinc_mg numeric,
  source_page integer,
  raw_data jsonb not null default '{}'::jsonb,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint foods_source_external_code_unique unique (source_id, external_code)
);

create index if not exists foods_source_id_idx on public.foods (source_id);
create index if not exists foods_category_id_idx on public.foods (category_id);
create index if not exists foods_normalized_name_idx on public.foods (normalized_name);
create index if not exists foods_name_trgm_idx on public.foods using gin (normalized_name extensions.gin_trgm_ops);
create index if not exists foods_name_fts_idx
on public.foods using gin (to_tsvector('spanish', coalesce(name, '') || ' ' || coalesce(normalized_name, '')));
create index if not exists food_categories_source_id_idx on public.food_categories (source_id);

do $$
begin
  if not exists (select 1 from pg_trigger where tgname = 'set_nutrition_sources_updated_at') then
    create trigger set_nutrition_sources_updated_at
    before update on public.nutrition_sources
    for each row execute function public.set_updated_at();
  end if;

  if not exists (select 1 from pg_trigger where tgname = 'set_food_categories_updated_at') then
    create trigger set_food_categories_updated_at
    before update on public.food_categories
    for each row execute function public.set_updated_at();
  end if;

  if not exists (select 1 from pg_trigger where tgname = 'set_foods_updated_at') then
    create trigger set_foods_updated_at
    before update on public.foods
    for each row execute function public.set_updated_at();
  end if;
end $$;

alter table public.nutrition_sources enable row level security;
alter table public.food_categories enable row level security;
alter table public.foods enable row level security;
do $$
begin
  if to_regclass('public.tenants') is not null then
    create table if not exists public.tenant_nutrition_settings (
      id uuid primary key default gen_random_uuid(),
      tenant_id uuid not null references public.tenants(id),
      default_source_id uuid references public.nutrition_sources(id),
      allow_source_switching boolean not null default true,
      created_at timestamptz not null default now(),
      updated_at timestamptz not null default now(),
      constraint tenant_nutrition_settings_tenant_unique unique (tenant_id)
    );

    create index if not exists tenant_nutrition_settings_tenant_id_idx
    on public.tenant_nutrition_settings (tenant_id);

    alter table public.tenant_nutrition_settings enable row level security;

    if not exists (select 1 from pg_trigger where tgname = 'set_tenant_nutrition_settings_updated_at') then
      create trigger set_tenant_nutrition_settings_updated_at
      before update on public.tenant_nutrition_settings
      for each row execute function public.set_updated_at();
    end if;
  end if;

  if to_regclass('public.diets') is not null then
    alter table public.diets
      add column if not exists nutrition_source_id uuid references public.nutrition_sources(id),
      add column if not exists nutrition_source_code text;

    create index if not exists diets_nutrition_source_id_idx
    on public.diets (nutrition_source_id);
  end if;
end $$;

do $$
begin
  if not exists (
    select 1 from pg_policies
    where schemaname = 'public' and tablename = 'nutrition_sources'
      and policyname = 'Active nutrition sources visible to authenticated users'
  ) then
    create policy "Active nutrition sources visible to authenticated users"
    on public.nutrition_sources for select
    to authenticated
    using (is_active = true);
  end if;

  if to_regprocedure('public.is_platform_admin()') is not null and not exists (
    select 1 from pg_policies
    where schemaname = 'public' and tablename = 'nutrition_sources'
      and policyname = 'Platform admins manage nutrition sources'
  ) then
    create policy "Platform admins manage nutrition sources"
    on public.nutrition_sources for all
    to authenticated
    using (public.is_platform_admin())
    with check (public.is_platform_admin());
  end if;

  if not exists (
    select 1 from pg_policies
    where schemaname = 'public' and tablename = 'food_categories'
      and policyname = 'Food categories visible to authenticated users'
  ) then
    create policy "Food categories visible to authenticated users"
    on public.food_categories for select
    to authenticated
    using (
      exists (
        select 1 from public.nutrition_sources ns
        where ns.id = source_id and ns.is_active = true
      )
    );
  end if;

  if to_regprocedure('public.is_platform_admin()') is not null and not exists (
    select 1 from pg_policies
    where schemaname = 'public' and tablename = 'food_categories'
      and policyname = 'Platform admins manage food categories'
  ) then
    create policy "Platform admins manage food categories"
    on public.food_categories for all
    to authenticated
    using (public.is_platform_admin())
    with check (public.is_platform_admin());
  end if;

  if not exists (
    select 1 from pg_policies
    where schemaname = 'public' and tablename = 'foods'
      and policyname = 'Foods visible to authenticated users'
  ) then
    create policy "Foods visible to authenticated users"
    on public.foods for select
    to authenticated
    using (
      exists (
        select 1 from public.nutrition_sources ns
        where ns.id = source_id and ns.is_active = true
      )
    );
  end if;

  if to_regprocedure('public.is_platform_admin()') is not null and not exists (
    select 1 from pg_policies
    where schemaname = 'public' and tablename = 'foods'
      and policyname = 'Platform admins manage foods'
  ) then
    create policy "Platform admins manage foods"
    on public.foods for all
    to authenticated
    using (public.is_platform_admin())
    with check (public.is_platform_admin());
  end if;

  if to_regclass('public.tenant_nutrition_settings') is not null
    and to_regprocedure('public.is_tenant_member(uuid)') is not null
    and not exists (
    select 1 from pg_policies
    where schemaname = 'public' and tablename = 'tenant_nutrition_settings'
      and policyname = 'Tenant members read nutrition settings'
  ) then
    create policy "Tenant members read nutrition settings"
    on public.tenant_nutrition_settings for select
    to authenticated
    using (public.is_tenant_member(tenant_id));
  end if;

  if to_regclass('public.tenant_nutrition_settings') is not null
    and to_regprocedure('public.has_tenant_role(uuid,text[])') is not null
    and not exists (
    select 1 from pg_policies
    where schemaname = 'public' and tablename = 'tenant_nutrition_settings'
      and policyname = 'Tenant owners manage nutrition settings'
  ) then
    create policy "Tenant owners manage nutrition settings"
    on public.tenant_nutrition_settings for all
    to authenticated
    using (public.has_tenant_role(tenant_id, array['owner', 'admin']))
    with check (public.has_tenant_role(tenant_id, array['owner', 'admin']));
  end if;
end $$;

insert into public.nutrition_sources (code, name, country, version, description, metadata, is_active)
values
  (
    'incap',
    'Tabla de Composicion de Alimentos INCAP',
    'Centroamerica',
    'validada',
    'Fuente nutricional INCAP importada desde CSV validado.',
    '{"importer": "nutrition_multi_source", "expected_records": 1448}'::jsonb,
    true
  ),
  (
    'usfq',
    'Base de Datos Nutricional USFQ',
    'Ecuador',
    '2021',
    'Tabla de composicion quimica de alimentos basada en nutrientes de interes para la poblacion ecuatoriana, USFQ, 2021.',
    '{"source_table": "tca_ecuador_2021"}'::jsonb,
    true
  )
on conflict (code) do update
set
  name = excluded.name,
  country = excluded.country,
  version = excluded.version,
  description = excluded.description,
  metadata = public.nutrition_sources.metadata || excluded.metadata,
  is_active = excluded.is_active,
  updated_at = now();

do $$
begin
  if to_regclass('public.tca_ecuador_2021') is not null then
    insert into public.food_categories (source_id, code, name, sort_order)
    select
      ns.id,
      nullif(split_part(tca.grupo, '.', 1), '') as code,
      tca.grupo as name,
      nullif(split_part(tca.grupo, '.', 1), '')::integer as sort_order
    from public.tca_ecuador_2021 tca
    cross join public.nutrition_sources ns
    where ns.code = 'usfq'
    group by ns.id, tca.grupo
    on conflict (source_id, name) do update
    set
      code = excluded.code,
      sort_order = excluded.sort_order,
      updated_at = now();

    insert into public.foods (
      source_id,
      category_id,
      external_code,
      name,
      normalized_name,
      portion_label,
      weight_g,
      energy_kcal,
      carbohydrates_g,
      protein_g,
      fat_g,
      fiber_g,
      calcium_mg,
      iron_mg,
      cholesterol_mg,
      sodium_mg,
      vitamin_c_mg,
      zinc_mg,
      source_page,
      raw_data
    )
    select
      ns.id,
      fc.id,
      tca.id::text,
      tca.alimento,
      lower(extensions.unaccent(tca.alimento)),
      tca.porcion_base,
      tca.cantidad_base,
      tca.energia_kcal,
      tca.carbohidratos_g,
      tca.proteina_g,
      tca.grasa_total_g,
      tca.fibra_g,
      tca.calcio_mg,
      tca.hierro_mg,
      tca.colesterol_mg,
      tca.sodio_mg,
      tca.vitamina_c_mg,
      tca.zinc_mg,
      tca.pagina_pdf,
      to_jsonb(tca)
    from public.tca_ecuador_2021 tca
    join public.nutrition_sources ns on ns.code = 'usfq'
    join public.food_categories fc on fc.source_id = ns.id and fc.name = tca.grupo
    on conflict (source_id, external_code) do update
    set
      category_id = excluded.category_id,
      name = excluded.name,
      normalized_name = excluded.normalized_name,
      portion_label = excluded.portion_label,
      weight_g = excluded.weight_g,
      energy_kcal = excluded.energy_kcal,
      carbohydrates_g = excluded.carbohydrates_g,
      protein_g = excluded.protein_g,
      fat_g = excluded.fat_g,
      fiber_g = excluded.fiber_g,
      calcium_mg = excluded.calcium_mg,
      iron_mg = excluded.iron_mg,
      cholesterol_mg = excluded.cholesterol_mg,
      sodium_mg = excluded.sodium_mg,
      vitamin_c_mg = excluded.vitamin_c_mg,
      zinc_mg = excluded.zinc_mg,
      source_page = excluded.source_page,
      raw_data = excluded.raw_data,
      updated_at = now();
  end if;
end $$;
