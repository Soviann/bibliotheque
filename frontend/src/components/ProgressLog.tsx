import { AlertCircle, CheckCircle, SkipForward } from "lucide-react";
import { useEffect, useRef } from "react";
import type { BatchLookupProgress } from "../types/api";
import ProgressBar from "./ProgressBar";

interface ProgressLogProps {
  progress: BatchLookupProgress[];
  total: number;
}

const statusConfig = {
  failed: { color: "text-red-500", icon: AlertCircle, label: "Erreur" },
  skipped: { color: "text-text-muted", icon: SkipForward, label: "Ignoré" },
  updated: {
    color: "text-green-600",
    icon: CheckCircle,
    label: "Mis à jour",
  },
} as const;

export default function ProgressLog({ progress, total }: ProgressLogProps) {
  const listRef = useRef<HTMLDivElement>(null);
  const current = progress.length;

  useEffect(() => {
    if (listRef.current) {
      listRef.current.scrollTop = listRef.current.scrollHeight;
    }
  }, [current]);

  return (
    <div className="space-y-3">
      <ProgressBar
        current={current}
        label="Progression du lookup"
        total={total}
      />

      <div
        className="max-h-80 space-y-1 overflow-y-auto rounded-lg border border-surface-border bg-surface-secondary p-3"
        ref={listRef}
      >
        {progress.map((entry, i) => {
          const config = statusConfig[entry.status];
          const Icon = config.icon;

          return (
            <div className="flex items-start gap-2 text-sm" key={i}>
              <Icon className={`mt-0.5 h-4 w-4 shrink-0 ${config.color}`} />
              <span className="text-text-primary">{entry.seriesTitle}</span>
              {entry.updatedFields.length > 0 && (
                <span className="text-xs text-text-muted">
                  ({entry.updatedFields.join(", ")})
                </span>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}
