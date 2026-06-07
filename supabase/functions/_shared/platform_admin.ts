import { createClient } from 'https://esm.sh/@supabase/supabase-js@2.106.2';

export const corsHeaders = {
  'Access-Control-Allow-Origin': '*',
  'Access-Control-Allow-Headers': 'authorization, x-client-info, apikey, content-type',
  'Access-Control-Allow-Methods': 'GET, POST, OPTIONS',
};

export const jsonResponse = (body: Record<string, unknown>, status = 200) =>
  new Response(JSON.stringify(body), {
    status,
    headers: { ...corsHeaders, 'Content-Type': 'application/json' },
  });

const requiredEnv = (name: string) => {
  const value = Deno.env.get(name);
  if (!value) throw new Error(`Missing environment variable: ${name}`);
  return value;
};

export const getServiceClient = () => {
  const supabaseUrl = requiredEnv('SUPABASE_URL');
  const serviceRoleKey = requiredEnv('SUPABASE_SERVICE_ROLE_KEY');
  return createClient(supabaseUrl, serviceRoleKey, {
    auth: { persistSession: false, autoRefreshToken: false },
  });
};

export const parseAuthToken = (req: Request) => {
  const authHeader = req.headers.get('authorization') || '';
  const token = authHeader.replace(/^Bearer\s+/i, '').trim();
  if (!token) throw Object.assign(new Error('Missing Authorization bearer token.'), { status: 401 });
  return token;
};

export const assertPlatformAdmin = async (
  supabase: ReturnType<typeof getServiceClient>,
  req: Request,
) => {
  const token = parseAuthToken(req);
  const { data: userData, error: userError } = await supabase.auth.getUser(token);
  if (userError || !userData.user) {
    throw Object.assign(new Error('Sesion invalida.'), { status: 401 });
  }

  const { data: profile, error: profileError } = await supabase
    .from('profiles')
    .select('id,email,full_name,platform_role')
    .eq('id', userData.user.id)
    .maybeSingle();

  if (profileError) throw profileError;
  if (profile?.platform_role !== 'platform_admin') {
    throw Object.assign(new Error('Esta funcion requiere platform_admin.'), { status: 403 });
  }

  return { user: userData.user, profile };
};

export const normalizeSlug = (value: string) =>
  String(value || '')
    .trim()
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');

export const parseJsonBody = async <T>(req: Request): Promise<T> => {
  const body = await req.json().catch(() => null);
  if (!body || typeof body !== 'object') {
    throw Object.assign(new Error('Invalid JSON body.'), { status: 400 });
  }
  return body as T;
};

export const ensureMethod = (req: Request, allowed: string[]) => {
  if (req.method === 'OPTIONS') {
    return new Response('ok', { headers: corsHeaders });
  }

  if (!allowed.includes(req.method)) {
    return jsonResponse({ error: 'Method not allowed' }, 405);
  }

  return null;
};

export const randomToken = () => {
  if (globalThis.crypto?.randomUUID) return globalThis.crypto.randomUUID();
  return `${Date.now()}-${Math.random().toString(36).slice(2)}`;
};

export const safeErrorMessage = (error: unknown) => {
  if (error instanceof Error) return error.message;
  return 'Unexpected error.';
};
