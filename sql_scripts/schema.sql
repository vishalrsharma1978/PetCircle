

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

COMMENT ON SCHEMA "public" IS 'standard public schema';

CREATE EXTENSION IF NOT EXISTS "pg_stat_statements" WITH SCHEMA "extensions";

CREATE EXTENSION IF NOT EXISTS "pg_trgm" WITH SCHEMA "public";

CREATE EXTENSION IF NOT EXISTS "pgcrypto" WITH SCHEMA "extensions";

CREATE EXTENSION IF NOT EXISTS "supabase_vault" WITH SCHEMA "vault";

CREATE EXTENSION IF NOT EXISTS "uuid-ossp" WITH SCHEMA "extensions";

CREATE TYPE "public"."friend_status" AS ENUM (
    'pending',
    'accepted',
    'blocked'
);

ALTER TYPE "public"."friend_status" OWNER TO "postgres";

CREATE TYPE "public"."group_member_role" AS ENUM (
    'admin',
    'moderator',
    'member'
);

ALTER TYPE "public"."group_member_role" OWNER TO "postgres";

CREATE TYPE "public"."membership_status" AS ENUM (
    'none',
    'pending',
    'approved',
    'rejected'
);

ALTER TYPE "public"."membership_status" OWNER TO "postgres";

CREATE TYPE "public"."post_type" AS ENUM (
    'text',
    'image',
    'video',
    'poll',
    'event'
);

ALTER TYPE "public"."post_type" OWNER TO "postgres";

CREATE TYPE "public"."user_role" AS ENUM (
    'member',
    'admin'
);

ALTER TYPE "public"."user_role" OWNER TO "postgres";

CREATE OR REPLACE FUNCTION "public"."current_app_user_id"() RETURNS "uuid"
    LANGUAGE "sql" STABLE SECURITY DEFINER
    SET "search_path" TO 'public'
    AS $$
  select id
  from public.users
  where auth_user_id = auth.uid()
  limit 1;
$$;

ALTER FUNCTION "public"."current_app_user_id"() OWNER TO "postgres";

CREATE OR REPLACE FUNCTION "public"."handle_new_auth_user"() RETURNS "trigger"
    LANGUAGE "plpgsql" SECURITY DEFINER
    SET "search_path" TO 'public'
    AS $$
declare
  app_user_id uuid;
  existing_auth_user_id uuid;
  display_name text;
  non_real_matches integer;
begin
  if new.email is null then
    raise exception 'Cannot create app user because auth.users.email is null';
  end if;

  display_name :=
    coalesce(
      new.raw_user_meta_data->>'full_name',
      new.raw_user_meta_data->>'name',
      split_part(new.email, '@', 1),
      'New Member'
    );

  select count(*)
  into non_real_matches
  from public.users u
  join public.user_migration_review r
    on r.user_id = u.id
  where lower(u.email) = lower(new.email)
    and r.migration_status in ('test_user', 'admin_test_user', 'delete_later');

  if non_real_matches > 0 then
    raise exception 'Blocked Auth signup: email matches a non-real/test app user: %', new.email;
  end if;

  select u.id, u.auth_user_id
  into app_user_id, existing_auth_user_id
  from public.users u
  join public.user_migration_review r
    on r.user_id = u.id
  where lower(u.email) = lower(new.email)
    and r.migration_status = 'real_user'
  limit 1;

  if app_user_id is not null then

    if existing_auth_user_id is null then
      update public.users
      set
        auth_user_id = new.id,
        updated_at = now()
      where id = app_user_id;

    elsif existing_auth_user_id <> new.id then
      raise exception 'Blocked Auth signup: app user email is already linked to another auth user: %', new.email;
    end if;

  else
    insert into public.users (
      email,
      password_hash,
      role,
      auth_user_id
    )
    values (
      new.email,
      null,
      'member'::public.user_role,
      new.id
    )
    returning id into app_user_id;

    insert into public.user_migration_review (
      user_id,
      migration_status,
      notes,
      reviewed_at
    )
    values (
      app_user_id,
      'real_user',
      'Created from Supabase Auth signup',
      now()
    )
    on conflict (user_id) do nothing;
  end if;

  insert into public.profiles (
    user_id,
    pet_name,
    parent_name,
    terms_accepted,
    privacy_accepted,
    accuracy_certified
  )
  values (
    app_user_id,
    display_name,
    display_name,
    false,
    false,
    false
  )
  on conflict (user_id) do nothing;

  return new;
end;
$$;

ALTER FUNCTION "public"."handle_new_auth_user"() OWNER TO "postgres";

CREATE OR REPLACE FUNCTION "public"."rls_auto_enable"() RETURNS "event_trigger"
    LANGUAGE "plpgsql" SECURITY DEFINER
    SET "search_path" TO 'pg_catalog'
    AS $$
DECLARE
  cmd record;
BEGIN
  FOR cmd IN
    SELECT *
    FROM pg_event_trigger_ddl_commands()
    WHERE command_tag IN ('CREATE TABLE', 'CREATE TABLE AS', 'SELECT INTO')
      AND object_type IN ('table','partitioned table')
  LOOP
     IF cmd.schema_name IS NOT NULL AND cmd.schema_name IN ('public') AND cmd.schema_name NOT IN ('pg_catalog','information_schema') AND cmd.schema_name NOT LIKE 'pg_toast%' AND cmd.schema_name NOT LIKE 'pg_temp%' THEN
      BEGIN
        EXECUTE format('alter table if exists %s enable row level security', cmd.object_identity);
        RAISE LOG 'rls_auto_enable: enabled RLS on %', cmd.object_identity;
      EXCEPTION
        WHEN OTHERS THEN
          RAISE LOG 'rls_auto_enable: failed to enable RLS on %', cmd.object_identity;
      END;
     ELSE
        RAISE LOG 'rls_auto_enable: skip % (either system schema or not in enforced list: %.)', cmd.object_identity, cmd.schema_name;
     END IF;
  END LOOP;
END;
$$;

ALTER FUNCTION "public"."rls_auto_enable"() OWNER TO "postgres";

CREATE OR REPLACE FUNCTION "public"."set_updated_at"() RETURNS "trigger"
    LANGUAGE "plpgsql"
    AS $$
begin
  new.updated_at = now();
  return new;
end;
$$;

ALTER FUNCTION "public"."set_updated_at"() OWNER TO "postgres";

CREATE OR REPLACE FUNCTION "public"."update_modified_column"() RETURNS "trigger"
    LANGUAGE "plpgsql"
    AS $$
BEGIN
   NEW.updated_at = now(); 
   RETURN NEW;
END;
$$;

ALTER FUNCTION "public"."update_modified_column"() OWNER TO "postgres";

SET default_tablespace = '';

SET default_table_access_method = "heap";

CREATE TABLE IF NOT EXISTS "public"."admin_roles" (
    "id" "uuid" DEFAULT "gen_random_uuid"() NOT NULL,
    "user_id" "uuid" NOT NULL,
    "role" "text" NOT NULL,
    "scope_type" "text" DEFAULT 'global'::"text" NOT NULL,
    "scope_value" "text" DEFAULT '*'::"text" NOT NULL,
    "created_by" "uuid",
    "created_at" timestamp with time zone DEFAULT "now"() NOT NULL,
    "revoked_at" timestamp with time zone,
    "revoked_by" "uuid",
    CONSTRAINT "admin_roles_role_check" CHECK (("role" = ANY (ARRAY['owner'::"text", 'platform_admin'::"text", 'pet_type_admin'::"text", 'breed_admin'::"text"]))),
    CONSTRAINT "admin_roles_scope_type_check" CHECK (("scope_type" = ANY (ARRAY['global'::"text", 'pet_type'::"text", 'breed'::"text"])))
);

ALTER TABLE "public"."admin_roles" OWNER TO "postgres";

CREATE TABLE IF NOT EXISTS "public"."admin_user_actions" (
    "id" "uuid" DEFAULT "gen_random_uuid"() NOT NULL,
    "user_id" "uuid" NOT NULL,
    "created_by" "uuid",
    "action_type" "text" NOT NULL,
    "reason" "text",
    "starts_at" timestamp with time zone DEFAULT "now"() NOT NULL,
    "ends_at" timestamp with time zone,
    "is_active" boolean DEFAULT true NOT NULL,
    "created_at" timestamp with time zone DEFAULT "now"() NOT NULL
);

ALTER TABLE "public"."admin_user_actions" OWNER TO "postgres";

CREATE TABLE IF NOT EXISTS "public"."admin_user_notes" (
    "id" "uuid" DEFAULT "gen_random_uuid"() NOT NULL,
    "user_id" "uuid" NOT NULL,
    "created_by" "uuid",
    "note_type" "text" DEFAULT 'note'::"text" NOT NULL,
    "note" "text" NOT NULL,
    "created_at" timestamp with time zone DEFAULT "now"() NOT NULL
);

ALTER TABLE "public"."admin_user_notes" OWNER TO "postgres";

CREATE TABLE IF NOT EXISTS "public"."audit_logs" (
    "id" "uuid" DEFAULT "gen_random_uuid"() NOT NULL,
    "user_id" "uuid",
    "action" "text" NOT NULL,
    "target_type" "text",
    "target_id" "text",
    "ip_hash" "text",
    "user_agent" "text",
    "metadata" "jsonb",
    "created_at" timestamp with time zone DEFAULT "now"() NOT NULL
);

ALTER TABLE "public"."audit_logs" OWNER TO "postgres";

CREATE TABLE IF NOT EXISTS "public"."auth_rate_limits" (
    "rate_key" "text" NOT NULL,
    "attempts" integer DEFAULT 0 NOT NULL,
    "window_start" timestamp with time zone DEFAULT "now"() NOT NULL,
    "blocked_until" timestamp with time zone,
    "updated_at" timestamp with time zone DEFAULT "now"() NOT NULL,
    "escalation_level" integer DEFAULT 0 NOT NULL,
    "last_blocked_at" timestamp with time zone,
    "last_failed_at" timestamp with time zone
);

ALTER TABLE "public"."auth_rate_limits" OWNER TO "postgres";









CREATE TABLE IF NOT EXISTS "public"."call_events" (
    "id" "uuid" DEFAULT "gen_random_uuid"() NOT NULL,
    "call_id" "uuid" NOT NULL,
    "user_id" "uuid",
    "event_type" "text" NOT NULL,
    "created_at" timestamp with time zone DEFAULT "now"()
);

ALTER TABLE "public"."call_events" OWNER TO "postgres";

CREATE TABLE IF NOT EXISTS "public"."call_participants" (
    "id" "uuid" DEFAULT "gen_random_uuid"() NOT NULL,
    "call_id" "uuid" NOT NULL,
    "user_id" "uuid" NOT NULL,
    "role" "text" DEFAULT 'participant'::"text" NOT NULL,
    "status" "text" DEFAULT 'invited'::"text" NOT NULL,
    "joined_at" timestamp with time zone,
    "left_at" timestamp with time zone,
    CONSTRAINT "call_participants_role_check" CHECK (("role" = ANY (ARRAY['host'::"text", 'participant'::"text"]))),
    CONSTRAINT "call_participants_status_check" CHECK (("status" = ANY (ARRAY['invited'::"text", 'ringing'::"text", 'joined'::"text", 'declined'::"text", 'left'::"text", 'missed'::"text"])))
);

ALTER TABLE "public"."call_participants" OWNER TO "postgres";

CREATE TABLE IF NOT EXISTS "public"."call_sessions" (
    "id" "uuid" DEFAULT "gen_random_uuid"() NOT NULL,
    "created_by" "uuid" NOT NULL,
    "call_type" "text" NOT NULL,
    "target_type" "text" NOT NULL,
    "group_id" "uuid",
    "provider" "text" DEFAULT 'daily'::"text" NOT NULL,
    "provider_room_id" "text",
    "provider_room_url" "text",
    "status" "text" DEFAULT 'created'::"text" NOT NULL,
    "created_at" timestamp with time zone DEFAULT "now"(),
    "started_at" timestamp with time zone,
    "ended_at" timestamp with time zone,
    "zoom_meeting_id" "text",
    "zoom_password" "text",
    "zoom_join_url" "text",
    CONSTRAINT "call_sessions_call_type_check" CHECK (("call_type" = ANY (ARRAY['voice'::"text", 'video'::"text"]))),
    CONSTRAINT "call_sessions_status_check" CHECK (("status" = ANY (ARRAY['created'::"text", 'ringing'::"text", 'active'::"text", 'ended'::"text", 'cancelled'::"text", 'missed'::"text"]))),
    CONSTRAINT "call_sessions_target_type_check" CHECK (("target_type" = ANY (ARRAY['direct'::"text", 'selected_users'::"text", 'group'::"text"])))
);

ALTER TABLE "public"."call_sessions" OWNER TO "postgres";

CREATE TABLE IF NOT EXISTS "public"."comment_likes" (
    "id" "uuid" DEFAULT "gen_random_uuid"() NOT NULL,
    "user_id" "uuid" NOT NULL,
    "comment_id" "uuid" NOT NULL,
    "created_at" timestamp with time zone DEFAULT "now"() NOT NULL
);

