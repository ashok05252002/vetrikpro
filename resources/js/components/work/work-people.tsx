import UserAvatar from '@/components/work/user-avatar';
import type { User } from '@/types';

type Person = Pick<User, 'id' | 'name'> | null | undefined;

const first = (person: Person) => person?.name.split(' ')[0];

/**
 * A card's footer line: who added the work and who handed it out. First names
 * keep it to one line; the full names are in the tooltip.
 */
export function CardPeople({ creator, assigner, addedVerb = 'Added' }: { creator: Person; assigner: Person; addedVerb?: string }) {
    if (!creator && !assigner) {
        return null;
    }

    const title = [creator && `${addedVerb} by ${creator.name}`, assigner && `Assigned by ${assigner.name}`].filter(Boolean).join(' · ');

    return (
        <p className="text-muted-foreground mt-2 truncate border-t pt-2 text-[11px]" title={title}>
            {creator && (
                <>
                    {addedVerb} by <span className="text-foreground/80">{first(creator)}</span>
                </>
            )}
            {creator && assigner && ' · '}
            {assigner && (
                <>
                    Assigned by <span className="text-foreground/80">{first(assigner)}</span>
                </>
            )}
        </p>
    );
}

/** A list row's assignee cell, with who assigned them underneath. */
export function AssigneeCell({ assignee, assigner, empty = 'Unassigned' }: { assignee: Person; assigner: Person; empty?: string }) {
    if (!assignee) {
        return <span className="text-muted-foreground text-sm">{empty}</span>;
    }

    return (
        <span className="flex items-center gap-2 text-sm">
            <UserAvatar name={assignee.name} className="size-5" />
            <span className="min-w-0">
                <span className="block truncate">{assignee.name}</span>
                {assigner && <span className="text-muted-foreground block truncate text-[11px]">by {assigner.name}</span>}
            </span>
        </span>
    );
}
