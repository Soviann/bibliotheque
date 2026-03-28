import { useQueryClient } from "@tanstack/react-query";
import { BookOpen, Filter, Heart, LayoutGrid, Loader2, RefreshCw, Rows3, Search } from "lucide-react";
import { useCallback, useEffect, useMemo, useState } from "react";
import { useNavigate, useSearchParams } from "react-router-dom";
import { toast } from "sonner";
import CardActionBar from "../components/CardActionBar";
import ComicCard from "../components/ComicCard";
import ComponentErrorBoundary from "../components/ComponentErrorBoundary";
import ComicCardSkeleton from "../components/ComicCardSkeleton";
import EmptyState from "../components/EmptyState";
import FilterChips from "../components/FilterChips";
import Filters from "../components/Filters";
import HeroCarousel from "../components/HeroCarousel";
import SearchInput from "../components/SearchInput";
import ShelfView from "../components/ShelfView";
import VirtualGrid from "../components/VirtualGrid";
import { useComics } from "../hooks/useComics";
import { useDebounce } from "../hooks/useDebounce";
import { useDeleteComic } from "../hooks/useDeleteComic";
import { useMediaQuery } from "../hooks/useMediaQuery";
import { usePullToRefresh } from "../hooks/usePullToRefresh";
import { useRestoreComic } from "../hooks/useTrash";
import { queryKeys } from "../queryKeys";
import type { ComicSeries } from "../types/api";
import { searchComics } from "../utils/searchComics";
import { sortComics } from "../utils/sortComics";
import type { SortOption } from "../utils/sortComics";

const VALID_SORTS: Set<string> = new Set([
  "createdAt-asc",
  "createdAt-desc",
  "title-asc",
  "title-desc",
  "tomes-asc",
  "tomes-desc",
]);

/** Nombre de séries affichées dans la section hero */
const HERO_COUNT = 10;

