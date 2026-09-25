import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { router } from '@inertiajs/react';
import { Columns3, List } from 'lucide-react';

export type WorkView = 'board' | 'list';

/**
 * Board / List switch. The choice lives in the query string, so a list view
 * link opens as a list.
 */
export default function ViewToggle({ url, view }: { url: string; view: WorkView }) {
    return (
        <ToggleGroup
            type="single"
            size="sm"
            variant="outline"
            value={view}
            onValueChange={(next) => next && next !== view && router.get(url, next === 'list' ? { view: 'list' } : {}, { preserveScroll: true })}
            aria-label="View"
        >
            <ToggleGroupItem value="board" className="gap-1.5 px-3 text-xs">
                <Columns3 className="size-4" /> Board
            </ToggleGroupItem>
            <ToggleGroupItem value="list" className="gap-1.5 px-3 text-xs">
                <List className="size-4" /> List
            </ToggleGroupItem>
        </ToggleGroup>
    );
}
