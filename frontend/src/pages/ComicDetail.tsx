import { ArrowLeft, Edit, Trash2 } from "lucide-react";
import { useCallback, useEffect, useState } from "react";
import { Link, useNavigate, useParams } from "react-router-dom";
import { toast } from "sonner";
import ConfirmModal from "../components/ConfirmModal";
import ProgressBar from "../components/ProgressBar";
import SkeletonBox from "../components/SkeletonBox";
import type { Tome } from "../types/api";
import { useComic } from "../hooks/useComic";
import { useDeleteComic } from "../hooks/useDeleteComic";
import { useUpdateTome } from "../hooks/useUpdateTome";
import { ComicStatusLabel, ComicTypeLabel } from "../types/enums";

export default function ComicDetail() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { data: comic, isLoading } = useComic(id ? Number(id) : undefined);
  const deleteComic = useDeleteComic();
  const updateTome = useUpdateTome();
  const [showDelete, setShowDelete] = useState(false);
  const [optimisticTomes, setOptimisticTomes] = useState<Tome[]>([]);

  useEffect(() => {
    if (comic?.tomes) {
      setOptimisticTomes(comic.tomes);
    }
  }, [comic?.tomes]);

  const handleToggleTome = useCallback(
    (tome: Tome, field: "bought" | "downloaded" | "onNas" | "read") => {
      const newValue = !tome[field];
      const fieldLabel = field === "bought" ? "Acheté" : field === "downloaded" ? "Téléchargé" : field === "read" ? "Lu" : "NAS";

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
              toast.success(`Tome ${tome.number} — ${fieldLabel} ${newValue ? "activé" : "désactivé"}`, { duration: 1500 });
            }
          },
        },
      );
    },
    [updateTome],
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
    return <div className="py-12 text-center text-text-muted">Série introuvable</div>;
  }

  const coverSrc = comic.coverUrl ?? (comic.coverImage ? `/uploads/covers/${comic.coverImage}` : null);
  const showProgress = !comic.isOneShot && optimisticTomes.length > 0;
  const progressTotal = comic.latestPublishedIssue ?? optimisticTomes.length;
  const boughtCount = optimisticTomes.filter((t) => t.bought).length;
  const readCount = optimisticTomes.filter((t) => t.read).length;
  const downloadedCount = optimisticTomes.filter((t) => t.downloaded).length;

  return (
    <div className="mx-auto max-w-4xl space-y-6">
      {/* Header */}
      <div className="flex items-center gap-3">
        <button aria-label="Retour" className="text-text-muted hover:text-text-secondary" onClick={() => navigate(-1)} type="button">
          <ArrowLeft className="h-5 w-5" />
        </button>
        <h1 className="flex-1 text-xl font-bold text-text-primary">{comic.title}</h1>
      </div>

      {/* Content */}
      <div className="flex flex-col gap-6 md:flex-row">
        {/* Cover */}
        <div className="w-full md:w-48">
          <img
            alt={comic.title}
            className="w-full rounded-lg shadow"
            src={coverSrc ?? "/placeholder-cover.png"}
          />
        </div>

        {/* Info */}
        <div className="flex-1 space-y-3">
          <div className="flex flex-wrap gap-2">
            <span className="rounded-full bg-primary-100 px-3 py-1 text-sm font-medium text-primary-700 dark:bg-primary-950/30 dark:text-primary-400">
              {ComicTypeLabel[comic.type]}
            </span>
            <span className="rounded-full bg-surface-tertiary px-3 py-1 text-sm font-medium text-text-secondary">
              {ComicStatusLabel[comic.status]}
            </span>
            {comic.isOneShot && (
              <span className="rounded-full bg-amber-100 px-3 py-1 text-sm font-medium text-amber-700 dark:bg-amber-950/30 dark:text-amber-400">
                One-shot
              </span>
            )}
          </div>

          {comic.authors.length > 0 && (
            <p className="text-sm text-text-secondary">
              <span className="font-medium">Auteurs :</span>{" "}
              {comic.authors.map((a) => a.name).join(", ")}
            </p>
          )}
          {comic.publisher && (
            <p className="text-sm text-text-secondary">
              <span className="font-medium">Éditeur :</span> {comic.publisher}
            </p>
          )}
          {comic.description && (
            <p className="text-sm leading-relaxed text-text-secondary">{comic.description}</p>
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
                  <th className="px-4 py-2 text-center font-medium text-text-secondary">Acheté</th>
                  <th className="px-4 py-2 text-center font-medium text-text-secondary">Téléchargé</th>
                  <th className="px-4 py-2 text-center font-medium text-text-secondary">Lu</th>
                  <th className="px-4 py-2 text-center font-medium text-text-secondary">NAS</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-surface-border">
                {optimisticTomes.map((tome) => (
                  <tr className="hover:bg-surface-tertiary/50" key={tome.id}>
                    <td className="px-4 py-2 font-medium text-text-primary">{tome.number}</td>
                    <td className="px-4 py-2 text-text-secondary">{tome.title ?? "\u2014"}</td>
                    {(["bought", "downloaded", "read", "onNas"] as const).map((field) => (
                      <td className="px-4 py-2 text-center" key={field}>
                        <label className="inline-flex min-h-[44px] min-w-[44px] cursor-pointer items-center justify-center">
                          <input
                            aria-label={`Tome ${tome.number} ${field === "bought" ? "acheté" : field === "downloaded" ? "téléchargé" : field === "read" ? "lu" : "NAS"}`}
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
        <button
          className="flex items-center gap-2 rounded-lg bg-red-600 px-5 py-2.5 text-base font-medium text-white hover:bg-red-700"
          onClick={() => setShowDelete(true)}
          type="button"
        >
          <Trash2 className="h-5 w-5" />
          Supprimer
        </button>
        <Link
          className="flex items-center gap-2 rounded-lg bg-primary-600 px-5 py-2.5 text-base font-medium text-white hover:bg-primary-700"
          to={`/comic/${comic.id}/edit`}
        >
          <Edit className="h-5 w-5" />
          Modifier
        </Link>
      </div>

      <ConfirmModal
        confirmLabel="Supprimer"
        description="Cette série sera déplacée vers la corbeille."
        onClose={() => setShowDelete(false)}
        onConfirm={() => {
          deleteComic.mutate({ id: comic.id }, {
            onSuccess: () => {
              toast.success("Série supprimée");
              navigate("/");
            },
          });
        }}
        open={showDelete}
        title="Supprimer cette série ?"
      />
    </div>
  );
}
