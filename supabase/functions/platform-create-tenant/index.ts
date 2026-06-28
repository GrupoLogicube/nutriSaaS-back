import {
  assertPlatformAdmin,
  ensureMethod,
  getServiceClient,
  jsonResponse,
  normalizeSlug,
  parseJsonBody,
  randomToken,
  safeErrorMessage,
} from '../_shared/platform_admin.ts';

type CreateTenantBody = {
  name: string;
  slug?: string;
  owner_email?: string;
  owner_user_id?: string;
  plan_code?: string;
  trial_days?: number;
  settings?: Record<string, unknown>;
};

const addDays = (date: Date, days: number) => {
  const result = new Date(date);
  result.setUTCDate(result.getUTCDate() + days);
  return result;
};

const ensureUniqueSlug = async (
  supabase: ReturnType<typeof getServiceClient>,
  baseSlug: string,
) => {
  let candidate = baseSlug || `tenant-${Date.now()}`;
  let attempt = 1;

  while (true) {
    const { data, error } = await supabase
      .from('tenants')
      .select('id')
      .eq('slug', candidate)
      .maybeSingle();

    if (error) throw error;
    if (!data) return candidate;

    attempt += 1;
    candidate = `${baseSlug}-${attempt}`;
  }
};

const findPlan = async (
  supabase: ReturnType<typeof getServiceClient>,
  planCode: string,
) => {
  const { data, error } = await supabase
    .from('plans')
    .select('id,code,name')
    .eq('code', planCode)
    .maybeSingle();

  if (error) throw error;
  if (!data) throw Object.assign(new Error(`Plan no encontrado: ${planCode}`), { status: 400 });
  return data;
};

const findOwner = async (
  supabase: ReturnType<typeof getServiceClient>,
  body: CreateTenantBody,
) => {
  if (body.owner_user_id) {
    const { data, error } = await supabase
      .from('profiles')
      .select('id,email,full_name')
      .eq('id', body.owner_user_id)
      .maybeSingle();

    if (error) throw error;
    return data;
  }

  if (!body.owner_email) return null;

  const { data, error } = await supabase
    .from('profiles')
    .select('id,email,full_name')
    .ilike('email', body.owner_email.trim().toLowerCase())
    .maybeSingle();

  if (error) throw error;
  return data;
};

Deno.serve(async (req) => {
  const methodResponse = ensureMethod(req, ['POST']);
  if (methodResponse) return methodResponse;

  const supabase = getServiceClient();

  try {
    const { user } = await assertPlatformAdmin(supabase, req);
    const body = await parseJsonBody<CreateTenantBody>(req);

    if (!body.name?.trim()) {
      throw Object.assign(new Error('name es requerido.'), { status: 400 });
    }

    const baseSlug = normalizeSlug(body.slug || body.name);
    if (!baseSlug) {
      throw Object.assign(new Error('No se pudo generar un slug valido.'), { status: 400 });
    }

    const slug = await ensureUniqueSlug(supabase, baseSlug);
    const plan = await findPlan(supabase, body.plan_code || 'starter');
    const ownerProfile = await findOwner(supabase, body);

    const { data: tenant, error: tenantError } = await supabase
      .from('tenants')
      .insert({
        name: body.name.trim(),
        slug,
        status: 'trial',
        created_by: user.id,
        settings: body.settings || {},
      })
      .select()
      .single();

    if (tenantError) throw tenantError;

    if (ownerProfile) {
      const { error: memberError } = await supabase
        .from('tenant_members')
        .insert({
          tenant_id: tenant.id,
          user_id: ownerProfile.id,
          role: 'owner',
          status: 'active',
          invited_by: user.id,
          joined_at: new Date().toISOString(),
        });

      if (memberError) throw memberError;
    } else if (body.owner_email) {
      const { error: invitationError } = await supabase
        .from('tenant_invitations')
        .insert({
          tenant_id: tenant.id,
          email: body.owner_email.trim().toLowerCase(),
          role: 'owner',
          status: 'pending',
          invited_by: user.id,
          token_hash: randomToken(),
        });

      if (invitationError) throw invitationError;
    }

    const periodStart = new Date();
    const periodEnd = addDays(periodStart, Math.max(1, Number(body.trial_days || 14)));

    const { data: subscription, error: subscriptionError } = await supabase
      .from('subscriptions')
      .insert({
        tenant_id: tenant.id,
        plan_id: plan.id,
        status: 'trialing',
        current_period_start: periodStart.toISOString(),
        current_period_end: periodEnd.toISOString(),
        trial_ends_at: periodEnd.toISOString(),
      })
      .select('id,status,current_period_start,current_period_end')
      .single();

    if (subscriptionError) throw subscriptionError;

    return jsonResponse({
      data: {
        tenant,
        subscription,
        owner: ownerProfile
          ? { type: 'member', id: ownerProfile.id, email: ownerProfile.email }
          : body.owner_email
            ? { type: 'invitation', email: body.owner_email.trim().toLowerCase() }
            : null,
        plan,
      },
    }, 201);
  } catch (error) {
    return jsonResponse({ error: safeErrorMessage(error) }, Number((error as { status?: number })?.status || 500));
  }
});
