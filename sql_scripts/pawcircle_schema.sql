create table public.call_events (
  id uuid not null default gen_random_uuid (),
  call_id uuid not null,
  user_id uuid null,
  event_type text not null,
  created_at timestamp with time zone null default now(),
  constraint call_events_pkey primary key (id),
  constraint call_events_call_id_fkey foreign KEY (call_id) references call_sessions (id) on delete CASCADE,
  constraint call_events_user_id_fkey foreign KEY (user_id) references users (id)
) TABLESPACE pg_default;

create table public.call_participants (
  id uuid not null default gen_random_uuid (),
  call_id uuid not null,
  user_id uuid not null,
  role text not null default 'participant'::text,
  status text not null default 'invited'::text,
  joined_at timestamp with time zone null,
  left_at timestamp with time zone null,
  constraint call_participants_pkey primary key (id),
  constraint call_participants_call_id_user_id_key unique (call_id, user_id),
  constraint call_participants_call_id_fkey foreign KEY (call_id) references call_sessions (id) on delete CASCADE,
  constraint call_participants_user_id_fkey foreign KEY (user_id) references users (id),
  constraint call_participants_role_check check (
    (
      role = any (array['host'::text, 'participant'::text])
    )
  ),
  constraint call_participants_status_check check (
    (
      status = any (
        array[
          'invited'::text,
          'ringing'::text,
          'joined'::text,
          'declined'::text,
          'left'::text,
          'missed'::text
        ]
      )
    )
  )
) TABLESPACE pg_default;

create unique INDEX IF not exists call_participants_call_user_unique on public.call_participants using btree (call_id, user_id) TABLESPACE pg_default;

create table public.call_sessions (
  id uuid not null default gen_random_uuid (),
  created_by uuid not null,
  call_type text not null,
  target_type text not null,
  group_id uuid null,
  provider text not null default 'daily'::text,
  provider_room_id text null,
  provider_room_url text null,
  status text not null default 'created'::text,
  created_at timestamp with time zone null default now(),
  started_at timestamp with time zone null,
  ended_at timestamp with time zone null,
  zoom_meeting_id text null,
  zoom_password text null,
  zoom_join_url text null,
  constraint call_sessions_pkey primary key (id),
  constraint call_sessions_created_by_fkey foreign KEY (created_by) references users (id),
  constraint call_sessions_group_id_fkey foreign KEY (group_id) references groups (id),
  constraint call_sessions_call_type_check check (
    (
      call_type = any (array['voice'::text, 'video'::text])
    )
  ),
  constraint call_sessions_status_check check (
    (
      status = any (
        array[
          'created'::text,
          'ringing'::text,
          'active'::text,
          'ended'::text,
          'cancelled'::text,
          'missed'::text
        ]
      )
    )
  ),
  constraint call_sessions_target_type_check check (
    (
      target_type = any (
        array[
          'direct'::text,
          'selected_users'::text,
          'group'::text
        ]
      )
    )
  )
) TABLESPACE pg_default;

create table public.event_rsvps (
  event_id uuid not null,
  user_id uuid not null,
  status text null default 'going'::text,
  created_at timestamp with time zone null default now(),
  constraint event_rsvps_pkey primary key (event_id, user_id),
  constraint event_rsvps_event_id_fkey foreign KEY (event_id) references events (id) on delete CASCADE,
  constraint event_rsvps_user_id_fkey foreign KEY (user_id) references users (id) on delete CASCADE
) TABLESPACE pg_default;

create table public.events (
  id uuid not null default extensions.uuid_generate_v4 (),
  title text not null,
  description text null,
  event_date date null,
  event_time time without time zone null,
  location text null,
  is_online boolean null default false,
  meeting_url text null,
  religion text null,
  community text null,
  banner_url text null,
  created_by uuid null,
  created_at timestamp with time zone null default now(),
  updated_at timestamp with time zone null default now(),
  constraint events_pkey primary key (id),
  constraint events_created_by_fkey foreign KEY (created_by) references users (id) on delete set null
) TABLESPACE pg_default;

create trigger trg_events_updated_at BEFORE
update on events for EACH row
execute FUNCTION set_updated_at ();

