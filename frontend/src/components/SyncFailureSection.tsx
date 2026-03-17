import { AlertTriangle, X } from "lucide-react";
import type { SyncFailure } from "../services/offlineQueue";
import { fieldLabels, formatSyncValue, operationLabels } from "../utils/syncLabels";

interface SyncFailureSectionProps {
  failure: SyncFailure;
  onDismiss: () => void;
}

export default function SyncFailureSection({ failure, onDismiss }: SyncFailureSectionProps) {
  const entries = Object.entries(failure.payload)
    .filter(([key]) => !key.startsWith("_") && key !== "id")
    .sort(([a], [b]) => a.localeCompare(b));

  return (
    <div className="rounded-lg border border-amber-300 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950/30">
      <div className="flex items-start gap-2">
        <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400" />
        <div className="min-w-0 flex-1">
          <p className="text-sm font-medium text-amber-800 dark:text-amber-300">
            {operationLabels[failure.operation] ?? failure.operation} échouée — {failure.error}
          </p>
          <p className="mt-1 text-xs text-amber-700 dark:text-amber-400">
            Modifications tentées hors ligne :
          </p>
          <dl className="mt-1 grid grid-cols-[auto_1fr] gap-x-3 gap-y-0.5 text-xs">
            {entries.map(([key, value]) => (
              <div className="contents" key={key}>
                <dt className="font-medium text-amber-800 dark:text-amber-300">
                  {fieldLabels[key] ?? key}
                </dt>
                <dd className="truncate text-amber-700 dark:text-amber-400">
                  {formatSyncValue(value)}
                </dd>
              </div>
            ))}
          </dl>
          <p className="mt-2 text-xs text-amber-600 dark:text-amber-500">
            Enregistrez le formulaire pour résoudre automatiquement cette erreur.
          </p>
        </div>
        <button
          className="shrink-0 rounded p-1 text-amber-500 hover:bg-amber-100 dark:hover:bg-amber-900/40"
          onClick={onDismiss}
          title="Ignorer"
          type="button"
        >
          <X className="h-4 w-4" />
        </button>
      </div>
    </div>
  );
}
