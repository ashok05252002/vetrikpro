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
    /** The section colour for its icon (lib/sections.ts). */
    tone?: import('@/components/viz/icon-chip').Tone;
    isActive?: boolean;
    /** Extra path prefixes that count as this item being open. */
    match?: string[];
    /** Sub-items: the item becomes a dropdown group instead of a link. */
    children?: NavItem[];
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    company: Company;
    display: DisplaySettings;
    flash: { success: string | null; error: string | null; invite_link?: string | null };
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

export interface PermissionAction {
    /** Full key, e.g. "users.edit". */
    key: string;
    /** The action part, e.g. "edit". */
    action: string;
    label: string;
    /** Doesn't bring its module's View (Permissions::STANDALONE), e.g. projects.create. */
    standalone: boolean;
}

export interface PermissionModule {
    key: string;
    label: string;
    actions: PermissionAction[];
}

/** One section of the permission matrix (App\Support\Permissions::forEditor). */
export interface PermissionGroup {
    group: string;
    modules: PermissionModule[];
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
    is_active: boolean;
    designations_count?: number;
    employees_count?: number;
    /** Named by anyone or anything, now or in history — then it can only be switched off. */
    in_use?: boolean;
}

export interface Designation {
    id: number;
    department_id: number | null;
    name: string;
    description: string | null;
    is_active: boolean;
    department?: Pick<Department, 'id' | 'name'> | null;
    employees_count?: number;
    /** Held by anyone, now or in their promotion history — then it can only be switched off. */
    in_use?: boolean;
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
    has_stipend?: boolean;
    stipend?: string | null;
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
    /** Dashboard only: open tasks assigned to the viewer. */
    my_open_tasks_count?: number;
}

