select
  id,
  email,
  full_name,
  platform_role
from public.profiles
where email = 'alejor500@gmail.com';

select
  count(*) as active_memberships
from public.tenant_members
where user_id = '03f9d53f-25fa-4461-8fde-bd32751239bc'
  and status = 'active';
