-- RLS policies for the multi-tenant Nutri SaaS model.
-- These policies trust auth.uid() and tenant membership, never frontend headers.

drop policy if exists "Profiles are visible to owner and shared tenants" on public.profiles;
create policy "Profiles are visible to owner and shared tenants"
on public.profiles for select
to authenticated
using (
  public.is_platform_admin()
  or id = auth.uid()
  or exists (
    select 1
    from public.tenant_members tm
    where tm.user_id = profiles.id
      and tm.status = 'active'
      and public.has_tenant_role(tm.tenant_id, array['owner', 'admin', 'nutritionist', 'assistant', 'viewer'])
  )
);

drop policy if exists "Users can create own profile" on public.profiles;
create policy "Users can create own profile"
on public.profiles for insert
to authenticated
with check (
  id = auth.uid()
  and platform_role is null
);

drop policy if exists "Users can update own non-platform profile" on public.profiles;
create policy "Users can update own non-platform profile"
on public.profiles for update
to authenticated
using (id = auth.uid())
with check (
  id = auth.uid()
  and platform_role is null
);

drop policy if exists "Platform admins manage profiles" on public.profiles;
create policy "Platform admins manage profiles"
on public.profiles for all
to authenticated
using (public.is_platform_admin())
with check (public.is_platform_admin());

drop policy if exists "Tenants visible to members" on public.tenants;
create policy "Tenants visible to members"
on public.tenants for select
to authenticated
using (public.is_tenant_member(id));

drop policy if exists "Platform admins create tenants" on public.tenants;
create policy "Platform admins create tenants"
on public.tenants for insert
to authenticated
with check (public.is_platform_admin());

drop policy if exists "Owners and admins update tenant settings" on public.tenants;
create policy "Owners and admins update tenant settings"
on public.tenants for update
to authenticated
using (public.has_tenant_role(id, array['owner', 'admin']))
with check (public.has_tenant_role(id, array['owner', 'admin']));

drop policy if exists "Platform admins delete tenants" on public.tenants;
create policy "Platform admins delete tenants"
on public.tenants for delete
to authenticated
using (public.is_platform_admin());

drop policy if exists "Tenant members visible to tenant members" on public.tenant_members;
create policy "Tenant members visible to tenant members"
on public.tenant_members for select
to authenticated
using (
  user_id = auth.uid()
  or public.has_tenant_role(tenant_id, array['owner', 'admin', 'nutritionist', 'assistant', 'viewer'])
);

drop policy if exists "Owners and admins invite members" on public.tenant_members;
create policy "Owners and admins invite members"
on public.tenant_members for insert
to authenticated
with check (
  public.has_tenant_role(tenant_id, array['owner'])
  or (
    public.has_tenant_role(tenant_id, array['admin'])
    and role <> 'owner'
  )
);

drop policy if exists "Owners and admins update members" on public.tenant_members;
create policy "Owners and admins update members"
on public.tenant_members for update
to authenticated
using (
  public.has_tenant_role(tenant_id, array['owner'])
  or (
    public.has_tenant_role(tenant_id, array['admin'])
    and role <> 'owner'
  )
)
with check (
  public.has_tenant_role(tenant_id, array['owner'])
  or (
    public.has_tenant_role(tenant_id, array['admin'])
    and role <> 'owner'
  )
);

drop policy if exists "Owners and admins remove members" on public.tenant_members;
create policy "Owners and admins remove members"
on public.tenant_members for delete
to authenticated
using (
  public.has_tenant_role(tenant_id, array['owner'])
  or (
    public.has_tenant_role(tenant_id, array['admin'])
    and role <> 'owner'
  )
);

drop policy if exists "Tenant invitations visible to owners and admins" on public.tenant_invitations;
create policy "Tenant invitations visible to owners and admins"
on public.tenant_invitations for select
to authenticated
using (public.has_tenant_role(tenant_id, array['owner', 'admin']));