ALTER TABLE "public"."comment_likes" OWNER TO "postgres";

CREATE TABLE IF NOT EXISTS "public"."direct_messages" (
    "id" "uuid" DEFAULT "extensions"."uuid_generate_v4"() NOT NULL,
    "sender_id" "uuid" NOT NULL,
    "recipient_id" "uuid" NOT NULL,
    "content" "text",
    "media_url" "text",
    "is_deleted" boolean DEFAULT false,
    "created_at" timestamp with time zone DEFAULT "now"(),
    CONSTRAINT "direct_messages_no_self" CHECK (("sender_id" <> "recipient_id"))
);

ALTER TABLE "public"."direct_messages" OWNER TO "postgres";

CREATE TABLE IF NOT EXISTS "public"."event_rsvps" (
    "event_id" "uuid" NOT NULL,
    "user_id" "uuid" NOT NULL,
    "status" "text" DEFAULT 'going'::"text",
    "created_at" timestamp with time zone DEFAULT "now"()
);

ALTER TABLE "public"."event_rsvps" OWNER TO "postgres";

CREATE TABLE IF NOT EXISTS "public"."events" (
    "id" "uuid" DEFAULT "extensions"."uuid_generate_v4"() NOT NULL,
    "title" "text" NOT NULL,
    "description" "text",
    "event_date" "date",
    "event_time" time without time zone,
    "location" "text",
    "is_online" boolean DEFAULT false,
    "meeting_url" "text",
    "pet_type" "text",
    "breed" "text",
    "banner_url" "text",
    "created_by" "uuid",
    "created_at" timestamp with time zone DEFAULT "now"(),
    "updated_at" timestamp with time zone DEFAULT "now"(),
    "recurrence_frequency" "text" DEFAULT 'none'::"text"
);

ALTER TABLE "public"."events" OWNER TO "postgres";

CREATE TABLE IF NOT EXISTS "public"."pet_pack_members" (
    "id" "uuid" DEFAULT "extensions"."uuid_generate_v4"() NOT NULL,
    "owner_user_id" "uuid" NOT NULL,
    "linked_user_id" "uuid",
    "pet_name" "text" NOT NULL,
    "relation" "text" NOT NULL,
    "date_of_birth" "date",
    "gender" "text",
    "pet_type" "text",
    "breed" "text",
    "microchip_number" "text",
    "sort_order" integer DEFAULT 100 NOT NULL,
    "created_at" timestamp with time zone DEFAULT "now"(),
    "updated_at" timestamp with time zone DEFAULT "now"(),
    CONSTRAINT "pet_pack_members_relation_check" CHECK (("relation" = ANY (ARRAY['Sibling Pet'::"text", 'Parent'::"text", 'Other'::"text"])))
);

ALTER TABLE "public"."pet_pack_members" OWNER TO "postgres";

CREATE TABLE IF NOT EXISTS "public"."friendships" (
    "id" "uuid" DEFAULT "extensions"."uuid_generate_v4"() NOT NULL,
    "requester" "uuid" NOT NULL,
    "addressee" "uuid" NOT NULL,
    "status" "public"."friend_status" DEFAULT 'pending'::"public"."friend_status",
    "created_at" timestamp with time zone DEFAULT "now"(),
    "updated_at" timestamp with time zone DEFAULT "now"(),
    CONSTRAINT "no_self_friend" CHECK (("requester" <> "addressee"))
);

ALTER TABLE "public"."friendships" OWNER TO "postgres";

CREATE TABLE IF NOT EXISTS "public"."gallery_collections" (
    "id" "uuid" DEFAULT "gen_random_uuid"() NOT NULL,
    "owner_user_id" "uuid" NOT NULL,
    "event_id" "uuid",
    "title" "text" NOT NULL,
    "description" "text",
    "visibility" "text" DEFAULT 'private'::"text" NOT NULL,
    "created_at" timestamp with time zone DEFAULT "now"() NOT NULL,
    "updated_at" timestamp with time zone DEFAULT "now"() NOT NULL,
    CONSTRAINT "gallery_collections_visibility_check" CHECK (("visibility" = ANY (ARRAY['public'::"text", 'breed'::"text", 'pet_type'::"text", 'private'::"text"])))
);

ALTER TABLE "public"."gallery_collections" OWNER TO "postgres";

CREATE TABLE IF NOT EXISTS "public"."gallery_items" (
    "id" "uuid" DEFAULT "gen_random_uuid"() NOT NULL,
    "gallery_id" "uuid" NOT NULL,
    "media_url" "text" NOT NULL,
    "media_type" "text" DEFAULT 'image'::"text" NOT NULL,
    "caption" "text",
    "sort_order" integer DEFAULT 0 NOT NULL,
    "created_at" timestamp with time zone DEFAULT "now"() NOT NULL,
    CONSTRAINT "gallery_items_media_type_check" CHECK (("media_type" = ANY (ARRAY['image'::"text", 'video'::"text"])))
);

ALTER TABLE "public"."gallery_items" OWNER TO "postgres";

CREATE TABLE IF NOT EXISTS "public"."group_members" (
    "group_id" "uuid" NOT NULL,
    "user_id" "uuid" NOT NULL,
    "role" "public"."group_member_role" DEFAULT 'member'::"public"."group_member_role",
    "joined_at" timestamp with time zone DEFAULT "now"()
);

ALTER TABLE "public"."group_members" OWNER TO "postgres";

CREATE TABLE IF NOT EXISTS "public"."group_messages" (
    "id" "uuid" DEFAULT "extensions"."uuid_generate_v4"() NOT NULL,
    "group_id" "uuid" NOT NULL,
    "sender_id" "uuid",
    "content" "text",
    "media_url" "text",
    "is_deleted" boolean DEFAULT false,
    "created_at" timestamp with time zone DEFAULT "now"()
);

ALTER TABLE "public"."group_messages" OWNER TO "postgres";

CREATE TABLE IF NOT EXISTS "public"."groups" (
    "id" "uuid" DEFAULT "extensions"."uuid_generate_v4"() NOT NULL,
    "name" "text" NOT NULL,
    "description" "text",
    "avatar_url" "text",
    "pet_type" "text",
    "breed" "text",
    "created_by" "uuid",
    "is_private" boolean DEFAULT false,
    "created_at" timestamp with time zone DEFAULT "now"(),
    "updated_at" timestamp with time zone DEFAULT "now"(),
    "scope" "text" DEFAULT 'global'::"text" NOT NULL,
    "pack_key" "text",
    CONSTRAINT "groups_scope_check" CHECK (("scope" = ANY (ARRAY['breed'::"text", 'pet_type'::"text", 'global'::"text"])))
);

ALTER TABLE "public"."groups" OWNER TO "postgres";

CREATE TABLE IF NOT EXISTS "public"."pet_services" (
    "id" "uuid" DEFAULT "gen_random_uuid"() NOT NULL,
    "slug" "text" NOT NULL,
    "service_type" "text" NOT NULL,
    "title" "text" NOT NULL,
    "subtitle" "text",
    "description" "text",
    "language" "text",
    "source_label" "text",
    "source_url" "text",
    "bucket_id" "text" DEFAULT 'pet-services'::"text" NOT NULL,
    "pdf_path" "text",
    "epub_path" "text",
    "default_read_mode" "text" DEFAULT 'scroll'::"text" NOT NULL,
    "scroll_enabled" boolean DEFAULT true NOT NULL,
    "page_enabled" boolean DEFAULT true NOT NULL,
    "pdf_download_enabled" boolean DEFAULT true NOT NULL,
    "epub_download_enabled" boolean DEFAULT false NOT NULL,
    "sort_order" integer DEFAULT 100 NOT NULL,
    "is_active" boolean DEFAULT true NOT NULL,
    "created_at" timestamp with time zone DEFAULT "now"() NOT NULL,
    "updated_at" timestamp with time zone DEFAULT "now"() NOT NULL,
    "external_pdf_url" "text",
    "external_epub_url" "text",
    CONSTRAINT "pet_services_default_read_mode_check" CHECK (("default_read_mode" = ANY (ARRAY['scroll'::"text", 'page'::"text"])))
);

ALTER TABLE "public"."pet_services" OWNER TO "postgres";

CREATE TABLE IF NOT EXISTS "public"."playdate_preferences" (
    "id" "uuid" DEFAULT "extensions"."uuid_generate_v4"() NOT NULL,
    "user_id" "uuid" NOT NULL,
    "pref_gender" "text" DEFAULT 'Any'::"text",
    "pref_age_min_months" integer DEFAULT 0,
    "pref_age_max_months" integer DEFAULT 240,
    "pref_size" "text" DEFAULT 'Any'::"text",
    "pref_energy_level" "text" DEFAULT 'Any'::"text",
    "pref_breed" "text" DEFAULT 'Any'::"text",
    "pref_pet_type" "text" DEFAULT 'Any'::"text",
    "created_at" timestamp with time zone DEFAULT "now"(),
    "updated_at" timestamp with time zone DEFAULT "now"()
);

ALTER TABLE "public"."playdate_preferences" OWNER TO "postgres";

CREATE TABLE IF NOT EXISTS "public"."playdate_profiles" (
    "user_id" "uuid" NOT NULL,
    "weight_kg" integer,
    "energy_level" "text",
    "size" "text",
    "vaccination_status" "text",
    "friendliness_to_dogs" "text",
    "friendliness_to_cats" "text",
    "favorite_activities" "text",
    "insurance_provider" "text",
    "dietary_restrictions" "text",
    "is_active" boolean DEFAULT true,
    "created_at" timestamp with time zone DEFAULT "now"(),
    "updated_at" timestamp with time zone DEFAULT "now"()
);

ALTER TABLE "public"."playdate_profiles" OWNER TO "postgres";

CREATE TABLE IF NOT EXISTS "public"."notifications" (
    "id" "uuid" DEFAULT "extensions"."uuid_generate_v4"() NOT NULL,
    "user_id" "uuid" NOT NULL,
    "type" "text" NOT NULL,
    "title" "text",
    "body" "text",
    "data" "jsonb",
    "is_read" boolean DEFAULT false,
    "created_at" timestamp with time zone DEFAULT "now"()
);

ALTER TABLE "public"."notifications" OWNER TO "postgres";

CREATE TABLE IF NOT EXISTS "public"."pet_memorials" (
    "id" "uuid" DEFAULT "extensions"."uuid_generate_v4"() NOT NULL,
    "full_name" "text" NOT NULL,
    "date_of_birth" "date",
    "date_of_death" "date" NOT NULL,
    "breed" "text",
    "pet_type" "text",
    "description" "text",
    "photo_url" "text",
    "submitted_by" "uuid",
    "is_approved" boolean DEFAULT false,
    "created_at" timestamp with time zone DEFAULT "now"()
);

ALTER TABLE "public"."pet_memorials" OWNER TO "postgres";

CREATE TABLE IF NOT EXISTS "public"."password_reset_tokens" (
    "id" "uuid" DEFAULT "gen_random_uuid"() NOT NULL,
    "user_id" "uuid" NOT NULL,
    "token_hash" "text" NOT NULL,
    "expires_at" timestamp with time zone NOT NULL,
    "used_at" timestamp with time zone,
    "created_at" timestamp with time zone DEFAULT "now"() NOT NULL
);

ALTER TABLE "public"."password_reset_tokens" OWNER TO "postgres";

CREATE TABLE IF NOT EXISTS "public"."post_comments" (
    "id" "uuid" DEFAULT "extensions"."uuid_generate_v4"() NOT NULL,
    "post_id" "uuid" NOT NULL,
    "user_id" "uuid" NOT NULL,
    "content" "text" NOT NULL,
    "is_deleted" boolean DEFAULT false,
    "created_at" timestamp with time zone DEFAULT "now"(),
    "parent_id" "uuid"
);

ALTER TABLE "public"."post_comments" OWNER TO "postgres";

CREATE TABLE IF NOT EXISTS "public"."post_likes" (
    "post_id" "uuid" NOT NULL,
    "user_id" "uuid" NOT NULL,
    "created_at" timestamp with time zone DEFAULT "now"()
);

ALTER TABLE "public"."post_likes" OWNER TO "postgres";

CREATE TABLE IF NOT EXISTS "public"."posts" (
    "id" "uuid" DEFAULT "extensions"."uuid_generate_v4"() NOT NULL,
    "user_id" "uuid" NOT NULL,
    "content" "text",
    "media_url" "text",
    "post_type" "public"."post_type" DEFAULT 'text'::"public"."post_type",
    "pet_type" "text",
    "breed" "text",
    "is_deleted" boolean DEFAULT false,
    "created_at" timestamp with time zone DEFAULT "now"(),
    "updated_at" timestamp with time zone DEFAULT "now"(),
    "title" "text",
    "description" "text",
    "hashtags" "text"[]
);

ALTER TABLE "public"."posts" OWNER TO "postgres";

