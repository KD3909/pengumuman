# Kingdom 3909 Full-Stack Starter

Stack: Next.js + Prisma + SQLite + bcrypt + signed HTTP-only session cookie.

## Run locally
1. Copy `.env.example` to `.env`.
2. `npm install`
3. `npx prisma generate`
4. `npm run db:push`
5. `npm run db:seed`
6. `npm run dev`
7. Open `http://localhost:3000`

Demo admin: `admin3909` / `Admin3909!` — change it before production.

Admin modules: `/admin/governors`, `/admin/alliances`, `/admin/events`, `/admin/news`.

Production hardening recommended: PostgreSQL, CSRF protection, rate limiting, stronger validation, audit logs, pagination, image storage, role permissions, backups and HTTPS.
