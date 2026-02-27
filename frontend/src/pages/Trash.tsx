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
      <h1 className="text-xl font-bold text-slate-900">Corbeille</h1>

      {isLoading ? (
        <div className="py-12 text-center text-slate-400">Chargement…</div>
      ) : comics.length === 0 ? (
        <div className="py-12 text-center text-slate-400">La corbeille est vide</div>
      ) : (
        <div className="space-y-2">
          {comics.map((comic) => (
            <div
              className="flex items-center gap-3 rounded-lg border border-slate-200 bg-white p-3"
              key={comic.id}
            >
              {comic.coverUrl || comic.coverImage ? (
                <img
                  alt={comic.title}
                  className="h-12 w-9 rounded object-cover"
                  src={comic.coverUrl ?? `/uploads/covers/${comic.coverImage}`}
                />
              ) : (
                <div className="flex h-12 w-9 items-center justify-center rounded bg-slate-100 text-xs text-slate-400">
                  ?
                </div>
              )}
              <span className="flex-1 font-medium text-slate-900">{comic.title}</span>
              <button
                className="rounded-lg bg-primary-100 p-2 text-primary-700 hover:bg-primary-200"
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
                className="rounded-lg bg-red-100 p-2 text-red-700 hover:bg-red-200"
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
