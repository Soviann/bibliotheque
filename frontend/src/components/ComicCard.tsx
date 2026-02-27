import { Edit, Trash2 } from "lucide-react";
import { Link, useNavigate } from "react-router-dom";
import type { ComicSeries } from "../types/api";
import { ComicTypeLabel } from "../types/enums";

interface ComicCardProps {
  comic: ComicSeries;
  onDelete?: (comic: ComicSeries) => void;
}

export default function ComicCard({ comic, onDelete }: ComicCardProps) {
  const navigate = useNavigate();
  const coverSrc = comic.coverUrl ?? (comic.coverImage ? `/uploads/covers/${comic.coverImage}` : null);
  const tomeCount = comic.tomes?.length ?? 0;

  return (
    <Link
      className="group block overflow-hidden rounded-xl border border-surface-border bg-surface-primary shadow-sm transition hover:shadow-md"
      to={`/comic/${comic.id}`}
    >
      {/* Cover */}
      <div className="aspect-[3/4] overflow-hidden bg-surface-tertiary">
        <img
          alt={comic.title}
          className="h-full w-full object-cover transition group-hover:scale-105"
          loading="lazy"
          src={coverSrc ?? "/placeholder-cover.png"}
        />
      </div>

      {/* Info */}
      <div className="flex items-start justify-between gap-1 p-2">
        <div className="min-w-0 flex-1">
          <h3 className="truncate text-sm font-semibold text-text-primary">{comic.title}</h3>
          <p className="truncate text-xs text-text-muted">
            {ComicTypeLabel[comic.type]}
            {!comic.isOneShot && ` · ${tomeCount} t.`}
          </p>
        </div>
        <div className="flex shrink-0 gap-0.5">
          <button
            className="rounded p-1 text-text-muted hover:bg-surface-tertiary hover:text-primary-600"
            onClick={(e) => {
              e.preventDefault();
              navigate(`/comic/${comic.id}/edit`);
            }}
            title="Modifier"
            type="button"
          >
            <Edit className="h-3.5 w-3.5" />
          </button>
          {onDelete && (
            <button
              className="rounded p-1 text-text-muted hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/30"
              onClick={(e) => {
                e.preventDefault();
                onDelete(comic);
              }}
              title="Supprimer"
              type="button"
            >
              <Trash2 className="h-3.5 w-3.5" />
            </button>
          )}
        </div>
      </div>
    </Link>
  );
}
