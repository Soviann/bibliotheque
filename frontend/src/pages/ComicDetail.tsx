import {
  AlertTriangle,
  ArrowLeft,
  Bell,
  BellOff,
  BookOpen,
  Edit,
  ExternalLink,
  HardDrive,
  ShoppingBag,
  Trash2,
} from "lucide-react";
import {
  useCallback,
  useEffect,
  useMemo,
  useReducer,
  useRef,
  useState,
} from "react";
import { Link, useNavigate, useParams } from "react-router-dom";
import { useGoBack } from "../hooks/useGoBack";
import { toast } from "sonner";
import ComponentErrorBoundary from "../components/ComponentErrorBoundary";
import CoverImage from "../components/CoverImage";
import CoverLightbox from "../components/CoverLightbox";
import EmptyState from "../components/EmptyState";
import SkeletonBox from "../components/SkeletonBox";
import SeriesEnrichmentProposals from "../components/SeriesEnrichmentProposals";
import SyncPendingIndicator from "../components/SyncPendingIndicator";
import TomeDrawer from "../components/TomeDrawer";
import VolumeMatrixAccordion, {
  type SortDirection,
  type SortKey,
} from "../components/VolumeMatrixAccordion";
import type { Tome } from "../types/api";
import { useComic } from "../hooks/useComic";
import { useCreateTome } from "../hooks/useCreateTome";
import { useCreateTomesBatch } from "../hooks/useCreateTomesBatch";
import { useDeleteComic } from "../hooks/useDeleteComic";
import { useDominantColor } from "../hooks/useDominantColor";
import { useRestoreComic } from "../hooks/useTrash";
import { useToggleAuthorFollow } from "../hooks/useFollowedAuthors";
import { useUpdateComic } from "../hooks/useUpdateComic";
import { useUpdateTome } from "../hooks/useUpdateTome";
import {
  ComicStatus,
  ComicStatusColor,
  ComicStatusLabel,
  ComicTypeLabel,
  ComicTypePlaceholder,
} from "../types/enums";
import { getCoverSrc } from "../utils/coverUtils";
import {
  countCoveredTomes,
  getTrailingMissingTomeNumbers,
} from "../utils/tomeUtils";

function AuthorWithFollow({
  author,
}: {
  author: { followedForNewSeries: boolean; id: number; name: string };
}) {
  const toggleFollow = useToggleAuthorFollow();
  const followed = author.followedForNewSeries;
  const Icon = followed ? Bell : BellOff;

  return (
    <span className="inline-flex items-center gap-1">
      {author.name}
      <button
        aria-label={
          followed ? `Ne plus suivre ${author.name}` : `Suivre ${author.name}`
        }
        className={`rounded p-0.5 ${followed ? "text-primary-600" : "text-text-muted hover:text-text-secondary"}`}
        onClick={() =>
          toggleFollow.mutate({ follow: !followed, id: author.id })
        }
        title={followed ? "Ne plus suivre" : "Suivre cet auteur"}
        type="button"
      >
        <Icon className="h-3.5 w-3.5" />
      </button>
    </span>
  );
}
function compareTomes(
  a: Tome,
  b: Tome,
  key: SortKey,
  direction: SortDirection,
): number {
  let result: number;

  if (key === "number") {
    result = a.number - b.number;
  } else if (key === "title") {
    const aTitle = a.title ?? "";
    const bTitle = b.title ?? "";
    result = aTitle.localeCompare(bTitle, "fr");
    if (result === 0) result = a.number - b.number;
  } else {
    // Boolean fields: false (0) before true (1) in ascending
    result = Number(a[key]) - Number(b[key]);
    if (result === 0) result = a.number - b.number;
  }

  return direction === "asc" ? result : -result;
}

const rtf = new Intl.RelativeTimeFormat("fr", { numeric: "auto" });

function formatRelativeDate(isoDate: string): string {
  const diffMs = Date.now() - new Date(isoDate).getTime();
  const diffDays = Math.floor(diffMs / 86_400_000);

  if (diffDays < 30) return rtf.format(-diffDays, "day");
  if (diffDays < 365) return rtf.format(-Math.floor(diffDays / 30), "month");
  return rtf.format(-Math.floor(diffDays / 365), "year");
}

