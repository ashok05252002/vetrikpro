import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
    /** Resolved permission keys (role + overrides). Hiding UI on these is cosmetic; the server enforces. */
    permissions: string[];
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    company: Company;
    display: DisplaySettings;
    flash: { success: string | null; error: string | null };
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    role_id: number | null;
    role?: Pick<Role, 'id' | 'name' | 'slug' | 'is_super'> | null;
    is_active: boolean;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    employee?: Pick<Employee, 'id' | 'employee_code'> | null;
    [key: string]: unknown; // This allows for additional properties...
}

export interface Role {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    is_super: boolean;
    is_system: boolean;
    permissions?: string[];
    users_count?: number;
    permissions_count?: number;
}

export interface PermissionGroup {
    group: string;
    permissions: { key: string; label: string }[];
}

/** A per-user deviation from the role; a key that is absent inherits. */
export type PermissionOverride = 'allow' | 'deny';

export interface Option {
    value: string;
    label: string;
}

export interface Department {
    id: number;
    name: string;
    code: string | null;
    description: string | null;
    designations_count?: number;
    employees_count?: number;
}

export interface Designation {
    id: number;
    department_id: number | null;
    name: string;
    description: string | null;
    department?: Pick<Department, 'id' | 'name'> | null;
    employees_count?: number;
}

export interface Employee {
    id: number;
    user_id: number;
    department_id: number | null;
    designation_id: number | null;
    employee_code: string;
    phone: string | null;
    date_of_birth: string | null;
    gender: string | null;
    date_of_joining: string | null;
    employment_type: string;
    salary: string | null;
    address: string | null;
    status: string;
    user?: Pick<User, 'id' | 'name' | 'email'> | null;
    department?: Pick<Department, 'id' | 'name'> | null;
    designation?: Pick<Designation, 'id' | 'name'> | null;
}

/** Shape of Laravel's length-aware paginator as serialised to Inertia. */
export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

export type TaskStatus = 'todo' | 'in_progress' | 'in_review' | 'done';
export type TaskPriority = 'low' | 'medium' | 'high' | 'urgent';
export type ProjectStatus = 'active' | 'on_hold' | 'completed' | 'archived';

export interface ProjectSummary {
    id: number;
    name: string;
    code: string;
    description?: string | null;
    status?: ProjectStatus;
    start_date?: string | null;
    due_date?: string | null;
    owner?: Pick<User, 'id' | 'name'> | null;
    members?: Pick<User, 'id' | 'name' | 'email'>[];
    tasks_count?: number;
    done_tasks_count?: number;
    members_count?: number;
    progress?: number;
}

export interface TaskSummary {
    id: number;
    title: string;
    status: TaskStatus;
    priority: TaskPriority;
    due_date: string | null;
    position?: number;
    project?: Pick<ProjectSummary, 'id' | 'name' | 'code'> | null;
    assignee?: Pick<User, 'id' | 'name'> | null;
    comments_count?: number;
    is_overdue?: boolean;
}

export interface TaskComment {
    id: number;
    body: string;
    user: Pick<User, 'id' | 'name'>;
    created_at: string;
}

export interface TaskDetail extends TaskSummary {
    project_id: number;
    description: string | null;
    completed_at: string | null;
    creator?: Pick<User, 'id' | 'name'> | null;
    created_at: string;
    comments: TaskComment[];
}

export interface BoardColumn {
    value: TaskStatus;
    label: string;
    tasks: TaskSummary[];
}

/** One workflow stage and how many tasks sit in it. */
export interface PipelineStage {
    value: TaskStatus;
    label: string;
    count: number;
}

export interface Company {
    name: string;
    /** Public URL, or null when no logo has been uploaded. */
    logo: string | null;
}

export type DateFormat = 'dmy' | 'mdy' | 'ymd';

export interface DisplaySettings {
    timezone: string;
    dateFormat: DateFormat;
    /** ISO 4217 code, e.g. INR. */
    currency: string;
}

/** The header every project workspace tab receives (App\Support\ProjectWorkspace). */
export interface ProjectWorkspaceHeader extends ProjectSummary {
    repository_url: string | null;
    default_branch: string;
    members_count: number;
    progress: number;
    viewer: { is_dev_admin: boolean; can_update: boolean; can_manage_members: boolean };
}

/** One person as the directory search returns them (App\Support\UserDirectory::row). */
export interface DirectoryUser {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    employee_id: number | null;
    employee_code: string | null;
    department: string | null;
    designation: string | null;
}

export type ProjectMemberRole = 'member' | 'dev_admin';

export interface ProjectMember extends DirectoryUser {
    role: ProjectMemberRole;
    joined_at: string | null;
    open_tasks_count: number;
}
