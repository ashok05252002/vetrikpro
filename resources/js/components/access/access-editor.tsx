import { Badge } from '@/components/ui/badge';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import type { PermissionGroup, PermissionOverride } from '@/types';
import { Check, Minus } from 'lucide-react';

export type Overrides = Record<string, PermissionOverride>;

interface AccessEditorProps {
    groups: PermissionGroup[];
    /** What the selected role grants. */
    inherited: string[];
    /** A super role holds everything and overrides do not apply. */
    superRole?: boolean;
    value: Overrides;
    onChange: (next: Overrides) => void;
    disabled?: boolean;
}

const INHERIT = 'inherit';

/**
 * Per-user access on top of a role: every permission shows what the role gives,
 * and can be left to inherit, forced on, or forced off for this one person.
 */
export default function AccessEditor({ groups, inherited, superRole = false, value, onChange, disabled = false }: AccessEditorProps) {
    const set = (key: string, next: string) => {
        const copy = { ...value };

        if (next === 'allow' || next === 'deny') {
            copy[key] = next;
        } else {
            delete copy[key];
        }

        onChange(copy);
    };

    if (superRole) {
        return (
            <p className="bg-muted/50 text-muted-foreground rounded-lg border px-4 py-3 text-sm">
                This role holds every permission, including ones added later. Individual overrides don’t apply to it.
            </p>
        );
    }

    const changed = Object.keys(value).length;

    return (
        <div className="space-y-4">
            {changed > 0 && (
                <p className="text-muted-foreground text-xs">
                    {changed} permission{changed === 1 ? '' : 's'} differ from the role for this person.
                </p>
            )}

            {groups.map((group) => (
                <div key={group.group} className="overflow-hidden rounded-lg border">
                    <div className="bg-muted/50 text-muted-foreground px-4 py-2 text-xs font-medium tracking-wide uppercase">{group.group}</div>
                    <ul className="divide-y">
                        {group.permissions.map((permission) => {
                            const fromRole = inherited.includes(permission.key);
                            const override = value[permission.key];
                            const effective = override ? override === 'allow' : fromRole;

                            return (
                                <li key={permission.key} className="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div className="min-w-0 space-y-0.5">
                                        <div className="flex items-center gap-2 text-sm">
                                            {effective ? (
                                                <Check className="size-4 shrink-0 text-emerald-600 dark:text-emerald-400" aria-label="Granted" />
                                            ) : (
                                                <Minus className="text-muted-foreground size-4 shrink-0" aria-label="Not granted" />
                                            )}
                                            <span className={effective ? '' : 'text-muted-foreground'}>{permission.label}</span>
                                        </div>
                                        <div className="text-muted-foreground flex items-center gap-2 pl-6 text-xs">
                                            <code>{permission.key}</code>
                                            <span>· role {fromRole ? 'grants' : 'does not grant'}</span>
                                            {override && <Badge variant="outline">Override</Badge>}
                                        </div>
                                    </div>

                                    <ToggleGroup
                                        type="single"
                                        size="sm"
                                        variant="outline"
                                        disabled={disabled}
                                        value={override ?? INHERIT}
                                        onValueChange={(next) => set(permission.key, next || INHERIT)}
                                        className="shrink-0 self-start sm:self-auto"
                                    >
                                        <ToggleGroupItem value={INHERIT} className="px-3 text-xs">
                                            Inherit
                                        </ToggleGroupItem>
                                        <ToggleGroupItem value="allow" className="px-3 text-xs">
                                            Allow
                                        </ToggleGroupItem>
                                        <ToggleGroupItem value="deny" className="px-3 text-xs">
                                            Deny
                                        </ToggleGroupItem>
                                    </ToggleGroup>
                                </li>
                            );
                        })}
                    </ul>
                </div>
            ))}
        </div>
    );
}