export default function ComicDetail() {
  const { id } = useParams<{ id: string }>();
  const goBack = useGoBack();
  const navigate = useNavigate();
  const { data: comic, isLoading } = useComic(id ? Number(id) : undefined);
  const deleteComic = useDeleteComic();
  const restoreComic = useRestoreComic();
  const updateComic = useUpdateComic();
  const updateTome = useUpdateTome(id ? Number(id) : undefined);
  const createTome = useCreateTome(id ? Number(id) : 0);
  const createTomesBatch = useCreateTomesBatch(id ? Number(id) : 0);

  const handleToggleTracking = useCallback(
    (axis: "buy" | "nas") => {
      if (!comic) return;
      if (axis === "buy") {
        const nextVal = !comic.notInterestedBuy;
        updateComic.mutate(
          { id: comic.id, notInterestedBuy: nextVal },
          {
            onError: () => toast.error("Erreur lors de la mise à jour"),
            onSuccess: () => {
              toast.success(
                nextVal
                  ? "Achat désactivé pour cette série"
                  : "Achat activé pour cette série",
              );
            },
          },
        );
      } else {
        const nextVal = !comic.notInterestedNas;
        updateComic.mutate(
          { id: comic.id, notInterestedNas: nextVal },
          {
            onError: () => toast.error("Erreur lors de la mise à jour"),
            onSuccess: () => {
              toast.success(
                nextVal
                  ? "Suivi NAS désactivé pour cette série"
                  : "Suivi NAS activé pour cette série",
              );
            },
          },
        );
      }
    },
    [comic, updateComic],
  );
  const [isCompleting, setIsCompleting] = useState(false);
  const coverSrc = comic ? getCoverSrc(comic) : null;
  const [dominantColor, extractColor] = useDominantColor(coverSrc);
  const [lightboxOpen, setLightboxOpen] = useState(false);
  const [optimisticTomes, setOptimisticTomes] = useState<Tome[]>([]);
  const [tomeView, setTomeView] = useState<"map" | "table">(
    () => (localStorage.getItem("tome-view-mode") as "map" | "table") ?? "map",
  );
  const [sort, dispatchSort] = useReducer(
    (
      state: { direction: SortDirection; key: SortKey },
      key: SortKey,
    ): { direction: SortDirection; key: SortKey } =>
      state.key === key
        ? {
            ...state,
            direction:
              state.direction === "asc" ? ("desc" as const) : ("asc" as const),
          }
        : { direction: "asc" as const, key },
    { direction: "asc" as const, key: "number" as SortKey },
  );
  const toggleCountRef = useRef(0);
  const toggleTimerRef = useRef<ReturnType<typeof setTimeout>>(undefined);

  const sortedTomes = useMemo(
    () =>
      [...optimisticTomes].sort((a, b) =>
        compareTomes(a, b, sort.key, sort.direction),
      ),
    [optimisticTomes, sort.key, sort.direction],
  );

  const { boughtCount, onNasCount, progressTotal, readCount, trailingMissing } =
    useMemo(() => {
      const covered = countCoveredTomes(optimisticTomes);
      const published = comic?.latestPublishedIssue ?? 0;
      return {
        boughtCount: countCoveredTomes(optimisticTomes, (t) => t.bought),
        onNasCount: countCoveredTomes(optimisticTomes, (t) => t.onNas),
        progressTotal: Math.max(published, covered),
        readCount: countCoveredTomes(optimisticTomes, (t) => t.read),
        trailingMissing: getTrailingMissingTomeNumbers(
          optimisticTomes,
          comic?.latestPublishedIssue,
        ),
      };
    }, [optimisticTomes, comic?.latestPublishedIssue]);

  useEffect(() => {
    if (comic?.tomes) {
      setOptimisticTomes(comic.tomes);
    }
  }, [comic?.tomes]);

  useEffect(() => {
    return () => clearTimeout(toggleTimerRef.current);
  }, []);

  const handleTomeViewChange = useCallback((mode: "map" | "table") => {
    setTomeView(mode);
    localStorage.setItem("tome-view-mode", mode);
  }, []);

  const handleToggleTome = useCallback(
    (tome: Tome, field: "bought" | "onNas" | "read") => {
      const newValue = !tome[field];

      // Optimistic update
      setOptimisticTomes((prev) =>
        prev.map((t) => (t.id === tome.id ? { ...t, [field]: newValue } : t)),
      );

      updateTome.mutate(
        { id: tome.id, [field]: newValue },
        {
          onError: () => {
            // Revert optimistic update
            setOptimisticTomes((prev) =>
              prev.map((t) =>
                t.id === tome.id ? { ...t, [field]: tome[field] } : t,
              ),
            );
            toast.error("Erreur lors de la mise à jour du tome");
          },
          onSuccess: () => {
            if (navigator.onLine) {
              toggleCountRef.current += 1;
              clearTimeout(toggleTimerRef.current);
              toggleTimerRef.current = setTimeout(() => {
                const count = toggleCountRef.current;
                toggleCountRef.current = 0;
                toast.success(
                  count === 1
                    ? "1 tome mis à jour"
                    : `${count} tomes mis à jour`,
                  { duration: 1500 },
                );
              }, 1000);
            }
          },
        },
      );
    },
    [updateTome],
  );

  const [selectedDrawerTome, setSelectedDrawerTome] = useState<{
    isHorsSerie: boolean;
    number: number;
    tome?: Tome;
  } | null>(null);

  const activeDrawerTome = useMemo(() => {
    if (!selectedDrawerTome) return undefined;
    if (selectedDrawerTome.isHorsSerie) {
      return optimisticTomes.find(
        (t) => t.isHorsSerie && t.number === selectedDrawerTome.number,
      );
    }
    return (
      optimisticTomes.find(
        (t) =>
          !t.isHorsSerie &&
          selectedDrawerTome.number >= t.number &&
          selectedDrawerTome.number <= (t.tomeEnd ?? t.number),
      ) ?? selectedDrawerTome.tome
    );
  }, [optimisticTomes, selectedDrawerTome]);

  const handleSelectMapTome = useCallback(
    (tomeNumber: number, tome?: Tome, isHorsSerie = false) => {
      setSelectedDrawerTome({
        isHorsSerie,
        number: tomeNumber,
        tome,
      });
    },
    [],
  );

  const handleDrawerToggleField = useCallback(
    (field: "bought" | "onNas" | "read") => {
      if (!selectedDrawerTome) return;

      const currentTome = activeDrawerTome ?? selectedDrawerTome.tome;
      if (currentTome) {
        handleToggleTome(currentTome, field);
      } else {
        const number = selectedDrawerTome.number;
        const isHorsSerie = selectedDrawerTome.isHorsSerie;
        const bought = field === "bought";
        const onNas = field === "onNas";
        const read = field === "read";

        createTome.mutate(
          {
            bought,
            isHorsSerie,
            isbn: null,
            number,
            onNas,
            read,
            title: null,
            tomeEnd: null,
          },
          {
            onError: () => {
              toast.error("Erreur lors de l'ajout du tome");
            },
            onSuccess: (newTome) => {
              setSelectedDrawerTome((prev) =>
                prev ? { ...prev, tome: newTome } : null,
              );
              toast.success("Tome ajouté", { duration: 1500 });
            },
          },
        );
      }
    },
    [activeDrawerTome, createTome, handleToggleTome, selectedDrawerTome],
  );

  const handleCompleteMissingTomes = useCallback(() => {
    if (!comic || trailingMissing.length === 0 || isCompleting) return;
    setIsCompleting(true);

    const bought = comic.defaultTomeBought;
    const onNas = comic.defaultTomeOnNas;
    const read = comic.defaultTomeRead;
    const count = trailingMissing.length;

    const tomes = trailingMissing.map((number) => ({
      bought,
      isHorsSerie: false,
      isbn: null,
      number,
      onNas,
      read,
      title: null,
      tomeEnd: null,
    }));

    createTomesBatch.mutate(
      { tomes },
      {
        onError: () => {
          setIsCompleting(false);
          toast.error("Erreur lors de l'ajout des tomes");
        },
        onSuccess: () => {
          setIsCompleting(false);
          if (navigator.onLine) {
            toast.success(
              count === 1 ? "1 tome ajouté" : `${count} tomes ajoutés`,
              { duration: 1500 },
            );
          }
        },
      },
    );
  }, [comic, createTomesBatch, isCompleting, trailingMissing]);

  const handleToggleAllTomes = useCallback(
    (field: "bought" | "onNas" | "read") => {
      const allChecked = optimisticTomes.every((t) => t[field]);
      const targetValue = !allChecked;

      const tomesToUpdate = optimisticTomes.filter(
        (t) => t[field] !== targetValue,
      );
      if (tomesToUpdate.length === 0) return;

      // Optimistic update: batch all tomes at once
      setOptimisticTomes((prev) =>
        prev.map((t) => ({ ...t, [field]: targetValue })),
      );

      if (navigator.onLine) {
        toast.success(`${tomesToUpdate.length} tomes mis à jour`, {
          duration: 1500,
        });
      }

      // Fire individual PATCH mutations for tomes that need changing
      for (const tome of tomesToUpdate) {
        updateTome.mutate(
          { id: tome.id, [field]: targetValue },
          {
            onError: () => {
              setOptimisticTomes((prev) =>
                prev.map((t) =>
                  t.id === tome.id ? { ...t, [field]: tome[field] } : t,
                ),
              );
              toast.error("Erreur lors de la mise à jour du tome");
            },
          },
        );
      }
    },
    [optimisticTomes, updateTome],
  );

  if (isLoading) {
    return (
      <div
        className="mx-auto max-w-4xl space-y-6"
        data-testid="comic-detail-skeleton"
      >
        {/* Header */}
        <div className="flex items-center gap-3">
          <SkeletonBox className="h-5 w-5" />
          <SkeletonBox className="h-6 w-48" />
        </div>
        {/* Content */}
        <div className="flex flex-col gap-6 md:flex-row">
          <SkeletonBox className="aspect-[3/4] w-full md:w-48" />
          <div className="flex-1 space-y-3">
            <div className="flex gap-2">
              <SkeletonBox className="h-7 w-20 !rounded-full" />
              <SkeletonBox className="h-7 w-24 !rounded-full" />
            </div>
            <SkeletonBox className="h-4 w-3/4" />
            <SkeletonBox className="h-4 w-1/2" />
            <SkeletonBox className="h-16 w-full" />
          </div>
        </div>
        {/* Tomes table */}
        <div>
          <SkeletonBox className="mb-3 h-6 w-32" />
          <div className="space-y-2">
            {Array.from({ length: 5 }, (_, i) => (
              <SkeletonBox className="h-10 w-full" key={i} />
            ))}
          </div>
        </div>
      </div>
    );
  }

  if (!comic) {
    return (
      <EmptyState
        actionHref="/"
        actionLabel="Retour à la bibliothèque"
        icon={BookOpen}
        title="Série introuvable"
      />
    );
  }

  const showProgress = !comic.isOneShot && optimisticTomes.length > 0;

  const handleDelete = () => {
    const seriesId = comic.id;
    deleteComic.mutate(
      { id: seriesId },
      {
        onError: () => toast.error("Erreur lors de la suppression"),
        onSuccess: () => {
          toast.success("Série supprimée", {
            action: {
              label: "Annuler",
              onClick: () => restoreComic.mutate({ id: seriesId }),
            },
            duration: 5000,
          });
          navigate("/", { viewTransition: true });
        },
      },
    );
  };

  const actionButtons = (
    <>
      <Link
        className="focus-ring-series btn-series-color flex items-center gap-2 rounded-xl px-5 py-2.5 text-base font-medium text-white transition-colors"
        to={`/comic/${comic.id}/edit`}
        viewTransition
      >
        <Edit className="h-5 w-5" />
        Modifier
      </Link>
      {comic.status === ComicStatus.BUYING && comic.amazonUrl && (
        <a
          className="focus-ring-series flex items-center gap-2 rounded-xl bg-amber-600 px-5 py-2.5 text-base font-medium text-white transition-colors hover:bg-amber-700"
          href={comic.amazonUrl}
          rel="noopener noreferrer"
          target="_blank"
        >
          <ExternalLink className="h-5 w-5" />
          Amazon
        </a>
      )}
      <button
        className="focus-ring-series flex items-center gap-2 rounded-xl border border-accent-danger px-5 py-2.5 text-base font-medium text-accent-danger transition-colors hover:bg-red-50 dark:hover:bg-red-950/30"
        onClick={handleDelete}
        type="button"
      >
        <Trash2 className="h-5 w-5" />
        Supprimer
      </button>
    </>
  );

  return (
    <div
      className="mx-auto max-w-4xl space-y-6"
      style={{ ["--series-color" as string]: dominantColor }}
    >
      {/* Header */}
      <div className="flex flex-wrap items-center gap-3">
        <button
          aria-label="Retour"
          className="inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-lg text-text-muted hover:text-text-secondary"
          onClick={goBack}
          type="button"
        >
          <ArrowLeft className="h-5 w-5" />
        </button>
        <h1 className="flex-1 font-display text-2xl font-bold text-text-primary">
          {comic._syncPending && <SyncPendingIndicator className="mr-1.5" />}
          {comic.title}
        </h1>
      </div>

      {/* Content — avec backdrop ambient en dark mode */}
      <div className="relative flex flex-col gap-6 md:flex-row">
        {/* Ambient backdrop — gradient from dominant color */}
        <div
          aria-hidden="true"
          className="pointer-events-none absolute -inset-x-4 -top-4 h-72 overflow-hidden rounded-2xl"
          style={{
            background: `radial-gradient(ellipse at top left, rgba(${dominantColor}, 0.12) 0%, transparent 70%)`,
          }}
        />
        {/* Ambient backdrop (dark mode only) */}
        {coverSrc && (
          <div
            aria-hidden="true"
            className="pointer-events-none absolute -inset-x-4 -top-4 hidden h-72 overflow-hidden rounded-2xl dark:block"
          >
            <img
              alt=""
              className="h-full w-full scale-110 object-cover blur-3xl"
              src={coverSrc}
              style={{ opacity: 0.15 }}
            />
            <div className="absolute inset-0 bg-gradient-to-b from-transparent via-surface-primary/50 to-surface-primary" />
          </div>
        )}

        {/* Cover */}
        <div
          className="relative z-10 w-full md:w-48"
          style={{ ["--glow-rgb" as string]: dominantColor }}
        >
          <CoverImage
            alt={comic.title}
            className={`card-glow w-full max-h-64 md:max-h-none rounded-xl shadow-lg${coverSrc ? " cursor-pointer" : ""}`}
            fallbackSrc={ComicTypePlaceholder[comic.type]}
            loading="eager"
            objectFit="contain"
            onClick={coverSrc ? () => setLightboxOpen(true) : undefined}
            onImageLoad={extractColor}
            src={coverSrc ?? ComicTypePlaceholder[comic.type]}
          />
        </div>

        {/* Info */}
        <div className="relative z-10 flex-1 space-y-3">
          <div className="flex flex-wrap gap-2">
            <span className="rounded-full bg-primary-100 px-3 py-1 text-sm font-medium text-primary-700 dark:bg-primary-950/30 dark:text-primary-400">
              {ComicTypeLabel[comic.type]}
            </span>
            <span
              className={`rounded-full px-3 py-1 text-sm font-medium ${ComicStatusColor[comic.status]}`}
            >
              {ComicStatusLabel[comic.status]}
            </span>
            {comic.isOneShot && (
              <span className="rounded-full bg-amber-100 px-3 py-1 text-sm font-medium text-amber-700 dark:bg-amber-950/30 dark:text-amber-400">
                One-shot
              </span>
            )}
            {comic.latestPublishedIssueComplete && (
              <span className="rounded-full bg-green-100 px-3 py-1 text-sm font-medium text-green-700 dark:bg-green-950/30 dark:text-green-400">
                Parution terminée
              </span>
            )}
          </div>

          <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
            {comic.authors.length > 0 && (
              <>
                <dt className="font-medium text-text-secondary">Auteurs</dt>
                <dd className="flex flex-wrap items-center gap-x-2 gap-y-1 text-text-secondary">
                  {comic.authors.map((a) => (
                    <AuthorWithFollow author={a} key={a.id} />
                  ))}
                </dd>
              </>
            )}
            {comic.publisher && (
              <>
                <dt className="font-medium text-text-secondary">Éditeur</dt>
                <dd className="text-text-secondary">{comic.publisher}</dd>
              </>
            )}
            {comic.publishedDate && (
              <>
                <dt className="font-medium text-text-secondary">Parution</dt>
                <dd className="text-text-secondary">
                  {new Date(comic.publishedDate).toLocaleDateString("fr-FR", {
                    day: "numeric",
                    month: "long",
                    year: "numeric",
                  })}
                </dd>
              </>
            )}
            {comic.latestPublishedIssue != null && (
              <>
                <dt className="font-medium text-text-secondary">Tomes parus</dt>
                <dd className="text-text-secondary">
                  {comic.latestPublishedIssue}
                  {comic.latestPublishedIssueComplete && " (terminée)"}
                  {comic.latestPublishedIssueUpdatedAt && (
                    <span className="ml-2 text-text-muted">
                      (mis à jour{" "}
                      {formatRelativeDate(comic.latestPublishedIssueUpdatedAt)})
                    </span>
                  )}
                </dd>
              </>
            )}
            {(comic.defaultTomeBought ||
              comic.defaultTomeOnNas ||
              comic.defaultTomeRead) && (
              <>
                <dt className="font-medium text-text-secondary">
                  Nouveaux tomes
                </dt>
                <dd className="text-text-secondary">
                  {[
                    comic.defaultTomeBought && "achetés",
                    comic.defaultTomeOnNas && "sur NAS",
                    comic.defaultTomeRead && "lus",
                  ]
                    .filter(Boolean)
                    .join(", ")}
                </dd>
              </>
            )}
          </dl>
          {comic.description && (
            <div>
              <h3 className="text-sm font-medium text-text-secondary">
                Description
              </h3>
              <p className="mt-1 text-sm leading-relaxed text-text-secondary">
                {comic.description}
              </p>
            </div>
          )}
        </div>
      </div>

      {/* Unified Metrics & Acquisition Tracking */}
      {showProgress && (
        <div className="space-y-3 rounded-2xl border border-surface-border bg-surface-secondary/60 p-3.5 shadow-xs dark:border-white/10 dark:bg-surface-elevated/40">
          {/* Acquisition Tracking Switches */}
          <div className="flex items-center justify-between border-b border-surface-border pb-2 text-xs dark:border-white/10">
            <span className="text-[11px] font-semibold text-text-muted">
              Suivi d'acquisition :
            </span>
            <div className="flex items-center gap-1.5">
              <button
                aria-label={
                  comic.notInterestedBuy
                    ? "Activer le suivi d'achat"
                    : "Désactiver le suivi d'achat"
                }
                className={`flex items-center gap-1 rounded-lg px-2.5 py-1 text-[10px] font-bold transition active:scale-95 ${
                  comic.notInterestedBuy
                    ? "border border-neutral-500/20 bg-neutral-500/10 text-text-muted"
                    : "border border-amber-500/40 bg-amber-500/20 text-amber-700 dark:text-amber-300"
                }`}
                onClick={() => handleToggleTracking("buy")}
                type="button"
              >
                <ShoppingBag className="h-3 w-3" />
                <span>À acheter : {comic.notInterestedBuy ? "Non" : "Oui"}</span>
              </button>
              <button
                aria-label={
                  comic.notInterestedNas
                    ? "Activer le suivi NAS"
                    : "Désactiver le suivi NAS"
                }
                className={`flex items-center gap-1 rounded-lg px-2.5 py-1 text-[10px] font-bold transition active:scale-95 ${
                  comic.notInterestedNas
                    ? "border border-neutral-500/20 bg-neutral-500/10 text-text-muted"
                    : "border border-blue-500/40 bg-blue-500/20 text-blue-700 dark:text-blue-300"
                }`}
                onClick={() => handleToggleTracking("nas")}
                type="button"
              >
                <HardDrive className="h-3 w-3" />
                <span>Sur NAS : {comic.notInterestedNas ? "Non" : "Oui"}</span>
              </button>
            </div>
          </div>

          {/* The 3 Figures in Tabular Font */}
          <div className="grid grid-cols-3 gap-2 text-center">
            {/* Achetés */}
            <div className="space-y-1">
              <span className="block text-[10px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
                Achetés
              </span>
              <div className="font-mono text-base font-bold text-text-primary">
                {comic.notInterestedBuy ? (
                  <span className="text-sm text-text-muted" title="Non suivi">
                    ✕
                  </span>
                ) : (
                  `${boughtCount} / ${progressTotal}`
                )}
              </div>
              <div
                aria-label="Achetés"
                aria-valuemax={progressTotal}
                aria-valuemin={0}
                aria-valuenow={comic.notInterestedBuy ? 0 : boughtCount}
                className="h-1.5 w-full overflow-hidden rounded-full bg-surface-tertiary dark:bg-white/10"
                role="progressbar"
              >
                <div
                  className="h-full rounded-full bg-emerald-500 transition-all duration-500"
                  style={{
                    width: `${
                      !comic.notInterestedBuy && progressTotal > 0
                        ? (boughtCount / progressTotal) * 100
                        : 0
                    }%`,
                  }}
                />
              </div>
            </div>

            {/* Sur NAS */}
            <div className="space-y-1 border-x border-surface-border px-2 dark:border-white/10">
              <span className="block text-[10px] font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400">
                Sur NAS
              </span>
              <div className="font-mono text-base font-bold text-text-primary">
                {comic.notInterestedNas ? (
                  <span className="text-sm text-text-muted" title="Non suivi">
                    ✕
                  </span>
                ) : (
                  `${onNasCount} / ${progressTotal}`
                )}
              </div>
              <div
                aria-label="Sur NAS"
                aria-valuemax={progressTotal}
                aria-valuemin={0}
                aria-valuenow={comic.notInterestedNas ? 0 : onNasCount}
                className="h-1.5 w-full overflow-hidden rounded-full bg-surface-tertiary dark:bg-white/10"
                role="progressbar"
              >
                <div
                  className="h-full rounded-full bg-blue-500 transition-all duration-500"
                  style={{
                    width: `${
                      !comic.notInterestedNas && progressTotal > 0
                        ? (onNasCount / progressTotal) * 100
                        : 0
                    }%`,
                  }}
                />
              </div>
            </div>

            {/* Lus */}
            <div className="space-y-1">
              <span className="block text-[10px] font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">
                Lus
              </span>
              <div className="font-mono text-base font-bold text-text-primary">
                {`${readCount} / ${progressTotal}`}
              </div>
              <div
                aria-label="Lus"
                aria-valuemax={progressTotal}
                aria-valuemin={0}
                aria-valuenow={readCount}
                className="h-1.5 w-full overflow-hidden rounded-full bg-surface-tertiary dark:bg-white/10"
                role="progressbar"
              >
                <div
                  className="h-full rounded-full bg-amber-500 transition-all duration-500"
                  style={{
                    width: `${
                      progressTotal > 0 ? (readCount / progressTotal) * 100 : 0
                    }%`,
                  }}
                />
              </div>
            </div>
          </div>

          {/* Integrated Missing Tomes (À Acheter & À Télécharger) */}
          <div className="space-y-1.5 border-t border-surface-border pt-2.5 text-xs dark:border-white/10">
            {comic.notInterestedBuy ? (
              <div className="flex items-center gap-1.5 text-xs italic text-text-muted">
                <span className="font-bold text-text-muted">✕</span> Série non
                suivie pour achat physique
              </div>
            ) : (
              <div className="flex items-center justify-between">
                <div className="flex min-w-0 items-center gap-1.5">
                  <span className="h-2 w-2 shrink-0 rounded-full bg-amber-500" />
                  <span className="truncate font-medium text-text-secondary">
                    À acheter :{" "}
                    <span className="font-mono font-bold text-amber-600 dark:text-amber-400">
                      {progressTotal - boughtCount > 0
                        ? `${progressTotal - boughtCount} tome${
                            progressTotal - boughtCount > 1 ? "s" : ""
                          }`
                        : "Complet"}
                    </span>
                  </span>
                </div>
                <span className="rounded bg-amber-500/10 px-1.5 py-0.5 text-[10px] font-bold text-amber-600 dark:text-amber-400">
                  Physique
                </span>
              </div>
            )}

            {comic.notInterestedNas ? (
              <div className="flex items-center gap-1.5 text-xs italic text-text-muted">
                <span className="font-bold text-text-muted">✕</span> Série non
                suivie sur le NAS
              </div>
            ) : (
              <div className="flex items-center justify-between">
                <div className="flex min-w-0 items-center gap-1.5">
                  <span className="h-2 w-2 shrink-0 rounded-full bg-blue-500" />
                  <span className="truncate font-medium text-text-secondary">
                    À télécharger :{" "}
                    <span className="font-mono font-bold text-blue-600 dark:text-blue-400">
                      {progressTotal - onNasCount > 0
                        ? `${progressTotal - onNasCount} tome${
                            progressTotal - onNasCount > 1 ? "s" : ""
                          }`
                        : "Sur NAS 100%"}
                    </span>
                  </span>
                </div>
                <span className="rounded bg-blue-500/10 px-1.5 py-0.5 text-[10px] font-bold text-blue-600 dark:text-blue-400">
                  {progressTotal - onNasCount === 0 ? "Sur NAS 100%" : "Numérique"}
                </span>
              </div>
            )}
          </div>
        </div>
      )}

      {/* Bannière tomes manquants */}
      {!comic.isOneShot && trailingMissing.length > 0 && (
        <div className="flex items-center gap-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-300">
          <AlertTriangle className="h-5 w-5 shrink-0" />
          <span>
            {trailingMissing.length === 1
              ? "1 tome paru non ajouté"
              : `${trailingMissing.length} tomes parus non ajoutés`}
          </span>
          <button
            className="ml-auto shrink-0 font-medium text-amber-700 underline hover:text-amber-900 disabled:cursor-not-allowed disabled:opacity-60 dark:text-amber-400 dark:hover:text-amber-200"
            disabled={isCompleting}
            onClick={handleCompleteMissingTomes}
            type="button"
          >
            Compléter
          </button>
        </div>
      )}

      {/* Tomes */}
      {!comic.isOneShot && optimisticTomes.length > 0 && (
        <ComponentErrorBoundary label="les tomes">
          <VolumeMatrixAccordion
            latestPublishedIssue={comic.latestPublishedIssue}
            onSelectMapTome={handleSelectMapTome}
            onSort={(key) => dispatchSort(key)}
            onToggleAllTomes={handleToggleAllTomes}
            onToggleTome={handleToggleTome}
            onTomeViewChange={handleTomeViewChange}
            sort={sort}
            sortedTomes={sortedTomes}
            tomes={optimisticTomes}
            tomeView={tomeView}
          />
        </ComponentErrorBoundary>
      )}

      {/* Enrichment proposals */}
      <SeriesEnrichmentProposals seriesId={comic.id} />

      {/* Action bar: sticky on mobile, inline on desktop */}
      {/* Spacer pour compenser la barre fixe sur mobile */}
      <div className="h-16 lg:hidden" />

      {/* Barre d'actions — fixée au-dessus de la navbar sur mobile, inline sur desktop */}
      <div className="fixed inset-x-0 bottom-[var(--bottom-nav-h)] z-40 flex justify-center gap-3 border-t border-surface-border bg-surface-primary/90 px-4 py-3 backdrop-blur-md dark:border-white/10 dark:bg-surface-primary/70 lg:static lg:justify-start lg:border-t-0 lg:bg-transparent lg:px-0 lg:py-0 lg:backdrop-blur-none">
        {actionButtons}
      </div>

      {coverSrc && (
        <CoverLightbox
          onClose={() => setLightboxOpen(false)}
          open={lightboxOpen}
          src={coverSrc}
          title={comic.title}
        />
      )}

      {/* Tome Bottom Drawer */}
      {selectedDrawerTome && (
        <TomeDrawer
          isHorsSerie={selectedDrawerTome.isHorsSerie}
          isOpen={true}
          onClose={() => setSelectedDrawerTome(null)}
          onToggleField={handleDrawerToggleField}
          seriesTitle={comic?.title}
          tome={activeDrawerTome}
          tomeNumber={selectedDrawerTome.number}
        />
      )}
    </div>
  );
}
