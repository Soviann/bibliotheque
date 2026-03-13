import { Loader2, Search, ShoppingCart } from "lucide-react";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import ComicCard from "../components/ComicCard";
import ComicCardSkeleton from "../components/ComicCardSkeleton";
import EmptyState from "../components/EmptyState";
import { useComics } from "../hooks/useComics";
import { searchComics } from "../utils/searchComics";
import { filterSeriesToBuy, getNextTomesToBuy } from "../utils/toBuyUtils";

export default function ToBuy() {
  const { data, isFetching, isLoading } = useComics();
  const allComics = data?.member ?? [];

  const [search, setSearch] = useState("");
  const [debouncedSearch, setDebouncedSearch] = useState("");
  const searchTimerRef = useRef<ReturnType<typeof setTimeout>>(undefined);

  const handleSearchChange = useCallback((v: string) => {
    setSearch(v);
    clearTimeout(searchTimerRef.current);
    searchTimerRef.current = setTimeout(() => setDebouncedSearch(v), 300);
  }, []);

  useEffect(() => {
    return () => clearTimeout(searchTimerRef.current);
  }, []);

  const filtered = useMemo(() => {
    const toBuy = filterSeriesToBuy(allComics);
    const searched = searchComics(toBuy, debouncedSearch);
    return [...searched].sort((a, b) => a.title.localeCompare(b.title, "fr"));
  }, [allComics, debouncedSearch]);

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-2">
        <div className="relative min-w-0 flex-1">
          <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
          <input
            className="w-full rounded-lg border border-surface-border bg-surface-primary py-2 pl-10 pr-4 text-sm text-text-primary placeholder:text-text-muted focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
            onChange={(e) => handleSearchChange(e.target.value)}
            placeholder="Rechercher…"
            type="search"
            value={search}
          />
        </div>
        <span className="flex shrink-0 items-center gap-1.5 text-sm text-text-muted">
          {isFetching && !isLoading && (
            <Loader2 className="h-3.5 w-3.5 animate-spin" />
          )}
          {filtered.length}
        </span>
      </div>

      {isLoading ? (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
          {Array.from({ length: 8 }, (_, i) => (
            <ComicCardSkeleton key={i} />
          ))}
        </div>
      ) : filtered.length === 0 ? (
        debouncedSearch ? (
          <EmptyState
            icon={Search}
            title={`Aucun résultat pour « ${debouncedSearch} »`}
          />
        ) : (
          <EmptyState
            description="Toutes vos séries en cours sont complètes"
            icon={ShoppingCart}
            title="Rien à acheter"
          />
        )
      ) : (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
          {filtered.map((comic) => (
            <div key={comic.id}>
              <ComicCard comic={comic} />
              <p className="mt-1 truncate px-1 text-xs text-emerald-600 dark:text-emerald-400">
                Prochain : {getNextTomesToBuy(comic).map((n) => `T.${n}`).join(", ")}
              </p>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
