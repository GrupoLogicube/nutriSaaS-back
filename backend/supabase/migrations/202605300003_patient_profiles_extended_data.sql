alter table public.patient_profiles
add column if not exists extended_data jsonb not null default '{}'::jsonb;
