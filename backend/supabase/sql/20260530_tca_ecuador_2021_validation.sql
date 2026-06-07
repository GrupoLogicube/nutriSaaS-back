with checks as (
  select 'tca_rows' as check_name, count(*)::text as value
  from public.tca_ecuador_2021
  union all
  select 'observaciones_rows', count(*)::text
  from public.tca_ecuador_2021_observaciones
  union all
  select 'porcion_100ml_grupo_11', count(*)::text
  from public.tca_ecuador_2021
  where porcion_base = '100 ml' and grupo like '11.%'
  union all
  select 'grupo_11_total', count(*)::text
  from public.tca_ecuador_2021
  where grupo like '11.%'
  union all
  select 'porcion_100g_resto', count(*)::text
  from public.tca_ecuador_2021
  where porcion_base = '100 g' and grupo not like '11.%'
  union all
  select 'resto_total', count(*)::text
  from public.tca_ecuador_2021
  where grupo not like '11.%'
  union all
  select 'ids_observados_presentes', count(*)::text
  from public.tca_ecuador_2021
  where id in (20, 34, 208, 482, 524, 703, 877, 880, 881)
  union all
  select 'valores_criticos_ok', count(*)::text
  from public.tca_ecuador_2021
  where
    (id = 20 and zinc_mg = 213.00)
    or (id = 34 and hierro_mg = 392.00)
    or (id = 208 and proteina_g = -1.00)
    or (id = 482 and zinc_mg = 272.00)
    or (id = 524 and colesterol_mg = 3100.00)
    or (id = 703 and carbohidratos_g = 240.00)
    or (id = 877 and energia_kcal = 222.00)
    or (id = 880 and carbohidratos_g = 104.00)
    or (id = 881 and energia_kcal = 28.00)
)
select *
from checks
order by check_name;
