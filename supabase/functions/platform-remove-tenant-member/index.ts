import {
  assertPlatformAdmin,
  ensureMethod,
  getServiceClient,
  jsonResponse,
  parseJsonBody,
  safeErrorMessage,
} from '../_shared/platform_admin.ts';

type RemoveBody = {
  source: 'member' | 'invitation';
  id: string;
};

Deno.serve(async (req) => {
  const methodResponse = ensureMethod(req, ['POST']);
  if (methodResponse) return methodResponse;

  const supabase = getServiceClient();

  try {
    await assertPlatformAdmin(supabase, req);
    const body = await parseJsonBody<RemoveBody>(req);

    if (!body.id || !body.source) {
      throw Object.assign(new Error('source e id son requeridos.'), { status: 400 });
    }

    if (body.source === 'member') {
      const { data: member, error: memberError } = await supabase
        .from('tenant_members')
        .select('id,role')
        .eq('id', body.id)
        .maybeSingle();

      if (memberError) throw memberError;
      if (!member) throw Object.assign(new Error('Miembro no encontrado.'), { status: 404 });
      if (member.role === 'owner') {
        throw Object.assign(new Error('No se puede eliminar un owner desde esta accion.'), { status: 400 });
      }

      const { error } = await supabase.from('tenant_members').delete().eq('id', body.id);
      if (error) throw error;
    } else {
      const { error } = await supabase.from('tenant_invitations').delete().eq('id', body.id);
      if (error) throw error;
    }

    return jsonResponse({ data: { success: true } });
  } catch (error) {
    return jsonResponse({ error: safeErrorMessage(error) }, Number((error as { status?: number })?.status || 500));
  }
});
