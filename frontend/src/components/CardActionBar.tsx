import { Edit, Trash2 } from "lucide-react";
import type { ComicSeries } from "../types/api";

interface CardActionBarProps {
  comic: ComicSeries | null;
  onClose: () => void;
  onDelete: (comic: ComicSeries) => void;
  onEdit: (comic: ComicSeries) => void;
}

export default function CardActionBar({ comic, onClose, onDelete, onEdit }: CardActionBarProps) {
  if (!comic) return null;

  return (
    <>
      {/* Overlay */}
      <div
        className="fixed inset-0 z-[60] bg-black/30"
        data-testid="card-action-overlay"
        onClick={onClose}
      />

      {/* Barre d'actions */}
      <div className="fixed inset-x-0 bottom-0 z-[60] border-t border-surface-border bg-surface-primary px-4 py-3 pb-safe">
        <p className="mb-2 truncate text-sm font-semibold text-text-primary">{comic.title}</p>
        <div className="flex gap-2">
          <button
            className="flex flex-1 items-center justify-center gap-2 rounded-lg bg-primary-50 py-3 text-sm font-medium text-primary-600 dark:bg-primary-950/30"
            onClick={() => onEdit(comic)}
            type="button"
          >
            <Edit className="h-4 w-4" />
            Modifier
          </button>
          <button
            className="flex flex-1 items-center justify-center gap-2 rounded-lg bg-red-50 py-3 text-sm font-medium text-red-600 dark:bg-red-950/30"
            onClick={() => onDelete(comic)}
            type="button"
          >
            <Trash2 className="h-4 w-4" />
            Supprimer
          </button>
        </div>
      </div>
    </>
  );
}
