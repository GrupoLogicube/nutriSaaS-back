import {
  assertPlatformAdmin,
  ensureMethod,
  getServiceClient,
  jsonResponse,
  parseJsonBody,
  randomToken,
  safeErrorMessage,
} from '../_shared/platform_admin.ts';

type InviteBody = {
  tenant_id: string;
  email: string;
  role: 'owner' | 'admin' | 'nutritionist' | 'assistant' | 'viewer';
};

Deno.serve(async (req) => {
  const methodResponse = ensureMethod(req, ['POST']);
  if (methodResponse) return methodResponse;

  const supabase = getServiceClient();

  try {
    const { user } = await assertPlatformAdmin(supabase, req);
    const body = await parseJsonBody<InviteBody>(req);

    if (!body.tenant_id || !body.email || !body.role) {
      throw Object.assign(new Error('tenant_id, email y role son requeridos.'), { status: 400 });
    }

    const normalizedEmail = body.email.trim().toLowerCase();

    const { data: existingProfile, error: profileError } = await supabase
      .from('profiles')
      .select('id,email,full_name')
      .ilike('email', normalizedEmail)
      .maybeSingle();

    if (profileError) throw profileError;

    if (existingProfile) {
      const { error: membershipError } = await supabase
        .from('tenant_members')
        .upsert({
          tenant_id: body.tenant_id,
          user_id: existingProfile.id,
          role: body.role,
          status: 'active',
          invited_by: user.id,
          joined_at: new Date().toISOString(),
        }, { onConflict: 'tenant_id,user_id' });

      if (membershipError) throw membershipError;

      return jsonResponse({
        data: {
          source: 'member',
          user_id: existingProfile.id,
          email: existingProfile.email,
          full_name: existingProfile.full_name,
          role: body.role,
          status: 'active',
          patient_count: 0,
        },
      }, 201);
    }

    const { data: invitation, error: invitationError } = await supabase
      .from('tenant_invitations')
      .insert({
        tenant_id: body.tenant_id,
        email: normalizedEmail,
        role: body.role,
        status: 'pending',
        invited_by: user.id,
        token_hash: randomToken(),
      })
      .select('id,email,role,status,expires_at,accepted_at,created_at')
      .single();

    if (invitationError) throw invitationError;

    return jsonResponse({
      data: {
        source: 'invitation',
        ...invitation,
      },
    }, 201);
  } catch (error) {
    return jsonResponse({ error: safeErrorMessage(error) }, Number((error as { status?: number })?.status || 500));
  }
});
