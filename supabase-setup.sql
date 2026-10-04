-- ============================================================
-- Kingdom 3909 Royal Portal — Setup Supabase (jalankan SEKALI di SQL Editor)
-- ============================================================
create extension if not exists pgcrypto;

create table public.alliances(
  id uuid primary key default gen_random_uuid(),
  tag text not null, name text not null,
  leader text default '', territory text default '',
  created_at timestamptz default now());
create unique index alliances_tag_uq on public.alliances (lower(tag));

create table public.profiles(
  id uuid primary key references auth.users(id) on delete cascade,
  username text not null default '',
  role text not null default 'member' check (role in ('member','alliance_admin','council','super_admin')),
  alliance_id uuid references public.alliances(id) on delete set null,
  created_at timestamptz default now());

create table public.governors(
  id uuid primary key default gen_random_uuid(),
  game_id text not null unique, name text not null,
  alliance_id uuid references public.alliances(id) on delete set null,
  power bigint not null default 0, kp bigint not null default 0, deads bigint not null default 0,
  created_at timestamptz default now());
create index governors_power_idx on public.governors (power desc);

create table public.events(
  id uuid primary key default gen_random_uuid(),
  title text not null, date date not null, category text default 'Kingdom',
  alliance_id uuid references public.alliances(id) on delete set null,
  description text default '', created_at timestamptz default now());

create table public.news(
  id uuid primary key default gen_random_uuid(),
  title text not null, category text default 'Umum',
  alliance_id uuid references public.alliances(id) on delete set null,
  content text default '', created_at timestamptz default now());

create table public.rules(
  id uuid primary key default gen_random_uuid(),
  title text not null, content text default '', sort int default 0,
  created_at timestamptz default now());

create table public.settings(
  id int primary key default 1 check (id = 1),
  name text default 'Kingdom 3909', motto text default '', king text default '');
insert into public.settings(id,name,motto,king) values (1,'Kingdom 3909','United We Rise, Together We Conquer.','');

create table public.audit(
  id bigserial primary key, at timestamptz default now(),
  username text, action text, target text, detail text);

-- ---------- Fungsi bantu ----------
create or replace function public.my_role() returns text language sql stable security definer set search_path=public as
$$ select role from public.profiles where id = auth.uid() $$;
create or replace function public.my_alliance() returns uuid language sql stable security definer set search_path=public as
$$ select alliance_id from public.profiles where id = auth.uid() $$;
create or replace function public.is_admin() returns boolean language sql stable security definer set search_path=public as
$$ select coalesce(public.my_role() in ('super_admin','council'), false) $$;
create or replace function public.is_staff() returns boolean language sql stable security definer set search_path=public as
$$ select coalesce(public.my_role() in ('super_admin','council','alliance_admin'), false) $$;
create or replace function public.has_admin() returns boolean language sql stable security definer set search_path=public as
$$ select exists(select 1 from public.profiles where role = 'super_admin') $$;

-- ---------- Profil otomatis saat daftar (akun pertama = Super Admin) ----------
create or replace function public.handle_new_user() returns trigger language plpgsql security definer set search_path=public as $$
begin
  insert into public.profiles(id, username, role) values (
    new.id,
    left(coalesce(nullif(trim(new.raw_user_meta_data->>'username'), ''), split_part(new.email, '@', 1)), 32),
    case when exists(select 1 from public.profiles where role = 'super_admin') then 'member' else 'super_admin' end);
  return new;
end $$;
create trigger on_auth_user_created after insert on auth.users for each row execute function public.handle_new_user();

-- ---------- Jaga Super Admin terakhir ----------
create or replace function public.guard_profiles() returns trigger language plpgsql security definer set search_path=public as $$
begin
  if tg_op = 'UPDATE' then
    new.id := old.id;
    if old.role = 'super_admin' and new.role <> 'super_admin'
       and (select count(*) from public.profiles where role = 'super_admin') < 2 then
      raise exception 'Super Admin terakhir tidak boleh diturunkan';
    end if;
    return new;
  end if;
  if old.role = 'super_admin' and (select count(*) from public.profiles where role = 'super_admin') < 2 then
    raise exception 'Super Admin terakhir tidak boleh dihapus';
  end if;
  return old;
end $$;
create trigger guard_profiles before update or delete on public.profiles for each row execute function public.guard_profiles();

-- ---------- Audit log otomatis ----------
create or replace function public.log_change() returns trigger language plpgsql security definer set search_path=public as $$
declare r jsonb; u text;
begin
  r := to_jsonb(case when tg_op = 'DELETE' then old else new end);
  select username into u from public.profiles where id = auth.uid();
  insert into public.audit(username, action, target, detail)
  values (coalesce(u, '-'), lower(tg_op), tg_table_name, left(coalesce(r->>'name', r->>'title', r->>'tag', r->>'username', r->>'id'), 200));
  return null;
