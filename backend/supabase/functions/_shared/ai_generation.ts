import { createClient } from 'https://esm.sh/@supabase/supabase-js@2.106.2';

type GenerationKind = 'diet' | 'workout';

type GenerateRequest = {
  tenant_id: string;
  patient_id: string;
  title?: string;
  objective?: string;
  starts_on?: string;
  ends_on?: string;
  notes?: string;
  nutrition_source_id?: string;
  nutrition_source_code?: string;
  context?: Record<string, unknown>;
  preferences?: Record<string, unknown>;
};

type HandlerOptions = {
  kind: GenerationKind;
  generationType: string;
  targetTable: 'diets' | 'workout_routines';
  titleFallback: string;
  systemPrompt: string;
};

const corsHeaders = {
  'Access-Control-Allow-Origin': '*',
  'Access-Control-Allow-Headers': 'authorization, x-client-info, apikey, content-type',
  'Access-Control-Allow-Methods': 'POST, OPTIONS',
};

const jsonResponse = (body: Record<string, unknown>, status = 200) =>
  new Response(JSON.stringify(body), {
    status,
    headers: { ...corsHeaders, 'Content-Type': 'application/json' },
  });

const requiredEnv = (name: string) => {
  const value = Deno.env.get(name);
  if (!value) throw new Error(`Missing environment variable: ${name}`);
  return value;
};

const getServiceClient = () => {
  const supabaseUrl = requiredEnv('SUPABASE_URL');
  const serviceRoleKey = requiredEnv('SUPABASE_SERVICE_ROLE_KEY');
  return createClient(supabaseUrl, serviceRoleKey, {
    auth: { persistSession: false, autoRefreshToken: false },
  });
};

const parseAuthToken = (req: Request) => {
  const authHeader = req.headers.get('authorization') || '';
  const token = authHeader.replace(/^Bearer\s+/i, '').trim();
  if (!token) throw Object.assign(new Error('Missing Authorization bearer token.'), { status: 401 });
  return token;
};

const assertGenerationAccess = async (
  supabase: ReturnType<typeof getServiceClient>,
  userId: string,
  tenantId: string,
  patientId: string,
) => {
  const { data: membership, error: membershipError } = await supabase
    .from('tenant_members')
    .select('role,status')
    .eq('tenant_id', tenantId)
    .eq('user_id', userId)
    .eq('status', 'active')
    .maybeSingle();

  if (membershipError) throw membershipError;
  if (!membership) {
    throw Object.assign(new Error('No tienes membresia activa en este tenant.'), { status: 403 });
  }

  if (!['owner', 'admin', 'nutritionist'].includes(membership.role)) {
    throw Object.assign(new Error('Tu rol no puede generar planes con IA.'), { status: 403 });
  }

  const { data: patient, error: patientError } = await supabase
    .from('patients')
    .select('id,tenant_id,assigned_nutritionist_id,first_name,last_name,email,birth_date,gender,status')
    .eq('id', patientId)
    .eq('tenant_id', tenantId)
    .maybeSingle();

  if (patientError) throw patientError;
  if (!patient) throw Object.assign(new Error('Paciente no encontrado en el tenant.'), { status: 404 });

  if (membership.role === 'nutritionist' && patient.assigned_nutritionist_id !== userId) {
    throw Object.assign(new Error('Solo puedes generar planes para pacientes asignados a ti.'), { status: 403 });
  }

  return { membership, patient };
};

const getActiveSubscriptionWithPlan = async (
  supabase: ReturnType<typeof getServiceClient>,
  tenantId: string,
) => {
  const { data, error } = await supabase
    .from('subscriptions')
    .select('id,status,current_period_start,current_period_end,plans(id,code,name,limits,features)')
    .eq('tenant_id', tenantId)
    .in('status', ['trialing', 'active'])
    .order('created_at', { ascending: false })
    .limit(1)
    .maybeSingle();

  if (error) throw error;
  if (!data) throw Object.assign(new Error('El tenant no tiene una suscripcion activa.'), { status: 402 });
  return data;
};

const getPeriod = (subscription: Record<string, unknown>) => {
  const now = new Date();
  const start = subscription.current_period_start
    ? new Date(String(subscription.current_period_start))
    : new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), 1));
  const end = subscription.current_period_end
    ? new Date(String(subscription.current_period_end))
    : new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth() + 1, 1));

  return { start, end };
};

