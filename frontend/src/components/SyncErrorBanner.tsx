import { AlertTriangle, RefreshCw, X } from "lucide-react";
import { Link } from "react-router-dom";
import { useSyncFailures } from "../hooks/useSyncFailures";

const operationLabels: Record<string, string> = {
  create: "Création",
  delete: "Suppression",
  update: "Mise à jour",
};

const resourceLabels: Record<string, string> = {
  comic_series: "série",
  tome: "tome",
};

export default function SyncErrorBanner() {
  const { failures, removeSyncFailure, resolveSyncFailure } = useSyncFailures();

  if (failures.length === 0) return null;

  return (
    <div className="border-b border-red-200 bg-red-50 px-4 py-2 dark:border-red-900 dark:bg-red-950/30">
      <div className="flex items-center gap-2 text-sm font-medium text-red-700 dark:text-red-400">
        <AlertTriangle className="h-4 w-4 shrink-0" />
        <span>Erreurs de synchronisation ({failures.length})</span>
      </div>
      <ul className="mt-1 space-y-1">
        {failures.map((failure) => (
          <li className="flex items-center gap-2 text-sm text-red-600 dark:text-red-400" key={failure.id}>
            <span className="flex-1 truncate">
              {operationLabels[failure.operation] ?? failure.operation}{" "}
              {resourceLabels[failure.resourceType] ?? failure.resourceType}
              {" — "}
              {failure.error}
            </span>
            {failure.resourceType === "comic_series" && failure.resourceId && (
              <Link
                className="shrink-0 text-xs font-medium text-red-700 underline hover:text-red-900 dark:text-red-300"
                to={`/comic/${failure.resourceId}/edit?syncFailureId=${failure.id}`}
              >
                Modifier
              </Link>
            )}
            <button
              className="shrink-0 rounded p-0.5 text-red-500 hover:bg-red-100 dark:hover:bg-red-900/40"
              onClick={() => void resolveSyncFailure(failure.id!)}
              title="Réessayer"
              type="button"
            >
              <RefreshCw className="h-3.5 w-3.5" />
            </button>
            <button
              className="shrink-0 rounded p-0.5 text-red-500 hover:bg-red-100 dark:hover:bg-red-900/40"
              onClick={() => void removeSyncFailure(failure.id!)}
              title="Ignorer"
              type="button"
            >
              <X className="h-3.5 w-3.5" />
            </button>
          </li>
        ))}
      </ul>
    </div>
  );
}
