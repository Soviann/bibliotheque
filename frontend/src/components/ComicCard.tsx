import { Edit, Trash2 } from "lucide-react";
import { Link, useNavigate } from "react-router-dom";
import type { ComicSeries } from "../types/api";
import { ComicTypeLabel } from "../types/enums";
import ProgressBar from "./ProgressBar";

interface ComicCardProps {
  comic: ComicSeries;
  onDelete?: (comic: ComicSeries) => void;
}

export default function ComicCard({ comic, onDelete }: ComicCardProps) {
  const navigate = useNavigate();
  const coverSrc = comic.coverUrl ?? (comic.coverImage ? `/uploads/covers/${comic.coverImage}` : null);
  const tomeCount = comic.tomes?.length ?? 0;
  const boughtCount = comic.tomes?.filter((t) => t.bought).length ?? 0;
  const total = comic.latestPublishedIssue ?? tomeCount;
  const showProgress = !comic.isOneShot && tomeCount > 0;

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
      <div className="p-2">
        <h3 className="truncate text-sm font-semibold text-text-primary">{comic.title}</h3>
        <p className="truncate text-xs text-text-muted">
          {ComicTypeLabel[comic.type]}
          {!comic.isOneShot && ` · ${tomeCount} t.`}
        </p>

        {showProgress && (
          <div className="mt-1.5">
            <ProgressBar compact current={boughtCount} label="Progression d'achat" total={total} />
          </div>
        )}

        {/* Actions */}
        <div className="mt-2 flex gap-1 border-t border-surface-border pt-2">
          <button
            className="flex flex-1 items-center justify-center rounded-lg py-1.5 text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-950/30"
            onClick={(e) => {
              e.preventDefault();
              navigate(`/comic/${comic.id}/edit`);
            }}
            title="Modifier"
            type="button"
          >
            <Edit className="h-4 w-4" />
          </button>
          {onDelete && (
            <button
              className="flex flex-1 items-center justify-center rounded-lg py-1.5 text-red-500 hover:bg-red-50 dark:hover:bg-red-950/30"
              onClick={(e) => {
                e.preventDefault();
                onDelete(comic);
              }}
              title="Supprimer"
              type="button"
            >
              <Trash2 className="h-4 w-4" />
            </button>
          )}
        </div>
      </div>
    </Link>
  );
}