const assertPlanLimit = async (
  supabase: ReturnType<typeof getServiceClient>,
  tenantId: string,
  kind: GenerationKind,
) => {
  const subscription = await getActiveSubscriptionWithPlan(supabase, tenantId);
  const plan = Array.isArray(subscription.plans) ? subscription.plans[0] : subscription.plans;
  const limits = (plan?.limits || {}) as Record<string, unknown>;
  const features = (plan?.features || {}) as Record<string, unknown>;
  const featureKey = kind === 'diet' ? 'ai_diets' : 'workout_routines';
  if (features[featureKey] === false) {
    throw Object.assign(new Error('El plan actual no incluye esta funcion de IA.'), { status: 402 });
  }
  const aiLimit = Number(limits.ai_generations ?? 0);
  if (!Number.isFinite(aiLimit) || aiLimit <= 0) {
    throw Object.assign(new Error('El plan actual no incluye generaciones IA.'), { status: 402 });
  }

  const { start, end } = getPeriod(subscription);
  const { count, error } = await supabase
    .from('ai_generations')
    .select('id', { count: 'exact', head: true })
    .eq('tenant_id', tenantId)
    .eq('status', 'completed')
    .gte('created_at', start.toISOString())
    .lt('created_at', end.toISOString());

  if (error) throw error;
  const used = count || 0;
  if (used >= aiLimit) {
    throw Object.assign(new Error('Limite mensual de generaciones IA alcanzado.'), { status: 402 });
  }

  return { subscription, plan, periodStart: start, periodEnd: end, used, aiLimit };
};

const updateUsage = async (
  supabase: ReturnType<typeof getServiceClient>,
  tenantId: string,
  subscriptionId: string,
  periodStart: Date,
  periodEnd: Date,
) => {
  const periodStartDate = periodStart.toISOString().slice(0, 10);
  const periodEndDate = periodEnd.toISOString().slice(0, 10);

  const { data: existing } = await supabase
    .from('subscription_usage')
    .select('id,ai_generations_count')
    .eq('tenant_id', tenantId)
    .eq('subscription_id', subscriptionId)
    .eq('period_start', periodStartDate)
    .eq('period_end', periodEndDate)
    .maybeSingle();

  if (existing) {
    await supabase
      .from('subscription_usage')
      .update({ ai_generations_count: Number(existing.ai_generations_count || 0) + 1 })
      .eq('id', existing.id);
    return;
  }

  await supabase
    .from('subscription_usage')
    .insert({
      tenant_id: tenantId,
      subscription_id: subscriptionId,
      period_start: periodStartDate,
      period_end: periodEndDate,
      ai_generations_count: 1,
    });
};

const callGitHubModels = async (messages: Array<Record<string, string>>) => {
  const githubToken = requiredEnv('GITHUB_MODELS_TOKEN');
  const model = Deno.env.get('GITHUB_MODELS_MODEL') || 'openai/gpt-4.1-mini';

  const response = await fetch('https://models.github.ai/inference/chat/completions', {
    method: 'POST',
    headers: {
      'Accept': 'application/vnd.github+json',
      'Authorization': `Bearer ${githubToken}`,
      'Content-Type': 'application/json',
      'X-GitHub-Api-Version': '2026-03-10',
    },
    body: JSON.stringify({
      model,
      messages,
      temperature: 0.3,
      max_tokens: 2500,
      response_format: { type: 'json_object' },
    }),
  });

  const payload = await response.json().catch(() => null);
  if (!response.ok) {
    const message = payload?.message || payload?.error?.message || 'GitHub Models request failed.';
    throw Object.assign(new Error(message), { status: 502, details: payload });
  }

  const content = payload?.choices?.[0]?.message?.content;
  if (!content) throw Object.assign(new Error('GitHub Models returned an empty response.'), { status: 502 });

  let parsed: Record<string, unknown>;
  try {
    parsed = JSON.parse(content);
  } catch (_error) {
    parsed = { raw_text: content };
  }

  return {
    model,
    output: parsed,
    usage: payload?.usage || {},
    raw: payload,
  };
};

