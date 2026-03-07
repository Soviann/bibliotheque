import { Eye, X } from "lucide-react";
import type { MergeGroup } from "../types/api";

interface MergeGroupCardProps {
  group: MergeGroup;
  onPreview: (group: MergeGroup) => void;
  onSkip: (group: MergeGroup) => void;
}

export default function MergeGroupCard({
  group,
  onPreview,
  onSkip,
}: MergeGroupCardProps) {
  return (
    <div className="rounded-lg border border-surface-border bg-surface-primary p-4 shadow-sm">
      <div className="flex items-center justify-between gap-3">
        <h3 className="font-semibold text-text-primary">{group.suggestedTitle}</h3>
        <span className="shrink-0 rounded-full bg-primary-100 px-2.5 py-0.5 text-xs font-medium text-primary-700 dark:bg-primary-950/30 dark:text-primary-400">
          {group.entries.length} séries
        </span>
      </div>

      <ul className="mt-3 space-y-1.5">
        {group.entries.map((entry) => (
          <li
            className="flex items-center justify-between text-sm text-text-secondary"
            key={entry.seriesId}
          >
            <span>{entry.originalTitle}</span>
            {entry.suggestedTomeNumber !== null && (
              <span className="shrink-0 rounded bg-surface-tertiary px-2 py-0.5 text-xs text-text-muted">
                Tome {entry.suggestedTomeNumber}
              </span>
            )}
          </li>
        ))}
      </ul>

      <div className="mt-4 flex items-center justify-end gap-2">
        <button
          className="rounded-lg px-3 py-1.5 text-sm font-medium text-text-secondary hover:bg-surface-tertiary"
          onClick={() => onSkip(group)}
          type="button"
        >
          <span className="flex items-center gap-1.5">
            <X className="h-4 w-4" />
            Ignorer
          </span>
        </button>
        <button
          className="flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-700"
          onClick={() => onPreview(group)}
          type="button"
        >
          <Eye className="h-4 w-4" />
          Aperçu et fusion
        </button>
      </div>
    </div>
  );
}