CREATE TABLE IF NOT EXISTS "public"."profiles" (
    "user_id" "uuid" NOT NULL,
    "pet_name" "text" NOT NULL,
    "parent_name" "text" NOT NULL,
    "pet_type" "text",
    "breed" "text",
    "microchip_number" "text",
    "date_of_birth" "date",
    "gender" "text",
    "mobile_number" "text",
    "current_city" "text",
    "profile_photo_url" "text",
    "membership_applied" boolean DEFAULT false,
    "status" "public"."membership_status" DEFAULT 'none'::"public"."membership_status",
    "terms_accepted" boolean DEFAULT false NOT NULL,
    "privacy_accepted" boolean DEFAULT false NOT NULL,
    "accuracy_certified" boolean DEFAULT false NOT NULL,
    "created_at" timestamp with time zone DEFAULT "now"(),
    "updated_at" timestamp with time zone DEFAULT "now"(),
    "is_public" boolean DEFAULT true NOT NULL,
    "cover_photo_url" "text",
    "visibility" "text" DEFAULT 'public'::"text" NOT NULL,
    "online_status" "text" DEFAULT 'offline'::"text" NOT NULL,
    "social_links" "jsonb" DEFAULT '{}'::"jsonb",
    "bio" "text",
    "privacy_settings" "jsonb" DEFAULT '{"hidePhotos": false, "hideContact": true}'::"jsonb",
    CONSTRAINT "profiles_visibility_check" CHECK (("visibility" = ANY (ARRAY['public'::"text", 'breed'::"text", 'pet_type'::"text", 'private'::"text"])))
);

ALTER TABLE "public"."profiles" OWNER TO "postgres";

CREATE TABLE IF NOT EXISTS "public"."rate_limits" (
    "id" "uuid" DEFAULT "gen_random_uuid"() NOT NULL,
    "key" "text" NOT NULL,
    "action" "text" NOT NULL,
    "count" integer DEFAULT 1 NOT NULL,
    "window_start" timestamp with time zone DEFAULT "now"() NOT NULL
);

ALTER TABLE "public"."rate_limits" OWNER TO "postgres";

CREATE TABLE IF NOT EXISTS "public"."servers" (
    "id" bigint NOT NULL,
    "name" "text" NOT NULL,
    "host" "text" NOT NULL,
    "port" integer DEFAULT 80 NOT NULL,
    "latitude" double precision NOT NULL,
    "longitude" double precision NOT NULL,
    "pet_type" "text" DEFAULT 'global'::"text" NOT NULL,
    "status" "text" DEFAULT 'online'::"text" NOT NULL,
    "latency_ms" integer,
    "created_at" timestamp with time zone DEFAULT "now"()
);

ALTER TABLE "public"."servers" OWNER TO "postgres";

COMMENT ON TABLE "public"."servers" IS 'PawCircle Global server infrastructure nodes for location globe & health status monitoring.';

COMMENT ON COLUMN "public"."servers"."id" IS 'Unique auto-incrementing node identifier.';

COMMENT ON COLUMN "public"."servers"."name" IS 'Human-readable label of the server node location (e.g. AP-South (Mumbai)).';

COMMENT ON COLUMN "public"."servers"."host" IS 'IP address or domain name to reach the node.';

COMMENT ON COLUMN "public"."servers"."port" IS 'TCP port to query for latency checks.';

COMMENT ON COLUMN "public"."servers"."latitude" IS 'Geographic coordinate (latitude) for 3D Globe projection.';

COMMENT ON COLUMN "public"."servers"."longitude" IS 'Geographic coordinate (longitude) for 3D Globe projection.';

COMMENT ON COLUMN "public"."servers"."pet_type" IS 'The pet type scope boundary of the node (global, dog, cat, etc.).';

COMMENT ON COLUMN "public"."servers"."status" IS 'Active operational status of the server (online, offline).';

COMMENT ON COLUMN "public"."servers"."latency_ms" IS 'Last recorded connection ping latency in milliseconds.';

ALTER TABLE "public"."servers" ALTER COLUMN "id" ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME "public"."servers_id_seq"
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE IF NOT EXISTS "public"."sessions" (
    "id" "uuid" DEFAULT "gen_random_uuid"() NOT NULL,
    "user_id" "uuid" NOT NULL,
    "token_hash" "text" NOT NULL,
    "created_at" timestamp with time zone DEFAULT "now"() NOT NULL,
    "expires_at" timestamp with time zone NOT NULL,
    "revoked_at" timestamp with time zone,
    "user_agent" "text",
    "ip_hash" "text"
);

ALTER TABLE "public"."sessions" OWNER TO "postgres";

CREATE TABLE IF NOT EXISTS "public"."signup_verifications" (
    "id" "uuid" DEFAULT "gen_random_uuid"() NOT NULL,
    "email" "text" NOT NULL,
    "code_hash" "text" NOT NULL,
    "payload" "jsonb" DEFAULT '{}'::"jsonb" NOT NULL,
    "attempts" integer DEFAULT 0 NOT NULL,
    "expires_at" timestamp with time zone NOT NULL,
    "created_at" timestamp with time zone DEFAULT "now"() NOT NULL
);

ALTER TABLE "public"."signup_verifications" OWNER TO "postgres";

CREATE TABLE IF NOT EXISTS "public"."user_login_events" (
    "id" "uuid" DEFAULT "gen_random_uuid"() NOT NULL,
    "user_id" "uuid",
    "email_hash" "text",
    "success" boolean DEFAULT false NOT NULL,
    "reason" "text",
    "ip_hash" "text",
    "user_agent" "text",
    "created_at" timestamp with time zone DEFAULT "now"() NOT NULL
);

ALTER TABLE "public"."user_login_events" OWNER TO "postgres";

CREATE TABLE IF NOT EXISTS "public"."user_migration_review" (
    "user_id" "uuid" NOT NULL,
    "migration_status" "text" DEFAULT 'needs_manual_review'::"text" NOT NULL,
    "notes" "text",
    "reviewed_at" timestamp with time zone,
    "reviewed_by" "uuid",
    CONSTRAINT "user_migration_review_status_check" CHECK (("migration_status" = ANY (ARRAY['real_user'::"text", 'test_user'::"text", 'admin_test_user'::"text", 'delete_later'::"text", 'needs_manual_review'::"text"])))
);

ALTER TABLE "public"."user_migration_review" OWNER TO "postgres";

CREATE TABLE IF NOT EXISTS "public"."user_sessions" (
    "id" "uuid" DEFAULT "gen_random_uuid"() NOT NULL,
    "user_id" "uuid" NOT NULL,
    "role" "text" DEFAULT 'member'::"text" NOT NULL,
    "token_hash" "text" NOT NULL,
    "csrf_hash" "text" NOT NULL,
    "ip_hash" "text",
    "user_agent" "text",
    "created_at" timestamp with time zone DEFAULT "now"() NOT NULL,
    "expires_at" timestamp with time zone NOT NULL,
    "revoked_at" timestamp with time zone,
    "last_seen_at" timestamp with time zone,
    "admin_mode_until" timestamp with time zone,
    CONSTRAINT "user_sessions_role_check" CHECK (("role" = ANY (ARRAY['member'::"text", 'admin'::"text"])))
);

ALTER TABLE "public"."user_sessions" OWNER TO "postgres";

CREATE TABLE IF NOT EXISTS "public"."users" (
    "id" "uuid" DEFAULT "extensions"."uuid_generate_v4"() NOT NULL,
    "email" "text" NOT NULL,
    "password_hash" "text",
    "role" "public"."user_role" DEFAULT 'member'::"public"."user_role",
    "created_at" timestamp with time zone DEFAULT "now"(),
    "updated_at" timestamp with time zone DEFAULT "now"(),
    "deactivated_at" timestamp with time zone,
    "last_login_at" timestamp with time zone,
    "last_active_at" timestamp with time zone,
    "is_verified" boolean DEFAULT false,
    "verified_at" timestamp with time zone,
    "verified_by" "uuid",
    "auth_user_id" "uuid"
);

ALTER TABLE "public"."users" OWNER TO "postgres";

CREATE TABLE IF NOT EXISTS "public"."verification_requests" (
    "id" "uuid" DEFAULT "gen_random_uuid"() NOT NULL,
    "user_id" "uuid" NOT NULL,
    "parent_name" "text" NOT NULL,
    "id_type" "text" NOT NULL,
    "microchip_number" "text" NOT NULL,
    "reason" "text",
    "status" "text" DEFAULT 'pending'::"text" NOT NULL,
    "created_at" timestamp with time zone DEFAULT "now"() NOT NULL,
    "reviewed_at" timestamp with time zone,
    "reviewed_by" "uuid"
);

ALTER TABLE "public"."verification_requests" OWNER TO "postgres";

CREATE TABLE IF NOT EXISTS "public"."volunteer_applications" (
    "id" "uuid" DEFAULT "extensions"."uuid_generate_v4"() NOT NULL,
    "opportunity_id" "uuid" NOT NULL,
    "user_id" "uuid" NOT NULL,
    "name" "text" NOT NULL,
    "phone" "text",
    "status" "text" DEFAULT 'confirmed'::"text" NOT NULL,
    "created_at" timestamp with time zone DEFAULT "now"() NOT NULL
);

ALTER TABLE "public"."volunteer_applications" OWNER TO "postgres";

CREATE TABLE IF NOT EXISTS "public"."volunteer_opportunities" (
    "id" "uuid" DEFAULT "extensions"."uuid_generate_v4"() NOT NULL,
    "owner_id" "uuid" NOT NULL,
    "title" "text" NOT NULL,
    "org" "text" NOT NULL,
    "category" "text" DEFAULT 'rescue'::"text" NOT NULL,
    "location" "text" NOT NULL,
    "event_date" "date",
    "slots" integer DEFAULT 10 NOT NULL,
    "urgency" "text" DEFAULT 'medium'::"text" NOT NULL,
    "contact" "text",
    "description" "text",
    "skills" "jsonb" DEFAULT '[]'::"jsonb",
    "status" "text" DEFAULT 'open'::"text" NOT NULL,
    "created_at" timestamp with time zone DEFAULT "now"() NOT NULL
);

ALTER TABLE "public"."volunteer_opportunities" OWNER TO "postgres";

ALTER TABLE ONLY "public"."admin_roles"
    ADD CONSTRAINT "admin_roles_pkey" PRIMARY KEY ("id");

ALTER TABLE ONLY "public"."admin_user_actions"
    ADD CONSTRAINT "admin_user_actions_pkey" PRIMARY KEY ("id");

ALTER TABLE ONLY "public"."admin_user_notes"
    ADD CONSTRAINT "admin_user_notes_pkey" PRIMARY KEY ("id");

ALTER TABLE ONLY "public"."audit_logs"
    ADD CONSTRAINT "audit_logs_pkey" PRIMARY KEY ("id");

ALTER TABLE ONLY "public"."auth_rate_limits"
    ADD CONSTRAINT "auth_rate_limits_pkey" PRIMARY KEY ("rate_key");

ALTER TABLE ONLY "public"."call_events"
    ADD CONSTRAINT "call_events_pkey" PRIMARY KEY ("id");

ALTER TABLE ONLY "public"."call_participants"
    ADD CONSTRAINT "call_participants_call_id_user_id_key" UNIQUE ("call_id", "user_id");

ALTER TABLE ONLY "public"."call_participants"
    ADD CONSTRAINT "call_participants_pkey" PRIMARY KEY ("id");

ALTER TABLE ONLY "public"."call_sessions"
    ADD CONSTRAINT "call_sessions_pkey" PRIMARY KEY ("id");

ALTER TABLE ONLY "public"."comment_likes"
    ADD CONSTRAINT "comment_likes_pkey" PRIMARY KEY ("id");

ALTER TABLE ONLY "public"."comment_likes"
    ADD CONSTRAINT "comment_likes_user_id_comment_id_key" UNIQUE ("user_id", "comment_id");

ALTER TABLE ONLY "public"."direct_messages"
    ADD CONSTRAINT "direct_messages_pkey" PRIMARY KEY ("id");

ALTER TABLE ONLY "public"."event_rsvps"
    ADD CONSTRAINT "event_rsvps_pkey" PRIMARY KEY ("event_id", "user_id");

ALTER TABLE ONLY "public"."events"
    ADD CONSTRAINT "events_pkey" PRIMARY KEY ("id");

ALTER TABLE ONLY "public"."pet_pack_members"
    ADD CONSTRAINT "pet_pack_members_pkey" PRIMARY KEY ("id");

ALTER TABLE ONLY "public"."friendships"
    ADD CONSTRAINT "friendships_pkey" PRIMARY KEY ("id");

ALTER TABLE ONLY "public"."friendships"
    ADD CONSTRAINT "friendships_requester_addressee_key" UNIQUE ("requester", "addressee");

ALTER TABLE ONLY "public"."gallery_collections"
    ADD CONSTRAINT "gallery_collections_pkey" PRIMARY KEY ("id");

ALTER TABLE ONLY "public"."gallery_items"
    ADD CONSTRAINT "gallery_items_pkey" PRIMARY KEY ("id");