const validateNutritionContext = async (
  supabase: ReturnType<typeof getServiceClient>,
  options: HandlerOptions,
  input: GenerateRequest,
) => {
  if (options.kind !== 'diet') return {};

  const { data: settings, error: settingsError } = await supabase
    .from('tenant_nutrition_settings')
    .select('default_source_id,nutrition_sources:default_source_id(id,code,name,is_active)')
    .eq('tenant_id', input.tenant_id)
    .maybeSingle();

  if (settingsError) throw settingsError;

  const activeSource = Array.isArray(settings?.nutrition_sources)
    ? settings?.nutrition_sources[0]
    : settings?.nutrition_sources;
  const requestedSourceId = input.nutrition_source_id || settings?.default_source_id || activeSource?.id;
  const requestedSourceCode = input.nutrition_source_code || activeSource?.code;

  if (!requestedSourceId && !requestedSourceCode) {
    throw Object.assign(new Error('El tenant no tiene fuente nutricional activa.'), { status: 400 });
  }

  let sourceQuery = supabase
    .from('nutrition_sources')
    .select('id,code,name,is_active')
    .eq('is_active', true);

  sourceQuery = requestedSourceId
    ? sourceQuery.eq('id', requestedSourceId)
    : sourceQuery.eq('code', requestedSourceCode);

  const { data: source, error: sourceError } = await sourceQuery.maybeSingle();
  if (sourceError) throw sourceError;
  if (!source) throw Object.assign(new Error('Fuente nutricional no encontrada o inactiva.'), { status: 400 });

  if (settings?.default_source_id && source.id !== settings.default_source_id) {
    throw Object.assign(new Error('La fuente enviada no coincide con la fuente activa del tenant.'), { status: 400 });
  }

  const selectedFoodIds = ((input.context?.selected_foods as Array<Record<string, unknown>> | undefined) || [])
    .map((food) => String(food.id || ''))
    .filter(Boolean);

  if (selectedFoodIds.length === 0) {
    return { source, selected_foods: [] };
  }

  const { data: foods, error: foodsError } = await supabase
    .from('foods')
    .select('id,external_code,name,portion_label,weight_g,energy_kcal,carbohydrates_g,protein_g,fat_g,fiber_g,category:category_id(name)')
    .eq('source_id', source.id)
    .in('id', selectedFoodIds);

  if (foodsError) throw foodsError;
  if ((foods || []).length !== selectedFoodIds.length) {
    throw Object.assign(new Error('Uno o mas alimentos seleccionados no pertenecen a la fuente activa.'), { status: 400 });
  }

  return { source, selected_foods: foods || [] };
};

const buildMessages = (
  options: HandlerOptions,
  input: GenerateRequest,
  patient: Record<string, unknown>,
  nutritionContext: Record<string, unknown> = {},
) => [
  {
    role: 'system',
    content: options.systemPrompt,
  },
  {
    role: 'user',
    content: JSON.stringify({
      instruction: 'Responde exclusivamente JSON valido. Incluye summary y plan.',
      patient,
      request: input,
      nutrition_context: nutritionContext,
    }),
  },
];

const insertGeneration = async (
  supabase: ReturnType<typeof getServiceClient>,
  input: {
    tenantId: string;
    patientId: string;
    userId: string;
    generationType: string;
    model: string;
    requestInput: Record<string, unknown>;
    output: Record<string, unknown>;
    usage?: Record<string, unknown>;
    status: 'completed' | 'failed';
    errorMessage?: string;
  },
) => {
  const { data, error } = await supabase
    .from('ai_generations')
    .insert({
      tenant_id: input.tenantId,
      patient_id: input.patientId,
      user_id: input.userId,
      requested_by: input.userId,
      generation_type: input.generationType,
      model: input.model,
      input: input.requestInput,
      output: input.output,
      prompt_tokens: Number(input.usage?.prompt_tokens || 0),
      completion_tokens: Number(input.usage?.completion_tokens || 0),
      status: input.status,
      error_message: input.errorMessage || null,
    })
    .select()
    .single();

  if (error) throw error;
  return data;
};

