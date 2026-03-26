import { Loader2, Search } from "lucide-react";
import { useState } from "react";
import { useDebounce } from "../hooks/useDebounce";
import { useLookupTitleCandidates } from "../hooks/useLookup";
import type { LookupCandidate } from "../types/api";
import type { ComicType } from "../types/enums";
import { typeOptions } from "../types/enums";
import CoverImage from "./CoverImage";

interface QuickAddSearchProps {
  onAdd: (result: { coverUrl: string | null; title: string; tomeNumber: number }) => void;
  onQueryChange?: (query: string) => void;
}

export default function QuickAddSearch({ onAdd, onQueryChange }: QuickAddSearchProps) {
  const [query, setQuery] = useState("");

  const handleQueryChange = (value: string) => {
    setQuery(value);
    onQueryChange?.(value);
  };
  const [type, setType] = useState<ComicType>("bd");
  const debouncedQuery = useDebounce(query, 400);
  const { data, isFetching } = useLookupTitleCandidates(debouncedQuery, type);
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
    <div className="flex flex-1 flex-col px-4">
      {/* Résultats — prennent l'espace restant, scrollent vers le haut */}
      <div className="flex min-h-0 flex-1 flex-col-reverse overflow-y-auto">
        <div className="space-y-2">
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

      {/* Type + Champ de recherche — en bas, proche des doigts */}
      <div className="shrink-0 space-y-2 pb-4 pt-3">
        {/* Type chips */}
        <div className="flex gap-2">
          {typeOptions.map((opt) => (
            <button
              className={`rounded-full px-3 py-1 text-xs font-medium transition-colors ${
                type === opt.value
                  ? "bg-primary-600 text-white dark:bg-primary-500"
                  : "bg-surface-tertiary text-text-muted hover:text-text-secondary"
              }`}
              key={opt.value}
              onClick={() => setType(opt.value as ComicType)}
              type="button"
            >
              {opt.label}
            </button>
          ))}
        </div>
      </div>
      <div className="shrink-0 pb-4">
        <div className="relative">
          <Search className="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-text-muted" />
          <input
            autoFocus
            className="w-full rounded-xl border border-surface-border bg-surface-primary py-3 pl-10 pr-4 text-sm text-text-primary placeholder-text-muted focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-white/10 dark:bg-surface-secondary"
            onChange={(e) => handleQueryChange(e.target.value)}
            placeholder="Titre de la série…"
            type="text"
            value={query}
          />
          {isFetching && (
            <Loader2 className="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 animate-spin text-text-muted" />
          )}
        </div>
      </div>
    </div>
  );
}
