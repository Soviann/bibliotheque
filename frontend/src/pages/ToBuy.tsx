import { Filter, Loader2, Search, ShoppingCart } from "lucide-react";
import { useCallback, useMemo, useState } from "react";
import { Link, useSearchParams } from "react-router-dom";
import ComicCard from "../components/ComicCard";
import ComicCardSkeleton from "../components/ComicCardSkeleton";
import CoverImage from "../components/CoverImage";
import EmptyState from "../components/EmptyState";
import FilterChips from "../components/FilterChips";
import Filters from "../components/Filters";
import SearchInput from "../components/SearchInput";
import VirtualGrid from "../components/VirtualGrid";
import { useComics } from "../hooks/useComics";
import { useDebounce } from "../hooks/useDebounce";
import { useMediaQuery } from "../hooks/useMediaQuery";
import { ComicTypePlaceholder } from "../types/enums";
import { getCoverSrc, getCoverThumbnailSrc } from "../utils/coverUtils";
import { searchComics } from "../utils/searchComics";
import { sortComics } from "../utils/sortComics";
import type { SortOption } from "../utils/sortComics";
import { filterSeriesToBuy, formatTomeRanges, getNextTomesToBuy } from "../utils/toBuyUtils";

const HERO_COUNT = 10;

const VALID_SORTS: Set<string> = new Set([
  "createdAt-asc",
  "createdAt-desc",
  "title-asc",
  "title-desc",
  "tomes-asc",
  "tomes-desc",
]);

