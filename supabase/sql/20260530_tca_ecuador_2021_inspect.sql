select
  table_name,
  table_type
from information_schema.tables
where table_schema = 'public'
  and table_name in ('tca_ecuador_2021', 'tca_ecuador_2021_observaciones')
order by table_name;

select
  table_name,
  column_name,
  data_type,
  is_nullable,
  column_default
from information_schema.columns
where table_schema = 'public'
  and table_name in ('tca_ecuador_2021', 'tca_ecuador_2021_observaciones')
order by table_name, ordinal_position;

select
  schemaname,
  tablename,
  rowsecurity
from pg_tables
where schemaname = 'public'
  and tablename in ('tca_ecuador_2021', 'tca_ecuador_2021_observaciones')
order by tablename;

select
  schemaname,
  tablename,
  policyname,
  permissive,
  roles,
  cmd,
  qual,
  with_check
from pg_policies
where schemaname = 'public'
  and tablename in ('tca_ecuador_2021', 'tca_ecuador_2021_observaciones')
order by tablename, policyname;
