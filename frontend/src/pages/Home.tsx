import { useQueryClient } from "@tanstack/react-query";
import {
  BookOpen,
  Filter,
  HardDrive,
  Heart,
  LayoutGrid,
  Loader2,
  RefreshCw,
  Rows3,
  Search,
  ShoppingCart,
} from "lucide-react";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { useNavigate, useSearchParams } from "react-router-dom";
import { toast } from "sonner";
import CardActionBar from "../components/CardActionBar";
import ComicCard from "../components/ComicCard";
import ComponentErrorBoundary from "../components/ComponentErrorBoundary";
import ComicCardSkeleton from "../components/ComicCardSkeleton";
import ContinueReading from "../components/ContinueReading";
import EmptyState from "../components/EmptyState";
import FilterChips from "../components/FilterChips";
import Filters from "../components/Filters";
import SearchInput from "../components/SearchInput";
import ShelfView from "../components/ShelfView";
import StickySearchBar from "../components/StickySearchBar";
import VirtualGrid from "../components/VirtualGrid";
import { useBuyTome } from "../hooks/useBuyTome";
import { useComics } from "../hooks/useComics";
import { useDebounce } from "../hooks/useDebounce";
import { useDeleteComic } from "../hooks/useDeleteComic";
import { useMediaQuery } from "../hooks/useMediaQuery";
import { usePullToRefresh } from "../hooks/usePullToRefresh";
import { useScrollReveal } from "../hooks/useScrollReveal";
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

