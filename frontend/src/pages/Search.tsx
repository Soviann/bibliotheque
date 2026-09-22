import {
  Check,
  ChevronDown,
  Clock,
  ScanBarcode,
  Search as SearchIcon,
  SlidersHorizontal,
  X,
  XCircle,
} from "lucide-react";
import { useCallback, useEffect, useMemo, useState } from "react";
import { Link, useSearchParams } from "react-router-dom";
import CoverImage from "../components/CoverImage";
import { useComics } from "../hooks/useComics";
import { useDebounce } from "../hooks/useDebounce";
import { ComicType, ComicTypeLabel, ComicTypePlaceholder } from "../types/enums";
import { getCoverSrc, getCoverThumbnailSrc } from "../utils/coverUtils";
import { searchComics } from "../utils/searchComics";

type Scope = "title" | "author" | "publisher" | "isbn";
type Availability = "all" | "tobuy" | "todownload" | "notBuy" | "notNas";

const RECENT_SEARCHES_KEY = "biblio-recent-searches";
const MAX_RECENT = 6;

export default function Search() {
  const [searchParams, setSearchParams] = useSearchParams();
  const initialQuery = searchParams.get("q") ?? "";
  const [query, setQuery] = useState(initialQuery);
  const debouncedQuery = useDebounce(query, 250);

  const [selectedType, setSelectedType] = useState<string>(
    searchParams.get("type") ?? "",
  );
  const [scopes, setScopes] = useState<Set<Scope>>(new Set());
  const [availability, setAvailability] = useState<Availability>("all");

  const [recentSearches, setRecentSearches] = useState<string[]>(() => {
    try {
      const saved = localStorage.getItem(RECENT_SEARCHES_KEY);
      return saved ? JSON.parse(saved) : ["Kentarō Miura", "Glénat", "Tomes manquants"];
    } catch {
      return [];
    }
  });

  const { data, isLoading } = useComics();
  const allComics = data?.member ?? [];

  // Synchronise query in URL
  useEffect(() => {
    setSearchParams(
      (prev) => {
        const next = new URLSearchParams(prev);
        if (debouncedQuery.trim()) {
          next.set("q", debouncedQuery.trim());
        } else {
          next.delete("q");
        }
        if (selectedType) {
          next.set("type", selectedType);
        } else {
          next.delete("type");
        }
        return next;
      },
      { replace: true },
    );
  }, [debouncedQuery, selectedType, setSearchParams]);

  // Save to recent searches when query is submitted / debounced
  useEffect(() => {
    const trimmed = debouncedQuery.trim();
    if (trimmed.length >= 3) {
      setRecentSearches((prev) => {
        const next = [trimmed, ...prev.filter((item) => item !== trimmed)].slice(
          0,
          MAX_RECENT,
        );
        try {
          localStorage.setItem(RECENT_SEARCHES_KEY, JSON.stringify(next));
        } catch {
          // ignore
        }
        return next;
      });
    }
  }, [debouncedQuery]);

  const removeRecentSearch = useCallback((item: string) => {
    setRecentSearches((prev) => {
      const next = prev.filter((s) => s !== item);
      try {
        localStorage.setItem(RECENT_SEARCHES_KEY, JSON.stringify(next));
      } catch {
        // ignore
      }
      return next;
    });
  }, []);

  const clearRecentSearches = useCallback(() => {
    setRecentSearches([]);
    localStorage.removeItem(RECENT_SEARCHES_KEY);
  }, []);

  const toggleScope = useCallback((scope: Scope) => {
    setScopes((prev) => {
      const next = new Set(prev);
      if (next.has(scope)) {
        next.delete(scope);
      } else {
        next.add(scope);
      }
      return next;
    });
  }, []);

  const resetFilters = useCallback(() => {
    setScopes(new Set());
    setAvailability("all");
    setSelectedType("");
  }, []);

  // Filtered results
  const results = useMemo(() => {
    let list = allComics;

    // Type filter
    if (selectedType) {
      list = list.filter((c) => c.type === selectedType);
    }

    // Availability filter
    if (availability === "tobuy") {
      list = list.filter(
        (c) =>
          !c.isOneShot &&
          !c.notInterestedBuy &&
          (c.unboughtTomes?.length ?? 0) > 0,
      );
    } else if (availability === "todownload") {
      list = list.filter((c) => {
        const total = Math.max(c.latestPublishedIssue ?? 0, c.coveredCount);
        return !c.isOneShot && !c.notInterestedNas && total - c.onNasCount > 0;
      });
    } else if (availability === "notBuy") {
      list = list.filter((c) => c.notInterestedBuy);
    } else if (availability === "notNas") {
      list = list.filter((c) => c.notInterestedNas);
    }

    const q = debouncedQuery.trim().toLowerCase();
    if (!q) return list;

    // Scope-specific search if any scope is active
    if (scopes.size > 0) {
      const cleanIsbn = q.replace(/[-\s]/g, "");
      return list.filter((comic) => {
        if (scopes.has("title") && comic.title.toLowerCase().includes(q)) {
          return true;
        }
        if (
          scopes.has("author") &&
          comic.authors.some((a) => a.name.toLowerCase().includes(q))
        ) {
          return true;
        }
        if (
          scopes.has("publisher") &&
          comic.publisher?.toLowerCase().includes(q)
        ) {
          return true;
        }
        if (
          scopes.has("isbn") &&
          comic.tomes?.some(
            (t) => t.isbn && t.isbn.replace(/[-\s]/g, "").includes(cleanIsbn),
          )
        ) {
          return true;
        }
        return false;
      });
    }

    // Default: fuzzy search across title, author, publisher
    return searchComics(list, q);
  }, [allComics, availability, debouncedQuery, scopes, selectedType]);

  const activeFiltersCount =
    (selectedType ? 1 : 0) + scopes.size + (availability !== "all" ? 1 : 0);

  return (
    <div className="mx-auto max-w-3xl space-y-4">
      {/* Header */}
      <div className="space-y-3">
        <div className="flex items-center justify-between">
          <h1 className="font-serif text-xl font-bold tracking-tight text-text-primary">
            Recherche Avancée
          </h1>
          <Link
            className="flex h-9 w-9 items-center justify-center rounded-full border border-surface-border bg-surface-primary text-text-secondary shadow-xs transition hover:text-amber-600 dark:bg-surface-secondary dark:hover:text-amber-400"
            title="Scanner code-barres"
            to="/quick-add"
            viewTransition
          >
            <ScanBarcode className="h-4 w-4" />
          </Link>
        </div>

        {/* Search input with clear button */}
        <div className="relative">
          <SearchIcon className="absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-amber-500" />
          <input
            aria-label="Rechercher"
            autoFocus
            className="w-full rounded-2xl border border-amber-500/40 bg-surface-primary py-3 pr-10 pl-10 text-xs font-semibold text-text-primary placeholder:text-text-muted shadow-xs focus:ring-2 focus:ring-amber-500/20 focus:outline-none dark:bg-surface-secondary"
            onChange={(e) => setQuery(e.target.value)}
            placeholder="Rechercher par titre, auteur, éditeur, ISBN…"
            type="text"
            value={query}
          />
          {query && (
            <button
              aria-label="Effacer la recherche"
              className="absolute top-1/2 right-3.5 -translate-y-1/2 text-text-muted hover:text-text-primary"
              onClick={() => setQuery("")}
              type="button"
            >
              <XCircle className="h-4 w-4" />
            </button>
          )}
        </div>

        {/* Type pills */}
        <div className="flex items-center gap-2 overflow-x-auto py-0.5 scrollbar-none">
          <button
            className={`shrink-0 rounded-full px-3 py-1 text-xs font-bold shadow-xs transition ${
              !selectedType
                ? "bg-neutral-900 text-white dark:bg-white dark:text-neutral-950"
                : "border border-surface-border bg-surface-primary text-text-secondary hover:text-amber-600 dark:bg-surface-elevated dark:hover:text-amber-400"
            }`}
            onClick={() => setSelectedType("")}
            type="button"
          >
            Tous
          </button>
          {[
            { label: "Mangas", value: ComicType.MANGA },
            { label: "BD", value: ComicType.BD },
            { label: "Comics", value: ComicType.COMICS },
            { label: "Livres", value: ComicType.LIVRE },
          ].map(({ label, value }) => (
            <button
              key={value}
              className={`shrink-0 rounded-full px-3 py-1 text-xs font-medium shadow-xs transition ${
                selectedType === value
                  ? "bg-neutral-900 text-white dark:bg-white dark:text-neutral-950 font-bold"
                  : "border border-surface-border bg-surface-primary text-text-secondary hover:text-amber-600 dark:bg-surface-elevated dark:hover:text-amber-400"
              }`}
              onClick={() =>
                setSelectedType(selectedType === value ? "" : value)
              }
              type="button"
            >
              {label}
            </button>
          ))}
        </div>
      </div>

      {/* Foldable Advanced Filters */}
      <details className="group rounded-2xl border border-surface-border bg-surface-primary p-3.5 shadow-xs transition-all duration-300 dark:bg-surface-secondary">
        <summary className="flex cursor-pointer list-none items-center justify-between text-xs font-bold text-text-primary">
          <div className="flex items-center gap-2">
            <SlidersHorizontal className="h-4 w-4 text-amber-500" />
            <span>Filtres multicritères avancés</span>
            {activeFiltersCount > 0 && (
              <span className="rounded-full bg-amber-500/20 px-1.5 py-0.2 font-bold text-[10px] text-amber-700 dark:text-amber-400">
                {activeFiltersCount} actif{activeFiltersCount > 1 ? "s" : ""}
              </span>
            )}
          </div>
          <ChevronDown className="h-4 w-4 text-text-muted transition-transform duration-300 group-open:rotate-180" />
        </summary>

        <div className="mt-3 space-y-3.5 border-t border-surface-border pt-3.5 text-xs">
          {/* Scope Target */}
          <div>
            <label className="mb-1.5 block font-semibold text-text-secondary">
              Rechercher précisément dans :
            </label>
            <div className="grid grid-cols-2 gap-2">
              {[
                { id: "title" as Scope, label: "Titre de série" },
                { id: "author" as Scope, label: "Auteur / Dessinateur" },
                { id: "publisher" as Scope, label: "Éditeur" },
                { id: "isbn" as Scope, label: "Numéro ISBN / EAN" },
              ].map(({ id, label }) => {
                const active = scopes.has(id);
                return (
                  <button
                    key={id}
                    className={`flex items-center justify-between rounded-xl px-3 py-2 text-left transition ${
                      active
                        ? "border border-amber-500/40 bg-amber-500/20 font-semibold text-amber-700 dark:text-amber-300"
                        : "bg-surface-elevated text-text-secondary hover:text-text-primary"
                    }`}
                    onClick={() => toggleScope(id)}
                    type="button"
                  >
                    <span>{label}</span>
                    {active && <Check className="h-3.5 w-3.5" />}
                  </button>
                );
              })}
            </div>
          </div>

          {/* Availability */}
          <div>
            <label className="mb-1.5 block font-semibold text-text-secondary">
              Suivi d'acquisition & disponibilité :
            </label>
            <div className="grid grid-cols-2 gap-2 text-xs">
              {[
                {
                  activeClass:
                    "bg-amber-500/20 border-amber-500/40 text-amber-700 dark:text-amber-300",
                  id: "tobuy" as Availability,
                  label: "À acheter (suivie + manquants)",
                },
                {
                  activeClass:
                    "bg-blue-500/20 border-blue-500/40 text-blue-700 dark:text-blue-300",
                  id: "todownload" as Availability,
                  label: "À télécharger (NAS + manquants)",
                },
                {
                  activeClass:
                    "bg-neutral-500/20 border-neutral-500/40 text-text-primary",
                  id: "notBuy" as Availability,
                  label: "Non suivie pour achat (✕)",
                },
                {
                  activeClass:
                    "bg-neutral-500/20 border-neutral-500/40 text-text-primary",
                  id: "notNas" as Availability,
                  label: "Non suivie sur NAS (✕)",
                },
              ].map(({ activeClass, id, label }) => {
                const active = availability === id;
                return (
                  <button
                    key={id}
                    className={`flex items-center justify-between rounded-xl px-3 py-2 text-left transition ${
                      active
                        ? `border font-semibold ${activeClass}`
                        : "bg-surface-elevated text-text-secondary hover:text-text-primary"
                    }`}
                    onClick={() =>
                      setAvailability(availability === id ? "all" : id)
                    }
                    type="button"
                  >
                    <span>{label}</span>
                    {active && <Check className="h-3.5 w-3.5" />}
                  </button>
                );
              })}
            </div>
          </div>

          <div className="flex gap-2 pt-1">
            <button
              className="rounded-xl bg-surface-elevated px-3 py-2 font-semibold text-text-secondary hover:text-text-primary"
              onClick={resetFilters}
              type="button"
            >
              Effacer les filtres
            </button>
          </div>
        </div>
      </details>

      {/* Recent searches */}
      {!debouncedQuery && recentSearches.length > 0 && (
        <div className="space-y-2">
          <div className="flex items-center justify-between text-xs text-text-secondary">
            <span className="flex items-center gap-1.5 font-semibold">
              <Clock className="h-3.5 w-3.5 text-text-muted" />
              Recherches récentes
            </span>
            <button
              className="font-bold text-amber-600 hover:underline dark:text-amber-400"
              onClick={clearRecentSearches}
              type="button"
            >
              Effacer
            </button>
          </div>
          <div className="flex flex-wrap gap-1.5">
            {recentSearches.map((item) => (
              <span
                key={item}
                className="inline-flex items-center gap-1.5 rounded-lg border border-surface-border bg-surface-primary px-2.5 py-1 text-xs font-medium text-text-primary shadow-xs dark:bg-surface-secondary"
              >
                <button
                  className="hover:text-amber-600 dark:hover:text-amber-400"
                  onClick={() => setQuery(item)}
                  type="button"
                >
                  {item}
                </button>
                <button
                  aria-label={`Supprimer ${item}`}
                  className="text-text-muted hover:text-text-primary"
                  onClick={() => removeRecentSearch(item)}
                  type="button"
                >
                  <X className="h-3 w-3" />
                </button>
              </span>
            ))}
          </div>
        </div>
      )}

      {/* Results Header */}
      <div className="flex items-center justify-between pt-1 text-xs">
        <span className="font-bold text-text-primary">
          {debouncedQuery
            ? `Résultats pour « ${debouncedQuery} »`
            : "Tous les résultats"}
        </span>
        <span className="font-mono-stats text-text-muted">
          {results.length} série{results.length > 1 ? "s" : ""} trouvée
          {results.length > 1 ? "s" : ""}
        </span>
      </div>

      {/* Results List */}
      {isLoading ? (
        <div className="space-y-3">
          {Array.from({ length: 4 }, (_, i) => (
            <div
              key={i}
              className="h-24 animate-pulse rounded-2xl border border-surface-border bg-surface-primary dark:bg-surface-secondary"
            />
          ))}
        </div>
      ) : results.length === 0 ? (
        <div className="rounded-2xl border border-surface-border bg-surface-primary p-8 text-center text-text-muted dark:bg-surface-secondary">
          <p className="text-sm font-semibold text-text-primary">
            Aucun résultat trouvé
          </p>
          <p className="mt-1 text-xs text-text-secondary">
            Essayez de modifier votre recherche ou de réinitialiser les filtres.
          </p>
        </div>
      ) : (
        <div className="space-y-3">
          {results.map((comic) => {
            const coverSrc =
              getCoverThumbnailSrc(comic) ?? getCoverSrc(comic);
            const total = Math.max(
              comic.latestPublishedIssue ?? 0,
              comic.coveredCount,
            );
            const unboughtCount = comic.unboughtTomes?.length ?? 0;

            return (
              <Link
                key={comic.id}
                className="group flex gap-3.5 rounded-2xl border border-surface-border bg-surface-primary p-3 shadow-xs transition hover:border-amber-500/40 active:scale-[0.99] dark:bg-surface-secondary"
                to={`/comic/${comic.id}`}
                viewTransition
              >
                <div className="h-24 w-16 shrink-0 overflow-hidden rounded-xl bg-surface-tertiary shadow">
                  <CoverImage
                    alt={comic.title}
                    className="h-full w-full transition-transform duration-300 group-hover:scale-105"
                    fallbackSrc={ComicTypePlaceholder[comic.type]}
                    height={96}
                    src={coverSrc ?? ComicTypePlaceholder[comic.type]}
                    width={64}
                  />
                </div>

                <div className="flex min-w-0 flex-1 flex-col justify-between py-0.5">
                  <div>
                    <div className="flex items-center gap-2">
                      <span className="rounded bg-black/60 px-1.5 py-0.2 font-bold text-[9px] text-white">
                        {ComicTypeLabel[comic.type]}
                      </span>
                      {comic.publisher && (
                        <span className="font-bold text-[10px] text-amber-600 dark:text-amber-400">
                          {comic.publisher}
                        </span>
                      )}
                    </div>
                    <h3 className="truncate font-serif text-base font-bold text-text-primary group-hover:text-amber-600 dark:group-hover:text-amber-400">
                      {comic.title}
                    </h3>
                    <p className="truncate text-xs text-text-secondary">
                      {comic.authors.map((a) => a.name).join(", ")}
                    </p>
                  </div>

                  {/* Numbers / Metrics */}
                  <div className="flex items-center justify-between border-t border-surface-border pt-1 font-mono-stats text-xs dark:border-white/10">
                    <span className="font-semibold text-text-muted">
                      {comic.tomesCount} tome{comic.tomesCount > 1 ? "s" : ""}
                    </span>
                    <div className="flex items-center gap-2">
                      {comic.notInterestedBuy ? (
                        <span
                          className="font-bold text-text-muted"
                          title="Non suivi pour achat"
                        >
                          Achat: ✕
                        </span>
                      ) : (
                        <span
                          className="font-bold text-emerald-600 dark:text-emerald-400"
                          title="Achetés"
                        >
                          {comic.boughtCount}/{total}
                        </span>
                      )}

                      {comic.notInterestedNas ? (
                        <span
                          className="font-bold text-text-muted"
                          title="Non suivi sur le NAS"
                        >
                          NAS: ✕
                        </span>
                      ) : (
                        <span
                          className="font-bold text-blue-600 dark:text-blue-400"
                          title="Sur NAS"
                        >
                          NAS {comic.onNasCount}/{total}
                        </span>
                      )}

                      {!comic.isOneShot &&
                      !comic.notInterestedBuy &&
                      unboughtCount > 0 ? (
                        <span className="rounded bg-amber-500/10 px-1.5 py-0.5 font-bold text-[10px] text-amber-600 dark:text-amber-400">
                          -{unboughtCount} à acheter
                        </span>
                      ) : (
                        <span className="font-bold text-amber-600 dark:text-amber-400">
                          Lu {comic.readCount}/{total}
                        </span>
                      )}
                    </div>
                  </div>
                </div>
              </Link>
            );
          })}
        </div>
      )}
    </div>
  );
}
