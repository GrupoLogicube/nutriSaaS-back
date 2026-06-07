select
  id,
  zinc_mg,
  hierro_mg,
  proteina_g,
  colesterol_mg,
  carbohidratos_g,
  energia_kcal
from public.tca_ecuador_2021
where id in (20, 34, 208, 482, 524, 703, 877, 880, 881)
order by id;
