import { Loader2, ShoppingCart } from "lucide-react";
import { useCallback, useMemo, useState } from "react";
import ComicCard from "../components/ComicCard";
import ComicCardSkeleton from "../components/ComicCardSkeleton";
import EmptyState from "../components/EmptyState";
import SearchInput from "../components/SearchInput";
import VirtualGrid from "../components/VirtualGrid";
import { useComics } from "../hooks/useComics";
import { useDebounce } from "../hooks/useDebounce";
import { searchComics } from "../utils/searchComics";
import { filterSeriesToBuy, getNextTomesToBuy } from "../utils/toBuyUtils";

export default function ToBuy() {
  const { data, isFetching, isLoading } = useComics();
  const allComics = data?.member ?? [];

  const [search, setSearch] = useState("");
  const debouncedSearch = useDebounce(search, 300);

  const handleSearchChange = useCallback((v: string) => setSearch(v), []);

  const filtered = useMemo(() => {
    const toBuy = filterSeriesToBuy(allComics);
    const searched = searchComics(toBuy, debouncedSearch);
    const sorted = [...searched].sort((a, b) => a.title.localeCompare(b.title, "fr"));
    return sorted.map((comic) => ({
      comic,
      nextTomes: getNextTomesToBuy(comic).map((n) => `T.${n}`).join(", "),
    }));
  }, [allComics, debouncedSearch]);

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-2">
        <SearchInput onChange={handleSearchChange} value={search} />
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
        <VirtualGrid
          items={filtered}
          renderItem={({ comic, nextTomes }) => (
            <>
              <ComicCard comic={comic} />
              <p className="mt-1 truncate px-1 text-xs text-emerald-600 dark:text-emerald-400">
                Prochain : {nextTomes}
              </p>
            </>
          )}
        />
      )}
    </div>
  );
}
