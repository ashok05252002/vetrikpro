import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
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
    role: UserRole;
    is_active: boolean;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    employee?: Pick<Employee, 'id' | 'employee_code'> | null;
    [key: string]: unknown; // This allows for additional properties...
}

export type UserRole = 'admin' | 'hr' | 'employee';

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
