import { Loader2, Search } from "lucide-react";
import { useState } from "react";
import { useDebounce } from "../hooks/useDebounce";
import { useLookupTitleCandidates } from "../hooks/useLookup";
import type { LookupCandidate } from "../types/api";
import CoverImage from "./CoverImage";

interface QuickAddSearchProps {
  onAdd: (result: { coverUrl: string | null; title: string; tomeNumber: number }) => void;
}

export default function QuickAddSearch({ onAdd }: QuickAddSearchProps) {
  const [query, setQuery] = useState("");
  const debouncedQuery = useDebounce(query, 400);
  const { data, isFetching } = useLookupTitleCandidates(debouncedQuery);
  const results = data?.results ?? [];

  const handleSelect = (candidate: LookupCandidate) => {
    onAdd({
      coverUrl: candidate.thumbnail,
      title: candidate.title ?? query,
      tomeNumber: candidate.tomeNumber ?? 1,
    });
    setQuery("");
  };

  return (
    <div className="flex flex-1 flex-col gap-4 px-4">
      {/* Champ de recherche */}
      <div className="relative">
        <Search className="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-text-muted" />
        <input
          autoFocus
          className="w-full rounded-xl border border-surface-border bg-surface-primary py-3 pl-10 pr-4 text-sm text-text-primary placeholder-text-muted focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-white/10 dark:bg-surface-secondary"
          onChange={(e) => setQuery(e.target.value)}
          placeholder="Titre de la série…"
          type="text"
          value={query}
        />
        {isFetching && (
          <Loader2 className="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 animate-spin text-text-muted" />
        )}
      </div>

      {/* Résultats */}
      <div className="flex-1 space-y-2 overflow-y-auto">
        {results.map((candidate, i) => (
          <button
            className="flex w-full items-center gap-3 rounded-xl border border-surface-border bg-surface-primary p-3 text-left transition-transform active:scale-[0.98] dark:border-white/10 dark:bg-surface-secondary"
            key={`${candidate.title}-${candidate.tomeNumber}-${i}`}
            onClick={() => handleSelect(candidate)}
            type="button"
          >
            {candidate.thumbnail ? (
              <CoverImage
                alt={candidate.title ?? ""}
                className="h-16 w-12 shrink-0 rounded-lg"
                src={candidate.thumbnail}
              />
            ) : (
              <div className="flex h-16 w-12 shrink-0 items-center justify-center rounded-lg bg-surface-tertiary text-xs text-text-muted">
                ?
              </div>
            )}
            <div className="min-w-0 flex-1">
              <h4 className="truncate text-sm font-semibold text-text-primary">
                {candidate.title ?? "Sans titre"}
              </h4>
              {candidate.tomeNumber && (
                <p className="text-xs text-text-muted">Tome {candidate.tomeNumber}</p>
              )}
              {candidate.publisher && (
                <p className="text-xs text-text-muted">{candidate.publisher}</p>
              )}
            </div>
          </button>
        ))}
        {debouncedQuery.length >= 2 && !isFetching && results.length === 0 && (
          <p className="py-8 text-center text-sm text-text-muted">Aucun résultat</p>
        )}
      </div>
    </div>
  );
}