ALTER TABLE ONLY "public"."group_members"
    ADD CONSTRAINT "group_members_pkey" PRIMARY KEY ("group_id", "user_id");

ALTER TABLE ONLY "public"."group_members"
    ADD CONSTRAINT "group_members_unique" UNIQUE ("group_id", "user_id");

ALTER TABLE ONLY "public"."group_messages"
    ADD CONSTRAINT "group_messages_pkey" PRIMARY KEY ("id");

ALTER TABLE ONLY "public"."groups"
    ADD CONSTRAINT "groups_pkey" PRIMARY KEY ("id");

ALTER TABLE ONLY "public"."pet_services"
    ADD CONSTRAINT "pet_services_pkey" PRIMARY KEY ("id");

ALTER TABLE ONLY "public"."pet_services"
    ADD CONSTRAINT "pet_services_slug_key" UNIQUE ("slug");

ALTER TABLE ONLY "public"."playdate_preferences"
    ADD CONSTRAINT "playdate_preferences_pkey" PRIMARY KEY ("id");

ALTER TABLE ONLY "public"."playdate_profiles"
    ADD CONSTRAINT "playdate_profiles_pkey" PRIMARY KEY ("user_id");

ALTER TABLE ONLY "public"."notifications"
    ADD CONSTRAINT "notifications_pkey" PRIMARY KEY ("id");

ALTER TABLE ONLY "public"."pet_memorials"
    ADD CONSTRAINT "pet_memorials_pkey" PRIMARY KEY ("id");

ALTER TABLE ONLY "public"."password_reset_tokens"
    ADD CONSTRAINT "password_reset_tokens_pkey" PRIMARY KEY ("id");

ALTER TABLE ONLY "public"."post_comments"
    ADD CONSTRAINT "post_comments_pkey" PRIMARY KEY ("id");

ALTER TABLE ONLY "public"."post_likes"
    ADD CONSTRAINT "post_likes_pkey" PRIMARY KEY ("post_id", "user_id");

ALTER TABLE ONLY "public"."post_likes"
    ADD CONSTRAINT "post_likes_unique" UNIQUE ("post_id", "user_id");

ALTER TABLE ONLY "public"."posts"
    ADD CONSTRAINT "posts_pkey" PRIMARY KEY ("id");

ALTER TABLE ONLY "public"."profiles"
    ADD CONSTRAINT "profiles_pkey" PRIMARY KEY ("user_id");

ALTER TABLE ONLY "public"."rate_limits"
    ADD CONSTRAINT "rate_limits_pkey" PRIMARY KEY ("id");

ALTER TABLE ONLY "public"."servers"
    ADD CONSTRAINT "servers_pkey" PRIMARY KEY ("id");

ALTER TABLE ONLY "public"."sessions"
    ADD CONSTRAINT "sessions_pkey" PRIMARY KEY ("id");

ALTER TABLE ONLY "public"."signup_verifications"
    ADD CONSTRAINT "signup_verifications_pkey" PRIMARY KEY ("id");

ALTER TABLE ONLY "public"."playdate_preferences"
    ADD CONSTRAINT "unique_user_preference" UNIQUE ("user_id");

ALTER TABLE ONLY "public"."user_login_events"
    ADD CONSTRAINT "user_login_events_pkey" PRIMARY KEY ("id");

ALTER TABLE ONLY "public"."user_migration_review"
    ADD CONSTRAINT "user_migration_review_pkey" PRIMARY KEY ("user_id");

ALTER TABLE ONLY "public"."user_sessions"
    ADD CONSTRAINT "user_sessions_pkey" PRIMARY KEY ("id");

ALTER TABLE ONLY "public"."user_sessions"
    ADD CONSTRAINT "user_sessions_token_hash_key" UNIQUE ("token_hash");

ALTER TABLE ONLY "public"."users"
    ADD CONSTRAINT "users_auth_user_id_key" UNIQUE ("auth_user_id");

ALTER TABLE ONLY "public"."users"
    ADD CONSTRAINT "users_email_key" UNIQUE ("email");

ALTER TABLE ONLY "public"."users"
    ADD CONSTRAINT "users_pkey" PRIMARY KEY ("id");

ALTER TABLE ONLY "public"."verification_requests"
    ADD CONSTRAINT "verification_requests_pkey" PRIMARY KEY ("id");

ALTER TABLE ONLY "public"."volunteer_applications"
    ADD CONSTRAINT "volunteer_applications_opportunity_id_user_id_key" UNIQUE ("opportunity_id", "user_id");

ALTER TABLE ONLY "public"."volunteer_applications"
    ADD CONSTRAINT "volunteer_applications_pkey" PRIMARY KEY ("id");

ALTER TABLE ONLY "public"."volunteer_opportunities"
    ADD CONSTRAINT "volunteer_opportunities_pkey" PRIMARY KEY ("id");

CREATE UNIQUE INDEX "call_participants_call_user_unique" ON "public"."call_participants" USING "btree" ("call_id", "user_id");

CREATE UNIQUE INDEX "groups_pack_key_unique" ON "public"."groups" USING "btree" ("pack_key") WHERE ("pack_key" IS NOT NULL);

CREATE UNIQUE INDEX "idx_admin_roles_active_unique" ON "public"."admin_roles" USING "btree" ("user_id", "role", "scope_type", "scope_value") WHERE ("revoked_at" IS NULL);

CREATE INDEX "idx_admin_roles_user_active" ON "public"."admin_roles" USING "btree" ("user_id") WHERE ("revoked_at" IS NULL);

CREATE INDEX "idx_admin_user_actions_active_window" ON "public"."admin_user_actions" USING "btree" ("is_active", "starts_at", "ends_at");

CREATE INDEX "idx_admin_user_actions_user_active" ON "public"."admin_user_actions" USING "btree" ("user_id", "is_active", "created_at" DESC);

CREATE INDEX "idx_admin_user_notes_user_created" ON "public"."admin_user_notes" USING "btree" ("user_id", "created_at" DESC);

CREATE INDEX "idx_audit_logs_action" ON "public"."audit_logs" USING "btree" ("action");

CREATE INDEX "idx_audit_logs_user" ON "public"."audit_logs" USING "btree" ("user_id");

CREATE INDEX "idx_auth_rate_limits_blocked_until" ON "public"."auth_rate_limits" USING "btree" ("blocked_until");

CREATE INDEX "idx_auth_rate_limits_updated_at" ON "public"."auth_rate_limits" USING "btree" ("updated_at");

CREATE INDEX "idx_comment_likes_comment_id" ON "public"."comment_likes" USING "btree" ("comment_id");

CREATE INDEX "idx_comment_likes_user_id" ON "public"."comment_likes" USING "btree" ("user_id");

CREATE INDEX "idx_comments_post_id_created_at" ON "public"."post_comments" USING "btree" ("post_id", "created_at");

CREATE INDEX "idx_direct_messages_recipient_sender" ON "public"."direct_messages" USING "btree" ("recipient_id", "sender_id", "created_at" DESC);

CREATE INDEX "idx_direct_messages_sender_recipient" ON "public"."direct_messages" USING "btree" ("sender_id", "recipient_id", "created_at" DESC);

CREATE INDEX "idx_events_created_at" ON "public"."events" USING "btree" ("created_at" DESC);

CREATE INDEX "idx_events_event_date" ON "public"."events" USING "btree" ("event_date" DESC);

CREATE INDEX "idx_pet_pack_members_linked_user" ON "public"."pet_pack_members" USING "btree" ("linked_user_id");

CREATE INDEX "idx_pet_pack_members_owner" ON "public"."pet_pack_members" USING "btree" ("owner_user_id", "sort_order", "created_at");

CREATE INDEX "idx_friendships_accepted_addressee" ON "public"."friendships" USING "btree" ("addressee", "created_at" DESC) WHERE ("status" = 'accepted'::"public"."friend_status");

CREATE INDEX "idx_friendships_accepted_requester" ON "public"."friendships" USING "btree" ("requester", "created_at" DESC) WHERE ("status" = 'accepted'::"public"."friend_status");

CREATE INDEX "idx_friendships_addressee" ON "public"."friendships" USING "btree" ("addressee");

CREATE INDEX "idx_friendships_addressee_status" ON "public"."friendships" USING "btree" ("addressee", "status");

CREATE INDEX "idx_friendships_pending_incoming" ON "public"."friendships" USING "btree" ("addressee", "created_at" DESC) WHERE ("status" = 'pending'::"public"."friend_status");

CREATE INDEX "idx_friendships_requester" ON "public"."friendships" USING "btree" ("requester");

CREATE INDEX "idx_friendships_requester_status" ON "public"."friendships" USING "btree" ("requester", "status");

CREATE INDEX "idx_gallery_collections_event" ON "public"."gallery_collections" USING "btree" ("event_id", "created_at" DESC);

CREATE INDEX "idx_gallery_collections_owner" ON "public"."gallery_collections" USING "btree" ("owner_user_id", "created_at" DESC);

CREATE INDEX "idx_gallery_collections_owner_created" ON "public"."gallery_collections" USING "btree" ("owner_user_id", "created_at" DESC);

CREATE INDEX "idx_gallery_collections_visibility_created" ON "public"."gallery_collections" USING "btree" ("visibility", "created_at" DESC);

CREATE INDEX "idx_gallery_items_gallery" ON "public"."gallery_items" USING "btree" ("gallery_id", "sort_order", "created_at");

CREATE INDEX "idx_gallery_items_gallery_sort" ON "public"."gallery_items" USING "btree" ("gallery_id", "sort_order", "created_at");

CREATE INDEX "idx_group_members_group_id" ON "public"."group_members" USING "btree" ("group_id");

CREATE INDEX "idx_group_members_user" ON "public"."group_members" USING "btree" ("user_id");

CREATE INDEX "idx_group_members_user_id" ON "public"."group_members" USING "btree" ("user_id");

CREATE INDEX "idx_group_messages_group_created_desc" ON "public"."group_messages" USING "btree" ("group_id", "created_at" DESC);

CREATE INDEX "idx_group_messages_group_id_created_at" ON "public"."group_messages" USING "btree" ("group_id", "created_at" DESC);

CREATE INDEX "idx_group_messages_grp" ON "public"."group_messages" USING "btree" ("group_id", "created_at" DESC);

CREATE INDEX "idx_notifications_user_id_created_at" ON "public"."notifications" USING "btree" ("user_id", "created_at" DESC);

CREATE INDEX "idx_notifs_user_unread" ON "public"."notifications" USING "btree" ("user_id") WHERE ("is_read" = false);

CREATE INDEX "idx_password_reset_tokens_user" ON "public"."password_reset_tokens" USING "btree" ("user_id");

CREATE INDEX "idx_post_comments_parent_id" ON "public"."post_comments" USING "btree" ("parent_id");

CREATE INDEX "idx_post_comments_post" ON "public"."post_comments" USING "btree" ("post_id");

CREATE INDEX "idx_post_comments_post_created" ON "public"."post_comments" USING "btree" ("post_id", "created_at");

CREATE INDEX "idx_post_likes_post_id" ON "public"."post_likes" USING "btree" ("post_id");

CREATE INDEX "idx_post_likes_post_user" ON "public"."post_likes" USING "btree" ("post_id", "user_id");

CREATE INDEX "idx_posts_created_at" ON "public"."posts" USING "btree" ("created_at" DESC);

CREATE INDEX "idx_posts_is_deleted_created_at" ON "public"."posts" USING "btree" ("is_deleted", "created_at" DESC);

CREATE INDEX "idx_posts_user_id" ON "public"."posts" USING "btree" ("user_id");

CREATE INDEX "idx_posts_user_id_created_at" ON "public"."posts" USING "btree" ("user_id", "created_at" DESC);

CREATE INDEX "idx_profiles_dob" ON "public"."profiles" USING "btree" ("date_of_birth");


CREATE INDEX "idx_profiles_search_city" ON "public"."profiles" USING "gin" ("current_city" "public"."gin_trgm_ops");



CREATE UNIQUE INDEX "idx_rate_limits_key_action" ON "public"."rate_limits" USING "btree" ("key", "action");

CREATE INDEX "idx_sessions_expires_at" ON "public"."sessions" USING "btree" ("expires_at");

CREATE INDEX "idx_sessions_token_hash" ON "public"."sessions" USING "btree" ("token_hash");

CREATE INDEX "idx_sessions_user_id" ON "public"."sessions" USING "btree" ("user_id");

CREATE INDEX "idx_signup_verifications_email" ON "public"."signup_verifications" USING "btree" ("email");

CREATE INDEX "idx_user_login_events_email_hash_created_at" ON "public"."user_login_events" USING "btree" ("email_hash", "created_at" DESC);

CREATE INDEX "idx_user_login_events_success_created_at" ON "public"."user_login_events" USING "btree" ("success", "created_at" DESC);

CREATE INDEX "idx_user_login_events_user_id_created_at" ON "public"."user_login_events" USING "btree" ("user_id", "created_at" DESC);

