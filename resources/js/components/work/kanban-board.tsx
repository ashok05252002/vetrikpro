import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { BoardColumn } from '@/types';
import {
    DndContext,
    DragOverlay,
    KeyboardSensor,
    PointerSensor,
    closestCorners,
    useDroppable,
    useSensor,
    useSensors,
    type DragEndEvent,
    type DragStartEvent,
} from '@dnd-kit/core';
import { SortableContext, arrayMove, sortableKeyboardCoordinates, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useEffect, useMemo, useState, type ReactNode } from 'react';

interface Card {
    id: number;
}

interface Props<S extends string, T extends Card> {
    columns: BoardColumn<S, T>[];
    /** The PATCH endpoint that places card `id` at `{ status, position }`. */
    moveUrl: (id: number) => string;
    renderCard: (item: T, state: { overlay: boolean }) => ReactNode;
    /** The column's identity mark: a dot, an icon. */
    columnMark: (status: S) => ReactNode;
    /** Each column's colour — the same one its badges use. */
    columnColor?: (status: S) => string;
    onAdd?: (status: S) => void;
    /** Props to reload if the server rejects a move, restoring its truth. */
    reloadOnError: string[];
}

function Column<S extends string>({
    value,
    label,
    count,
    mark,
    color,
    onAdd,
    children,
}: {
    value: S;
    label: string;
    count: number;
    mark: ReactNode;
    color?: string;
    onAdd?: () => void;
    children: ReactNode;
}) {
    const { setNodeRef, isOver } = useDroppable({ id: `column:${value}` });

    return (
        <section
            className="bg-muted/40 flex min-w-72 flex-1 flex-col overflow-hidden rounded-xl border-t-[3px]"
            style={color ? { borderTopColor: color } : undefined}
        >
            {/* The column's colour runs across its top and tints its heading, so a board reads left to right at a glance. */}
            <header
                className="flex items-center gap-2 px-3 pt-3 pb-2"
                style={color ? { background: `linear-gradient(to bottom, color-mix(in oklab, ${color} 12%, transparent), transparent)` } : undefined}
            >
                {mark}
                <h2 className="text-sm font-semibold">{label}</h2>
                <span
                    className="rounded-full px-1.5 text-xs font-medium tabular-nums"
                    style={{ background: color ? `color-mix(in oklab, ${color} 18%, transparent)` : undefined }}
                >
                    {count}
                </span>

                {onAdd && (
                    <Button variant="ghost" size="sm" className="ml-auto size-7 p-0" onClick={onAdd} aria-label={`Add to ${label}`}>
                        <Plus className="size-4" />
                    </Button>
                )}
            </header>

            <div ref={setNodeRef} className={cn('flex min-h-32 flex-1 flex-col gap-2 rounded-b-xl p-2 transition-colors', isOver && 'bg-muted')}>
                {children}

                {count === 0 && <p className="text-muted-foreground px-1 py-6 text-center text-xs">Nothing here.</p>}
            </div>
        </section>
    );
}

/**
 * Drag-and-drop board shared by tasks and test points. Moves are applied
 * optimistically and sent to the server, whose answer always wins: new props
 * replace local state, and a rejected move reloads the board.
 */
export default function KanbanBoard<S extends string, T extends Card>({
    columns: initial,
    moveUrl,
    renderCard,
    columnMark,
    columnColor,
    onAdd,
    reloadOnError,
}: Props<S, T>) {
    const [columns, setColumns] = useState(initial);
    const [active, setActive] = useState<T | null>(null);

    useEffect(() => setColumns(initial), [initial]);

    const sensors = useSensors(
        // A small distance threshold keeps a click on the card from starting a drag.
        useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
        useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
    );

    const index = useMemo(() => {
        const map = new Map<number, { item: T; status: S }>();
        columns.forEach((column) => column.items.forEach((item) => map.set(item.id, { item, status: column.value })));
        return map;
    }, [columns]);

    const columnOf = (id: string | number): S | null => {
        if (typeof id === 'string' && id.startsWith('column:')) {
            return id.slice('column:'.length) as S;
        }
        return index.get(Number(id))?.status ?? null;
    };

    const handleDragStart = ({ active }: DragStartEvent) => setActive(index.get(Number(active.id))?.item ?? null);

    const handleDragEnd = ({ active, over }: DragEndEvent) => {
        setActive(null);

        if (!over) {
            return;
        }

        const from = columnOf(active.id);
        const to = columnOf(over.id);

        if (!from || !to) {
            return;
        }

        const id = Number(active.id);
        const source = columns.find((c) => c.value === from)!;
        const target = columns.find((c) => c.value === to)!;
        const item = source.items.find((t) => t.id === id)!;

        // Dropping on the column itself appends; dropping on a card inserts there.
        const overIndex = target.items.findIndex((t) => t.id === Number(over.id));
        const position = overIndex === -1 ? target.items.length : overIndex;

        if (from === to) {
            const oldIndex = source.items.findIndex((t) => t.id === id);
            if (oldIndex === position) {
                return;
            }

            setColumns((current) => current.map((c) => (c.value === from ? { ...c, items: arrayMove(c.items, oldIndex, position) } : c)));
        } else {
            // Move optimistically so the card doesn't snap back while the request runs.
            setColumns((current) =>
                current.map((c) => {
                    if (c.value === from) {
                        return { ...c, items: c.items.filter((t) => t.id !== id) };
                    }
                    if (c.value === to) {
                        const next = [...c.items];
                        next.splice(position, 0, { ...item, status: to });
                        return { ...c, items: next };
                    }
                    return c;
                }),
            );
        }

        router.patch(
            moveUrl(id),
            { status: to, position },
            {
                preserveScroll: true,
                preserveState: true,
                onError: () => router.reload({ only: reloadOnError }),
            },
        );
    };

    return (
        <DndContext
            sensors={sensors}
            collisionDetection={closestCorners}
            onDragStart={handleDragStart}
            onDragEnd={handleDragEnd}
            onDragCancel={() => setActive(null)}
        >
            <div className="flex flex-1 gap-4 overflow-x-auto pb-4">
                {columns.map((column) => (
                    <Column
                        key={column.value}
                        value={column.value}
                        label={column.label}
                        count={column.items.length}
                        mark={columnMark(column.value)}
                        color={columnColor?.(column.value)}
                        onAdd={onAdd ? () => onAdd(column.value) : undefined}
                    >
                        <SortableContext items={column.items.map((t) => t.id)} strategy={verticalListSortingStrategy}>
                            {column.items.map((item) => (
                                <div key={item.id}>{renderCard(item, { overlay: false })}</div>
                            ))}
                        </SortableContext>
                    </Column>
                ))}
            </div>

            <DragOverlay>{active && renderCard(active, { overlay: true })}</DragOverlay>
        </DndContext>
    );
}
