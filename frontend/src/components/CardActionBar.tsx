import { Edit, Trash2 } from "lucide-react";
import { memo, useEffect, useRef } from "react";
import type { ComicSeries } from "../types/api";

interface CardActionBarProps {
  comic: ComicSeries | null;
  onClose: () => void;
  onDelete: (comic: ComicSeries) => void;
  onEdit: (comic: ComicSeries) => void;
}

export default memo(function CardActionBar({ comic, onClose, onDelete, onEdit }: CardActionBarProps) {
  const barRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!comic) return;

    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === "Escape") {
        onClose();
      }
    };

    document.addEventListener("keydown", handleKeyDown);
    return () => document.removeEventListener("keydown", handleKeyDown);
  }, [comic, onClose]);

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
      <div
        aria-label={`Actions pour ${comic.title}`}
        className="fixed inset-x-0 bottom-0 z-[60] rounded-t-2xl border-t border-surface-border bg-surface-primary px-4 py-3 dark:border-white/10 dark:bg-surface-elevated/95 dark:backdrop-blur-xl"
        ref={barRef}
        role="dialog"
      >
        <p className="mb-2 truncate text-sm font-semibold text-text-primary">{comic.title}</p>
        <div className="flex gap-2">
          <button
            className="flex flex-1 items-center justify-center gap-2 rounded-xl bg-primary-50 py-3 text-sm font-medium text-primary-600 dark:bg-primary-950/30"
            onClick={() => onEdit(comic)}
            type="button"
          >
            <Edit className="h-4 w-4" />
            Modifier
          </button>
          <button
            className="flex flex-1 items-center justify-center gap-2 rounded-xl bg-red-50 py-3 text-sm font-medium text-accent-danger dark:bg-red-950/30"
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
});