export interface TaskSummary {
    id: number;
    /** Per-project number; `reference` is the display form, e.g. "T-12". */
    number?: number;
    reference?: string;
    title: string;
    status: TaskStatus;
    priority: TaskPriority;
    due_date: string | null;
    position?: number;
    project?: Pick<ProjectSummary, 'id' | 'name' | 'code'> | null;
    assignee?: Pick<User, 'id' | 'name'> | null;
    /** Who added it. */
    creator?: Pick<User, 'id' | 'name'> | null;
    /** Who gave it to the assignee; null for work assigned before this was recorded. */
    assigner?: Pick<User, 'id' | 'name'> | null;
    comments_count?: number;
    is_overdue?: boolean;
    /** Whether the viewer may change the status (creator, assignee, project owner, admin). */
    can_move?: boolean;
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

/** One column of a Kanban board: a status and the cards in it, in order. */
export interface BoardColumn<S extends string = TaskStatus, T = TaskSummary> {
    value: S;
    label: string;
    items: T[];
}

/** One workflow stage and how many tasks sit in it. */
export interface PipelineStage {
    value: TaskStatus;
    label: string;
    count: number;
}

export interface Company {
    name: string;
    /** Public URL of the logo for light backgrounds, or null when none has been uploaded. */
    logo: string | null;
    /** Public URL of the logo for dark backgrounds (navy sidebar, dark mode), or null. */
    logo_dark: string | null;
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
    /** Left the organisation: kept on the project for history, never given new work. */
    is_archived: boolean;
}

export type ProjectMemberRole = 'member' | 'dev_admin';

export interface ProjectMember extends DirectoryUser {
    role: ProjectMemberRole;
    joined_at: string | null;
    open_tasks_count: number;
}

/** Header for every staff-profile tab (App\Support\EmployeeProfile). */
export interface EmployeeProfileHeader {
    id: number;
    employee_code: string;
    status: string;
    employment_type: string;
    date_of_joining: string | null;
    name: string;
    email: string;
    is_active: boolean;
    archived_at: string | null;
    archived_by: string | null;
    deactivated_at: string | null;
    deactivated_by: string | null;
    user_id: number;
    role: Pick<Role, 'id' | 'name' | 'is_super'> | null;
    department: string | null;
    designation: string | null;
    onboarding_status: OnboardingStatus | null;
    counts: { documents: number | null; projects: number; open_tasks: number };
    viewer: {
        can_edit: boolean;
        can_documents: boolean;
        can_upload: boolean;
        can_delete_documents: boolean;
        can_access: boolean;
        can_toggle_access: boolean;
    };
}

export interface EmployeeDocument {
    id: number;
    type: string;
    type_label: string;
    title: string;
    original_name: string;
    mime_type: string;
    size: number;
    expires_at: string | null;
    is_expired: boolean;
    uploaded_by: string | null;
    uploaded_at: string;
}

export type TestPointStatus = 'open' | 'in_progress' | 'ready_for_test' | 'repeated' | 'closed';

export interface TestPointSummary {
    id: number;
    project_id: number;
    number: number;
    reference: string;
    title: string;
    status: TestPointStatus;
    priority: TaskPriority;
    position?: number;
    assignee?: Pick<User, 'id' | 'name'> | null;
    /** Who reported it. */
    reporter?: Pick<User, 'id' | 'name'> | null;
    /** Who gave it to the assignee; null for work assigned before this was recorded. */
    assigner?: Pick<User, 'id' | 'name'> | null;
    task?: { id: number; reference: string; title: string } | null;
    last_tested_at: string | null;
    can_move?: boolean;
}

export interface TestPointDetail extends TestPointSummary {
    steps: string | null;
    expected_result: string | null;
    actual_result: string | null;
    assigned_to: number | null;
    task_id: number | null;
    created_at: string;
    creator?: Pick<User, 'id' | 'name'> | null;
    last_tester?: Pick<User, 'id' | 'name'> | null;
}

export type TestResult = 'not_run' | 'passed' | 'failed' | 'blocked';
export type TestRunStatus = 'open' | 'completed';

export interface TestRunSummary {
    id: number;
    number: number;
    reference: string;
    name: string;
    status: TestRunStatus;
    created_at: string;
    completed_at: string | null;
    creator?: Pick<User, 'id' | 'name'> | null;
    tally: Record<TestResult, number>;
    total: number;
}

export interface TestRunResultRow {
    id: number;
    reference: string;
    title: string;
    result: TestResult;
    notes: string | null;
    tested_at: string | null;
    tester: Pick<User, 'id' | 'name'> | null;
    /** Null once the point itself has been deleted; the run keeps its copy. */
    point: {
        id: number;
        steps: string | null;
        expected_result: string | null;
        priority: TaskPriority;
        status: TestPointStatus;
        assignee: Pick<User, 'id' | 'name'> | null;
    } | null;
    can_record: boolean;
}

/** The Testing module's two tabs. */
export interface TestingCounts {
    points: number;
    runs: number;
}

export type BranchStatus = 'active' | 'merged' | 'closed';
export type MergeRequestStatus = 'open' | 'changes_requested' | 'approved' | 'merged' | 'closed';

export interface MergeRequestRow {
    id: number;
    project_id: number;
    number: number;
    reference: string;
    title: string;
    status: MergeRequestStatus;
    target_branch: string;
    created_at: string;
    merged_at: string | null;
    branch: { id: number; name: string } | null;
    project: { id: number; name: string; code: string } | null;
    requester: Pick<User, 'id' | 'name'> | null;
    reviewer: Pick<User, 'id' | 'name'> | null;
}

export interface BranchRow {
    id: number;
    project_id: number;
    name: string;
    base_branch: string;
    status: BranchStatus;
    merged_at: string | null;
    created_at: string;
    creator: Pick<User, 'id' | 'name'> | null;
    tasks_count: number | null;
    test_points_count: number | null;
    live_merge_request: MergeRequestRow | null;
}

export interface Readiness {
    tasks_open: number;
    tests_not_passed: number;
    tests_failed: number;
    ready: boolean;
}

export interface BranchDetail extends BranchRow {
    description: string | null;
    tasks: TaskSummary[];
    test_points: TestPointSummary[];
    merge_requests: MergeRequestRow[];
    readiness: Readiness;
}

export interface MergeRequestEvent {
    id: number;
    action: 'opened' | 'approved' | 'changes_requested' | 'resubmitted' | 'merged' | 'closed' | 'commented';
    from_status: MergeRequestStatus | null;
    to_status: MergeRequestStatus | null;
    note: string | null;
    created_at: string;
    user: Pick<User, 'id' | 'name'> | null;
}

export interface MergeRequestDetail extends Omit<MergeRequestRow, 'branch'> {
    description: string | null;
    branch: { id: number; name: string; base_branch: string; status: BranchStatus; description: string | null };
    reviewed_by: Pick<User, 'id' | 'name'> | null;
    reviewed_at: string | null;
    merged_by: Pick<User, 'id' | 'name'> | null;
    tasks: TaskSummary[];
    test_points: TestPointSummary[];
    readiness: Readiness;
    events: MergeRequestEvent[];
    next: MergeRequestStatus[];
}

export type OnboardingStatus = 'invited' | 'in_progress' | 'submitted' | 'returned' | 'completed';

export interface OnboardingItem {
    key: string;
    label: string;
    done: boolean;
    required: boolean;
    section: 'details' | 'bank' | 'documents' | 'offer';
}

export interface OnboardingSlot {
    type_id: number;
    name: string;
    description: string | null;
    required: boolean;
    is_offer_letter: boolean;
    document: { id: number; title: string; original_name: string; size: number; uploaded_at: string } | null;
}

export interface OnboardingState {
    status: OnboardingStatus;
    status_label: string;
    editable: boolean;
    invited_at: string | null;
    submitted_at: string | null;
    completed_at: string | null;
    note: string | null;
    checklist: OnboardingItem[];
    progress: { done: number; total: number; percent: number; complete: boolean };
    details: { phone: string | null; date_of_birth: string | null; gender: string | null; address: string | null };
    bank: { account_name: string | null; account_number: string | null; ifsc: string | null; bank_name: string | null; branch: string | null };
    // kind: which generated letter it is; null when HR uploaded their own.
    offer_letter: { name: string; kind: 'offer' | 'welcome' | null } | null;
    slots: OnboardingSlot[];
}

export type InvoiceStatus = 'draft' | 'sent' | 'paid' | 'cancelled';

export interface Customer {
    id: number;
    name: string;
    contact_person: string | null;
    email: string | null;
    phone: string | null;
    address: string | null;
    state_code: string | null;
    state_name?: string | null;
    gstin: string | null;
    is_active: boolean;
    invoices_count?: number;
}

export type ProductType = 'product' | 'service';

export interface Product {
    id: number;
    name: string;
    type: ProductType;
    code: string | null;
    hsn_sac: string | null;
    unit: string;
    price: string;
    gst_rate: string;
    description: string | null;
    is_active: boolean;
    invoice_items_count?: number;
}

export interface InvoiceSummary {
    id: number;
    number: number | null;
    reference: string;
    status: InvoiceStatus;
    bill_name: string;
    issue_date: string;
    due_date: string | null;
    total: string;
    issued_at: string | null;
    sent_at: string | null;
    sent_to: string | null;
    paid_at: string | null;
    is_overdue: boolean;
}

/** A type alias, not an interface, so it satisfies useForm's index-signature constraint. */
export type InvoiceLine = {
    product_id: number | null;
    description: string;
    hsn_sac: string | null;
    quantity: string | number;
    unit: string;
    unit_price: string | number;
    discounted_price: string | number;
    gst_rate: string | number;
};