export default function ToBuy() {
  const [searchParams, setSearchParams] = useSearchParams();
  const type = searchParams.get("type") ?? "";
  const status = searchParams.get("status") ?? "";
  const sortParam = searchParams.get("sort") ?? "";
  const sort: SortOption = VALID_SORTS.has(sortParam) ? (sortParam as SortOption) : "title-asc";

  const { data, isFetching, isLoading } = useComics();
  const allComics = data?.member ?? [];

  const [search, setSearch] = useState("");
  const debouncedSearch = useDebounce(search, 300);
  const isMobile = useMediaQuery("(max-width: 639px)");

  const updateParam = useCallback(
    (key: string, value: string) => {
      setSearchParams(
        (prev) => {
          const next = new URLSearchParams(prev);
          if (value) {
            next.set(key, value);
          } else {
            next.delete(key);
          }
          return next;
        },
        { replace: true },
      );
    },
    [setSearchParams],
  );

  const handleSearchChange = useCallback((v: string) => setSearch(v), []);
  const handleStatusChange = useCallback((v: string) => updateParam("status", v), [updateParam]);
  const handleTypeChange = useCallback((v: string) => updateParam("type", v), [updateParam]);
  const handleSortChange = useCallback(
    (v: SortOption) => updateParam("sort", v === "title-asc" ? "" : v),
    [updateParam],
  );

  const toBuyComics = useMemo(() => filterSeriesToBuy(allComics), [allComics]);

  const filtered = useMemo(() => {
    const preFiltered = toBuyComics.filter((c) => {
      if (type && c.type !== type) return false;
      if (status && c.status !== status) return false;
      return true;
    });
    const searched = searchComics(preFiltered, debouncedSearch);
    return sortComics(searched, sort).map((comic) => ({
      comic,
      nextTomes: formatTomeRanges(getNextTomesToBuy(comic)),
    }));
  }, [toBuyComics, debouncedSearch, sort, type, status]);

  const recentlyAdded = useMemo(() => {
    if (toBuyComics.length === 0) return [];
    return [...toBuyComics]
      .sort((a, b) => new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime())
      .slice(0, HERO_COUNT);
  }, [toBuyComics]);

  const handleResetFilters = useCallback(() => {
    setSearchParams(
      (prev) => {
        const next = new URLSearchParams(prev);
        next.delete("type");
        next.delete("status");
        next.delete("sort");
        return next;
      },
      { replace: true },
    );
  }, [setSearchParams]);

  const hasActiveFilters = !!type || !!status;
  const showHero = !isLoading && !debouncedSearch && !hasActiveFilters && recentlyAdded.length > 0;

  return (
    <div className="space-y-4">
      {/* Hero — Récemment ajoutés */}
      {showHero && (
        <section className="space-y-2">
          <h2 className="font-display text-sm font-semibold text-text-secondary dark:font-body dark:text-xs dark:uppercase dark:tracking-widest dark:text-text-muted">
            Récemment ajoutés
          </h2>
          <div className="-mx-4 flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 pb-2 scrollbar-none">
            {recentlyAdded.map((comic) => {
              const src = getCoverThumbnailSrc(comic) ?? getCoverSrc(comic);
              return (
                <Link
                  className="group flex w-[140px] shrink-0 snap-center flex-col gap-1.5 sm:w-[160px]"
                  key={comic.id}
                  to={`/comic/${comic.id}`}
                  viewTransition
                >
                  <div className="card-glow overflow-hidden rounded-xl transition-transform duration-200 group-hover:-translate-y-1 group-hover:scale-[1.02]"
                    style={{ ["--glow-rgb" as string]: "99, 102, 241" }}
                  >
                    <CoverImage
                      alt={comic.title}
                      className="aspect-[3/4]"
                      fallbackSrc={ComicTypePlaceholder[comic.type]}
                      height={240}
                      src={src ?? ComicTypePlaceholder[comic.type]}
                      width={180}
                    />
                  </div>
                  <h3 className="truncate font-display text-sm font-medium text-text-primary dark:font-body dark:text-xs">
                    {comic.title}
                  </h3>
                  <p className="font-mono-stats text-xs text-accent-sage">
                    Prochain : {formatTomeRanges(getNextTomesToBuy(comic))}
                  </p>
                </Link>
              );
            })}
          </div>
        </section>
      )}

      {/* Séparateur */}
      {showHero && (
        <div className="flex items-center gap-3">
          <hr className="flex-1 border-surface-border dark:border-white/5" />
          <span className="font-display text-sm font-semibold text-text-secondary dark:font-body dark:text-xs dark:uppercase dark:tracking-widest dark:text-text-muted">
            Toutes les séries
          </span>
          <hr className="flex-1 border-surface-border dark:border-white/5" />
        </div>
      )}

      {/* Search + filters */}
      <div className="flex items-center gap-2">
        <SearchInput onChange={handleSearchChange} value={search} />
        {isMobile && (
          <Filters
            onSortChange={handleSortChange}
            onStatusChange={handleStatusChange}
            onTypeChange={handleTypeChange}
            sort={sort}
            status={status}
            type={type}
          />
        )}
        <span className="flex shrink-0 items-center gap-1.5 font-mono-stats text-sm text-text-muted">
          {isFetching && !isLoading && (
            <Loader2 className="h-3.5 w-3.5 animate-spin" />
          )}
          {filtered.length}/{toBuyComics.length}
        </span>
      </div>

      {/* Quick filter chips */}
      <FilterChips
        onStatusChange={handleStatusChange}
        onTypeChange={handleTypeChange}
        status={status}
        type={type}
      />

      {/* Desktop filters */}
      {!isMobile && (
        <div className="flex min-w-0 items-center gap-3">
          <Filters
            onSortChange={handleSortChange}
            onStatusChange={handleStatusChange}
            onTypeChange={handleTypeChange}
            sort={sort}
            status={status}
            type={type}
          />
        </div>
      )}

      {isLoading ? (
        <div className="grid grid-cols-2 gap-5 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
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
        ) : hasActiveFilters ? (
          <EmptyState
            actionLabel="Réinitialiser les filtres"
            icon={Filter}
            onAction={handleResetFilters}
            title="Aucune série avec ces filtres"
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
              <p className="mt-1 truncate px-1 text-xs text-accent-sage">
                Prochain : {nextTomes}
              </p>
            </>
          )}
        />
      )}
    </div>
  );
}
