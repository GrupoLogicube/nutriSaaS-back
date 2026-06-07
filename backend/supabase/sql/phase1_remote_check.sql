select
  to_regclass('public.profiles') is not null as has_profiles_table,
  to_regclass('public.tenant_members') is not null as has_tenant_members_table,
  to_regclass('public.patients') is not null as has_patients_table,
  to_regclass('public.subscription_usage') is not null as has_subscription_usage_table,
  (
    select count(*)
    from auth.users
    where email in (
      'platform.demo@nutrisaas.local',
      'owner.demo@nutrisaas.local',
      'nutri.demo@nutrisaas.local',
      'paciente.demo@nutrisaas.local'
    )
  ) as demo_users;