drop policy if exists "Owners and admins create invitations" on public.tenant_invitations;
create policy "Owners and admins create invitations"
on public.tenant_invitations for insert
to authenticated
with check (
  public.has_tenant_role(tenant_id, array['owner'])
  or (
    public.has_tenant_role(tenant_id, array['admin'])
    and role <> 'owner'
  )
);

drop policy if exists "Owners and admins update invitations" on public.tenant_invitations;
create policy "Owners and admins update invitations"
on public.tenant_invitations for update
to authenticated
using (public.has_tenant_role(tenant_id, array['owner', 'admin']))
with check (
  public.has_tenant_role(tenant_id, array['owner'])
  or (
    public.has_tenant_role(tenant_id, array['admin'])
    and role <> 'owner'
  )
);

drop policy if exists "Owners and admins delete invitations" on public.tenant_invitations;
create policy "Owners and admins delete invitations"
on public.tenant_invitations for delete
to authenticated
using (public.has_tenant_role(tenant_id, array['owner', 'admin']));

drop policy if exists "Active plans visible to authenticated users" on public.plans;
create policy "Active plans visible to authenticated users"
on public.plans for select
to authenticated
using (is_active or public.is_platform_admin());

drop policy if exists "Platform admins manage plans" on public.plans;
create policy "Platform admins manage plans"
on public.plans for all
to authenticated
using (public.is_platform_admin())
with check (public.is_platform_admin());

drop policy if exists "Subscriptions visible to tenant owners" on public.subscriptions;
create policy "Subscriptions visible to tenant owners"
on public.subscriptions for select
to authenticated
using (public.has_tenant_role(tenant_id, array['owner', 'admin']));

drop policy if exists "Platform admins manage subscriptions" on public.subscriptions;
create policy "Platform admins manage subscriptions"
on public.subscriptions for all
to authenticated
using (public.is_platform_admin())
with check (public.is_platform_admin());

drop policy if exists "Subscription usage visible to tenant owners" on public.subscription_usage;
create policy "Subscription usage visible to tenant owners"
on public.subscription_usage for select
to authenticated
using (public.has_tenant_role(tenant_id, array['owner', 'admin']));

drop policy if exists "Platform admins manage subscription usage" on public.subscription_usage;
create policy "Platform admins manage subscription usage"
on public.subscription_usage for all
to authenticated
using (public.is_platform_admin())
with check (public.is_platform_admin());

drop policy if exists "Patients visible by tenant role" on public.patients;
create policy "Patients visible by tenant role"
on public.patients for select
to authenticated
using (
  public.has_tenant_role(tenant_id, array['owner', 'admin', 'assistant'])
  or (
    public.has_tenant_role(tenant_id, array['nutritionist'])
    and assigned_nutritionist_id = auth.uid()
  )
  or user_id = auth.uid()
);

drop policy if exists "Staff can create patients" on public.patients;
create policy "Staff can create patients"
on public.patients for insert
to authenticated
with check (
  public.has_tenant_role(tenant_id, array['owner', 'admin', 'assistant'])
  or (
    public.has_tenant_role(tenant_id, array['nutritionist'])
    and assigned_nutritionist_id = auth.uid()
  )
);

drop policy if exists "Staff can update patients" on public.patients;
create policy "Staff can update patients"
on public.patients for update
to authenticated
using (
  public.has_tenant_role(tenant_id, array['owner', 'admin', 'assistant'])
  or (
    public.has_tenant_role(tenant_id, array['nutritionist'])
    and assigned_nutritionist_id = auth.uid()
  )
)
with check (
  public.has_tenant_role(tenant_id, array['owner', 'admin', 'assistant'])
  or (
    public.has_tenant_role(tenant_id, array['nutritionist'])
    and assigned_nutritionist_id = auth.uid()
  )
);

drop policy if exists "Owners and admins delete patients" on public.patients;
create policy "Owners and admins delete patients"
on public.patients for delete
to authenticated
using (public.has_tenant_role(tenant_id, array['owner', 'admin']));

