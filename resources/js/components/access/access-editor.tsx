import PermissionMatrix, { viewKey } from '@/components/access/permission-matrix';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { PermissionGroup, PermissionModule, PermissionOverride } from '@/types';
import { Check, X } from 'lucide-react';

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

type CellState = 'inherited-on' | 'inherited-off' | 'allow' | 'deny';

function Mark({ state }: { state: CellState }) {
    return (
        <span
            className={cn(
                'inline-flex size-6 items-center justify-center rounded-md border transition-colors',
                state === 'inherited-on' && 'bg-muted text-foreground border-transparent',
                state === 'inherited-off' && 'border-input text-transparent',
                state === 'allow' && 'border-transparent text-white',
                state === 'deny' && 'border-transparent text-white',
            )}
            style={state === 'allow' ? { background: 'var(--status-good)' } : state === 'deny' ? { background: 'var(--status-critical)' } : undefined}
        >
            {state === 'deny' ? <X className="size-3.5" /> : <Check className="size-3.5" />}
        </span>
    );
}

const stateLabel: Record<CellState, string> = {
    'inherited-on': 'granted by the role',
    'inherited-off': 'not granted by the role',
    allow: 'allowed for this person',
    deny: 'denied for this person',
};

/**
 * Per-user access on top of a role, as the same matrix the role editor uses.
 * Each cell cycles: inherit from the role → allow → deny → inherit.
 */
export default function AccessEditor({ groups, inherited, superRole = false, value, onChange, disabled = false }: AccessEditorProps) {
    if (superRole) {
        return (
            <p className="bg-muted/50 text-muted-foreground rounded-lg border px-4 py-3 text-sm">
                This role holds every permission, including ones added later. Individual overrides don’t apply to it.
            </p>
        );
    }

    const effective = (key: string) => (value[key] ? value[key] === 'allow' : inherited.includes(key));
    const stateOf = (key: string): CellState => (value[key] ?? (inherited.includes(key) ? 'inherited-on' : 'inherited-off')) as CellState;

    const cycle = (key: string, module: PermissionModule) => {
        const current = value[key];
        const nextState: PermissionOverride | undefined = current === undefined ? 'allow' : current === 'allow' ? 'deny' : undefined;
        const next = { ...value };
        const view = viewKey(module);

        if (nextState) {
            next[key] = nextState;
        } else {
            delete next[key];
        }

        // Keep the row coherent: allowing an action allows seeing the module,
        // and denying View denies everything else in it.
        if (nextState === 'allow' && view && key !== view && !effective(view)) {
            next[view] = 'allow';
        }
        if (nextState === 'deny' && key === view) {
            module.actions.forEach((a) => {
                if (a.key !== view && (inherited.includes(a.key) || next[a.key] === 'allow')) {
                    next[a.key] = 'deny';
                }
            });
        }

        onChange(next);
    };

    const changed = Object.keys(value).length;

    return (
        <div className="space-y-3">
            <div className="text-muted-foreground flex flex-wrap items-center gap-x-5 gap-y-2 text-xs">
                <span className="inline-flex items-center gap-1.5">
                    <Mark state="inherited-on" /> From the role
                </span>
                <span className="inline-flex items-center gap-1.5">
                    <Mark state="allow" /> Allowed for this person
                </span>
                <span className="inline-flex items-center gap-1.5">
                    <Mark state="deny" /> Denied for this person
                </span>
                <span>Click a cell to cycle.</span>
                {changed > 0 && (
                    <Button type="button" variant="ghost" size="sm" className="ml-auto h-7" onClick={() => onChange({})} disabled={disabled}>
                        Reset {changed} override{changed === 1 ? '' : 's'}
                    </Button>
                )}
            </div>

            <PermissionMatrix
                groups={groups}
                cell={(key, label, module) => {
                    const state = stateOf(key);

                    return (
                        <button
                            type="button"
                            disabled={disabled}
                            onClick={() => cycle(key, module)}
                            className="focus-visible:ring-ring rounded-md focus-visible:ring-2 focus-visible:outline-hidden disabled:opacity-50"
                            aria-label={`${label}: ${stateLabel[state]}`}
                        >
                            <Mark state={state} />
                        </button>
                    );
                }}
            />
        </div>
    );
}