CREATE INDEX "idx_user_sessions_status" ON "public"."user_sessions" USING "btree" ("revoked_at", "expires_at", "last_seen_at" DESC);

CREATE INDEX "idx_user_sessions_user" ON "public"."user_sessions" USING "btree" ("user_id", "expires_at" DESC);

CREATE INDEX "idx_user_sessions_valid" ON "public"."user_sessions" USING "btree" ("token_hash") WHERE ("revoked_at" IS NULL);

CREATE UNIQUE INDEX "idx_users_auth_user_id_unique" ON "public"."users" USING "btree" ("auth_user_id") WHERE ("auth_user_id" IS NOT NULL);

CREATE INDEX "idx_users_deactivated_at" ON "public"."users" USING "btree" ("deactivated_at");

CREATE INDEX "idx_users_last_active_at" ON "public"."users" USING "btree" ("last_active_at" DESC);

CREATE INDEX "idx_users_last_login_at" ON "public"."users" USING "btree" ("last_login_at" DESC);

CREATE INDEX "idx_verification_requests_status" ON "public"."verification_requests" USING "btree" ("status");

CREATE INDEX "idx_verification_requests_user_id" ON "public"."verification_requests" USING "btree" ("user_id");

CREATE INDEX "idx_volunteer_applications_opp" ON "public"."volunteer_applications" USING "btree" ("opportunity_id");

CREATE INDEX "idx_volunteer_applications_user" ON "public"."volunteer_applications" USING "btree" ("user_id");

CREATE INDEX "idx_volunteer_opportunities_category" ON "public"."volunteer_opportunities" USING "btree" ("category");

CREATE INDEX "idx_volunteer_opportunities_owner" ON "public"."volunteer_opportunities" USING "btree" ("owner_id");

CREATE INDEX "idx_volunteer_opportunities_status" ON "public"."volunteer_opportunities" USING "btree" ("status");

CREATE UNIQUE INDEX "playdate_preferences_user_id_key" ON "public"."playdate_preferences" USING "btree" ("user_id");

CREATE INDEX "posts_hashtags_gin_idx" ON "public"."posts" USING "gin" ("hashtags");

CREATE OR REPLACE TRIGGER "set_pet_services_updated_at" BEFORE UPDATE ON "public"."pet_services" FOR EACH ROW EXECUTE FUNCTION "public"."set_updated_at"();

CREATE OR REPLACE TRIGGER "trg_events_updated_at" BEFORE UPDATE ON "public"."events" FOR EACH ROW EXECUTE FUNCTION "public"."set_updated_at"();

CREATE OR REPLACE TRIGGER "trg_pet_pack_members_updated_at" BEFORE UPDATE ON "public"."pet_pack_members" FOR EACH ROW EXECUTE FUNCTION "public"."set_updated_at"();

CREATE OR REPLACE TRIGGER "trg_friendships_updated_at" BEFORE UPDATE ON "public"."friendships" FOR EACH ROW EXECUTE FUNCTION "public"."set_updated_at"();

CREATE OR REPLACE TRIGGER "trg_groups_updated_at" BEFORE UPDATE ON "public"."groups" FOR EACH ROW EXECUTE FUNCTION "public"."set_updated_at"();

CREATE OR REPLACE TRIGGER "trg_playdate_profiles_updated_at" BEFORE UPDATE ON "public"."playdate_profiles" FOR EACH ROW EXECUTE FUNCTION "public"."set_updated_at"();

CREATE OR REPLACE TRIGGER "trg_posts_updated_at" BEFORE UPDATE ON "public"."posts" FOR EACH ROW EXECUTE FUNCTION "public"."set_updated_at"();

CREATE OR REPLACE TRIGGER "trg_profiles_updated_at" BEFORE UPDATE ON "public"."profiles" FOR EACH ROW EXECUTE FUNCTION "public"."set_updated_at"();

CREATE OR REPLACE TRIGGER "trg_users_updated_at" BEFORE UPDATE ON "public"."users" FOR EACH ROW EXECUTE FUNCTION "public"."set_updated_at"();

CREATE OR REPLACE TRIGGER "update_playdate_preferences_modtime" BEFORE UPDATE ON "public"."playdate_preferences" FOR EACH ROW EXECUTE FUNCTION "public"."update_modified_column"();

ALTER TABLE ONLY "public"."admin_roles"
    ADD CONSTRAINT "admin_roles_created_by_fkey" FOREIGN KEY ("created_by") REFERENCES "public"."users"("id") ON DELETE SET NULL;

ALTER TABLE ONLY "public"."admin_roles"
    ADD CONSTRAINT "admin_roles_revoked_by_fkey" FOREIGN KEY ("revoked_by") REFERENCES "public"."users"("id") ON DELETE SET NULL;

ALTER TABLE ONLY "public"."admin_roles"
    ADD CONSTRAINT "admin_roles_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE CASCADE;

ALTER TABLE ONLY "public"."admin_user_actions"
    ADD CONSTRAINT "admin_user_actions_created_by_fkey" FOREIGN KEY ("created_by") REFERENCES "public"."users"("id") ON DELETE SET NULL;

ALTER TABLE ONLY "public"."admin_user_actions"
    ADD CONSTRAINT "admin_user_actions_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE CASCADE;

ALTER TABLE ONLY "public"."admin_user_notes"
    ADD CONSTRAINT "admin_user_notes_created_by_fkey" FOREIGN KEY ("created_by") REFERENCES "public"."users"("id") ON DELETE SET NULL;

ALTER TABLE ONLY "public"."admin_user_notes"
    ADD CONSTRAINT "admin_user_notes_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE CASCADE;

ALTER TABLE ONLY "public"."audit_logs"
    ADD CONSTRAINT "audit_logs_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE SET NULL;

ALTER TABLE ONLY "public"."call_events"
    ADD CONSTRAINT "call_events_call_id_fkey" FOREIGN KEY ("call_id") REFERENCES "public"."call_sessions"("id") ON DELETE CASCADE;

ALTER TABLE ONLY "public"."call_events"
    ADD CONSTRAINT "call_events_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id");

ALTER TABLE ONLY "public"."call_participants"
    ADD CONSTRAINT "call_participants_call_id_fkey" FOREIGN KEY ("call_id") REFERENCES "public"."call_sessions"("id") ON DELETE CASCADE;

ALTER TABLE ONLY "public"."call_participants"
    ADD CONSTRAINT "call_participants_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id");

ALTER TABLE ONLY "public"."call_sessions"
    ADD CONSTRAINT "call_sessions_created_by_fkey" FOREIGN KEY ("created_by") REFERENCES "public"."users"("id");

ALTER TABLE ONLY "public"."call_sessions"
    ADD CONSTRAINT "call_sessions_group_id_fkey" FOREIGN KEY ("group_id") REFERENCES "public"."groups"("id");

ALTER TABLE ONLY "public"."comment_likes"
    ADD CONSTRAINT "comment_likes_comment_id_fkey" FOREIGN KEY ("comment_id") REFERENCES "public"."post_comments"("id") ON DELETE CASCADE;

ALTER TABLE ONLY "public"."comment_likes"
    ADD CONSTRAINT "comment_likes_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE CASCADE;

ALTER TABLE ONLY "public"."direct_messages"
    ADD CONSTRAINT "direct_messages_recipient_id_fkey" FOREIGN KEY ("recipient_id") REFERENCES "public"."users"("id") ON DELETE CASCADE;

ALTER TABLE ONLY "public"."direct_messages"
    ADD CONSTRAINT "direct_messages_sender_id_fkey" FOREIGN KEY ("sender_id") REFERENCES "public"."users"("id") ON DELETE CASCADE;

ALTER TABLE ONLY "public"."event_rsvps"
    ADD CONSTRAINT "event_rsvps_event_id_fkey" FOREIGN KEY ("event_id") REFERENCES "public"."events"("id") ON DELETE CASCADE;

ALTER TABLE ONLY "public"."event_rsvps"
    ADD CONSTRAINT "event_rsvps_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE CASCADE;

ALTER TABLE ONLY "public"."events"
    ADD CONSTRAINT "events_created_by_fkey" FOREIGN KEY ("created_by") REFERENCES "public"."users"("id") ON DELETE SET NULL;

ALTER TABLE ONLY "public"."pet_pack_members"
    ADD CONSTRAINT "pet_pack_members_linked_user_id_fkey" FOREIGN KEY ("linked_user_id") REFERENCES "public"."users"("id") ON DELETE SET NULL;

ALTER TABLE ONLY "public"."pet_pack_members"
    ADD CONSTRAINT "pet_pack_members_owner_user_id_fkey" FOREIGN KEY ("owner_user_id") REFERENCES "public"."users"("id") ON DELETE CASCADE;

ALTER TABLE ONLY "public"."friendships"
    ADD CONSTRAINT "friendships_addressee_fkey" FOREIGN KEY ("addressee") REFERENCES "public"."users"("id") ON DELETE CASCADE;

ALTER TABLE ONLY "public"."friendships"
    ADD CONSTRAINT "friendships_requester_fkey" FOREIGN KEY ("requester") REFERENCES "public"."users"("id") ON DELETE CASCADE;

ALTER TABLE ONLY "public"."gallery_collections"
    ADD CONSTRAINT "gallery_collections_event_id_fkey" FOREIGN KEY ("event_id") REFERENCES "public"."events"("id") ON DELETE SET NULL;

ALTER TABLE ONLY "public"."gallery_collections"
    ADD CONSTRAINT "gallery_collections_owner_user_id_fkey" FOREIGN KEY ("owner_user_id") REFERENCES "public"."users"("id") ON DELETE CASCADE;

ALTER TABLE ONLY "public"."gallery_items"
    ADD CONSTRAINT "gallery_items_gallery_id_fkey" FOREIGN KEY ("gallery_id") REFERENCES "public"."gallery_collections"("id") ON DELETE CASCADE;

ALTER TABLE ONLY "public"."group_members"
    ADD CONSTRAINT "group_members_group_id_fkey" FOREIGN KEY ("group_id") REFERENCES "public"."groups"("id") ON DELETE CASCADE;

ALTER TABLE ONLY "public"."group_members"
    ADD CONSTRAINT "group_members_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE CASCADE;

ALTER TABLE ONLY "public"."group_messages"
    ADD CONSTRAINT "group_messages_group_id_fkey" FOREIGN KEY ("group_id") REFERENCES "public"."groups"("id") ON DELETE CASCADE;

ALTER TABLE ONLY "public"."group_messages"
    ADD CONSTRAINT "group_messages_sender_id_fkey" FOREIGN KEY ("sender_id") REFERENCES "public"."users"("id") ON DELETE SET NULL;

ALTER TABLE ONLY "public"."groups"
    ADD CONSTRAINT "groups_created_by_fkey" FOREIGN KEY ("created_by") REFERENCES "public"."users"("id") ON DELETE SET NULL;

ALTER TABLE ONLY "public"."playdate_preferences"
    ADD CONSTRAINT "playdate_preferences_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE CASCADE;

ALTER TABLE ONLY "public"."playdate_profiles"
    ADD CONSTRAINT "playdate_profiles_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE CASCADE;

ALTER TABLE ONLY "public"."notifications"
    ADD CONSTRAINT "notifications_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE CASCADE;

ALTER TABLE ONLY "public"."pet_memorials"
    ADD CONSTRAINT "pet_memorials_submitted_by_fkey" FOREIGN KEY ("submitted_by") REFERENCES "public"."users"("id") ON DELETE SET NULL;

ALTER TABLE ONLY "public"."password_reset_tokens"
    ADD CONSTRAINT "password_reset_tokens_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE CASCADE;

ALTER TABLE ONLY "public"."post_comments"
    ADD CONSTRAINT "post_comments_parent_id_fkey" FOREIGN KEY ("parent_id") REFERENCES "public"."post_comments"("id") ON DELETE CASCADE;

ALTER TABLE ONLY "public"."post_comments"
    ADD CONSTRAINT "post_comments_post_id_fkey" FOREIGN KEY ("post_id") REFERENCES "public"."posts"("id") ON DELETE CASCADE;

ALTER TABLE ONLY "public"."post_comments"
    ADD CONSTRAINT "post_comments_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE CASCADE;

ALTER TABLE ONLY "public"."post_likes"
    ADD CONSTRAINT "post_likes_post_id_fkey" FOREIGN KEY ("post_id") REFERENCES "public"."posts"("id") ON DELETE CASCADE;

ALTER TABLE ONLY "public"."post_likes"
    ADD CONSTRAINT "post_likes_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE CASCADE;

ALTER TABLE ONLY "public"."posts"
    ADD CONSTRAINT "posts_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE CASCADE;

ALTER TABLE ONLY "public"."profiles"
    ADD CONSTRAINT "profiles_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE CASCADE;

ALTER TABLE ONLY "public"."sessions"
    ADD CONSTRAINT "sessions_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE CASCADE;

ALTER TABLE ONLY "public"."user_login_events"
    ADD CONSTRAINT "user_login_events_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE SET NULL;

