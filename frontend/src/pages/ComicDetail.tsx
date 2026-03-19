import { ArrowLeft, BookOpen, Edit, ExternalLink, Trash2 } from "lucide-react";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { Link, useNavigate, useParams } from "react-router-dom";
import { toast } from "sonner";
import CoverLightbox from "../components/CoverLightbox";
import EmptyState from "../components/EmptyState";
import ProgressBar from "../components/ProgressBar";
import SkeletonBox from "../components/SkeletonBox";
import SyncPendingIndicator from "../components/SyncPendingIndicator";
import type { Tome } from "../types/api";
import { useComic } from "../hooks/useComic";
import { useDeleteComic } from "../hooks/useDeleteComic";
import { useRestoreComic } from "../hooks/useTrash";
import { useUpdateTome } from "../hooks/useUpdateTome";
import { ComicStatus, ComicStatusColor, ComicStatusLabel, ComicTypeLabel, ComicTypePlaceholder } from "../types/enums";
import { getCoverSrc } from "../utils/coverUtils";
import { countCoveredTomes } from "../utils/tomeUtils";

type BooleanField = "bought" | "downloaded" | "onNas" | "read";

const FIELD_LABELS: Record<BooleanField, string> = {
  bought: "acheté",
  downloaded: "téléchargé",
  onNas: "NAS",
  read: "lu",
};

function HeaderCheckbox({ field, onChange, tomes }: { field: BooleanField; onChange: () => void; tomes: Tome[] }) {
  const ref = useRef<HTMLInputElement>(null);
  const checkedCount = tomes.filter((t) => t[field]).length;
  const allChecked = checkedCount === tomes.length;
  const someChecked = checkedCount > 0 && !allChecked;

  useEffect(() => {
    if (ref.current) {
      ref.current.indeterminate = someChecked;
    }
  }, [someChecked]);

  return (
    <input
      aria-label={`Tout cocher ${FIELD_LABELS[field]}`}
      checked={allChecked}
      className="h-4 w-4 cursor-pointer accent-primary-600"
      onChange={onChange}
      ref={ref}
      type="checkbox"
    />
  );
}

function formatRelativeDate(isoDate: string): string {
  const date = new Date(isoDate);
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

  if (diffDays === 0) return "aujourd'hui";
  if (diffDays === 1) return "hier";
  if (diffDays < 30) return `il y a ${diffDays} jours`;
  if (diffDays < 365) {
    const months = Math.floor(diffDays / 30);
    return `il y a ${months} mois`;
  }
  const years = Math.floor(diffDays / 365);
  return `il y a ${years} an${years > 1 ? "s" : ""}`;
}

