import { Search } from "lucide-react";
import { useMemo, useState } from "react";
import ComicCard from "../components/ComicCard";
import Filters from "../components/Filters";
import { useComics } from "../hooks/useComics";
import { ComicStatus } from "../types/enums";

export default function Wishlist() {
  const [search, setSearch] = useState("");
  const [type, setType] = useState("");

  const { data, isLoading } = useComics();
  const allWishlist = useMemo(
    () => (data?.member ?? []).filter((c) => c.status === ComicStatus.WISHLIST),
    [data],
  );

  const filtered = useMemo(() => {
    const q = search.toLowerCase().trim();
    return allWishlist.filter((c) => {
      if (type && c.type !== type) return false;
      if (q && !c.title.toLowerCase().includes(q)) return false;
      return true;
    });
  }, [allWishlist, search, type]);

  return (
    <div className="space-y-4">
      <h1 className="text-xl font-bold text-text-primary">Liste de souhaits</h1>

      <div className="relative">
        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
        <input
          className="w-full rounded-lg border border-surface-border bg-surface-primary py-2 pl-10 pr-4 text-sm text-text-primary placeholder:text-text-muted focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
          onChange={(e) => setSearch(e.target.value)}
          placeholder="Rechercher…"
          type="search"
          value={search}
        />
      </div>

      <div className="flex items-center gap-3">
        <Filters
          hideStatus
          onStatusChange={() => {}}
          onTypeChange={setType}
          status=""
          type={type}
        />
        <span className="shrink-0 text-sm text-text-muted">
          {filtered.length} souhait{filtered.length !== 1 ? "s" : ""}
        </span>
      </div>

      {isLoading ? (
        <div className="py-12 text-center text-text-muted">Chargement…</div>
      ) : filtered.length === 0 ? (
        <div className="py-12 text-center text-text-muted">Aucun souhait pour le moment</div>
      ) : (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
          {filtered.map((comic) => (
            <ComicCard comic={comic} key={comic.id} />
          ))}
        </div>
      )}
    </div>
  );
}