ALTER TABLE ONLY "public"."user_migration_review"
    ADD CONSTRAINT "user_migration_review_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE CASCADE;

ALTER TABLE ONLY "public"."user_sessions"
    ADD CONSTRAINT "user_sessions_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE CASCADE;

ALTER TABLE ONLY "public"."users"
    ADD CONSTRAINT "users_auth_user_id_fkey" FOREIGN KEY ("auth_user_id") REFERENCES "auth"."users"("id") ON DELETE SET NULL;

ALTER TABLE ONLY "public"."users"
    ADD CONSTRAINT "users_verified_by_fkey" FOREIGN KEY ("verified_by") REFERENCES "public"."users"("id");

ALTER TABLE ONLY "public"."verification_requests"
    ADD CONSTRAINT "verification_requests_reviewed_by_fkey" FOREIGN KEY ("reviewed_by") REFERENCES "public"."users"("id");

ALTER TABLE ONLY "public"."verification_requests"
    ADD CONSTRAINT "verification_requests_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE CASCADE;

ALTER TABLE ONLY "public"."volunteer_applications"
    ADD CONSTRAINT "volunteer_applications_opportunity_id_fkey" FOREIGN KEY ("opportunity_id") REFERENCES "public"."volunteer_opportunities"("id") ON DELETE CASCADE;

ALTER TABLE ONLY "public"."volunteer_applications"
    ADD CONSTRAINT "volunteer_applications_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE CASCADE;

ALTER TABLE ONLY "public"."volunteer_opportunities"
    ADD CONSTRAINT "volunteer_opportunities_owner_id_fkey" FOREIGN KEY ("owner_id") REFERENCES "public"."users"("id") ON DELETE CASCADE;

CREATE POLICY "Public can read active holy books" ON "public"."pet_services" FOR SELECT USING (("is_active" = true));

CREATE POLICY "Users can insert their own preferences" ON "public"."playdate_preferences" FOR INSERT WITH CHECK (("auth"."uid"() = "user_id"));

CREATE POLICY "Users can update their own preferences" ON "public"."playdate_preferences" FOR UPDATE USING (("auth"."uid"() = "user_id"));

CREATE POLICY "Users can view their own preferences" ON "public"."playdate_preferences" FOR SELECT USING (("auth"."uid"() = "user_id"));

ALTER TABLE "public"."admin_roles" ENABLE ROW LEVEL SECURITY;

ALTER TABLE "public"."admin_user_actions" ENABLE ROW LEVEL SECURITY;

ALTER TABLE "public"."admin_user_notes" ENABLE ROW LEVEL SECURITY;

CREATE POLICY "allow_delete_events" ON "public"."events" FOR DELETE USING (true);

CREATE POLICY "allow_delete_friendships" ON "public"."friendships" FOR DELETE USING (true);

CREATE POLICY "allow_delete_post_likes" ON "public"."post_likes" FOR DELETE USING (true);

CREATE POLICY "allow_insert_comments" ON "public"."post_comments" FOR INSERT WITH CHECK (true);

CREATE POLICY "allow_insert_events" ON "public"."events" FOR INSERT WITH CHECK (true);

CREATE POLICY "allow_insert_friendships" ON "public"."friendships" FOR INSERT WITH CHECK (true);

CREATE POLICY "allow_insert_group_members" ON "public"."group_members" FOR INSERT WITH CHECK (true);

CREATE POLICY "allow_insert_group_msgs" ON "public"."group_messages" FOR INSERT WITH CHECK (true);

CREATE POLICY "allow_insert_groups" ON "public"."groups" FOR INSERT WITH CHECK (true);

CREATE POLICY "allow_insert_post_likes" ON "public"."post_likes" FOR INSERT WITH CHECK (true);

CREATE POLICY "allow_insert_posts" ON "public"."posts" FOR INSERT WITH CHECK (true);

CREATE POLICY "allow_update_friendships" ON "public"."friendships" FOR UPDATE USING (true);

ALTER TABLE "public"."audit_logs" ENABLE ROW LEVEL SECURITY;

ALTER TABLE "public"."auth_rate_limits" ENABLE ROW LEVEL SECURITY;



ALTER TABLE "public"."call_events" ENABLE ROW LEVEL SECURITY;

ALTER TABLE "public"."call_participants" ENABLE ROW LEVEL SECURITY;

ALTER TABLE "public"."call_sessions" ENABLE ROW LEVEL SECURITY;

ALTER TABLE "public"."comment_likes" ENABLE ROW LEVEL SECURITY;

CREATE POLICY "comments_insert" ON "public"."post_comments" FOR INSERT WITH CHECK (("user_id" = "auth"."uid"()));

CREATE POLICY "comments_select" ON "public"."post_comments" FOR SELECT USING (("is_deleted" = false));

CREATE POLICY "comments_update" ON "public"."post_comments" FOR UPDATE USING (("user_id" = "auth"."uid"()));

ALTER TABLE "public"."direct_messages" ENABLE ROW LEVEL SECURITY;

ALTER TABLE "public"."event_rsvps" ENABLE ROW LEVEL SECURITY;

CREATE POLICY "event_rsvps_insert" ON "public"."event_rsvps" FOR INSERT WITH CHECK (("user_id" = "auth"."uid"()));

CREATE POLICY "event_rsvps_select" ON "public"."event_rsvps" FOR SELECT USING (true);

ALTER TABLE "public"."events" ENABLE ROW LEVEL SECURITY;

CREATE POLICY "events_select_all" ON "public"."events" FOR SELECT USING (true);

ALTER TABLE "public"."pet_pack_members" ENABLE ROW LEVEL SECURITY;

ALTER TABLE "public"."friendships" ENABLE ROW LEVEL SECURITY;

CREATE POLICY "friendships_insert" ON "public"."friendships" FOR INSERT WITH CHECK (("requester" = "auth"."uid"()));

CREATE POLICY "friendships_select" ON "public"."friendships" FOR SELECT USING ((("requester" = "auth"."uid"()) OR ("addressee" = "auth"."uid"())));

CREATE POLICY "friendships_update" ON "public"."friendships" FOR UPDATE USING ((("requester" = "auth"."uid"()) OR ("addressee" = "auth"."uid"())));

ALTER TABLE "public"."gallery_collections" ENABLE ROW LEVEL SECURITY;

ALTER TABLE "public"."gallery_items" ENABLE ROW LEVEL SECURITY;

ALTER TABLE "public"."group_members" ENABLE ROW LEVEL SECURITY;

ALTER TABLE "public"."group_messages" ENABLE ROW LEVEL SECURITY;

CREATE POLICY "group_messages_insert" ON "public"."group_messages" FOR INSERT WITH CHECK ((("sender_id" = "auth"."uid"()) AND ("group_id" IN ( SELECT "group_members"."group_id"
   FROM "public"."group_members"
  WHERE ("group_members"."user_id" = "auth"."uid"())))));

CREATE POLICY "group_messages_select" ON "public"."group_messages" FOR SELECT USING (("group_id" IN ( SELECT "group_members"."group_id"
   FROM "public"."group_members"
  WHERE ("group_members"."user_id" = "auth"."uid"()))));

ALTER TABLE "public"."groups" ENABLE ROW LEVEL SECURITY;

CREATE POLICY "groups_select" ON "public"."groups" FOR SELECT USING ((("is_private" = false) OR ("id" IN ( SELECT "group_members"."group_id"
   FROM "public"."group_members"
  WHERE ("group_members"."user_id" = "auth"."uid"())))));

ALTER TABLE "public"."pet_services" ENABLE ROW LEVEL SECURITY;

ALTER TABLE "public"."playdate_preferences" ENABLE ROW LEVEL SECURITY;

ALTER TABLE "public"."playdate_profiles" ENABLE ROW LEVEL SECURITY;

CREATE POLICY "playdate_select" ON "public"."playdate_profiles" FOR SELECT USING (("is_active" = true));

CREATE POLICY "playdate_update_own" ON "public"."playdate_profiles" FOR UPDATE USING (("user_id" = "auth"."uid"()));

ALTER TABLE "public"."notifications" ENABLE ROW LEVEL SECURITY;

CREATE POLICY "notifs_select_own" ON "public"."notifications" FOR SELECT USING (("user_id" = "auth"."uid"()));

CREATE POLICY "notifs_update_own" ON "public"."notifications" FOR UPDATE USING (("user_id" = "auth"."uid"()));

ALTER TABLE "public"."pet_memorials" ENABLE ROW LEVEL SECURITY;

CREATE POLICY "pet_memorials_select" ON "public"."pet_memorials" FOR SELECT USING (("is_approved" = true));

ALTER TABLE "public"."password_reset_tokens" ENABLE ROW LEVEL SECURITY;

ALTER TABLE "public"."post_comments" ENABLE ROW LEVEL SECURITY;

ALTER TABLE "public"."post_likes" ENABLE ROW LEVEL SECURITY;

CREATE POLICY "post_likes_delete" ON "public"."post_likes" FOR DELETE USING (("user_id" = "auth"."uid"()));

CREATE POLICY "post_likes_insert" ON "public"."post_likes" FOR INSERT WITH CHECK (("user_id" = "auth"."uid"()));

CREATE POLICY "post_likes_select" ON "public"."post_likes" FOR SELECT USING (true);

ALTER TABLE "public"."posts" ENABLE ROW LEVEL SECURITY;

CREATE POLICY "posts_insert_own" ON "public"."posts" FOR INSERT WITH CHECK (("user_id" = "auth"."uid"()));

CREATE POLICY "posts_select_all" ON "public"."posts" FOR SELECT USING (("is_deleted" = false));

CREATE POLICY "posts_update_own" ON "public"."posts" FOR UPDATE USING (("user_id" = "auth"."uid"()));

ALTER TABLE "public"."profiles" ENABLE ROW LEVEL SECURITY;

CREATE POLICY "profiles_select_public" ON "public"."profiles" FOR SELECT USING ((("is_public" = true) OR ("user_id" = "auth"."uid"())));

CREATE POLICY "profiles_update_own" ON "public"."profiles" FOR UPDATE USING (("user_id" = "auth"."uid"()));

ALTER TABLE "public"."rate_limits" ENABLE ROW LEVEL SECURITY;

ALTER TABLE "public"."servers" ENABLE ROW LEVEL SECURITY;

CREATE POLICY "service_insert_profiles" ON "public"."profiles" FOR INSERT WITH CHECK (true);

CREATE POLICY "service_insert_users" ON "public"."users" FOR INSERT WITH CHECK (true);

ALTER TABLE "public"."sessions" ENABLE ROW LEVEL SECURITY;

ALTER TABLE "public"."signup_verifications" ENABLE ROW LEVEL SECURITY;

ALTER TABLE "public"."user_login_events" ENABLE ROW LEVEL SECURITY;

ALTER TABLE "public"."user_migration_review" ENABLE ROW LEVEL SECURITY;

ALTER TABLE "public"."user_sessions" ENABLE ROW LEVEL SECURITY;

ALTER TABLE "public"."users" ENABLE ROW LEVEL SECURITY;

CREATE POLICY "users can update their own profile" ON "public"."profiles" FOR UPDATE USING (("user_id" = "public"."current_app_user_id"())) WITH CHECK (("user_id" = "public"."current_app_user_id"()));

CREATE POLICY "users can view their own full profile" ON "public"."profiles" FOR SELECT USING (("user_id" = "public"."current_app_user_id"()));

CREATE POLICY "users_select_own" ON "public"."users" FOR SELECT USING (("id" = "auth"."uid"()));

CREATE POLICY "users_update_own" ON "public"."users" FOR UPDATE USING (("id" = "auth"."uid"()));

ALTER TABLE "public"."verification_requests" ENABLE ROW LEVEL SECURITY;

ALTER TABLE "public"."volunteer_applications" ENABLE ROW LEVEL SECURITY;

ALTER TABLE "public"."volunteer_opportunities" ENABLE ROW LEVEL SECURITY;

ALTER PUBLICATION "supabase_realtime" OWNER TO "postgres";

GRANT USAGE ON SCHEMA "public" TO "postgres";
GRANT USAGE ON SCHEMA "public" TO "anon";
GRANT USAGE ON SCHEMA "public" TO "authenticated";
GRANT USAGE ON SCHEMA "public" TO "service_role";

GRANT ALL ON FUNCTION "public"."gtrgm_in"("cstring") TO "postgres";
GRANT ALL ON FUNCTION "public"."gtrgm_in"("cstring") TO "anon";
GRANT ALL ON FUNCTION "public"."gtrgm_in"("cstring") TO "authenticated";
GRANT ALL ON FUNCTION "public"."gtrgm_in"("cstring") TO "service_role";

GRANT ALL ON FUNCTION "public"."gtrgm_out"("public"."gtrgm") TO "postgres";
GRANT ALL ON FUNCTION "public"."gtrgm_out"("public"."gtrgm") TO "anon";
GRANT ALL ON FUNCTION "public"."gtrgm_out"("public"."gtrgm") TO "authenticated";
GRANT ALL ON FUNCTION "public"."gtrgm_out"("public"."gtrgm") TO "service_role";

