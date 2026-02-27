import { RotateCcw, Trash2 } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";
import ConfirmModal from "../components/ConfirmModal";
import { usePermanentDelete, useRestoreComic, useTrash } from "../hooks/useTrash";
import type { ComicSeries } from "../types/api";

export default function Trash() {
  const { data, isLoading } = useTrash();
  const restoreComic = useRestoreComic();
  const permanentDelete = usePermanentDelete();
  const [deleteTarget, setDeleteTarget] = useState<ComicSeries | null>(null);

  const comics = data?.member ?? [];

  return (
    <div className="space-y-4">
      <h1 className="text-xl font-bold text-text-primary">Corbeille</h1>

      {isLoading ? (
        <div className="py-12 text-center text-text-muted">Chargement…</div>
      ) : comics.length === 0 ? (
        <div className="py-12 text-center text-text-muted">La corbeille est vide</div>
      ) : (
        <div className="space-y-2">
          {comics.map((comic) => (
            <div
              className="flex items-center gap-3 rounded-lg border border-surface-border bg-surface-primary p-3"
              key={comic.id}
            >
              <img
                alt={comic.title}
                className="h-12 w-9 rounded object-cover"
                src={comic.coverUrl ?? (comic.coverImage ? `/uploads/covers/${comic.coverImage}` : "/placeholder-cover.png")}
              />
              <span className="flex-1 font-medium text-text-primary">{comic.title}</span>
              <button
                className="rounded-lg bg-primary-100 p-2 text-primary-700 hover:bg-primary-200 dark:bg-primary-950/30 dark:text-primary-400 dark:hover:bg-primary-900/40"
                onClick={() => {
                  restoreComic.mutate(comic.id, {
                    onSuccess: () => toast.success(`${comic.title} restaurée`),
                  });
                }}
                title="Restaurer"
                type="button"
              >
                <RotateCcw className="h-4 w-4" />
              </button>
              <button
                className="rounded-lg bg-red-100 p-2 text-red-700 hover:bg-red-200 dark:bg-red-950/30 dark:text-red-400 dark:hover:bg-red-900/40"
                onClick={() => setDeleteTarget(comic)}
                title="Supprimer définitivement"
                type="button"
              >
                <Trash2 className="h-4 w-4" />
              </button>
            </div>
          ))}
        </div>
      )}

      <ConfirmModal
        confirmLabel="Supprimer définitivement"
        description="Cette action est irréversible. La série sera définitivement supprimée."
        onClose={() => setDeleteTarget(null)}
        onConfirm={() => {
          if (deleteTarget) {
            permanentDelete.mutate(deleteTarget.id, {
              onSuccess: () => toast.success(`${deleteTarget.title} supprimée définitivement`),
            });
          }
        }}
        open={deleteTarget !== null}
        title={`Supprimer ${deleteTarget?.title} ?`}
      />
    </div>
  );
}
