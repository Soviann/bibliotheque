import { ArrowLeft, Edit, Trash2 } from "lucide-react";
import { useState } from "react";
import { Link, useNavigate, useParams } from "react-router-dom";
import { toast } from "sonner";
import ConfirmModal from "../components/ConfirmModal";
import { useComic } from "../hooks/useComic";
import { useDeleteComic } from "../hooks/useDeleteComic";
import { ComicStatus, ComicStatusLabel, ComicTypeLabel } from "../types/enums";

export default function ComicDetail() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { data: comic, isLoading } = useComic(id ? Number(id) : undefined);
  const deleteComic = useDeleteComic();
  const [showDelete, setShowDelete] = useState(false);

  if (isLoading) {
    return <div className="py-12 text-center text-text-muted">Chargement…</div>;
  }

  if (!comic) {
    return <div className="py-12 text-center text-text-muted">Série introuvable</div>;
  }

  const coverSrc = comic.coverUrl ?? (comic.coverImage ? `/uploads/covers/${comic.coverImage}` : null);

  return (
    <div className="mx-auto max-w-4xl space-y-6">
      {/* Header */}
      <div className="flex items-center gap-3">
        <Link className="text-text-muted hover:text-text-secondary" to={comic.status === ComicStatus.WISHLIST ? "/wishlist" : "/"}>
          <ArrowLeft className="h-5 w-5" />
        </Link>
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

      {/* Tomes */}
      {!comic.isOneShot && comic.tomes.length > 0 && (
        <div>
          <h2 className="mb-3 text-lg font-semibold text-text-primary">
            Tomes ({comic.tomes.length})
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
                {comic.tomes.map((tome) => (
                  <tr className="hover:bg-surface-tertiary/50" key={tome.id}>
                    <td className="px-4 py-2 font-medium text-text-primary">{tome.number}</td>
                    <td className="px-4 py-2 text-text-secondary">{tome.title ?? "—"}</td>
                    <td className="px-4 py-2 text-center">{tome.bought ? "✓" : "—"}</td>
                    <td className="px-4 py-2 text-center">{tome.downloaded ? "✓" : "—"}</td>
                    <td className="px-4 py-2 text-center">{tome.read ? "✓" : "—"}</td>
                    <td className="px-4 py-2 text-center">{tome.onNas ? "✓" : "—"}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Sticky action bar */}
      <div className="fixed bottom-14 left-0 right-0 z-40 flex justify-center gap-3 border-t border-surface-border bg-surface-primary px-4 py-3">
        <Link
          className="flex items-center gap-2 rounded-lg bg-primary-600 px-5 py-2.5 text-base font-medium text-white hover:bg-primary-700"
          to={`/comic/${comic.id}/edit`}
        >
          <Edit className="h-5 w-5" />
          Modifier
        </Link>
        <button
          className="flex items-center gap-2 rounded-lg bg-red-600 px-5 py-2.5 text-base font-medium text-white hover:bg-red-700"
          onClick={() => setShowDelete(true)}
          type="button"
        >
          <Trash2 className="h-5 w-5" />
          Supprimer
        </button>
      </div>

      <ConfirmModal
        confirmLabel="Supprimer"
        description="Cette série sera déplacée vers la corbeille."
        onClose={() => setShowDelete(false)}
        onConfirm={() => {
          deleteComic.mutate(comic.id, {
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
