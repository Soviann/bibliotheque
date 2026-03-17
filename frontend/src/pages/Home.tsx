import { BookOpen, Filter, Heart, Loader2, Search } from "lucide-react";
import { useCallback, useEffect, useMemo, useState } from "react";
import { useNavigate, useSearchParams } from "react-router-dom";
import { toast } from "sonner";
import CardActionBar from "../components/CardActionBar";
import ComicCard from "../components/ComicCard";
import ComicCardSkeleton from "../components/ComicCardSkeleton";
import ConfirmModal from "../components/ConfirmModal";
import EmptyState from "../components/EmptyState";
import Filters from "../components/Filters";
import VirtualGrid from "../components/VirtualGrid";
import { useComics } from "../hooks/useComics";
import { useDebounce } from "../hooks/useDebounce";
import { useDeleteComic } from "../hooks/useDeleteComic";
import { useMediaQuery } from "../hooks/useMediaQuery";
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
  const sort: SortOption = VALID_SORTS.has(sortParam) ? (sortParam as SortOption) : "title-asc";
  const searchParam = searchParams.get("search") ?? "";

  const navigate = useNavigate();
  const [search, setSearch] = useState(searchParam);
  const debouncedSearch = useDebounce(search, 300);
  const [deleteTarget, setDeleteTarget] = useState<ComicSeries | null>(null);
  const [menuComic, setMenuComic] = useState<ComicSeries | null>(null);

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

  const handleStatusChange = useCallback((v: string) => updateParam("status", v), [updateParam]);
  const handleTypeChange = useCallback((v: string) => updateParam("type", v), [updateParam]);
  const handleSortChange = useCallback(
    (v: SortOption) => updateParam("sort", v === "title-asc" ? "" : v),
    [updateParam],
  );
  const handleSearchChange = useCallback((v: string) => setSearch(v), []);
  const handleMenuClose = useCallback(() => setMenuComic(null), []);
  const handleMenuDelete = useCallback((c: ComicSeries) => {
    setMenuComic(null);
    setDeleteTarget(c);
  }, []);
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
  const allComics = data?.member ?? [];

  const filtered = useMemo(() => {
    const preFiltered = allComics.filter((c) => {
      if (type && c.type !== type) return false;
      if (status && c.status !== status) return false;
      return true;
    });
    return sortComics(searchComics(preFiltered, debouncedSearch), sort);
  }, [allComics, debouncedSearch, sort, status, type]);

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

  return (
    <div className="space-y-4">
      <h1 className="text-xl font-bold text-text-primary">Ma bibliothèque</h1>
      {/* Search bar + filter button (mobile) + count */}
      <div className="flex items-center gap-2">
        <div className="relative min-w-0 flex-1">
          <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
          <input
            aria-label="Rechercher par titre, auteur, éditeur"
            className="w-full rounded-lg border border-surface-border bg-surface-primary py-2 pl-10 pr-4 text-sm text-text-primary placeholder:text-text-muted focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
            onChange={(e) => handleSearchChange(e.target.value)}
            placeholder="Rechercher par titre, auteur, éditeur…"
            type="search"
            value={search}
          />
        </div>
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
        <span className="flex shrink-0 items-center gap-1.5 text-sm text-text-muted">
          {isFetching && !isLoading && (
            <Loader2 className="h-3.5 w-3.5 animate-spin" data-testid="search-loading" />
          )}
          {filtered.length}/{allComics.length}
        </span>
      </div>

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
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
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
      ) : (
        <VirtualGrid
          items={filtered}
          renderItem={(comic) => (
            <ComicCard comic={comic} onDelete={setDeleteTarget} onMenuOpen={setMenuComic} />
          )}
          testId="comics-grid"
        />
      )}

      <CardActionBar
        comic={menuComic}
        onClose={handleMenuClose}
        onDelete={handleMenuDelete}
        onEdit={handleMenuEdit}
      />

      <ConfirmModal
        confirmLabel="Supprimer"
        description="Cette série sera déplacée vers la corbeille."
        onClose={() => setDeleteTarget(null)}
        onConfirm={() => {
          if (deleteTarget) {
            deleteComic.mutate({ id: deleteTarget.id }, {
              onError: () => toast.error(`Erreur lors de la suppression de ${deleteTarget.title}`),
              onSuccess: () => toast.success(`${deleteTarget.title} supprimée`),
            });
          }
        }}
        open={deleteTarget !== null}
        title={`Supprimer ${deleteTarget?.title} ?`}
      />
    </div>
  );
}
