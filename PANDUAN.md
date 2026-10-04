# Kingdom 3909 Royal Portal — Panduan Cepat

Situs statis (GitHub Pages) + database & login di **Supabase** (gratis). Tampilan responsif untuk HP dan laptop.

## 1. Supabase (±10 menit)
1. Daftar di https://supabase.com → **New project** (region Singapore).
2. **SQL Editor → New query** → tempel isi `supabase-setup.sql` → **Run**.
3. Query baru → tempel isi `supabase-admin.sql` → **Run**. Ini membuat Super Admin:
   **username `danipuja1` / password `danipuja1`**.
4. **Project Settings → API** → salin *Project URL* dan *anon key* ke `config.js`.
5. (Disarankan) **Authentication → Providers → Email** → matikan *Confirm email*.

## 2. GitHub Pages
1. Buat repositori, unggah: `index.html`, `config.js`, `PANDUAN.md`.
   **Jangan unggah `supabase-admin.sql`** (berisi password).
2. **Settings → Pages** → Deploy from a branch → `main` / root → Save.
3. Buka `https://NAMA-GITHUB.github.io/NAMA-REPO/`, lalu isi **Site URL** di Supabase (*Authentication → URL Configuration*).

## 3. Masuk
Klik **Masuk** → username `danipuja1`, password `danipuja1`.
**Segera ganti password** di Panel → Dashboard → *Ganti Password* (password ini mudah ditebak).

## Catatan
- Anggota lain mendaftar lewat **Daftar** (role awal `member`); beri role di menu **User**.
- Untuk menutup pendaftaran bebas: *Authentication → Sign In / Providers → matikan "Allow new users to sign up"*.
- Format CSV governor: `game_id,name,power,kill_points,deads,alliance_tag`.
- Muncul layar "Hubungkan database": `config.js` belum diisi. Muncul "relation not found": `supabase-setup.sql` belum dijalankan. Login gagal: jalankan ulang `supabase-admin.sql`.