export default function Home() {
  const [searchParams, setSearchParams] = useSearchParams();
  const status = searchParams.get("status") ?? "";
  const type = searchParams.get("type") ?? "";
  const sortParam = searchParams.get("sort") ?? "";
  const sort: SortOption = VALID_SORTS.has(sortParam) ? (sortParam as SortOption) : "title-asc";
  const searchParam = searchParams.get("search") ?? "";

  const navigate = useNavigate();
  const [search, setSearch] = useState(searchParam);
  const debouncedSearch = useDebounce(search, 300);
  const [menuComic, setMenuComic] = useState<ComicSeries | null>(null);
  const [viewMode, setViewMode] = useState<"grid" | "shelves">(() => {
    return (localStorage.getItem("home-view-mode") as "grid" | "shelves") ?? "grid";
  });

  useEffect(() => {
    setSearch(searchParam);
  }, [searchParam]);

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

  const handleViewModeChange = useCallback((mode: "grid" | "shelves") => {
    setViewMode(mode);
    localStorage.setItem("home-view-mode", mode);
  }, []);

  const handleStatusChange = useCallback((v: string) => updateParam("status", v), [updateParam]);
  const handleTypeChange = useCallback((v: string) => updateParam("type", v), [updateParam]);

  const handleShelfSeeAll = useCallback((shelfStatus: string) => {
    handleViewModeChange("grid");
    handleStatusChange(shelfStatus);
  }, [handleViewModeChange, handleStatusChange]);
  const handleSortChange = useCallback(
    (v: SortOption) => updateParam("sort", v === "title-asc" ? "" : v),
    [updateParam],
  );
  const handleSearchChange = useCallback((v: string) => setSearch(v), []);
  const handleMenuClose = useCallback(() => setMenuComic(null), []);
  const handleMenuEdit = useCallback((c: ComicSeries) => {
    setMenuComic(null);
    navigate(`/comic/${c.id}/edit`, { viewTransition: true });
  }, [navigate]);

  useEffect(() => {
    updateParam("search", debouncedSearch.trim());
  }, [debouncedSearch, updateParam]);

  const isMobile = useMediaQuery("(max-width: 639px)");
  const { data, isFetching, isLoading } = useComics();
  const deleteComic = useDeleteComic();
  const restoreComic = useRestoreComic();
  const queryClient = useQueryClient();
  const handleRefresh = useCallback(
    () =>
      Promise.all([
        queryClient.invalidateQueries({ queryKey: queryKeys.comics.all }),
        queryClient.invalidateQueries({ queryKey: queryKeys.comics.detailPrefix }),
      ]).then(() => undefined),
    [queryClient],
  );
  const { isRefreshing, pullDistance } = usePullToRefresh({ onRefresh: handleRefresh });
  const allComics = data?.member ?? [];

  const handleDelete = useCallback((c: ComicSeries) => {
    deleteComic.mutate({ id: c.id }, {
      onError: () => toast.error(`Erreur lors de la suppression de ${c.title}`),
      onSuccess: () => {
        toast.success(`${c.title} supprimée`, {
          action: {
            label: "Annuler",
            onClick: () => restoreComic.mutate({ id: c.id }),
          },
          duration: 5000,
        });
      },
    });
  }, [deleteComic, restoreComic]);

  const handleMenuDelete = useCallback((c: ComicSeries) => {
    setMenuComic(null);
    handleDelete(c);
  }, [handleDelete]);

  const filtered = useMemo(() => {
    const preFiltered = allComics.filter((c) => {
      if (type && c.type !== type) return false;
      if (status && c.status !== status) return false;
      return true;
    });
    return sortComics(searchComics(preFiltered, debouncedSearch), sort);
  }, [allComics, debouncedSearch, sort, status, type]);

  // Récemment ajoutés — triés par date de création décroissante
  const recentlyAdded = useMemo(() => {
    if (allComics.length === 0) return [];
    return [...allComics]
      .sort((a, b) => new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime())
      .slice(0, HERO_COUNT);
  }, [allComics]);

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

  const pullIndicatorHeight = isRefreshing ? 48 : Math.min(pullDistance, 80);
  const pullProgress = Math.min(pullDistance / 80, 1);

  // N'afficher la section hero que sur la vue par défaut (pas de filtre/recherche)
  const showHero = !isLoading && !debouncedSearch && !type && !status && recentlyAdded.length > 0;

  return (
    <div className={`space-y-4 transition-[filter] duration-300 ${isRefreshing ? "blur-[1px]" : ""}`}>
      {(pullDistance > 0 || isRefreshing) && (
        <div
          aria-label={isRefreshing ? "Actualisation en cours" : "Tirer pour actualiser"}
          className="flex items-center justify-center overflow-hidden transition-[height] duration-200"
          data-testid="pull-to-refresh-indicator"
          style={{ height: pullIndicatorHeight }}
        >
          <RefreshCw
            className={`h-5 w-5 text-primary-500 ${isRefreshing ? "animate-spin" : ""}`}
            style={{ opacity: pullProgress, transform: `rotate(${pullProgress * 360}deg)` }}
          />
        </div>
      )}

      <h1 className="font-display text-2xl font-bold text-text-primary">
        Ma bibliothèque
      </h1>

      {/* Hero — Récemment ajoutés */}
      {showHero && <HeroCarousel comics={recentlyAdded} />}

      {/* Séparateur visuel */}
      {showHero && (
        <div className="flex items-center gap-3">
          <hr className="flex-1 border-surface-border dark:border-white/5" />
          <span className="font-display text-sm font-semibold text-text-secondary">
            Toute la collection
          </span>
          <hr className="flex-1 border-surface-border dark:border-white/5" />
        </div>
      )}

      {/* Search bar + filter button (mobile) + count */}
      <div className="flex items-center gap-2">
        <SearchInput
          ariaLabel="Rechercher par titre, auteur, éditeur"
          onChange={handleSearchChange}
          placeholder="Rechercher par titre, auteur, éditeur…"
          value={search}
        />
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
        {/* Vue toggle — visible only on default view (no filters/search) */}
        {!debouncedSearch && !type && !status && (
          <div className="flex shrink-0 rounded-lg border border-surface-border p-0.5 dark:border-white/10">
            <button
              aria-label="Vue grille"
              aria-pressed={viewMode === "grid"}
              className={`rounded-md p-1.5 transition-colors ${viewMode === "grid" ? "bg-primary-100 text-primary-600 dark:bg-primary-950/50 dark:text-primary-400" : "text-text-muted hover:text-text-secondary"}`}
              onClick={() => handleViewModeChange("grid")}
              type="button"
            >
              <LayoutGrid className="h-4 w-4" />
            </button>
            <button
              aria-label="Vue étagères"
              aria-pressed={viewMode === "shelves"}
              className={`rounded-md p-1.5 transition-colors ${viewMode === "shelves" ? "bg-primary-100 text-primary-600 dark:bg-primary-950/50 dark:text-primary-400" : "text-text-muted hover:text-text-secondary"}`}
              onClick={() => handleViewModeChange("shelves")}
              type="button"
            >
              <Rows3 className="h-4 w-4" />
            </button>
          </div>
        )}
        <span className="flex shrink-0 items-center gap-1.5 font-mono-stats text-sm text-text-muted">
          {isFetching && !isLoading && (
            <Loader2 className="h-3.5 w-3.5 animate-spin" data-testid="search-loading" />
          )}
          {filtered.length}/{allComics.length}
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
        allComics.length === 0 ? (
          <EmptyState
            actionHref="/comic/new"
            actionLabel="Ajouter une série"
            description="Commencez par ajouter votre première série"
            icon={BookOpen}
            title="Votre bibliothèque est vide"
          />
        ) : debouncedSearch ? (
          <EmptyState
            icon={Search}
            title={`Aucun résultat pour « ${debouncedSearch} »`}
          />
        ) : status === "wishlist" ? (
          <EmptyState
            actionHref="/comic/new"
            actionLabel="Ajouter une série"
            description="Les séries que vous souhaitez acheter apparaîtront ici"
            icon={Heart}
            title="Votre liste de souhaits est vide"
          />
        ) : (
          <EmptyState
            actionLabel="Réinitialiser les filtres"
            icon={Filter}
            onAction={handleResetFilters}
            title="Aucune série avec ces filtres"
          />
        )
      ) : viewMode === "shelves" && !debouncedSearch && !type && !status ? (
        <ShelfView comics={filtered} onFilterByStatus={handleShelfSeeAll} />
      ) : (
        <ComponentErrorBoundary label="la grille">
          <VirtualGrid
            items={filtered}
            renderItem={(comic) => (
              <ComicCard comic={comic} onDelete={handleDelete} onMenuOpen={setMenuComic} />
            )}
            testId="comics-grid"
          />
        </ComponentErrorBoundary>
      )}

      <CardActionBar
        comic={menuComic}
        onClose={handleMenuClose}
        onDelete={handleMenuDelete}
        onEdit={handleMenuEdit}
      />

    </div>
  );
}