end $$;
create trigger audit_alliances after insert or update or delete on public.alliances for each row execute function public.log_change();
create trigger audit_events    after insert or update or delete on public.events    for each row execute function public.log_change();
create trigger audit_news      after insert or update or delete on public.news      for each row execute function public.log_change();
create trigger audit_rules     after insert or update or delete on public.rules     for each row execute function public.log_change();
create trigger audit_profiles  after update or delete on public.profiles            for each row execute function public.log_change();
create trigger audit_settings  after update on public.settings                       for each row execute function public.log_change();
create trigger audit_gov_del   after delete on public.governors                      for each row execute function public.log_change();

-- ---------- Row Level Security ----------
alter table public.alliances enable row level security;
alter table public.profiles  enable row level security;
alter table public.governors enable row level security;
alter table public.events    enable row level security;
alter table public.news      enable row level security;
alter table public.rules     enable row level security;
alter table public.settings  enable row level security;
alter table public.audit     enable row level security;

-- settings
create policy set_read  on public.settings for select using (true);
create policy set_write on public.settings for update using (public.is_admin()) with check (public.is_admin());

-- alliances: publik baca; admin kelola; Alliance Admin hanya alliance sendiri
create policy al_read on public.alliances for select using (true);
create policy al_ins  on public.alliances for insert with check (public.is_admin());
create policy al_upd  on public.alliances for update
  using (public.is_admin() or (public.my_role() = 'alliance_admin' and id = public.my_alliance()))
  with check (public.is_admin() or (public.my_role() = 'alliance_admin' and id = public.my_alliance()));
create policy al_del  on public.alliances for delete using (public.is_admin());

-- governors: tabel penuh hanya untuk staf; publik memakai view tanpa game_id
create policy gv_read on public.governors for select using (public.is_staff());
create policy gv_ins  on public.governors for insert
  with check (public.is_admin() or (public.my_role() = 'alliance_admin' and alliance_id = public.my_alliance()));
create policy gv_upd  on public.governors for update
  using (public.is_admin() or (public.my_role() = 'alliance_admin' and alliance_id = public.my_alliance()))
  with check (public.is_admin() or (public.my_role() = 'alliance_admin' and alliance_id = public.my_alliance()));
create policy gv_del  on public.governors for delete using (public.is_admin());

create view public.governors_public as
  select id, name, alliance_id, power, kp, deads from public.governors;
grant select on public.governors_public to anon, authenticated;

-- events & news
create policy ev_read on public.events for select using (true);
create policy ev_ins  on public.events for insert
  with check (public.is_admin() or (public.my_role() = 'alliance_admin' and alliance_id = public.my_alliance()));
create policy ev_upd  on public.events for update
  using (public.is_admin() or (public.my_role() = 'alliance_admin' and alliance_id = public.my_alliance()))
  with check (public.is_admin() or (public.my_role() = 'alliance_admin' and alliance_id = public.my_alliance()));
create policy ev_del  on public.events for delete
  using (public.is_admin() or (public.my_role() = 'alliance_admin' and alliance_id = public.my_alliance()));

create policy nw_read on public.news for select using (true);
create policy nw_ins  on public.news for insert
  with check (public.is_admin() or (public.my_role() = 'alliance_admin' and alliance_id = public.my_alliance()));
create policy nw_upd  on public.news for update
  using (public.is_admin() or (public.my_role() = 'alliance_admin' and alliance_id = public.my_alliance()))
  with check (public.is_admin() or (public.my_role() = 'alliance_admin' and alliance_id = public.my_alliance()));
create policy nw_del  on public.news for delete
  using (public.is_admin() or (public.my_role() = 'alliance_admin' and alliance_id = public.my_alliance()));

-- rules
create policy ru_read  on public.rules for select using (true);
create policy ru_write on public.rules for all using (public.is_admin()) with check (public.is_admin());

-- profiles: lihat diri sendiri / admin; Council tidak bisa menyentuh Super Admin
create policy pr_read on public.profiles for select using (id = auth.uid() or public.is_admin());
create policy pr_upd  on public.profiles for update
  using (public.my_role() = 'super_admin' or (public.my_role() = 'council' and role <> 'super_admin'))
  with check (public.my_role() = 'super_admin' or (public.my_role() = 'council' and role <> 'super_admin'));

-- audit: hanya Super Admin yang bisa membaca (penulisan lewat trigger)
create policy au_read on public.audit for select using (public.my_role() = 'super_admin');
