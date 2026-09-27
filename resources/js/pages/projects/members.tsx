import DeleteButton from '@/components/admin/delete-button';
import FilterBar from '@/components/admin/filter-bar';
import Pagination from '@/components/admin/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AddMembersDialog from '@/components/work/add-members-dialog';
import UserAvatar from '@/components/work/user-avatar';
import { useFormat } from '@/hooks/use-format';
import { usePermission } from '@/hooks/use-permission';
import ProjectWorkspaceLayout from '@/layouts/project/workspace-layout';
import type { Option, Paginated, ProjectMember, ProjectWorkspaceHeader } from '@/types';
import { Link, router } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import { useState } from 'react';

interface Props {
    project: ProjectWorkspaceHeader;
    members: Paginated<ProjectMember>;
    roles: Option[];
    departments: Option[];
    designations: Option[];
    filters: { search?: string; department?: string; designation?: string; role?: string };
}

export default function ProjectMembers({ project, members, roles, departments, designations, filters }: Props) {
    const format = useFormat();
    const { can } = usePermission();
    const [adding, setAdding] = useState(false);
    const manage = project.viewer.can_manage_members;
    const roleLabel = (value: string) => roles.find((r) => r.value === value)?.label ?? value;

    const changeRole = (member: ProjectMember, role: string) =>
        router.patch(route('projects.members.update', [project.id, member.id]), { role }, { preserveScroll: true, preserveState: true });

    return (
        <ProjectWorkspaceLayout
            project={project}
            tab="members"
            actions={
                manage && (
                    <Button size="sm" onClick={() => setAdding(true)}>
                        <UserPlus className="size-4" /> Add members
                    </Button>
                )
            }
        >
            <FilterBar
                url={route('projects.members.index', project.id)}
                filters={filters}
                searchPlaceholder="Name, email or employee code…"
                selects={[
                    { name: 'role', placeholder: 'Any project role', options: roles },
                    { name: 'department', placeholder: 'All departments', options: departments },
                    { name: 'designation', placeholder: 'All designations', options: designations },
                ]}
            />

            <div className="rounded-xl border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Member</TableHead>
                            <TableHead className="hidden md:table-cell">Department</TableHead>
                            <TableHead className="hidden lg:table-cell">Designation</TableHead>
                            <TableHead>Project role</TableHead>
                            <TableHead className="hidden sm:table-cell">Open tasks</TableHead>
                            <TableHead className="hidden lg:table-cell">Joined</TableHead>
                            {manage && <TableHead className="text-right">Remove</TableHead>}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {members.data.length === 0 && (
                            <TableRow>
                                <TableCell colSpan={7} className="text-muted-foreground py-10 text-center">
                                    {Object.values(filters).some(Boolean) ? 'No members match these filters.' : 'Nobody is on this project yet.'}
                                </TableCell>
                            </TableRow>
                        )}

                        {members.data.map((member) => (
                            <TableRow key={member.id}>
                                <TableCell>
                                    <div className="flex items-center gap-3">
                                        <UserAvatar name={member.name} className="size-8" />
                                        <div className="min-w-0">
                                            {member.employee_id && can('employees.view') ? (
                                                <Link
                                                    href={route('admin.employees.show', member.employee_id)}
                                                    className="font-medium hover:underline"
                                                >
                                                    {member.name}
                                                </Link>
                                            ) : (
                                                <span className="font-medium">{member.name}</span>
                                            )}
                                            <p className="text-muted-foreground truncate text-xs">{member.email}</p>
                                        </div>
                                        {member.is_archived ? (
                                            <Badge variant="secondary">Archived</Badge>
                                        ) : (
                                            !member.is_active && <Badge variant="secondary">Disabled</Badge>
                                        )}
                                    </div>
                                </TableCell>
                                <TableCell className="text-muted-foreground hidden md:table-cell">{member.department ?? '—'}</TableCell>
                                <TableCell className="text-muted-foreground hidden lg:table-cell">{member.designation ?? '—'}</TableCell>
                                <TableCell>
                                    {manage ? (
                                        <Select value={member.role} onValueChange={(role) => changeRole(member, role)}>
                                            <SelectTrigger className="h-8 w-32" aria-label={`Project role for ${member.name}`}>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {roles.map((r) => (
                                                    <SelectItem key={r.value} value={r.value}>
                                                        {r.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    ) : (
                                        <Badge variant={member.role === 'dev_admin' ? 'default' : 'outline'}>{roleLabel(member.role)}</Badge>
                                    )}
                                </TableCell>
                                <TableCell className="hidden tabular-nums sm:table-cell">{member.open_tasks_count}</TableCell>
                                <TableCell className="text-muted-foreground hidden lg:table-cell">
                                    {member.joined_at ? format.date(member.joined_at) : '—'}
                                </TableCell>
                                {manage && (
                                    <TableCell className="text-right">
                                        <DeleteButton
                                            verb="Remove"
                                            url={route('projects.members.destroy', [project.id, member.id])}
                                            label={`${member.name} from ${project.name}`}
                                            description="They lose access to the board. Tasks already assigned to them keep their assignee."
                                        />
                                    </TableCell>
                                )}
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>

            <Pagination meta={members} />

            {manage && <AddMembersDialog projectId={project.id} open={adding} onOpenChange={setAdding} departments={departments} roles={roles} />}
        </ProjectWorkspaceLayout>
    );
}