drop policy if exists "Patient profiles visible by clinical access" on public.patient_profiles;
create policy "Patient profiles visible by clinical access"
on public.patient_profiles for select
to authenticated
using (public.can_read_patient_record(tenant_id, patient_id));

drop policy if exists "Clinical staff create patient profiles" on public.patient_profiles;
create policy "Clinical staff create patient profiles"
on public.patient_profiles for insert
to authenticated
with check (
  public.has_tenant_role(tenant_id, array['owner', 'admin'])
  or public.is_assigned_nutritionist(tenant_id, patient_id)
);

drop policy if exists "Clinical staff update patient profiles" on public.patient_profiles;
create policy "Clinical staff update patient profiles"
on public.patient_profiles for update
to authenticated
using (
  public.has_tenant_role(tenant_id, array['owner', 'admin'])
  or public.is_assigned_nutritionist(tenant_id, patient_id)
)
with check (
  public.has_tenant_role(tenant_id, array['owner', 'admin'])
  or public.is_assigned_nutritionist(tenant_id, patient_id)
);

drop policy if exists "Owners and admins delete patient profiles" on public.patient_profiles;
create policy "Owners and admins delete patient profiles"
on public.patient_profiles for delete
to authenticated
using (public.has_tenant_role(tenant_id, array['owner', 'admin']));

drop policy if exists "Appointments visible by tenant role" on public.appointments;
create policy "Appointments visible by tenant role"
on public.appointments for select
to authenticated
using (
  public.has_tenant_role(tenant_id, array['owner', 'admin', 'assistant'])
  or (
    public.has_tenant_role(tenant_id, array['nutritionist'])
    and (nutritionist_id = auth.uid() or public.is_assigned_nutritionist(tenant_id, patient_id))
  )
  or public.is_patient_self(tenant_id, patient_id)
);

drop policy if exists "Staff can create appointments" on public.appointments;
create policy "Staff can create appointments"
on public.appointments for insert
to authenticated
with check (
  public.has_tenant_role(tenant_id, array['owner', 'admin', 'assistant'])
  or (
    public.has_tenant_role(tenant_id, array['nutritionist'])
    and (nutritionist_id = auth.uid() or public.is_assigned_nutritionist(tenant_id, patient_id))
  )
);

drop policy if exists "Staff can update appointments" on public.appointments;
create policy "Staff can update appointments"
on public.appointments for update
to authenticated
using (
  public.has_tenant_role(tenant_id, array['owner', 'admin', 'assistant'])
  or (
    public.has_tenant_role(tenant_id, array['nutritionist'])
    and (nutritionist_id = auth.uid() or public.is_assigned_nutritionist(tenant_id, patient_id))
  )
)
with check (
  public.has_tenant_role(tenant_id, array['owner', 'admin', 'assistant'])
  or (
    public.has_tenant_role(tenant_id, array['nutritionist'])
    and (nutritionist_id = auth.uid() or public.is_assigned_nutritionist(tenant_id, patient_id))
  )
);

drop policy if exists "Agenda managers can delete appointments" on public.appointments;
create policy "Agenda managers can delete appointments"
on public.appointments for delete
to authenticated
using (
  public.has_tenant_role(tenant_id, array['owner', 'admin', 'assistant'])
  or (
    public.has_tenant_role(tenant_id, array['nutritionist'])
    and (nutritionist_id = auth.uid() or public.is_assigned_nutritionist(tenant_id, patient_id))
  )
);

drop policy if exists "Clinical notes visible by clinical access" on public.clinical_notes;
create policy "Clinical notes visible by clinical access"
on public.clinical_notes for select
to authenticated
using (
  public.has_tenant_role(tenant_id, array['owner', 'admin'])
  or public.is_assigned_nutritionist(tenant_id, patient_id)
  or (
    visibility = 'patient_portal'
    and public.is_patient_self(tenant_id, patient_id)
  )
);

