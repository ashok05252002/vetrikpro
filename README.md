# HRMS Task

Task-management + HRMS built on **Laravel 12 + Inertia v2 + React 19 + TypeScript + Tailwind 4**, backed by MySQL/MariaDB.

Covers **people** (users, roles, employee profiles, departments, designations) and
**work** (projects, a drag-and-drop task board, comments).

## Requirements

- PHP 8.4, Composer 2
- Node 20+
- MySQL / MariaDB (XAMPP)

## Setup

```bash
composer install
npm install
cp .env.example .env        # already done; DB points at hrms_task
php artisan key:generate
php artisan migrate --seed
npm run build
```

## Running

```bash
composer run dev            # serve + queue worker + vite, all at once
# or
php artisan serve
npm run dev
```

Then open http://127.0.0.1:8000 — `/` is the login page; there is no marketing
page. A signed-in visitor hitting `/` goes straight to their dashboard.

## Seeded account

The seeder creates one administrator, `admin@vetrik.in` (password in
`database/seeders/DatabaseSeeder.php`), and no demo data. Departments, staff
and projects are entered through the app. Change the password after first
sign-in.

These are development credentials only — change them before this goes anywhere real.

## Roles

| Role | Can do |
| --- | --- |
| `admin` | Everything, including the `/admin` area |
| `hr` | The `/admin` area (users, employees, master data) |
| `employee` | Dashboard and their own settings only |

Access is enforced by `EnsureUserManagesPeople` middleware, aliased as `manages-people`
and applied to the whole `/admin` route group in `routes/admin.php`.

**Public self-registration is disabled** — an admin creates accounts under
`/admin/users`. To reopen sign-ups, uncomment the two register routes in
`routes/auth.php`.

## Data model

```
users ──1:1── employees ──┬── departments
  │                       └── designations ──── departments
  ├── role, is_active
  │
  ├──< project_user >── projects ──< tasks ──< task_comments
  │                        │           │
  └── owns projects        │           └── assigned_to / created_by → users
                           └── owner_id → users
```

- Deleting a **user** cascades to their employee profile.
- Deleting a **department** or **designation** nulls the link; employee records survive.
- Employee codes auto-increment as `EMP-0001`, `EMP-0002`, … (`Employee::nextCode()`).
- Deleting a **project** cascades to its tasks and their comments.
- `tasks.completed_at` is derived in a model hook from the column a task sits
  in, so the two can never disagree however the status was changed.

## Routes

Everyone signed in:

| Path | Purpose |
| --- | --- |
| `/dashboard` | KPI row, task pipeline, project progress, your assigned work |
| `/tasks` | Your tasks, filterable by stage, priority and overdue |
| `/projects` | Project cards for boards you own or belong to |
| `/projects/{id}` | Kanban board with drag-and-drop |
| `/tasks/{id}` | Task detail and comment thread |

Admin and HR only:

| Path | Purpose |
| --- | --- |
| `/admin/users` | Login accounts, roles, active/disabled |
| `/admin/employees` | HR records attached to user accounts |
| `/admin/departments` | Organisational units |
| `/admin/designations` | Job titles, optionally scoped to a department |
| `/admin/projects` | Create projects, set an owner, choose members |

Administrator only (stricter than the rest of `/admin` — HR is refused):

| Path | Purpose |
| --- | --- |
| `/admin/settings` | Company name, logo, contact details, regional defaults |

## Settings

Organisation-wide settings live in a key/value `settings` table, read through a
declared schema in `app/Support/Settings.php`. **That schema is the single source
of truth** for a key's type, default and group — adding a setting means one entry
there plus a field on the admin form. Unknown keys sent to `set()` are ignored, so
a stray form field cannot create a phantom setting. Values are cached and the
cache is flushed on write.

**The company name is the one that shows up everywhere.** It drives the sidebar,
the sign-in screen, and `config('app.name')` — so the browser tab title and the
default mail "from" name follow it too, without touching `.env`. The provider that
sets it is guarded on the table existing, so a fresh `migrate` still works.

**The logo** is written to `public/uploads` on its own `uploads` disk, so no
`storage:link` symlink stands between a deploy and the image. The **path is stored
relative to the disk, never as a URL**, which would otherwise still name localhost
after a move to a real domain. Replacing or removing a logo deletes the old file.

**Regional settings are display-only, on purpose.** `display.timezone` deliberately
does *not* touch `config('app.timezone')`: timestamps are stored in the app
timezone, so changing it would silently reinterpret every row already written.
Timezone, date format and currency affect rendering and nothing else, applied
through the `useFormat()` hook.

| Group | Keys |
| --- | --- |
| Company | `company.name` (required), `company.legal_name`, `company.tax_id` |
| Contact | `company.email`, `company.phone`, `company.website`, `company.address` — stored, not yet rendered anywhere |
| Branding | `company.logo` |
| Regional | `display.timezone`, `display.date_format`, `display.currency` |

## Authorization

Two mechanisms, deliberately:

- **Middleware** (`manages-people`) gates the whole `/admin` prefix to admin and
  HR; a second alias (`admin`) gates `/admin/settings` to administrators alone,
  since the company's own identity is not HR's to change.
- **Policies** (`ProjectPolicy`, `TaskPolicy`) scope project and task access per
  record, so an employee sees only their own projects. `TaskPolicy::move` is
  deliberately looser than `update`: whoever a task is assigned to may advance
  their own work across the board, even without project membership.

## Tests

```bash
php artisan test
```

97 tests covering auth, authorization, CRUD, validation, board mechanics
(column moves, position reindexing, `completed_at`), settings (defaults, unknown-key
rejection, logo replace/remove, the name reaching the browser title) and the
relational cascades.

## Quality

```bash
./vendor/bin/pint     # PHP formatting
npm run lint          # ESLint
npm run format        # Prettier
npx tsc --noEmit      # TypeScript
```

## Chart colours

The dashboard's task pipeline is an **ordinal** scale (To do → Done), so the four
stages take one hue light-to-dark from a blue sequential ramp rather than four
categorical hues — see `--stage-*` in `resources/css/app.css`. Dark mode is
selected, not flipped: the same ramp re-stepped for the dark surface, where more
progress reads as more luminous. Both ramps were validated (monotone lightness,
step gaps ≥ 0.06 L, light end clearing the surface). **Re-validate before
reordering or recolouring them.**

Task priority uses the reserved status palette (good/warning/serious/critical)
and always ships an icon and the word, so colour never carries meaning alone.

## Not built yet

Attendance and leave management. The schema and scaffolding are laid out so they
slot in as new route groups under `routes/admin.php`.
