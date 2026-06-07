import {
  assertPlatformAdmin,
  ensureMethod,
  getServiceClient,
  jsonResponse,
  safeErrorMessage,
} from '../_shared/platform_admin.ts';

const formatMember = (
  member: Record<string, unknown>,
  patientsByNutritionist: Map<string, number>,
) => {
  const profile = Array.isArray(member.profiles) ? member.profiles[0] : member.profiles;
  return {
    id: member.id,
    source: 'member',
    user_id: member.user_id,
    email: profile?.email || null,
    full_name: profile?.full_name || null,
    role: member.role,
    status: member.status,
    joined_at: member.joined_at || null,
    created_at: member.created_at || null,
    patient_count: patientsByNutritionist.get(String(member.user_id)) || 0,
  };
};

const formatInvitation = (invitation: Record<string, unknown>) => ({
  id: invitation.id,
  source: 'invitation',
  email: invitation.email,
  role: invitation.role,
  status: invitation.status,
  expires_at: invitation.expires_at || null,
  accepted_at: invitation.accepted_at || null,
  created_at: invitation.created_at || null,
});

Deno.serve(async (req) => {
  const methodResponse = ensureMethod(req, ['GET', 'POST']);
  if (methodResponse) return methodResponse;

  const supabase = getServiceClient();

  try {
    await assertPlatformAdmin(supabase, req);

    const url = new URL(req.url);
    const body = req.method === 'POST' ? await req.json().catch(() => ({})) : {};
    const tenantId = url.searchParams.get('tenant_id') || body?.tenant_id || null;
    if (!tenantId) {
      throw Object.assign(new Error('tenant_id es requerido.'), { status: 400 });
    }

    const [
      { data: members, error: membersError },
      { data: invitations, error: invitationsError },
      { data: patients, error: patientsError },
      { data: tenant, error: tenantError },
    ] = await Promise.all([
      supabase
        .from('tenant_members')
        .select(
          `
            id,
            tenant_id,
            user_id,
            role,
            status,
            joined_at,
            created_at,
            profiles!tenant_members_user_id_fkey (
              id,
              email,
              full_name
            )
          `
        )
        .eq('tenant_id', tenantId)
        .order('created_at', { ascending: true }),
      supabase
        .from('tenant_invitations')
        .select('id,email,role,status,expires_at,accepted_at,created_at')
        .eq('tenant_id', tenantId)
        .order('created_at', { ascending: false }),
      supabase
        .from('patients')
        .select('assigned_nutritionist_id')
        .eq('tenant_id', tenantId),
      supabase
        .from('tenants')
        .select('id,name,slug,status')
        .eq('id', tenantId)
        .maybeSingle(),
    ]);

    if (membersError) throw membersError;
    if (invitationsError) throw invitationsError;
    if (patientsError) throw patientsError;
    if (tenantError) throw tenantError;
    if (!tenant) throw Object.assign(new Error('Tenant no encontrado.'), { status: 404 });

    const patientsByNutritionist = new Map<string, number>();
    (patients || []).forEach((patient) => {
      if (!patient.assigned_nutritionist_id) return;
      const key = String(patient.assigned_nutritionist_id);
      patientsByNutritionist.set(key, (patientsByNutritionist.get(key) || 0) + 1);
    });

    return jsonResponse({
      data: {
        tenant,
        members: (members || []).map((member) => formatMember(member, patientsByNutritionist)),
        invitations: (invitations || []).map(formatInvitation),
      },
    });
  } catch (error) {
    return jsonResponse({ error: safeErrorMessage(error) }, Number((error as { status?: number })?.status || 500));
  }
});
