import {
  assertPlatformAdmin,
  corsHeaders,
  ensureMethod,
  getServiceClient,
  jsonResponse,
  safeErrorMessage,
} from '../_shared/platform_admin.ts';

const listTenants = async (supabase: ReturnType<typeof getServiceClient>) => {
  const { data: tenants, error } = await supabase
    .from('tenants')
    .select('id,name,slug,status,created_at,updated_at,created_by,settings')
    .order('created_at', { ascending: false });

  if (error) throw error;

  const tenantIds = (tenants || []).map((tenant) => tenant.id);
  if (tenantIds.length === 0) return [];

  const [
    { data: members, error: membersError },
    { data: subscriptions, error: subscriptionsError },
    { data: patients, error: patientsError },
  ] = await Promise.all([
    supabase
      .from('tenant_members')
      .select('tenant_id,role,status,user_id')
      .in('tenant_id', tenantIds),
    supabase
      .from('subscriptions')
      .select('tenant_id,status,current_period_end,plans(code,name)')
      .in('tenant_id', tenantIds)
      .order('created_at', { ascending: false }),
    supabase
      .from('patients')
      .select('tenant_id,id')
      .in('tenant_id', tenantIds),
  ]);

  if (membersError) throw membersError;
  if (subscriptionsError) throw subscriptionsError;
  if (patientsError) throw patientsError;

  const memberCounts = new Map<string, number>();
  const ownerByTenant = new Map<string, string | null>();
  const patientCounts = new Map<string, number>();
  const subscriptionByTenant = new Map<string, Record<string, unknown>>();

  (members || []).forEach((member) => {
    memberCounts.set(member.tenant_id, (memberCounts.get(member.tenant_id) || 0) + 1);
    if (member.role === 'owner' && member.status === 'active' && !ownerByTenant.has(member.tenant_id)) {
      ownerByTenant.set(member.tenant_id, member.user_id);
    }
  });

  (patients || []).forEach((patient) => {
    patientCounts.set(patient.tenant_id, (patientCounts.get(patient.tenant_id) || 0) + 1);
  });

  (subscriptions || []).forEach((subscription) => {
    if (!subscriptionByTenant.has(subscription.tenant_id)) {
      subscriptionByTenant.set(subscription.tenant_id, subscription);
    }
  });

  return (tenants || []).map((tenant) => {
    const subscription = subscriptionByTenant.get(tenant.id) || null;
    const plan = Array.isArray(subscription?.plans) ? subscription?.plans?.[0] : subscription?.plans;
    return {
      id: tenant.id,
      nombre: tenant.name,
      name: tenant.name,
      slug: tenant.slug,
      estado: tenant.status,
      status: tenant.status,
      created_at: tenant.created_at,
      updated_at: tenant.updated_at,
      owner_user_id: ownerByTenant.get(tenant.id) || null,
      member_count: memberCounts.get(tenant.id) || 0,
      patient_count: patientCounts.get(tenant.id) || 0,
      plan_code: plan?.code || null,
      plan_name: plan?.name || null,
      subscription_status: subscription?.status || null,
      current_period_end: subscription?.current_period_end || null,
      settings: tenant.settings || {},
    };
  });
};

Deno.serve(async (req) => {
  const methodResponse = ensureMethod(req, ['GET']);
  if (methodResponse) return methodResponse;

  const supabase = getServiceClient();

  try {
    await assertPlatformAdmin(supabase, req);
    const tenants = await listTenants(supabase);
    return jsonResponse({ data: tenants });
  } catch (error) {
    return jsonResponse({ error: safeErrorMessage(error) }, Number((error as { status?: number })?.status || 500));
  }
});
