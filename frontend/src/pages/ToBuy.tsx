import { ExternalLink, Eye, Loader2, Search, ShoppingCart } from "lucide-react";
import { memo, useCallback, useMemo, useRef, useState } from "react";
import { Link } from "react-router-dom";
import CoverImage from "../components/CoverImage";
import EmptyState from "../components/EmptyState";
import SearchInput from "../components/SearchInput";
import { useBuyTome } from "../hooks/useBuyTome";
import { useComics } from "../hooks/useComics";
import { useDebounce } from "../hooks/useDebounce";
import type { ComicSeries } from "../types/api";
import { ComicTypeLabel, ComicTypePlaceholder } from "../types/enums";
import type { ComicType } from "../types/enums";
import { getCoverSrc, getCoverThumbnailSrc } from "../utils/coverUtils";
import { searchComics } from "../utils/searchComics";
import { filterSeriesToBuy, formatTomeRanges, getNextTomesToBuy } from "../utils/toBuyUtils";

const HERO_COUNT = 10;

/** Ordre d'affichage des types. */
const TYPE_ORDER: ComicType[] = ["manga", "bd", "comics", "livre"];

export default function ToBuy() {
  const { data, isFetching, isLoading } = useComics();
  const allComics = data?.member ?? [];

  const [search, setSearch] = useState("");
  const debouncedSearch = useDebounce(search, 300);

  const buyTome = useBuyTome();
  const buyTomeRef = useRef(buyTome);
  buyTomeRef.current = buyTome;

  const toBuyComics = useMemo(() => filterSeriesToBuy(allComics), [allComics]);

  const filtered = useMemo(() => {
    return searchComics(toBuyComics, debouncedSearch);
  }, [toBuyComics, debouncedSearch]);

  /** Séries groupées par type, triées A-Z dans chaque groupe. */
  const groupedByType = useMemo(() => {
    const groups = new Map<ComicType, ComicSeries[]>();
    for (const comic of filtered) {
      const type = comic.type as ComicType;
      if (!groups.has(type)) groups.set(type, []);
      groups.get(type)!.push(comic);
    }
    // Tri A-Z dans chaque groupe
    for (const list of groups.values()) {
      list.sort((a, b) => a.title.localeCompare(b.title, "fr"));
    }
    return groups;
  }, [filtered]);

  const recentlyAdded = useMemo(() => {
    if (toBuyComics.length === 0) return [];
    return [...toBuyComics]
      .sort((a, b) => new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime())
      .slice(0, HERO_COUNT);
  }, [toBuyComics]);

  const handleSearchChange = useCallback((v: string) => setSearch(v), []);
  const handleBuyTome = useCallback(
    (seriesId: number, tomeId: number) => {
      buyTomeRef.current.mutate({ seriesId, tomeId });
    },
    [],
  );

  const showHero = !isLoading && !debouncedSearch && recentlyAdded.length > 0;

  return (
    <div className="space-y-4">
      {/* Hero — Récemment ajoutés */}
      {showHero && (
        <section className="space-y-2">
          <h2 className="font-display text-sm font-semibold text-text-secondary">
            Récemment ajoutés
          </h2>
          <div className="-mx-4 flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 pb-2 scrollbar-none">
            {recentlyAdded.map((comic) => {
              const src = getCoverThumbnailSrc(comic) ?? getCoverSrc(comic);
              const tomes = getNextTomesToBuy(comic);
              const regularNumbers = tomes.filter((t) => !t.isHorsSerie).map((t) => t.number);
              return (
                <Link
                  className="group flex w-[140px] shrink-0 snap-center flex-col gap-1.5 sm:w-[160px]"
                  key={comic.id}
                  to={`/comic/${comic.id}`}
                  viewTransition
                >
                  <div
                    className="card-glow overflow-hidden rounded-xl transition-transform duration-200 group-hover:-translate-y-1 group-hover:scale-[1.02]"
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
                  <h3 className="truncate font-display text-sm font-medium text-text-primary">
                    {comic.title}
                  </h3>
                  <p className="font-mono-stats text-xs text-accent-sage">
                    Prochain : {formatTomeRanges(regularNumbers)}
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
          <span className="font-display text-sm font-semibold text-text-secondary">
            Toutes les séries
          </span>
          <hr className="flex-1 border-surface-border dark:border-white/5" />
        </div>
      )}

      {/* Search */}
      <div className="flex items-center gap-2">
        <SearchInput onChange={handleSearchChange} value={search} />
        <span className="flex shrink-0 items-center gap-1.5 font-mono-stats text-sm text-text-muted">
          {isFetching && !isLoading && (
            <Loader2 className="h-3.5 w-3.5 animate-spin" />
          )}
          {filtered.length}/{toBuyComics.length}
        </span>
      </div>

      {/* Contenu */}
      {isLoading ? (
        <div className="space-y-6">
          {Array.from({ length: 3 }, (_, i) => (
            <div className="animate-pulse space-y-3" key={i}>
              <div className="h-5 w-20 rounded bg-surface-elevated" />
              <div className="h-12 rounded bg-surface-elevated" />
              <div className="h-12 rounded bg-surface-elevated" />
            </div>
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
        <div className="space-y-6">
          {TYPE_ORDER.filter((type) => groupedByType.has(type)).map((type) => (
            <section data-testid={`type-section-${type}`} key={type}>
              <h2 className="mb-3 font-display text-base font-semibold text-text-primary">
                {ComicTypeLabel[type]}{" "}
                <span className="text-sm font-normal text-text-muted">
                  ({groupedByType.get(type)!.length})
                </span>
              </h2>
              <div className="space-y-2">
                {groupedByType.get(type)!.map((comic) => (
                  <SeriesRow
                    comic={comic}
                    key={comic.id}
                    onBuyTome={handleBuyTome}
                  />
                ))}
              </div>
            </section>
          ))}
        </div>
      )}
    </div>
  );
}

interface SeriesRowProps {
  comic: ComicSeries;
  onBuyTome: (seriesId: number, tomeId: number) => void;
}

const SeriesRow = memo(function SeriesRow({ comic, onBuyTome }: SeriesRowProps) {
  const src = getCoverThumbnailSrc(comic) ?? getCoverSrc(comic);
  const tomes = getNextTomesToBuy(comic);

  return (
    <div className="flex items-start gap-3 rounded-xl bg-surface-elevated/50 p-3 dark:bg-white/[0.03]">
      {/* Couverture miniature */}
      <Link className="shrink-0" to={`/comic/${comic.id}`} viewTransition>
        <CoverImage
          alt={comic.title}
          className="aspect-[3/4] w-10 rounded-md"
          fallbackSrc={ComicTypePlaceholder[comic.type]}
          height={60}
          src={src ?? ComicTypePlaceholder[comic.type]}
          width={40}
        />
      </Link>

      {/* Contenu */}
      <div className="min-w-0 flex-1">
        <div className="flex items-center gap-2">
          <h3
            className="truncate font-display text-sm font-medium text-text-primary"
            data-testid="series-title"
          >
            {comic.title}
          </h3>

          {/* Actions rapides */}
          <div className="ml-auto flex shrink-0 items-center gap-1.5">
            <Link
              aria-label="Voir le détail"
              className="rounded-md p-1 text-text-muted transition hover:bg-surface-border hover:text-text-primary dark:hover:bg-white/10"
              to={`/comic/${comic.id}`}
              viewTransition
            >
              <Eye className="h-4 w-4" />
            </Link>
            {comic.amazonUrl && (
              <a
                aria-label="Voir sur Amazon"
                className="rounded-md p-1 text-text-muted transition hover:bg-surface-border hover:text-text-primary dark:hover:bg-white/10"
                href={comic.amazonUrl}
                rel="noopener noreferrer"
                target="_blank"
              >
                <ExternalLink className="h-4 w-4" />
              </a>
            )}
          </div>
        </div>

        {/* Badges des tomes */}
        <div className="mt-1.5 flex flex-wrap gap-1.5">
          {tomes.map((tome) => (
            <TomeBadge
              isHorsSerie={tome.isHorsSerie}
              key={tome.id}
              number={tome.number}
              onBuy={() => onBuyTome(comic.id, tome.id)}
            />
          ))}
        </div>
      </div>
    </div>
  );
});

interface TomeBadgeProps {
  isHorsSerie: boolean;
  number: number;
  onBuy: () => void;
}

const TomeBadge = memo(function TomeBadge({ isHorsSerie, number, onBuy }: TomeBadgeProps) {
  const label = isHorsSerie ? `HS ${number}` : `${number}`;
  return (
    <button
      aria-label={`Marquer le tome ${label} comme acheté`}
      className="cursor-pointer rounded-full bg-primary-100 px-2.5 py-0.5 font-mono-stats text-xs font-semibold text-primary-700 transition active:scale-90 dark:bg-primary-950/30 dark:text-primary-300"
      onClick={onBuy}
      type="button"
    >
      {label}
    </button>
  );
});