drop policy if exists "Clinical staff create notes" on public.clinical_notes;
create policy "Clinical staff create notes"
on public.clinical_notes for insert
to authenticated
with check (
  (
    public.has_tenant_role(tenant_id, array['owner', 'admin'])
    or public.is_assigned_nutritionist(tenant_id, patient_id)
  )
  and (author_id is null or author_id = auth.uid())
);

drop policy if exists "Clinical staff update notes" on public.clinical_notes;
create policy "Clinical staff update notes"
on public.clinical_notes for update
to authenticated
using (
  locked_at is null
  and (
    public.has_tenant_role(tenant_id, array['owner', 'admin'])
    or public.is_assigned_nutritionist(tenant_id, patient_id)
  )
)
with check (
  public.has_tenant_role(tenant_id, array['owner', 'admin'])
  or public.is_assigned_nutritionist(tenant_id, patient_id)
);

drop policy if exists "Owners and admins delete notes" on public.clinical_notes;
create policy "Owners and admins delete notes"
on public.clinical_notes for delete
to authenticated
using (public.has_tenant_role(tenant_id, array['owner', 'admin']));

drop policy if exists "Diets visible by clinical or patient access" on public.diets;
create policy "Diets visible by clinical or patient access"
on public.diets for select
to authenticated
using (
  public.has_tenant_role(tenant_id, array['owner', 'admin'])
  or public.is_assigned_nutritionist(tenant_id, patient_id)
  or (
    status = 'published'
    and public.is_patient_self(tenant_id, patient_id)
  )
);

drop policy if exists "Clinical staff create diets" on public.diets;
create policy "Clinical staff create diets"
on public.diets for insert
to authenticated
with check (
  public.has_tenant_role(tenant_id, array['owner', 'admin'])
  or public.is_assigned_nutritionist(tenant_id, patient_id)
);

drop policy if exists "Clinical staff update diets" on public.diets;
create policy "Clinical staff update diets"
on public.diets for update
to authenticated
using (
  public.has_tenant_role(tenant_id, array['owner', 'admin'])
  or public.is_assigned_nutritionist(tenant_id, patient_id)
)
with check (
  public.has_tenant_role(tenant_id, array['owner', 'admin'])
  or public.is_assigned_nutritionist(tenant_id, patient_id)
);

drop policy if exists "Owners and admins delete diets" on public.diets;
create policy "Owners and admins delete diets"
on public.diets for delete
to authenticated
using (public.has_tenant_role(tenant_id, array['owner', 'admin']));

drop policy if exists "Workout routines visible by clinical or patient access" on public.workout_routines;
create policy "Workout routines visible by clinical or patient access"
on public.workout_routines for select
to authenticated
using (
  public.has_tenant_role(tenant_id, array['owner', 'admin'])
  or public.is_assigned_nutritionist(tenant_id, patient_id)
  or (
    status = 'published'
    and public.is_patient_self(tenant_id, patient_id)
  )
);

drop policy if exists "Clinical staff create workout routines" on public.workout_routines;
create policy "Clinical staff create workout routines"
on public.workout_routines for insert
to authenticated
with check (
  public.has_tenant_role(tenant_id, array['owner', 'admin'])
  or public.is_assigned_nutritionist(tenant_id, patient_id)
);

drop policy if exists "Clinical staff update workout routines" on public.workout_routines;
create policy "Clinical staff update workout routines"
on public.workout_routines for update
to authenticated
using (
  public.has_tenant_role(tenant_id, array['owner', 'admin'])
  or public.is_assigned_nutritionist(tenant_id, patient_id)
)
with check (
  public.has_tenant_role(tenant_id, array['owner', 'admin'])
  or public.is_assigned_nutritionist(tenant_id, patient_id)
);

drop policy if exists "Owners and admins delete workout routines" on public.workout_routines;
create policy "Owners and admins delete workout routines"
on public.workout_routines for delete
to authenticated
using (public.has_tenant_role(tenant_id, array['owner', 'admin']));

drop policy if exists "Measurements visible by clinical or patient access" on public.measurements;
create policy "Measurements visible by clinical or patient access"
on public.measurements for select
to authenticated
using (public.can_read_patient_record(tenant_id, patient_id));