create table public.friendships (
  id uuid not null default extensions.uuid_generate_v4 (),
  requester uuid not null,
  addressee uuid not null,
  status public.friend_status null default 'pending'::friend_status,
  created_at timestamp with time zone null default now(),
  updated_at timestamp with time zone null default now(),
  constraint friendships_pkey primary key (id),
  constraint friendships_requester_addressee_key unique (requester, addressee),
  constraint friendships_addressee_fkey foreign KEY (addressee) references users (id) on delete CASCADE,
  constraint friendships_requester_fkey foreign KEY (requester) references users (id) on delete CASCADE,
  constraint no_self_friend check ((requester <> addressee))
) TABLESPACE pg_default;

create trigger trg_friendships_updated_at BEFORE
update on friendships for EACH row
execute FUNCTION set_updated_at ();

create table public.group_members (
  group_id uuid not null,
  user_id uuid not null,
  role public.group_member_role null default 'member'::group_member_role,
  joined_at timestamp with time zone null default now(),
  constraint group_members_pkey primary key (group_id, user_id),
  constraint group_members_group_id_fkey foreign KEY (group_id) references groups (id) on delete CASCADE,
  constraint group_members_user_id_fkey foreign KEY (user_id) references users (id) on delete CASCADE
) TABLESPACE pg_default;

create table public.group_messages (
  id uuid not null default extensions.uuid_generate_v4 (),
  group_id uuid not null,
  sender_id uuid null,
  content text null,
  media_url text null,
  is_deleted boolean null default false,
  created_at timestamp with time zone null default now(),
  constraint group_messages_pkey primary key (id),
  constraint group_messages_group_id_fkey foreign KEY (group_id) references groups (id) on delete CASCADE,
  constraint group_messages_sender_id_fkey foreign KEY (sender_id) references users (id) on delete set null
) TABLESPACE pg_default;

create index IF not exists idx_group_messages_grp on public.group_messages using btree (group_id, created_at desc) TABLESPACE pg_default;

create table public.groups (
  id uuid not null default extensions.uuid_generate_v4 (),
  name text not null,
  description text null,
  avatar_url text null,
  community text null,
  religion text null,
  created_by uuid null,
  is_private boolean null default false,
  created_at timestamp with time zone null default now(),
  updated_at timestamp with time zone null default now(),
  constraint groups_pkey primary key (id),
  constraint groups_created_by_fkey foreign KEY (created_by) references users (id) on delete set null
) TABLESPACE pg_default;

create trigger trg_groups_updated_at BEFORE
update on groups for EACH row
execute FUNCTION set_updated_at ();

create table public.matrimonial_profiles (
  user_id uuid not null,
  seeking text null,
  height_cm integer null,
  complexion text null,
  marital_status text null,
  family_type text null,
  family_values text null,
  annual_income text null,
  highest_education text null,
  occupation text null,
  expectations text null,
  is_active boolean null default true,
  created_at timestamp with time zone null default now(),
  updated_at timestamp with time zone null default now(),
  constraint matrimonial_profiles_pkey primary key (user_id),
  constraint matrimonial_profiles_user_id_fkey foreign KEY (user_id) references users (id) on delete CASCADE
) TABLESPACE pg_default;

create trigger trg_matrimonial_profiles_updated_at BEFORE
update on matrimonial_profiles for EACH row
execute FUNCTION set_updated_at ();

create table public.notifications (
  id uuid not null default extensions.uuid_generate_v4 (),
  user_id uuid not null,
  type text not null,
  title text null,
  body text null,
  data jsonb null,
  is_read boolean null default false,
  created_at timestamp with time zone null default now(),
  constraint notifications_pkey primary key (id),
  constraint notifications_user_id_fkey foreign KEY (user_id) references users (id) on delete CASCADE
) TABLESPACE pg_default;

create index IF not exists idx_notifs_user_unread on public.notifications using btree (user_id) TABLESPACE pg_default
where
  (is_read = false);

create table public.obituaries (
  id uuid not null default extensions.uuid_generate_v4 (),
  full_name text not null,
  date_of_birth date null,
  date_of_death date not null,
  community text null,
  religion text null,
  description text null,
  photo_url text null,
  submitted_by uuid null,
  is_approved boolean null default false,
  created_at timestamp with time zone null default now(),
  constraint obituaries_pkey primary key (id),
  constraint obituaries_submitted_by_fkey foreign KEY (submitted_by) references users (id) on delete set null
) TABLESPACE pg_default;