REVOKE ALL ON FUNCTION "public"."current_app_user_id"() FROM PUBLIC;
GRANT ALL ON FUNCTION "public"."current_app_user_id"() TO "anon";
GRANT ALL ON FUNCTION "public"."current_app_user_id"() TO "authenticated";
GRANT ALL ON FUNCTION "public"."current_app_user_id"() TO "service_role";

GRANT ALL ON FUNCTION "public"."gin_extract_query_trgm"("text", "internal", smallint, "internal", "internal", "internal", "internal") TO "postgres";
GRANT ALL ON FUNCTION "public"."gin_extract_query_trgm"("text", "internal", smallint, "internal", "internal", "internal", "internal") TO "anon";
GRANT ALL ON FUNCTION "public"."gin_extract_query_trgm"("text", "internal", smallint, "internal", "internal", "internal", "internal") TO "authenticated";
GRANT ALL ON FUNCTION "public"."gin_extract_query_trgm"("text", "internal", smallint, "internal", "internal", "internal", "internal") TO "service_role";

GRANT ALL ON FUNCTION "public"."gin_extract_value_trgm"("text", "internal") TO "postgres";
GRANT ALL ON FUNCTION "public"."gin_extract_value_trgm"("text", "internal") TO "anon";
GRANT ALL ON FUNCTION "public"."gin_extract_value_trgm"("text", "internal") TO "authenticated";
GRANT ALL ON FUNCTION "public"."gin_extract_value_trgm"("text", "internal") TO "service_role";

GRANT ALL ON FUNCTION "public"."gin_trgm_consistent"("internal", smallint, "text", integer, "internal", "internal", "internal", "internal") TO "postgres";
GRANT ALL ON FUNCTION "public"."gin_trgm_consistent"("internal", smallint, "text", integer, "internal", "internal", "internal", "internal") TO "anon";
GRANT ALL ON FUNCTION "public"."gin_trgm_consistent"("internal", smallint, "text", integer, "internal", "internal", "internal", "internal") TO "authenticated";
GRANT ALL ON FUNCTION "public"."gin_trgm_consistent"("internal", smallint, "text", integer, "internal", "internal", "internal", "internal") TO "service_role";

GRANT ALL ON FUNCTION "public"."gin_trgm_triconsistent"("internal", smallint, "text", integer, "internal", "internal", "internal") TO "postgres";
GRANT ALL ON FUNCTION "public"."gin_trgm_triconsistent"("internal", smallint, "text", integer, "internal", "internal", "internal") TO "anon";
GRANT ALL ON FUNCTION "public"."gin_trgm_triconsistent"("internal", smallint, "text", integer, "internal", "internal", "internal") TO "authenticated";
GRANT ALL ON FUNCTION "public"."gin_trgm_triconsistent"("internal", smallint, "text", integer, "internal", "internal", "internal") TO "service_role";

GRANT ALL ON FUNCTION "public"."gtrgm_compress"("internal") TO "postgres";
GRANT ALL ON FUNCTION "public"."gtrgm_compress"("internal") TO "anon";
GRANT ALL ON FUNCTION "public"."gtrgm_compress"("internal") TO "authenticated";
GRANT ALL ON FUNCTION "public"."gtrgm_compress"("internal") TO "service_role";

GRANT ALL ON FUNCTION "public"."gtrgm_consistent"("internal", "text", smallint, "oid", "internal") TO "postgres";
GRANT ALL ON FUNCTION "public"."gtrgm_consistent"("internal", "text", smallint, "oid", "internal") TO "anon";
GRANT ALL ON FUNCTION "public"."gtrgm_consistent"("internal", "text", smallint, "oid", "internal") TO "authenticated";
GRANT ALL ON FUNCTION "public"."gtrgm_consistent"("internal", "text", smallint, "oid", "internal") TO "service_role";

GRANT ALL ON FUNCTION "public"."gtrgm_decompress"("internal") TO "postgres";
GRANT ALL ON FUNCTION "public"."gtrgm_decompress"("internal") TO "anon";
GRANT ALL ON FUNCTION "public"."gtrgm_decompress"("internal") TO "authenticated";
GRANT ALL ON FUNCTION "public"."gtrgm_decompress"("internal") TO "service_role";

GRANT ALL ON FUNCTION "public"."gtrgm_distance"("internal", "text", smallint, "oid", "internal") TO "postgres";
GRANT ALL ON FUNCTION "public"."gtrgm_distance"("internal", "text", smallint, "oid", "internal") TO "anon";
GRANT ALL ON FUNCTION "public"."gtrgm_distance"("internal", "text", smallint, "oid", "internal") TO "authenticated";
GRANT ALL ON FUNCTION "public"."gtrgm_distance"("internal", "text", smallint, "oid", "internal") TO "service_role";

GRANT ALL ON FUNCTION "public"."gtrgm_options"("internal") TO "postgres";
GRANT ALL ON FUNCTION "public"."gtrgm_options"("internal") TO "anon";
GRANT ALL ON FUNCTION "public"."gtrgm_options"("internal") TO "authenticated";
GRANT ALL ON FUNCTION "public"."gtrgm_options"("internal") TO "service_role";

GRANT ALL ON FUNCTION "public"."gtrgm_penalty"("internal", "internal", "internal") TO "postgres";
GRANT ALL ON FUNCTION "public"."gtrgm_penalty"("internal", "internal", "internal") TO "anon";
GRANT ALL ON FUNCTION "public"."gtrgm_penalty"("internal", "internal", "internal") TO "authenticated";
GRANT ALL ON FUNCTION "public"."gtrgm_penalty"("internal", "internal", "internal") TO "service_role";

GRANT ALL ON FUNCTION "public"."gtrgm_picksplit"("internal", "internal") TO "postgres";
GRANT ALL ON FUNCTION "public"."gtrgm_picksplit"("internal", "internal") TO "anon";
GRANT ALL ON FUNCTION "public"."gtrgm_picksplit"("internal", "internal") TO "authenticated";
GRANT ALL ON FUNCTION "public"."gtrgm_picksplit"("internal", "internal") TO "service_role";

GRANT ALL ON FUNCTION "public"."gtrgm_same"("public"."gtrgm", "public"."gtrgm", "internal") TO "postgres";
GRANT ALL ON FUNCTION "public"."gtrgm_same"("public"."gtrgm", "public"."gtrgm", "internal") TO "anon";
GRANT ALL ON FUNCTION "public"."gtrgm_same"("public"."gtrgm", "public"."gtrgm", "internal") TO "authenticated";
GRANT ALL ON FUNCTION "public"."gtrgm_same"("public"."gtrgm", "public"."gtrgm", "internal") TO "service_role";

GRANT ALL ON FUNCTION "public"."gtrgm_union"("internal", "internal") TO "postgres";
GRANT ALL ON FUNCTION "public"."gtrgm_union"("internal", "internal") TO "anon";
GRANT ALL ON FUNCTION "public"."gtrgm_union"("internal", "internal") TO "authenticated";
GRANT ALL ON FUNCTION "public"."gtrgm_union"("internal", "internal") TO "service_role";

GRANT ALL ON FUNCTION "public"."handle_new_auth_user"() TO "anon";
GRANT ALL ON FUNCTION "public"."handle_new_auth_user"() TO "authenticated";
GRANT ALL ON FUNCTION "public"."handle_new_auth_user"() TO "service_role";

GRANT ALL ON FUNCTION "public"."rls_auto_enable"() TO "anon";
GRANT ALL ON FUNCTION "public"."rls_auto_enable"() TO "authenticated";
GRANT ALL ON FUNCTION "public"."rls_auto_enable"() TO "service_role";

GRANT ALL ON FUNCTION "public"."set_limit"(real) TO "postgres";
GRANT ALL ON FUNCTION "public"."set_limit"(real) TO "anon";
GRANT ALL ON FUNCTION "public"."set_limit"(real) TO "authenticated";
GRANT ALL ON FUNCTION "public"."set_limit"(real) TO "service_role";

GRANT ALL ON FUNCTION "public"."set_updated_at"() TO "anon";
GRANT ALL ON FUNCTION "public"."set_updated_at"() TO "authenticated";
GRANT ALL ON FUNCTION "public"."set_updated_at"() TO "service_role";

GRANT ALL ON FUNCTION "public"."show_limit"() TO "postgres";
GRANT ALL ON FUNCTION "public"."show_limit"() TO "anon";
GRANT ALL ON FUNCTION "public"."show_limit"() TO "authenticated";
GRANT ALL ON FUNCTION "public"."show_limit"() TO "service_role";

GRANT ALL ON FUNCTION "public"."show_trgm"("text") TO "postgres";
GRANT ALL ON FUNCTION "public"."show_trgm"("text") TO "anon";
GRANT ALL ON FUNCTION "public"."show_trgm"("text") TO "authenticated";
GRANT ALL ON FUNCTION "public"."show_trgm"("text") TO "service_role";

GRANT ALL ON FUNCTION "public"."similarity"("text", "text") TO "postgres";
GRANT ALL ON FUNCTION "public"."similarity"("text", "text") TO "anon";
GRANT ALL ON FUNCTION "public"."similarity"("text", "text") TO "authenticated";
GRANT ALL ON FUNCTION "public"."similarity"("text", "text") TO "service_role";

GRANT ALL ON FUNCTION "public"."similarity_dist"("text", "text") TO "postgres";
GRANT ALL ON FUNCTION "public"."similarity_dist"("text", "text") TO "anon";
GRANT ALL ON FUNCTION "public"."similarity_dist"("text", "text") TO "authenticated";
GRANT ALL ON FUNCTION "public"."similarity_dist"("text", "text") TO "service_role";

GRANT ALL ON FUNCTION "public"."similarity_op"("text", "text") TO "postgres";
GRANT ALL ON FUNCTION "public"."similarity_op"("text", "text") TO "anon";
GRANT ALL ON FUNCTION "public"."similarity_op"("text", "text") TO "authenticated";
GRANT ALL ON FUNCTION "public"."similarity_op"("text", "text") TO "service_role";

GRANT ALL ON FUNCTION "public"."strict_word_similarity"("text", "text") TO "postgres";
GRANT ALL ON FUNCTION "public"."strict_word_similarity"("text", "text") TO "anon";
GRANT ALL ON FUNCTION "public"."strict_word_similarity"("text", "text") TO "authenticated";
GRANT ALL ON FUNCTION "public"."strict_word_similarity"("text", "text") TO "service_role";

GRANT ALL ON FUNCTION "public"."strict_word_similarity_commutator_op"("text", "text") TO "postgres";
GRANT ALL ON FUNCTION "public"."strict_word_similarity_commutator_op"("text", "text") TO "anon";
GRANT ALL ON FUNCTION "public"."strict_word_similarity_commutator_op"("text", "text") TO "authenticated";
GRANT ALL ON FUNCTION "public"."strict_word_similarity_commutator_op"("text", "text") TO "service_role";

GRANT ALL ON FUNCTION "public"."strict_word_similarity_dist_commutator_op"("text", "text") TO "postgres";
GRANT ALL ON FUNCTION "public"."strict_word_similarity_dist_commutator_op"("text", "text") TO "anon";
GRANT ALL ON FUNCTION "public"."strict_word_similarity_dist_commutator_op"("text", "text") TO "authenticated";
GRANT ALL ON FUNCTION "public"."strict_word_similarity_dist_commutator_op"("text", "text") TO "service_role";

GRANT ALL ON FUNCTION "public"."strict_word_similarity_dist_op"("text", "text") TO "postgres";
GRANT ALL ON FUNCTION "public"."strict_word_similarity_dist_op"("text", "text") TO "anon";
GRANT ALL ON FUNCTION "public"."strict_word_similarity_dist_op"("text", "text") TO "authenticated";
GRANT ALL ON FUNCTION "public"."strict_word_similarity_dist_op"("text", "text") TO "service_role";

GRANT ALL ON FUNCTION "public"."strict_word_similarity_op"("text", "text") TO "postgres";
GRANT ALL ON FUNCTION "public"."strict_word_similarity_op"("text", "text") TO "anon";
GRANT ALL ON FUNCTION "public"."strict_word_similarity_op"("text", "text") TO "authenticated";
GRANT ALL ON FUNCTION "public"."strict_word_similarity_op"("text", "text") TO "service_role";

GRANT ALL ON FUNCTION "public"."update_modified_column"() TO "anon";
GRANT ALL ON FUNCTION "public"."update_modified_column"() TO "authenticated";
GRANT ALL ON FUNCTION "public"."update_modified_column"() TO "service_role";

GRANT ALL ON FUNCTION "public"."word_similarity"("text", "text") TO "postgres";
GRANT ALL ON FUNCTION "public"."word_similarity"("text", "text") TO "anon";
GRANT ALL ON FUNCTION "public"."word_similarity"("text", "text") TO "authenticated";
GRANT ALL ON FUNCTION "public"."word_similarity"("text", "text") TO "service_role";

