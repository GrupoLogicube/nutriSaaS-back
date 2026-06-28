with per_source as (
  select
    ns.code,
    count(f.id)::integer as foods_count,
    count(distinct fc.id)::integer as categories_count,
    count(*) filter (where f.external_code is null)::integer as missing_external_code,
    (
      select count(*)
      from (
        select source_id, external_code
        from public.foods
        group by source_id, external_code
        having count(*) > 1
      ) duplicates
      where duplicates.source_id = ns.id
    )::integer as duplicate_external_codes
  from public.nutrition_sources ns
  left join public.foods f on f.source_id = ns.id
  left join public.food_categories fc on fc.id = f.category_id
  where ns.code in ('incap', 'usfq')
  group by ns.id, ns.code
),
checks as (
  select
    code || '_foods_count' as check_name,
    foods_count::text as value
  from per_source
  union all
  select code || '_categories_count', categories_count::text from per_source
  union all
  select code || '_duplicate_external_codes', duplicate_external_codes::text from per_source
  union all
  select code || '_missing_external_code', missing_external_code::text from per_source
  union all
  select
    'incap_empty_values_preserved_as_null',
    count(*)::text
  from public.foods f
  join public.nutrition_sources ns on ns.id = f.source_id
  where ns.code = 'incap'
    and f.external_code = '16013'
    and f.fat_g is null
)
select *
from checks
order by check_name;
