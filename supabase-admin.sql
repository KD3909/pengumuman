-- ============================================================
-- Super Admin awal  |  username: danipuja1  |  password: danipuja1
-- Jalankan di SQL Editor SETELAH supabase-setup.sql. Aman dijalankan ulang
-- (mereset password ke danipuja1). JANGAN unggah file ini ke GitHub publik.
-- ============================================================
do $$
declare uid uuid; em text := 'danipuja1@kingdom3909.app';
begin
  select id into uid from auth.users where email = em;
  if uid is null then
    uid := gen_random_uuid();
    insert into auth.users(instance_id,id,aud,role,email,encrypted_password,email_confirmed_at,
      raw_app_meta_data,raw_user_meta_data,created_at,updated_at,
      confirmation_token,recovery_token,email_change_token_new,email_change,
      email_change_token_current,phone_change,phone_change_token,reauthentication_token)
    values('00000000-0000-0000-0000-000000000000',uid,'authenticated','authenticated',em,
      crypt('danipuja1',gen_salt('bf')),now(),
      '{"provider":"email","providers":["email"]}','{"username":"danipuja1"}',now(),now(),
      '','','','','','','','');
    insert into auth.identities(id,provider_id,user_id,identity_data,provider,last_sign_in_at,created_at,updated_at)
    values(gen_random_uuid(),uid::text,uid,
      jsonb_build_object('sub',uid::text,'email',em,'email_verified',true),'email',now(),now(),now());
  else
    update auth.users set encrypted_password=crypt('danipuja1',gen_salt('bf')),
      email_confirmed_at=coalesce(email_confirmed_at,now()) where id=uid;
  end if;
  insert into public.profiles(id,username,role) values(uid,'danipuja1','super_admin')
    on conflict (id) do update set username='danipuja1', role='super_admin';
end $$;
