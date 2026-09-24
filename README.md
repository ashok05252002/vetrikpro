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

## Seeded accounts

| Email | Password | Role |
| --- | --- | --- |
| `admin@hrms.test` | `password` | Administrator |
| `hr@hrms.test` | `password` | HR Manager |
| `arun@hrms.test` | `password` | Employee |
| `meera@hrms.test` | `password` | Employee |
| `rahul@hrms.test` | `password` | Employee |
| `sneha@hrms.test` | `password` | Employee |

Sign in as an employee to see the narrowed view: no admin sidebar, only the
projects they belong to.

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

## Authorization

Two mechanisms, deliberately:

- **Middleware** (`manages-people`) gates the whole `/admin` prefix to admin and HR.
- **Policies** (`ProjectPolicy`, `TaskPolicy`) scope project and task access per
  record, so an employee sees only their own projects. `TaskPolicy::move` is
  deliberately looser than `update`: whoever a task is assigned to may advance
  their own work across the board, even without project membership.

## Tests

```bash
php artisan test
```

81 tests covering auth, authorization, CRUD, validation, board mechanics
(column moves, position reindexing, `completed_at`) and the relational cascades.

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
