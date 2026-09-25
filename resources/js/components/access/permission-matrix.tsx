import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import type { PermissionGroup, PermissionModule } from '@/types';
import type { ReactNode } from 'react';

/** Standard columns, in order. Anything else a module offers goes under "More". */
export const STANDARD_ACTIONS = ['view', 'create', 'edit', 'delete'] as const;

const columnLabel: Record<string, string> = { view: 'View', create: 'Create', edit: 'Edit', delete: 'Delete' };

export function viewKey(module: PermissionModule): string | null {
    return module.actions.find((a) => a.action === 'view')?.key ?? null;
}

interface Props {
    groups: PermissionGroup[];
    /** Renders one cell. `key` is the permission, `label` what it allows. */
    cell: (key: string, label: string, module: PermissionModule) => ReactNode;
    /** Optional control at the end of each row, e.g. "all". */
    rowAction?: (module: PermissionModule) => ReactNode;
}

/**
 * Modules down the side, actions across the top. A module without a given
 * action shows an empty cell; extra actions (onboard, review) sit in "More".
 */
export default function PermissionMatrix({ groups, cell, rowAction }: Props) {
    return (
        <TooltipProvider delayDuration={200}>
            <div className="space-y-4">
                {groups.map((group) => (
                    <div key={group.group} className="overflow-x-auto rounded-lg border">
                        <table className="w-full min-w-[560px] text-sm">
                            <thead>
                                <tr className="bg-muted/50 text-muted-foreground text-xs">
                                    <th className="px-4 py-2 text-left font-semibold tracking-wide uppercase">{group.group}</th>
                                    {STANDARD_ACTIONS.map((action) => (
                                        <th key={action} className="w-20 px-2 py-2 text-center font-medium">
                                            {columnLabel[action]}
                                        </th>
                                    ))}
                                    <th className="px-2 py-2 text-left font-medium">More</th>
                                    {rowAction && <th className="w-16 px-2 py-2" />}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {group.modules.map((module) => {
                                    const extras = module.actions.filter((a) => !(STANDARD_ACTIONS as readonly string[]).includes(a.action));

                                    return (
                                        <tr key={module.key}>
                                            <th scope="row" className="px-4 py-2.5 text-left font-medium">
                                                {module.label}
                                            </th>
                                            {STANDARD_ACTIONS.map((action) => {
                                                const permission = module.actions.find((a) => a.action === action);

                                                return (
                                                    <td key={action} className="px-2 py-2 text-center">
                                                        {permission ? (
                                                            <Tooltip>
                                                                <TooltipTrigger asChild>
                                                                    <span className="inline-flex">
                                                                        {cell(permission.key, permission.label, module)}
                                                                    </span>
                                                                </TooltipTrigger>
                                                                <TooltipContent>{permission.label}</TooltipContent>
                                                            </Tooltip>
                                                        ) : (
                                                            <span className="text-muted-foreground/40" aria-hidden>
                                                                ·
                                                            </span>
                                                        )}
                                                    </td>
                                                );
                                            })}
                                            <td className="px-2 py-2">
                                                <div className="flex flex-wrap gap-3">
                                                    {extras.map((permission) => (
                                                        <label key={permission.key} className={cn('inline-flex items-center gap-2 text-xs')}>
                                                            {cell(permission.key, permission.label, module)}
                                                            <span>{permission.label}</span>
                                                        </label>
                                                    ))}
                                                </div>
                                            </td>
                                            {rowAction && <td className="px-2 py-2 text-right">{rowAction(module)}</td>}
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                ))}
            </div>
        </TooltipProvider>
    );
}