const saveFinalPlan = async (
  supabase: ReturnType<typeof getServiceClient>,
  options: HandlerOptions,
  input: GenerateRequest,
  userId: string,
  output: Record<string, unknown>,
) => {
  let nutritionSourceId = input.nutrition_source_id || null;
  let nutritionSourceCode = input.nutrition_source_code || null;

  if (nutritionSourceId || nutritionSourceCode) {
    let sourceQuery = supabase
      .from('nutrition_sources')
      .select('id,code,is_active')
      .eq('is_active', true);

    sourceQuery = nutritionSourceId
      ? sourceQuery.eq('id', nutritionSourceId)
      : sourceQuery.eq('code', nutritionSourceCode);

    const { data: source, error: sourceError } = await sourceQuery.maybeSingle();
    if (sourceError) throw sourceError;
    if (!source) throw Object.assign(new Error('Fuente nutricional no encontrada o inactiva.'), { status: 400 });

    nutritionSourceId = source.id;
    nutritionSourceCode = source.code;
  }

  const payload: Record<string, unknown> = {
    tenant_id: input.tenant_id,
    patient_id: input.patient_id,
    nutritionist_id: userId,
    created_by: userId,
    title: input.title || String(output.title || options.titleFallback),
    objective: input.objective || null,
    status: 'draft',
    starts_on: input.starts_on || null,
    ends_on: input.ends_on || null,
    content: output,
    notes: input.notes || String(output.summary || ''),
    generated_by_ai: true,
  };

  if (options.targetTable === 'diets') {
    payload.nutrition_source_id = nutritionSourceId;
    payload.nutrition_source_code = nutritionSourceCode;
  }

  const { data, error } = await supabase
    .from(options.targetTable)
    .insert(payload)
    .select()
    .single();

  if (error) throw error;
  return data;
};

export const handleGeneration = async (req: Request, options: HandlerOptions) => {
  if (req.method === 'OPTIONS') return new Response('ok', { headers: corsHeaders });
  if (req.method !== 'POST') return jsonResponse({ error: 'Method not allowed' }, 405);

  const supabase = getServiceClient();
  let input: GenerateRequest | null = null;
  let userId = '';
  let model = Deno.env.get('GITHUB_MODELS_MODEL') || 'openai/gpt-4.1-mini';

  try {
    const token = parseAuthToken(req);
    const { data: userData, error: userError } = await supabase.auth.getUser(token);
    if (userError || !userData.user) {
      throw Object.assign(new Error('Sesion invalida.'), { status: 401 });
    }

    userId = userData.user.id;
    input = await req.json();
    if (!input?.tenant_id || !input?.patient_id) {
      throw Object.assign(new Error('tenant_id y patient_id son requeridos.'), { status: 400 });
    }

    const { patient } = await assertGenerationAccess(supabase, userId, input.tenant_id, input.patient_id);
    const limitContext = await assertPlanLimit(supabase, input.tenant_id, options.kind);
    const nutritionContext = await validateNutritionContext(supabase, options, input);
    const messages = buildMessages(options, input, patient, nutritionContext);
    const aiResult = await callGitHubModels(messages);
    model = aiResult.model;

    const generation = await insertGeneration(supabase, {
      tenantId: input.tenant_id,
      patientId: input.patient_id,
      userId,
      generationType: options.generationType,
      model,
      requestInput: input as unknown as Record<string, unknown>,
      output: aiResult.output,
      usage: aiResult.usage,
      status: 'completed',
    });

    const finalPlan = await saveFinalPlan(supabase, options, input, userId, aiResult.output);
    await updateUsage(
      supabase,
      input.tenant_id,
      String(limitContext.subscription.id),
      limitContext.periodStart,
      limitContext.periodEnd,
    );

    return jsonResponse({
      data: {
        generation,
        result: finalPlan,
        usage: {
          used_before_request: limitContext.used,
          limit: limitContext.aiLimit,
          prompt_tokens: Number(aiResult.usage?.prompt_tokens || 0),
          completion_tokens: Number(aiResult.usage?.completion_tokens || 0),
        },
      },
    });
  } catch (error) {
    const status = Number(error?.status || 500);
    const message = error?.message || 'Unexpected error.';

    if (input?.tenant_id && input?.patient_id && userId) {
      try {
        await insertGeneration(supabase, {
          tenantId: input.tenant_id,
          patientId: input.patient_id,
          userId,
          generationType: options.generationType,
          model,
          requestInput: input as unknown as Record<string, unknown>,
          output: {},
          status: 'failed',
          errorMessage: message,
        });
      } catch (_ignored) {
        // Avoid masking the original error.
      }
    }

    return jsonResponse({ error: message }, status);
  }
};