drop policy if exists "Clinical staff create measurements" on public.measurements;
create policy "Clinical staff create measurements"
on public.measurements for insert
to authenticated
with check (
  (
    public.has_tenant_role(tenant_id, array['owner', 'admin'])
    or public.is_assigned_nutritionist(tenant_id, patient_id)
  )
  and (recorded_by is null or recorded_by = auth.uid())
);

drop policy if exists "Clinical staff update measurements" on public.measurements;
create policy "Clinical staff update measurements"
on public.measurements for update
to authenticated
using (
  public.has_tenant_role(tenant_id, array['owner', 'admin'])
  or public.is_assigned_nutritionist(tenant_id, patient_id)
)
with check (
  public.has_tenant_role(tenant_id, array['owner', 'admin'])
  or public.is_assigned_nutritionist(tenant_id, patient_id)
);

drop policy if exists "Clinical staff delete measurements" on public.measurements;
create policy "Clinical staff delete measurements"
on public.measurements for delete
to authenticated
using (
  public.has_tenant_role(tenant_id, array['owner', 'admin'])
  or public.is_assigned_nutritionist(tenant_id, patient_id)
);

drop policy if exists "Food recalls visible by clinical or patient access" on public.food_recalls;
create policy "Food recalls visible by clinical or patient access"
on public.food_recalls for select
to authenticated
using (public.can_read_patient_record(tenant_id, patient_id));

drop policy if exists "Food recalls can be created by clinical staff or patient" on public.food_recalls;
create policy "Food recalls can be created by clinical staff or patient"
on public.food_recalls for insert
to authenticated
with check (
  (
    public.has_tenant_role(tenant_id, array['owner', 'admin'])
    or public.is_assigned_nutritionist(tenant_id, patient_id)
    or public.is_patient_self(tenant_id, patient_id)
  )
  and (recorded_by is null or recorded_by = auth.uid())
);

drop policy if exists "Food recalls can be updated by clinical staff or patient" on public.food_recalls;
create policy "Food recalls can be updated by clinical staff or patient"
on public.food_recalls for update
to authenticated
using (
  public.has_tenant_role(tenant_id, array['owner', 'admin'])
  or public.is_assigned_nutritionist(tenant_id, patient_id)
  or public.is_patient_self(tenant_id, patient_id)
)
with check (
  public.has_tenant_role(tenant_id, array['owner', 'admin'])
  or public.is_assigned_nutritionist(tenant_id, patient_id)
  or public.is_patient_self(tenant_id, patient_id)
);

drop policy if exists "Clinical staff delete food recalls" on public.food_recalls;
create policy "Clinical staff delete food recalls"
on public.food_recalls for delete
to authenticated
using (
  public.has_tenant_role(tenant_id, array['owner', 'admin'])
  or public.is_assigned_nutritionist(tenant_id, patient_id)
);

drop policy if exists "AI generations visible to tenant clinical staff" on public.ai_generations;
create policy "AI generations visible to tenant clinical staff"
on public.ai_generations for select
to authenticated
using (
  public.has_tenant_role(tenant_id, array['owner', 'admin'])
  or (
    public.has_tenant_role(tenant_id, array['nutritionist'])
    and (
      requested_by = auth.uid()
      or patient_id is null
      or public.is_assigned_nutritionist(tenant_id, patient_id)
    )
  )
);

drop policy if exists "Clinical staff create AI generations" on public.ai_generations;
create policy "Clinical staff create AI generations"
on public.ai_generations for insert
to authenticated
with check (
  (
    public.has_tenant_role(tenant_id, array['owner', 'admin'])
    or (
      public.has_tenant_role(tenant_id, array['nutritionist'])
      and (
        patient_id is null
        or public.is_assigned_nutritionist(tenant_id, patient_id)
      )
    )
  )
  and (requested_by is null or requested_by = auth.uid())
);

