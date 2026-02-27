import { ArrowLeft, Edit, Trash2 } from "lucide-react";
import { useState } from "react";
import { Link, useNavigate, useParams } from "react-router-dom";
import { toast } from "sonner";
import ConfirmModal from "../components/ConfirmModal";
import { useComic } from "../hooks/useComic";
import { useDeleteComic } from "../hooks/useDeleteComic";
import { ComicStatusLabel, ComicTypeLabel } from "../types/enums";

export default function ComicDetail() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { data: comic, isLoading } = useComic(id ? Number(id) : undefined);
  const deleteComic = useDeleteComic();
  const [showDelete, setShowDelete] = useState(false);

  if (isLoading) {
    return <div className="py-12 text-center text-slate-400">Chargement…</div>;
  }

  if (!comic) {
    return <div className="py-12 text-center text-slate-400">Série introuvable</div>;
  }

  const coverSrc = comic.coverUrl ?? (comic.coverImage ? `/uploads/covers/${comic.coverImage}` : null);

  return (
    <div className="mx-auto max-w-4xl space-y-6">
      {/* Header */}
      <div className="flex items-center gap-3">
        <button className="text-slate-400 hover:text-slate-600" onClick={() => navigate(-1)} type="button">
          <ArrowLeft className="h-5 w-5" />
        </button>
        <h1 className="flex-1 text-xl font-bold text-slate-900">{comic.title}</h1>
        <Link className="rounded-lg bg-primary-100 p-2 text-primary-700 hover:bg-primary-200" to={`/comic/${comic.id}/edit`}>
          <Edit className="h-4 w-4" />
        </Link>
        <button
          className="rounded-lg bg-red-100 p-2 text-red-700 hover:bg-red-200"
          onClick={() => setShowDelete(true)}
          type="button"
        >
          <Trash2 className="h-4 w-4" />
        </button>
      </div>

      {/* Content */}
      <div className="flex flex-col gap-6 md:flex-row">
        {/* Cover */}
        {coverSrc && (
          <div className="w-full md:w-48">
            <img alt={comic.title} className="w-full rounded-lg shadow" src={coverSrc} />
          </div>
        )}

        {/* Info */}
        <div className="flex-1 space-y-3">
          <div className="flex flex-wrap gap-2">
            <span className="rounded-full bg-primary-100 px-3 py-1 text-sm font-medium text-primary-700">
              {ComicTypeLabel[comic.type]}
            </span>
            <span className="rounded-full bg-slate-100 px-3 py-1 text-sm font-medium text-slate-700">
              {ComicStatusLabel[comic.status]}
            </span>
            {comic.isOneShot && (
              <span className="rounded-full bg-amber-100 px-3 py-1 text-sm font-medium text-amber-700">
                One-shot
              </span>
            )}
          </div>

          {comic.authors.length > 0 && (
            <p className="text-sm text-slate-600">
              <span className="font-medium">Auteurs :</span>{" "}
              {comic.authors.map((a) => a.name).join(", ")}
            </p>
          )}
          {comic.publisher && (
            <p className="text-sm text-slate-600">
              <span className="font-medium">Éditeur :</span> {comic.publisher}
            </p>
          )}
          {comic.description && (
            <p className="text-sm leading-relaxed text-slate-600">{comic.description}</p>
          )}
        </div>
      </div>

      {/* Tomes */}
      {!comic.isOneShot && comic.tomes.length > 0 && (
        <div>
          <h2 className="mb-3 text-lg font-semibold text-slate-900">
            Tomes ({comic.tomes.length})
          </h2>
          <div className="overflow-x-auto rounded-lg border border-slate-200">
            <table className="w-full text-sm">
              <thead className="bg-slate-50">
                <tr>
                  <th className="px-4 py-2 text-left font-medium text-slate-600">#</th>
                  <th className="px-4 py-2 text-left font-medium text-slate-600">Titre</th>
                  <th className="px-4 py-2 text-center font-medium text-slate-600">Acheté</th>
                  <th className="px-4 py-2 text-center font-medium text-slate-600">Téléchargé</th>
                  <th className="px-4 py-2 text-center font-medium text-slate-600">Lu</th>
                  <th className="px-4 py-2 text-center font-medium text-slate-600">NAS</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {comic.tomes.map((tome) => (
                  <tr className="hover:bg-slate-50" key={tome.id}>
                    <td className="px-4 py-2 font-medium">{tome.number}</td>
                    <td className="px-4 py-2 text-slate-600">{tome.title ?? "—"}</td>
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
