# Kingdom 3909 Royal Portal — Panduan Hosting

## Syarat hosting
- PHP 7.4 atau lebih baru dengan ekstensi **pdo_sqlite** (aktif di hampir semua hosting cPanel)
- Folder situs bisa ditulis oleh PHP (untuk membuat folder `data/`)
- Disarankan HTTPS (SSL gratis di cPanel: menu "SSL/TLS Status" → AutoSSL)

## Langkah pemasangan
1. Masuk **cPanel → File Manager → public_html** (atau subfolder, misalnya `public_html/3909`).
2. Unggah `kingdom3909-hosting.zip`, lalu **Extract**. Pastikan `index.html`, `api.php`, dan `.htaccess` ada di folder yang sama.
3. Buka alamat situs Anda. Klik **Masuk** — karena belum ada akun, formulir otomatis menjadi **Buat Super Admin**. Buat akun King Anda (password minimal 8 karakter).
4. Di panel, buka **Pengaturan** untuk mengisi nama kingdom, motto, dan King. Untuk mulai cepat, **Impor CSV** governor.
5. Buat akun Council / Alliance Admin lewat menu **User**.

## Format CSV governor
`game_id,name,power,kill_points,deads,alliance_tag` (pemisah koma atau titik koma; angka boleh pakai titik/koma ribuan). Buat dulu alliance-nya agar `alliance_tag` terbaca.

## Keamanan & backup
- Data tersimpan di `data/portal.sqlite` (folder `data/` otomatis diblokir dari akses web lewat `.htaccess`). Jika hosting Anda memakai Nginx (bukan Apache), minta penyedia hosting memblokir akses ke folder `data/`.
- Backup rutin: salin `data/portal.sqlite` lewat File Manager, atau pakai **Backup → Unduh cadangan JSON** (khusus Super Admin).
- Role dicek di server: Council tidak bisa mengubah Super Admin, Alliance Admin terkunci pada alliance-nya, login dibatasi 8 percobaan gagal per 15 menit.
- Alamat IP pengguna dipakai hanya untuk pembatasan login.

## Pemecahan masalah
- Pembuatan akun gagal / error lain: buka `alamat-situs/api.php?a=diag` — halaman itu menampilkan status PHP, SQLite, izin folder `data/`, dan sesi.
- "Gagal memuat data": PHP belum aktif atau `api.php` belum terunggah.
- "Database SQLite tidak tersedia": aktifkan `pdo_sqlite` di **cPanel → Select PHP Version → Extensions**.
- Folder `data/` tidak terbentuk: ubah izin folder situs menjadi 755.