drop policy if exists "Owners and admins update AI generations" on public.ai_generations;
create policy "Owners and admins update AI generations"
on public.ai_generations for update
to authenticated
using (public.has_tenant_role(tenant_id, array['owner', 'admin']))
with check (public.has_tenant_role(tenant_id, array['owner', 'admin']));

drop policy if exists "Owners and admins delete AI generations" on public.ai_generations;
create policy "Owners and admins delete AI generations"
on public.ai_generations for delete
to authenticated
using (public.has_tenant_role(tenant_id, array['owner', 'admin']));

drop policy if exists "Audit logs visible to tenant admins" on public.audit_logs;
create policy "Audit logs visible to tenant admins"
on public.audit_logs for select
to authenticated
using (public.has_tenant_role(tenant_id, array['owner', 'admin']));

drop policy if exists "Tenant members can write audit logs" on public.audit_logs;
create policy "Tenant members can write audit logs"
on public.audit_logs for insert
to authenticated
with check (
  public.is_tenant_member(tenant_id)
  and (actor_user_id is null or actor_user_id = auth.uid())
);

drop policy if exists "Platform admins delete audit logs" on public.audit_logs;
create policy "Platform admins delete audit logs"
on public.audit_logs for delete
to authenticated
using (public.is_platform_admin());

drop policy if exists "Tenant documents visible by tenant role" on storage.objects;
create policy "Tenant documents visible by tenant role"
on storage.objects for select
to authenticated
using (
  bucket_id = 'tenant-documents'
  and (
    public.has_tenant_role(public.storage_tenant_id(name), array['owner', 'admin', 'nutritionist', 'assistant'])
    or (
      public.storage_patient_id(name) is not null
      and public.is_patient_self(public.storage_tenant_id(name), public.storage_patient_id(name))
    )
  )
);

drop policy if exists "Tenant documents writable by staff" on storage.objects;
create policy "Tenant documents writable by staff"
on storage.objects for insert
to authenticated
with check (
  bucket_id = 'tenant-documents'
  and public.has_tenant_role(public.storage_tenant_id(name), array['owner', 'admin', 'nutritionist', 'assistant'])
);

drop policy if exists "Tenant documents updatable by staff" on storage.objects;
create policy "Tenant documents updatable by staff"
on storage.objects for update
to authenticated
using (
  bucket_id = 'tenant-documents'
  and public.has_tenant_role(public.storage_tenant_id(name), array['owner', 'admin', 'nutritionist', 'assistant'])
)
with check (
  bucket_id = 'tenant-documents'
  and public.has_tenant_role(public.storage_tenant_id(name), array['owner', 'admin', 'nutritionist', 'assistant'])
);

drop policy if exists "Tenant documents deletable by admins" on storage.objects;
create policy "Tenant documents deletable by admins"
on storage.objects for delete
to authenticated
using (
  bucket_id = 'tenant-documents'
  and public.has_tenant_role(public.storage_tenant_id(name), array['owner', 'admin'])
);

drop policy if exists "Profile avatars visible to everyone" on storage.objects;
create policy "Profile avatars visible to everyone"
on storage.objects for select
to anon, authenticated
using (bucket_id = 'profile-avatars');

drop policy if exists "Users upload own avatars" on storage.objects;
create policy "Users upload own avatars"
on storage.objects for insert
to authenticated
with check (
  bucket_id = 'profile-avatars'
  and split_part(name, '/', 1) = auth.uid()::text
);

drop policy if exists "Users update own avatars" on storage.objects;
create policy "Users update own avatars"
on storage.objects for update
to authenticated
using (
  bucket_id = 'profile-avatars'
  and split_part(name, '/', 1) = auth.uid()::text
)
with check (
  bucket_id = 'profile-avatars'
  and split_part(name, '/', 1) = auth.uid()::text
);

drop policy if exists "Users delete own avatars" on storage.objects;
create policy "Users delete own avatars"
on storage.objects for delete
to authenticated
using (
  bucket_id = 'profile-avatars'
  and split_part(name, '/', 1) = auth.uid()::text
);