export default function ComicDetail() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { data: comic, isLoading } = useComic(id ? Number(id) : undefined);
  const deleteComic = useDeleteComic();
  const restoreComic = useRestoreComic();
  const updateTome = useUpdateTome(id ? Number(id) : undefined);
  const [lightboxOpen, setLightboxOpen] = useState(false);
  const [optimisticTomes, setOptimisticTomes] = useState<Tome[]>([]);
  const toggleCountRef = useRef(0);
  const toggleTimerRef = useRef<ReturnType<typeof setTimeout>>(undefined);

  const { boughtCount, downloadedCount, progressTotal, readCount } = useMemo(() => ({
    boughtCount: countCoveredTomes(optimisticTomes, (t) => t.bought),
    downloadedCount: countCoveredTomes(optimisticTomes, (t) => t.downloaded),
    progressTotal: Math.max(comic?.latestPublishedIssue ?? 0, countCoveredTomes(optimisticTomes)),
    readCount: countCoveredTomes(optimisticTomes, (t) => t.read),
  }), [optimisticTomes, comic?.latestPublishedIssue]);

  useEffect(() => {
    if (comic?.tomes) {
      setOptimisticTomes(comic.tomes);
    }
  }, [comic?.tomes]);

  useEffect(() => {
    return () => clearTimeout(toggleTimerRef.current);
  }, []);

  const handleToggleTome = useCallback(
    (tome: Tome, field: "bought" | "downloaded" | "onNas" | "read") => {
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
              prev.map((t) => (t.id === tome.id ? { ...t, [field]: tome[field] } : t)),
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
                  count === 1 ? "1 tome mis à jour" : `${count} tomes mis à jour`,
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

  const handleToggleAllTomes = useCallback(
    (field: "bought" | "downloaded" | "onNas" | "read") => {
      const allChecked = optimisticTomes.every((t) => t[field]);
      const targetValue = !allChecked;

      const tomesToUpdate = optimisticTomes.filter((t) => t[field] !== targetValue);
      if (tomesToUpdate.length === 0) return;

      // Optimistic update: batch all tomes at once
      setOptimisticTomes((prev) =>
        prev.map((t) => ({ ...t, [field]: targetValue })),
      );

      if (navigator.onLine) {
        toast.success(`${tomesToUpdate.length} tomes mis à jour`, { duration: 1500 });
      }

      // Fire individual PATCH mutations for tomes that need changing
      for (const tome of tomesToUpdate) {
        updateTome.mutate(
          { id: tome.id, [field]: targetValue },
          {
            onError: () => {
              setOptimisticTomes((prev) =>
                prev.map((t) => (t.id === tome.id ? { ...t, [field]: tome[field] } : t)),
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
      <div className="mx-auto max-w-4xl space-y-6" data-testid="comic-detail-skeleton">
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

  const coverSrc = getCoverSrc(comic);
  const showProgress = !comic.isOneShot && optimisticTomes.length > 0;

  return (
    <div className="mx-auto max-w-4xl space-y-6">
      {/* Header */}
      <div className="flex items-center gap-3">
        <button aria-label="Retour" className="inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-lg text-text-muted hover:text-text-secondary" onClick={() => navigate(-1)} type="button">
          <ArrowLeft className="h-5 w-5" />
        </button>
        <h1 className="flex-1 text-xl font-bold text-text-primary">
          {comic._syncPending && <SyncPendingIndicator className="mr-1.5" />}
          {comic.title}
        </h1>
      </div>

      {/* Content */}
      <div className="flex flex-col gap-6 md:flex-row">
        {/* Cover */}
        <div className="w-full md:w-48">
          <img
            alt={comic.title}
            className={`w-full max-h-64 md:max-h-none object-contain rounded-lg shadow${coverSrc ? " cursor-pointer" : ""}`}
            onClick={coverSrc ? () => setLightboxOpen(true) : undefined}
            src={coverSrc ?? ComicTypePlaceholder[comic.type]}
          />
        </div>

        {/* Info */}
        <div className="flex-1 space-y-3">
          <div className="flex flex-wrap gap-2">
            <span className="rounded-full bg-primary-100 px-3 py-1 text-sm font-medium text-primary-700 dark:bg-primary-950/30 dark:text-primary-400">
              {ComicTypeLabel[comic.type]}
            </span>
            <span className={`rounded-full px-3 py-1 text-sm font-medium ${ComicStatusColor[comic.status]}`}>
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
                <dd className="text-text-secondary">{comic.authors.map((a) => a.name).join(", ")}</dd>
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
                  {new Date(comic.publishedDate).toLocaleDateString("fr-FR", { day: "numeric", month: "long", year: "numeric" })}
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
                      (mis à jour {formatRelativeDate(comic.latestPublishedIssueUpdatedAt)})
                    </span>
                  )}
                </dd>
              </>
            )}
            {(comic.defaultTomeBought || comic.defaultTomeDownloaded || comic.defaultTomeRead) && (
              <>
                <dt className="font-medium text-text-secondary">Nouveaux tomes</dt>
                <dd className="text-text-secondary">
                  {[
                    comic.defaultTomeBought && "achetés",
                    comic.defaultTomeDownloaded && "téléchargés",
                    comic.defaultTomeRead && "lus",
                  ].filter(Boolean).join(", ")}
                </dd>
              </>
            )}
          </dl>
          {comic.description && (
            <div>
              <h3 className="text-sm font-medium text-text-secondary">Description</h3>
              <p className="mt-1 text-sm leading-relaxed text-text-secondary">{comic.description}</p>
            </div>
          )}
        </div>
      </div>

      {/* Progression */}
      {showProgress && (
        <div className="grid gap-3 sm:grid-cols-3">
          <ProgressBar color="bg-primary-600" current={boughtCount} label="Achetés" total={progressTotal} />
          <ProgressBar color="bg-green-500" current={readCount} label="Lus" total={progressTotal} />
          <ProgressBar color="bg-blue-500" current={downloadedCount} label="Téléchargés" total={progressTotal} />
        </div>
      )}

      {/* Tomes */}
      {!comic.isOneShot && optimisticTomes.length > 0 && (
        <div>
          <h2 className="mb-3 text-lg font-semibold text-text-primary">
            Tomes ({optimisticTomes.length})
          </h2>
          <div className="overflow-x-auto rounded-lg border border-surface-border">
            <table className="w-full text-sm">
              <thead className="bg-surface-tertiary">
                <tr>
                  <th className="px-4 py-2 text-left font-medium text-text-secondary">#</th>
                  <th className="px-4 py-2 text-left font-medium text-text-secondary">Titre</th>
                  {(["bought", "downloaded", "read", "onNas"] as const).map((field) => (
                    <th className="px-4 py-2 text-center font-medium text-text-secondary" key={field}>
                      <div className="flex flex-col items-center gap-1">
                        <span>{field === "bought" ? "Acheté" : field === "downloaded" ? "Téléchargé" : field === "read" ? "Lu" : "NAS"}</span>
                        <HeaderCheckbox field={field} onChange={() => handleToggleAllTomes(field)} tomes={optimisticTomes} />
                      </div>
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-surface-border">
                {optimisticTomes.map((tome) => (
                  <tr className="hover:bg-surface-tertiary/50" key={tome.id}>
                    <td className="px-4 py-2 font-medium text-text-primary">
                      {tome._syncPending && <SyncPendingIndicator className="mr-1" />}
                      {tome.isHorsSerie ? "HS" : ""}{tome.tomeEnd ? `${tome.number}-${tome.tomeEnd}` : tome.number}
                    </td>
                    <td className="px-4 py-2 text-text-secondary">{tome.title ?? "\u2014"}</td>
                    {(["bought", "downloaded", "read", "onNas"] as const).map((field) => (
                      <td className="px-4 py-2 text-center" key={field}>
                        <label className="inline-flex min-h-[44px] min-w-[44px] cursor-pointer items-center justify-center">
                          <input
                            aria-label={`Tome ${tome.tomeEnd ? `${tome.number}-${tome.tomeEnd}` : tome.number} ${field === "bought" ? "acheté" : field === "downloaded" ? "téléchargé" : field === "read" ? "lu" : "NAS"}`}
                            checked={tome[field]}
                            className="h-5 w-5 cursor-pointer accent-primary-600"
                            onChange={() => handleToggleTome(tome, field)}
                            type="checkbox"
                          />
                        </label>
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Sticky action bar */}
      <div className="sticky bottom-[var(--bottom-nav-h)] z-40 flex justify-center gap-3 border-t border-surface-border bg-surface-primary px-4 py-3">
        <Link
          className="flex items-center gap-2 rounded-lg bg-primary-600 px-5 py-2.5 text-base font-medium text-white hover:bg-primary-700"
          to={`/comic/${comic.id}/edit`}
          viewTransition
        >
          <Edit className="h-5 w-5" />
          Modifier
        </Link>
        {comic.status === ComicStatus.BUYING && comic.amazonUrl && (
          <a
            className="flex items-center gap-2 rounded-lg bg-amber-600 px-5 py-2.5 text-base font-medium text-white hover:bg-amber-700"
            href={comic.amazonUrl}
            rel="noopener noreferrer"
            target="_blank"
          >
            <ExternalLink className="h-5 w-5" />
            Amazon
          </a>
        )}
        <button
          className="flex items-center gap-2 rounded-lg border border-red-600 px-5 py-2.5 text-base font-medium text-red-600 hover:bg-red-50 dark:text-red-400 dark:border-red-400 dark:hover:bg-red-950/30"
          onClick={() => {
            const seriesId = comic.id;
            deleteComic.mutate({ id: seriesId }, {
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
            });
          }}
          type="button"
        >
          <Trash2 className="h-5 w-5" />
          Supprimer
        </button>
      </div>

      {coverSrc && (
        <CoverLightbox
          onClose={() => setLightboxOpen(false)}
          open={lightboxOpen}
          src={coverSrc}
          title={comic.title}
        />
      )}
    </div>
  );
}
