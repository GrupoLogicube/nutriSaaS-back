alter table public.clinical_notes
add column if not exists tags text[] not null default '{}'::text[],
add column if not exists pinned boolean not null default false;

create index if not exists clinical_notes_pinned_idx on public.clinical_notes (tenant_id, pinned, updated_at desc);
