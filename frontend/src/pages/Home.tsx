import { Search } from "lucide-react";
import { useMemo, useState } from "react";
import { toast } from "sonner";
import ComicCard from "../components/ComicCard";
import ComicCardSkeleton from "../components/ComicCardSkeleton";
import ConfirmModal from "../components/ConfirmModal";
import Filters from "../components/Filters";
import { useComics } from "../hooks/useComics";
import { useDeleteComic } from "../hooks/useDeleteComic";
import type { ComicSeries } from "../types/api";
import { searchComics } from "../utils/searchComics";
import { sortComics } from "../utils/sortComics";
import type { SortOption } from "../utils/sortComics";

export default function Home() {
  const [search, setSearch] = useState("");
  const [sort, setSort] = useState<SortOption>("title-asc");
  const [status, setStatus] = useState("");
  const [type, setType] = useState("");
  const [deleteTarget, setDeleteTarget] = useState<ComicSeries | null>(null);

  const { data, isLoading } = useComics();
  const deleteComic = useDeleteComic();
  const allComics = data?.member ?? [];

  const filtered = useMemo(() => {
    const preFiltered = allComics.filter((c) => {
      if (type && c.type !== type) return false;
      if (status && c.status !== status) return false;
      return true;
    });
    return sortComics(searchComics(preFiltered, search), sort);
  }, [allComics, search, sort, status, type]);

  return (
    <div className="space-y-4">
      {/* Search bar */}
      <div className="relative">
        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
        <input
          className="w-full rounded-lg border border-surface-border bg-surface-primary py-2 pl-10 pr-4 text-sm text-text-primary placeholder:text-text-muted focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
          onChange={(e) => setSearch(e.target.value)}
          placeholder="Rechercher par titre, auteur, éditeur…"
          type="search"
          value={search}
        />
      </div>

      {/* Filters + count */}
      <div className="flex items-center gap-3">
        <Filters
          onSortChange={setSort}
          onStatusChange={setStatus}
          onTypeChange={setType}
          sort={sort}
          status={status}
          type={type}
        />
        <span className="shrink-0 text-sm text-text-muted">
          {filtered.length}/{allComics.length}
        </span>
      </div>

      {isLoading ? (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
          {Array.from({ length: 8 }, (_, i) => (
            <ComicCardSkeleton key={i} />
          ))}
        </div>
      ) : filtered.length === 0 ? (
        <div className="py-12 text-center text-text-muted">Aucune série trouvée</div>
      ) : (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
          {filtered.map((comic) => (
            <ComicCard comic={comic} key={comic.id} onDelete={setDeleteTarget} />
          ))}
        </div>
      )}

      <ConfirmModal
        confirmLabel="Supprimer"
        description="Cette série sera déplacée vers la corbeille."
        onClose={() => setDeleteTarget(null)}
        onConfirm={() => {
          if (deleteTarget) {
            deleteComic.mutate({ id: deleteTarget.id }, {
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
