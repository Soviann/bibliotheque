import { Loader2 } from "lucide-react";
import FilterChips from "./FilterChips";
import Filters from "./Filters";
import SearchInput from "./SearchInput";
import { useMediaQuery } from "../hooks/useMediaQuery";
import type { SortOption } from "../utils/sortComics";

interface StickySearchBarProps {
  filteredCount: number;
  isFetching: boolean;
  isLoading: boolean;
  onSearchChange: (value: string) => void;
  onSortChange: (sort: SortOption) => void;
  onStatusChange: (status: string) => void;
  onTypeChange: (type: string) => void;
  search: string;
  sort: SortOption;
  status: string;
  totalCount: number;
  type: string;
  visible: boolean;
}

export default function StickySearchBar({
  filteredCount,
  isFetching,
  isLoading,
  onSearchChange,
  onSortChange,
  onStatusChange,
  onTypeChange,
  search,
  sort,
  status,
  totalCount,
  type,
  visible,
}: StickySearchBarProps) {
  const isMobile = useMediaQuery("(max-width: 639px)");

  return (
    <div
      aria-hidden={!visible}
      className={`fixed left-0 right-0 z-[39] space-y-2 border-b border-surface-border bg-surface-primary/90 px-4 pb-2 pt-4 backdrop-blur-md transition-transform duration-200 ease-out dark:border-white/10 dark:bg-surface-primary/70 ${visible ? "translate-y-0" : "-translate-y-full"}`}
      style={{ top: "var(--header-h)" }}
    >
      <div className="flex items-center gap-2">
        <SearchInput
          ariaLabel="Rechercher par titre, auteur, éditeur"
          onChange={onSearchChange}
          placeholder="Rechercher…"
          value={search}
        />
        {isMobile && (
          <Filters
            onSortChange={onSortChange}
            onStatusChange={onStatusChange}
            onTypeChange={onTypeChange}
            sort={sort}
            status={status}
            type={type}
          />
        )}
        <span className="flex shrink-0 items-center gap-1.5 font-mono-stats text-sm text-text-muted">
          {isFetching && !isLoading && (
            <Loader2 className="h-3.5 w-3.5 animate-spin" />
          )}
          {filteredCount}/{totalCount}
        </span>
      </div>
      <FilterChips
        onStatusChange={onStatusChange}
        onTypeChange={onTypeChange}
        status={status}
        type={type}
      />
    </div>
  );
}
