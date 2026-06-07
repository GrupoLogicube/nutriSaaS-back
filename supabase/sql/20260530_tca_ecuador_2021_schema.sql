create table if not exists public.tca_ecuador_2021 (
  id integer primary key,
  fuente integer,
  grupo text not null,
  alimento text not null,
  nombre_ingles text,
  porcion_base text not null,
  cantidad_base numeric(12,2) not null,
  energia_kcal numeric(12,2),
  proteina_g numeric(12,2),
  grasa_total_g numeric(12,2),
  carbohidratos_g numeric(12,2),
  fibra_g numeric(12,2),
  ags_g numeric(12,2),
  agm_g numeric(12,2),
  agpi_g numeric(12,2),
  colesterol_mg numeric(12,2),
  calcio_mg numeric(12,2),
  fosforo_mg numeric(12,2),
  hierro_mg numeric(12,2),
  potasio_mg numeric(12,2),
  sodio_mg numeric(12,2),
  zinc_mg numeric(12,2),
  vitamina_c_mg numeric(12,2),
  vitamina_a_ug_ere numeric(12,2),
  folatos_ug numeric(12,2),
  vitamina_b12_ug numeric(12,2),
  pagina_pdf integer,
  estado_validacion text not null default 'validado',
  fuente_documental text not null default 'Tabla de composicion quimica de los alimentos: basada en nutrientes de interes para la poblacion ecuatoriana, USFQ, 2021',
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

alter table public.tca_ecuador_2021
  add column if not exists fuente integer,
  add column if not exists grupo text,
  add column if not exists alimento text,
  add column if not exists nombre_ingles text,
  add column if not exists porcion_base text,
  add column if not exists cantidad_base numeric(12,2),
  add column if not exists energia_kcal numeric(12,2),
  add column if not exists proteina_g numeric(12,2),
  add column if not exists grasa_total_g numeric(12,2),
  add column if not exists carbohidratos_g numeric(12,2),
  add column if not exists fibra_g numeric(12,2),
  add column if not exists ags_g numeric(12,2),
  add column if not exists agm_g numeric(12,2),
  add column if not exists agpi_g numeric(12,2),
  add column if not exists colesterol_mg numeric(12,2),
  add column if not exists calcio_mg numeric(12,2),
  add column if not exists fosforo_mg numeric(12,2),
  add column if not exists hierro_mg numeric(12,2),
  add column if not exists potasio_mg numeric(12,2),
  add column if not exists sodio_mg numeric(12,2),
  add column if not exists zinc_mg numeric(12,2),
  add column if not exists vitamina_c_mg numeric(12,2),
  add column if not exists vitamina_a_ug_ere numeric(12,2),
  add column if not exists folatos_ug numeric(12,2),
  add column if not exists vitamina_b12_ug numeric(12,2),
  add column if not exists pagina_pdf integer,
  add column if not exists estado_validacion text not null default 'validado',
  add column if not exists fuente_documental text not null default 'Tabla de composicion quimica de los alimentos: basada en nutrientes de interes para la poblacion ecuatoriana, USFQ, 2021',
  add column if not exists created_at timestamptz not null default now(),
  add column if not exists updated_at timestamptz not null default now();

alter table public.tca_ecuador_2021
  alter column estado_validacion set default 'validado',
  alter column fuente_documental set default 'Tabla de composicion quimica de los alimentos: basada en nutrientes de interes para la poblacion ecuatoriana, USFQ, 2021';

create table if not exists public.tca_ecuador_2021_observaciones (
  id bigserial primary key,
  alimento_id integer not null,
  pagina_pdf integer,
  tipo text not null,
  detalle text not null,
  accion text not null default 'conservado sin modificacion por coincidencia con la fuente original',
  created_at timestamptz not null default now()
);

alter table public.tca_ecuador_2021_observaciones
  add column if not exists alimento_id integer,
  add column if not exists pagina_pdf integer,
  add column if not exists tipo text,
  add column if not exists detalle text,
  add column if not exists accion text not null default 'conservado sin modificacion por coincidencia con la fuente original',
  add column if not exists created_at timestamptz not null default now();

do $$
begin
  if not exists (
    select 1
    from pg_constraint
    where conname = 'tca_ecuador_2021_observaciones_alimento_id_fkey'
      and conrelid = 'public.tca_ecuador_2021_observaciones'::regclass
  ) then
    alter table public.tca_ecuador_2021_observaciones
      add constraint tca_ecuador_2021_observaciones_alimento_id_fkey
      foreign key (alimento_id)
      references public.tca_ecuador_2021(id)
      on delete cascade;
  end if;
end $$;

create unique index if not exists tca_ecuador_2021_observaciones_unique_idx
on public.tca_ecuador_2021_observaciones (alimento_id, tipo, detalle);

create index if not exists tca_ecuador_2021_grupo_idx
on public.tca_ecuador_2021 (grupo);

create index if not exists tca_ecuador_2021_pagina_pdf_idx
on public.tca_ecuador_2021 (pagina_pdf);

create index if not exists tca_ecuador_2021_observaciones_alimento_id_idx
on public.tca_ecuador_2021_observaciones (alimento_id);

alter table public.tca_ecuador_2021 enable row level security;
alter table public.tca_ecuador_2021_observaciones enable row level security;