create table public.post_comments (
  id uuid not null default extensions.uuid_generate_v4 (),
  post_id uuid not null,
  user_id uuid not null,
  content text not null,
  is_deleted boolean null default false,
  created_at timestamp with time zone null default now(),
  constraint post_comments_pkey primary key (id),
  constraint post_comments_post_id_fkey foreign KEY (post_id) references posts (id) on delete CASCADE,
  constraint post_comments_user_id_fkey foreign KEY (user_id) references users (id) on delete CASCADE
) TABLESPACE pg_default;

create index IF not exists idx_post_comments_post on public.post_comments using btree (post_id) TABLESPACE pg_default;

create table public.post_likes (
  post_id uuid not null,
  user_id uuid not null,
  created_at timestamp with time zone null default now(),
  constraint post_likes_pkey primary key (post_id, user_id),
  constraint post_likes_post_id_fkey foreign KEY (post_id) references posts (id) on delete CASCADE,
  constraint post_likes_user_id_fkey foreign KEY (user_id) references users (id) on delete CASCADE
) TABLESPACE pg_default;

create index IF not exists idx_post_likes_post_id on public.post_likes using btree (post_id) TABLESPACE pg_default;

create table public.posts (
  id uuid not null default extensions.uuid_generate_v4 (),
  user_id uuid not null,
  content text null,
  media_url text null,
  post_type public.post_type null default 'text'::post_type,
  community text null,
  is_deleted boolean null default false,
  created_at timestamp with time zone null default now(),
  updated_at timestamp with time zone null default now(),
  religion text null,
  constraint posts_pkey primary key (id),
  constraint posts_user_id_fkey foreign KEY (user_id) references users (id) on delete CASCADE
) TABLESPACE pg_default;

create index IF not exists idx_posts_user_id on public.posts using btree (user_id) TABLESPACE pg_default;

create index IF not exists idx_posts_created_at on public.posts using btree (created_at desc) TABLESPACE pg_default;

create trigger trg_posts_updated_at BEFORE
update on posts for EACH row
execute FUNCTION set_updated_at ();

create table public.profiles (
  user_id uuid not null,
  full_name text not null,
  date_of_birth date null,
  gender text null,
  aadhaar_number text null,
  mobile_number text null,
  religion text null,
  community text null,
  mother_tongue text null,
  gotra text null,
  native_village text null,
  current_city text null,
  occupation text null,
  highest_education text null,
  skills text[] null,
  organization text null,
  linkedin_url text null,
  primary_interests text[] null,
  age_group text null,
  source text null,
  profile_photo_url text null,
  membership_applied boolean null default false,
  status public.membership_status null default 'none'::membership_status,
  terms_accepted boolean null default false,
  privacy_accepted boolean null default false,
  accuracy_certified boolean null default false,
  created_at timestamp with time zone null default now(),
  updated_at timestamp with time zone null default now(),
  is_public boolean null default true,
  cover_photo_url text null,
  constraint profiles_pkey primary key (user_id),
  constraint profiles_user_id_fkey foreign KEY (user_id) references users (id) on delete CASCADE
) TABLESPACE pg_default;

create index IF not exists idx_profiles_community on public.profiles using btree (community) TABLESPACE pg_default;

create index IF not exists idx_profiles_religion on public.profiles using btree (religion) TABLESPACE pg_default;

create trigger trg_profiles_updated_at BEFORE
update on profiles for EACH row
execute FUNCTION set_updated_at ();

create table public.users (
  id uuid not null default extensions.uuid_generate_v4 (),
  email text not null,
  password_hash text not null,
  role public.user_role null default 'member'::user_role,
  created_at timestamp with time zone null default now(),
  updated_at timestamp with time zone null default now(),
  constraint users_pkey primary key (id),
  constraint users_email_key unique (email)
) TABLESPACE pg_default;

create trigger trg_users_updated_at BEFORE
update on users for EACH row
execute FUNCTION set_updated_at ();

