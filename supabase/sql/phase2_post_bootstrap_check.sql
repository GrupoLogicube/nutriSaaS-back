select
  (
    select count(*)
    from public.profiles
    where email = 'alejor500@gmail.com'
  ) as profiles_found,
  (
    select count(*)
    from public.tenant_members
    where user_id = '03f9d53f-25fa-4461-8fde-bd32751239bc'
      and status = 'active'
  ) as active_memberships;
