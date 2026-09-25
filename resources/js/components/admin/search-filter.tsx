import FilterBar from '@/components/admin/filter-bar';

/**
 * Search-only form of FilterBar, kept for pages with nothing to filter by.
 */
export default function SearchFilter({ url, initial = '', placeholder = 'Search…' }: { url: string; initial?: string; placeholder?: string }) {
    return <FilterBar url={url} filters={{ search: initial }} searchPlaceholder={placeholder} />;
}