export default function Home() {
  const [searchParams, setSearchParams] = useSearchParams();
  const status = searchParams.get("status") ?? "";
  const type = searchParams.get("type") ?? "";
  const sortParam = searchParams.get("sort") ?? "";
  const sort: SortOption = VALID_SORTS.has(sortParam)
    ? (sortParam as SortOption)
    : "title-asc";
  const searchParam = searchParams.get("search") ?? "";

  const navigate = useNavigate();
  const [search, setSearch] = useState(searchParam);
  const debouncedSearch = useDebounce(search, 300);
  const [menuComic, setMenuComic] = useState<ComicSeries | null>(null);
  const [viewMode, setViewMode] = useState<"grid" | "shelves">(() => {
    return (
      (localStorage.getItem("home-view-mode") as "grid" | "shelves") ?? "grid"
    );
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

  const handleStatusChange = useCallback(
    (v: string) => updateParam("status", v),
    [updateParam],
  );
  const handleTypeChange = useCallback(
    (v: string) => updateParam("type", v),
    [updateParam],
  );

  const handleShelfSeeAll = useCallback(
    (shelfStatus: string) => {
      handleViewModeChange("grid");
      handleStatusChange(shelfStatus);
    },
    [handleViewModeChange, handleStatusChange],
  );
  const handleSortChange = useCallback(
    (v: SortOption) => updateParam("sort", v === "title-asc" ? "" : v),
    [updateParam],
  );
  const handleSearchChange = useCallback((v: string) => setSearch(v), []);
  const handleMenuClose = useCallback(() => setMenuComic(null), []);
  const handleMenuEdit = useCallback(
    (c: ComicSeries) => {
      setMenuComic(null);
      navigate(`/comic/${c.id}/edit`, { viewTransition: true });
    },
    [navigate],
  );

  useEffect(() => {
    updateParam("search", debouncedSearch.trim());
  }, [debouncedSearch, updateParam]);

  const searchSentinelRef = useRef<HTMLDivElement>(null);
  const { showStickyBar } = useScrollReveal({
    sentinelRef: searchSentinelRef,
  });
  const isMobile = useMediaQuery("(max-width: 639px)");
  const { data, isFetching, isLoading } = useComics();
  const deleteComic = useDeleteComic();
  const restoreComic = useRestoreComic();
  const queryClient = useQueryClient();
  const handleRefresh = useCallback(
    () =>
      Promise.all([
        queryClient.invalidateQueries({ queryKey: queryKeys.comics.all }),
        queryClient.invalidateQueries({
          queryKey: queryKeys.comics.detailPrefix,
        }),
      ]).then(() => undefined),
    [queryClient],
  );
  const { isRefreshing, pullDistance } = usePullToRefresh({
    onRefresh: handleRefresh,
  });
  const allComics = data?.member ?? [];

  const handleDelete = useCallback(
    (c: ComicSeries) => {
      deleteComic.mutate(
        { id: c.id },
        {
          onError: () =>
            toast.error(`Erreur lors de la suppression de ${c.title}`),
          onSuccess: () => {
            toast.success(`${c.title} supprimée`, {
              action: {
                label: "Annuler",
                onClick: () => restoreComic.mutate({ id: c.id }),
              },
              duration: 5000,
            });
          },
        },
      );
    },
    [deleteComic, restoreComic],
  );

  const handleMenuDelete = useCallback(
    (c: ComicSeries) => {
      setMenuComic(null);
      handleDelete(c);
    },
    [handleDelete],
  );

  const modeParam = searchParams.get("mode") ?? "all";
  const mode: "all" | "tobuy" | "todownload" =
    modeParam === "tobuy" || modeParam === "todownload" ? modeParam : "all";

  const handleModeChange = useCallback(
    (m: "all" | "tobuy" | "todownload") =>
      updateParam("mode", m === "all" ? "" : m),
    [updateParam],
  );

  const buyTome = useBuyTome();
  const handleBuyTome = useCallback(
    (seriesId: number, tomeId: number) => {
      buyTome.mutate(
        { seriesId, tomeId },
        {
          onError: () => toast.error("Erreur lors de la mise à jour du tome"),
          onSuccess: () => toast.success("Tome marqué comme acheté"),
        },
      );
    },
    [buyTome],
  );

  const filtered = useMemo(() => {
    const preFiltered = allComics.filter((c) => {
      if (type && c.type !== type) return false;
      if (status && c.status !== status) return false;
      if (mode === "tobuy") {
        if (
          c.isOneShot ||
          c.notInterestedBuy ||
          (c.unboughtTomes?.length ?? 0) === 0
        ) {
          return false;
        }
      } else if (mode === "todownload") {
        const total = Math.max(c.latestPublishedIssue ?? 0, c.coveredCount);
        if (c.isOneShot || c.notInterestedNas || total - c.onNasCount <= 0) {
          return false;
        }
      }
      return true;
    });
    return sortComics(searchComics(preFiltered, debouncedSearch), sort);
  }, [allComics, debouncedSearch, mode, sort, status, type]);

  const toBuyCount = useMemo(() => {
    return allComics.filter(
      (c) =>
        !c.isOneShot &&
        !c.notInterestedBuy &&
        (c.unboughtTomes?.length ?? 0) > 0,
    ).length;
  }, [allComics]);

  const toDownloadCount = useMemo(() => {
    return allComics.filter((c) => {
      const total = Math.max(c.latestPublishedIssue ?? 0, c.coveredCount);
      return !c.isOneShot && !c.notInterestedNas && total - c.onNasCount > 0;
    }).length;
  }, [allComics]);

  const handleResetFilters = useCallback(() => {
    setSearchParams(
      (prev) => {
        const next = new URLSearchParams(prev);
        next.delete("type");
        next.delete("status");
        next.delete("sort");
        next.delete("mode");
        return next;
      },
      { replace: true },
    );
  }, [setSearchParams]);

  const pullIndicatorHeight = isRefreshing ? 48 : Math.min(pullDistance, 80);
  const pullProgress = Math.min(pullDistance / 80, 1);

  // N'afficher la section « Continuer la lecture » que sur la vue par défaut (pas de filtre/recherche)
  const showContinueReading =
    !isLoading && !debouncedSearch && !type && !status && mode === "all";

  return (
    <div
      className={`space-y-4 transition-[filter] duration-300 ${isRefreshing ? "blur-[1px]" : ""}`}
    >
      {(pullDistance > 0 || isRefreshing) && (
        <div
          aria-label={
            isRefreshing ? "Actualisation en cours" : "Tirer pour actualiser"
          }
          className="flex items-center justify-center overflow-hidden transition-[height] duration-200"
          data-testid="pull-to-refresh-indicator"
          style={{ height: pullIndicatorHeight }}
        >
          <RefreshCw
            className={`h-5 w-5 text-primary-500 ${isRefreshing ? "animate-spin" : ""}`}
            style={{
              opacity: pullProgress,
              transform: `rotate(${pullProgress * 360}deg)`,
            }}
          />
        </div>
      )}

      {/* Continuer la lecture */}
      {showContinueReading && <ContinueReading comics={allComics} />}

      {/* Sentinel pour détecter quand la barre de recherche sort du viewport */}
      <div ref={searchSentinelRef} aria-hidden="true" />

      {/* Search bar + filter button (mobile) + count */}
      <div className="flex items-center gap-2">
        <SearchInput
          ariaLabel="Rechercher par titre, auteur, éditeur"
          autoFocus
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
            <Loader2
              className="h-3.5 w-3.5 animate-spin"
              data-testid="search-loading"
            />
          )}
          {filtered.length}/{allComics.length}
        </span>
      </div>

      {/* Operational Quick Filter Tabs: Toutes / À acheter / À télécharger */}
      <div className="flex items-center gap-2 overflow-x-auto py-0.5 scrollbar-none">
        <button
          className={`flex shrink-0 items-center gap-1.5 rounded-xl px-3.5 py-1.5 text-xs font-bold shadow-xs transition ${
            mode === "all"
              ? "bg-neutral-900 text-white dark:bg-white dark:text-neutral-950"
              : "border border-surface-border bg-surface-primary text-text-secondary hover:text-amber-600 dark:bg-surface-elevated dark:hover:text-amber-400"
          }`}
          onClick={() => handleModeChange("all")}
          type="button"
        >
          <BookOpen className="h-3.5 w-3.5" />
          <span>Toutes</span>
        </button>

        <button
          className={`flex shrink-0 items-center gap-1.5 rounded-xl px-3.5 py-1.5 text-xs font-bold shadow-xs transition ${
            mode === "tobuy"
              ? "bg-neutral-900 text-white dark:bg-white dark:text-neutral-950"
              : "border border-surface-border bg-surface-primary text-text-secondary hover:text-amber-600 dark:bg-surface-elevated dark:hover:text-amber-400"
          }`}
          onClick={() => handleModeChange("tobuy")}
          type="button"
        >
          <ShoppingCart className="h-3.5 w-3.5 text-amber-500" />
          <span>À acheter</span>
          {toBuyCount > 0 && (
            <span className="rounded bg-amber-500/15 px-1.5 py-0.2 font-mono text-[10px] text-amber-600 dark:text-amber-400">
              {toBuyCount}
            </span>
          )}
        </button>

        <button
          className={`flex shrink-0 items-center gap-1.5 rounded-xl px-3.5 py-1.5 text-xs font-bold shadow-xs transition ${
            mode === "todownload"
              ? "bg-neutral-900 text-white dark:bg-white dark:text-neutral-950"
              : "border border-surface-border bg-surface-primary text-text-secondary hover:text-amber-600 dark:bg-surface-elevated dark:hover:text-amber-400"
          }`}
          onClick={() => handleModeChange("todownload")}
          type="button"
        >
          <HardDrive className="h-3.5 w-3.5 text-blue-500" />
          <span>À télécharger</span>
          {toDownloadCount > 0 && (
            <span className="rounded bg-blue-500/15 px-1.5 py-0.2 font-mono text-[10px] text-blue-600 dark:text-blue-400">
              {toDownloadCount}
            </span>
          )}
        </button>
      </div>

      {/* Mode active banner */}
      {mode === "tobuy" && (
        <div className="flex items-center gap-2 rounded-xl border border-amber-500/30 bg-amber-500/10 px-3.5 py-2 text-xs font-semibold text-amber-800 dark:text-amber-300">
          <ShoppingCart className="h-4 w-4 shrink-0 text-amber-500" />
          <span>
            Mode « À acheter » actif : séries suivies pour achat avec tomes manquants
          </span>
        </div>
      )}

      {mode === "todownload" && (
        <div className="flex items-center gap-2 rounded-xl border border-blue-500/30 bg-blue-500/10 px-3.5 py-2 text-xs font-semibold text-blue-800 dark:text-blue-300">
          <HardDrive className="h-4 w-4 shrink-0 text-blue-500" />
          <span>
            Mode « À télécharger » actif : séries suivies sur le NAS avec tomes manquants
          </span>
        </div>
      )}

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
      ) : viewMode === "shelves" && !debouncedSearch && !type && !status && mode === "all" ? (
        <ShelfView comics={filtered} onFilterByStatus={handleShelfSeeAll} />
      ) : (
        <ComponentErrorBoundary label="la grille">
          <VirtualGrid
            items={filtered}
            renderItem={(comic) => (
              <ComicCard
                acquisitionMode={mode}
                comic={comic}
                onBuyTome={handleBuyTome}
                onDelete={handleDelete}
                onMenuOpen={setMenuComic}
              />
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

      <StickySearchBar
        filteredCount={filtered.length}
        isFetching={isFetching}
        isLoading={isLoading}
        onSearchChange={handleSearchChange}
        onSortChange={handleSortChange}
        onStatusChange={handleStatusChange}
        onTypeChange={handleTypeChange}
        search={search}
        sort={sort}
        status={status}
        totalCount={allComics.length}
        type={type}
        visible={showStickyBar}
      />
    </div>
  );
}