GRANT ALL ON FUNCTION "public"."word_similarity_commutator_op"("text", "text") TO "postgres";
GRANT ALL ON FUNCTION "public"."word_similarity_commutator_op"("text", "text") TO "anon";
GRANT ALL ON FUNCTION "public"."word_similarity_commutator_op"("text", "text") TO "authenticated";
GRANT ALL ON FUNCTION "public"."word_similarity_commutator_op"("text", "text") TO "service_role";

GRANT ALL ON FUNCTION "public"."word_similarity_dist_commutator_op"("text", "text") TO "postgres";
GRANT ALL ON FUNCTION "public"."word_similarity_dist_commutator_op"("text", "text") TO "anon";
GRANT ALL ON FUNCTION "public"."word_similarity_dist_commutator_op"("text", "text") TO "authenticated";
GRANT ALL ON FUNCTION "public"."word_similarity_dist_commutator_op"("text", "text") TO "service_role";

GRANT ALL ON FUNCTION "public"."word_similarity_dist_op"("text", "text") TO "postgres";
GRANT ALL ON FUNCTION "public"."word_similarity_dist_op"("text", "text") TO "anon";
GRANT ALL ON FUNCTION "public"."word_similarity_dist_op"("text", "text") TO "authenticated";
GRANT ALL ON FUNCTION "public"."word_similarity_dist_op"("text", "text") TO "service_role";

GRANT ALL ON FUNCTION "public"."word_similarity_op"("text", "text") TO "postgres";
GRANT ALL ON FUNCTION "public"."word_similarity_op"("text", "text") TO "anon";
GRANT ALL ON FUNCTION "public"."word_similarity_op"("text", "text") TO "authenticated";
GRANT ALL ON FUNCTION "public"."word_similarity_op"("text", "text") TO "service_role";

GRANT ALL ON TABLE "public"."admin_roles" TO "anon";
GRANT ALL ON TABLE "public"."admin_roles" TO "authenticated";
GRANT ALL ON TABLE "public"."admin_roles" TO "service_role";

GRANT ALL ON TABLE "public"."admin_user_actions" TO "anon";
GRANT ALL ON TABLE "public"."admin_user_actions" TO "authenticated";
GRANT ALL ON TABLE "public"."admin_user_actions" TO "service_role";

GRANT ALL ON TABLE "public"."admin_user_notes" TO "anon";
GRANT ALL ON TABLE "public"."admin_user_notes" TO "authenticated";
GRANT ALL ON TABLE "public"."admin_user_notes" TO "service_role";

GRANT ALL ON TABLE "public"."audit_logs" TO "anon";
GRANT ALL ON TABLE "public"."audit_logs" TO "authenticated";
GRANT ALL ON TABLE "public"."audit_logs" TO "service_role";

GRANT ALL ON TABLE "public"."auth_rate_limits" TO "anon";
GRANT ALL ON TABLE "public"."auth_rate_limits" TO "authenticated";
GRANT ALL ON TABLE "public"."auth_rate_limits" TO "service_role";









GRANT ALL ON TABLE "public"."call_events" TO "anon";
GRANT ALL ON TABLE "public"."call_events" TO "authenticated";
GRANT ALL ON TABLE "public"."call_events" TO "service_role";

GRANT ALL ON TABLE "public"."call_participants" TO "anon";
GRANT ALL ON TABLE "public"."call_participants" TO "authenticated";
GRANT ALL ON TABLE "public"."call_participants" TO "service_role";

GRANT ALL ON TABLE "public"."call_sessions" TO "anon";
GRANT ALL ON TABLE "public"."call_sessions" TO "authenticated";
GRANT ALL ON TABLE "public"."call_sessions" TO "service_role";

GRANT ALL ON TABLE "public"."comment_likes" TO "anon";
GRANT ALL ON TABLE "public"."comment_likes" TO "authenticated";
GRANT ALL ON TABLE "public"."comment_likes" TO "service_role";

GRANT ALL ON TABLE "public"."direct_messages" TO "anon";
GRANT ALL ON TABLE "public"."direct_messages" TO "authenticated";
GRANT ALL ON TABLE "public"."direct_messages" TO "service_role";

GRANT ALL ON TABLE "public"."event_rsvps" TO "anon";
GRANT ALL ON TABLE "public"."event_rsvps" TO "authenticated";
GRANT ALL ON TABLE "public"."event_rsvps" TO "service_role";

GRANT ALL ON TABLE "public"."events" TO "anon";
GRANT ALL ON TABLE "public"."events" TO "authenticated";
GRANT ALL ON TABLE "public"."events" TO "service_role";

GRANT ALL ON TABLE "public"."pet_pack_members" TO "anon";
GRANT ALL ON TABLE "public"."pet_pack_members" TO "authenticated";
GRANT ALL ON TABLE "public"."pet_pack_members" TO "service_role";

GRANT ALL ON TABLE "public"."friendships" TO "anon";
GRANT ALL ON TABLE "public"."friendships" TO "authenticated";
GRANT ALL ON TABLE "public"."friendships" TO "service_role";

GRANT ALL ON TABLE "public"."gallery_collections" TO "anon";
GRANT ALL ON TABLE "public"."gallery_collections" TO "authenticated";
GRANT ALL ON TABLE "public"."gallery_collections" TO "service_role";

GRANT ALL ON TABLE "public"."gallery_items" TO "anon";
GRANT ALL ON TABLE "public"."gallery_items" TO "authenticated";
GRANT ALL ON TABLE "public"."gallery_items" TO "service_role";

GRANT ALL ON TABLE "public"."group_members" TO "anon";
GRANT ALL ON TABLE "public"."group_members" TO "authenticated";
GRANT ALL ON TABLE "public"."group_members" TO "service_role";

GRANT ALL ON TABLE "public"."group_messages" TO "anon";
GRANT ALL ON TABLE "public"."group_messages" TO "authenticated";
GRANT ALL ON TABLE "public"."group_messages" TO "service_role";

GRANT ALL ON TABLE "public"."groups" TO "anon";
GRANT ALL ON TABLE "public"."groups" TO "authenticated";
GRANT ALL ON TABLE "public"."groups" TO "service_role";

GRANT ALL ON TABLE "public"."pet_services" TO "anon";
GRANT ALL ON TABLE "public"."pet_services" TO "authenticated";
GRANT ALL ON TABLE "public"."pet_services" TO "service_role";

GRANT ALL ON TABLE "public"."playdate_preferences" TO "anon";
GRANT ALL ON TABLE "public"."playdate_preferences" TO "authenticated";
GRANT ALL ON TABLE "public"."playdate_preferences" TO "service_role";

GRANT ALL ON TABLE "public"."playdate_profiles" TO "anon";
GRANT ALL ON TABLE "public"."playdate_profiles" TO "authenticated";
GRANT ALL ON TABLE "public"."playdate_profiles" TO "service_role";

GRANT ALL ON TABLE "public"."notifications" TO "anon";
GRANT ALL ON TABLE "public"."notifications" TO "authenticated";
GRANT ALL ON TABLE "public"."notifications" TO "service_role";

GRANT ALL ON TABLE "public"."pet_memorials" TO "anon";
GRANT ALL ON TABLE "public"."pet_memorials" TO "authenticated";
GRANT ALL ON TABLE "public"."pet_memorials" TO "service_role";

GRANT ALL ON TABLE "public"."password_reset_tokens" TO "anon";
GRANT ALL ON TABLE "public"."password_reset_tokens" TO "authenticated";
GRANT ALL ON TABLE "public"."password_reset_tokens" TO "service_role";

GRANT ALL ON TABLE "public"."post_comments" TO "anon";
GRANT ALL ON TABLE "public"."post_comments" TO "authenticated";
GRANT ALL ON TABLE "public"."post_comments" TO "service_role";

GRANT ALL ON TABLE "public"."post_likes" TO "anon";
GRANT ALL ON TABLE "public"."post_likes" TO "authenticated";
GRANT ALL ON TABLE "public"."post_likes" TO "service_role";

GRANT ALL ON TABLE "public"."posts" TO "anon";
GRANT ALL ON TABLE "public"."posts" TO "authenticated";
GRANT ALL ON TABLE "public"."posts" TO "service_role";

GRANT ALL ON TABLE "public"."profiles" TO "anon";
GRANT ALL ON TABLE "public"."profiles" TO "authenticated";
GRANT ALL ON TABLE "public"."profiles" TO "service_role";

GRANT ALL ON TABLE "public"."rate_limits" TO "anon";
GRANT ALL ON TABLE "public"."rate_limits" TO "authenticated";
GRANT ALL ON TABLE "public"."rate_limits" TO "service_role";

GRANT ALL ON TABLE "public"."servers" TO "anon";
GRANT ALL ON TABLE "public"."servers" TO "authenticated";
GRANT ALL ON TABLE "public"."servers" TO "service_role";

GRANT ALL ON SEQUENCE "public"."servers_id_seq" TO "anon";
GRANT ALL ON SEQUENCE "public"."servers_id_seq" TO "authenticated";
GRANT ALL ON SEQUENCE "public"."servers_id_seq" TO "service_role";

GRANT ALL ON TABLE "public"."sessions" TO "anon";
GRANT ALL ON TABLE "public"."sessions" TO "authenticated";
GRANT ALL ON TABLE "public"."sessions" TO "service_role";

GRANT ALL ON TABLE "public"."signup_verifications" TO "anon";
GRANT ALL ON TABLE "public"."signup_verifications" TO "authenticated";
GRANT ALL ON TABLE "public"."signup_verifications" TO "service_role";

GRANT ALL ON TABLE "public"."user_login_events" TO "anon";
GRANT ALL ON TABLE "public"."user_login_events" TO "authenticated";
GRANT ALL ON TABLE "public"."user_login_events" TO "service_role";

GRANT ALL ON TABLE "public"."user_migration_review" TO "anon";
GRANT ALL ON TABLE "public"."user_migration_review" TO "authenticated";
GRANT ALL ON TABLE "public"."user_migration_review" TO "service_role";

GRANT ALL ON TABLE "public"."user_sessions" TO "anon";
GRANT ALL ON TABLE "public"."user_sessions" TO "authenticated";
GRANT ALL ON TABLE "public"."user_sessions" TO "service_role";

GRANT ALL ON TABLE "public"."users" TO "anon";
GRANT ALL ON TABLE "public"."users" TO "authenticated";
GRANT ALL ON TABLE "public"."users" TO "service_role";

GRANT ALL ON TABLE "public"."verification_requests" TO "anon";
GRANT ALL ON TABLE "public"."verification_requests" TO "authenticated";
GRANT ALL ON TABLE "public"."verification_requests" TO "service_role";

GRANT ALL ON TABLE "public"."volunteer_applications" TO "anon";
GRANT ALL ON TABLE "public"."volunteer_applications" TO "authenticated";
GRANT ALL ON TABLE "public"."volunteer_applications" TO "service_role";

GRANT ALL ON TABLE "public"."volunteer_opportunities" TO "anon";
GRANT ALL ON TABLE "public"."volunteer_opportunities" TO "authenticated";
GRANT ALL ON TABLE "public"."volunteer_opportunities" TO "service_role";

ALTER DEFAULT PRIVILEGES FOR ROLE "postgres" IN SCHEMA "public" GRANT ALL ON SEQUENCES TO "postgres";
ALTER DEFAULT PRIVILEGES FOR ROLE "postgres" IN SCHEMA "public" GRANT ALL ON SEQUENCES TO "anon";
ALTER DEFAULT PRIVILEGES FOR ROLE "postgres" IN SCHEMA "public" GRANT ALL ON SEQUENCES TO "authenticated";
ALTER DEFAULT PRIVILEGES FOR ROLE "postgres" IN SCHEMA "public" GRANT ALL ON SEQUENCES TO "service_role";

ALTER DEFAULT PRIVILEGES FOR ROLE "postgres" IN SCHEMA "public" GRANT ALL ON FUNCTIONS TO "postgres";
ALTER DEFAULT PRIVILEGES FOR ROLE "postgres" IN SCHEMA "public" GRANT ALL ON FUNCTIONS TO "anon";
ALTER DEFAULT PRIVILEGES FOR ROLE "postgres" IN SCHEMA "public" GRANT ALL ON FUNCTIONS TO "authenticated";
ALTER DEFAULT PRIVILEGES FOR ROLE "postgres" IN SCHEMA "public" GRANT ALL ON FUNCTIONS TO "service_role";

ALTER DEFAULT PRIVILEGES FOR ROLE "postgres" IN SCHEMA "public" GRANT ALL ON TABLES TO "postgres";
ALTER DEFAULT PRIVILEGES FOR ROLE "postgres" IN SCHEMA "public" GRANT ALL ON TABLES TO "anon";
ALTER DEFAULT PRIVILEGES FOR ROLE "postgres" IN SCHEMA "public" GRANT ALL ON TABLES TO "authenticated";
ALTER DEFAULT PRIVILEGES FOR ROLE "postgres" IN SCHEMA "public" GRANT ALL ON TABLES TO "service_role";

