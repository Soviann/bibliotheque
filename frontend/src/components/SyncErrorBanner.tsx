import { AlertTriangle, ChevronDown, ChevronUp, X } from "lucide-react";
import { useState } from "react";
import { Link } from "react-router-dom";
import { useSyncFailures } from "../hooks/useSyncFailures";
import type { SyncFailure } from "../services/offlineQueue";

const operationLabels: Record<string, string> = {
  create: "Création",
  delete: "Suppression",
  update: "Mise à jour",
};

const resourceLabels: Record<string, string> = {
  comic_series: "série",
  tome: "tome",
};

const fieldLabels: Record<string, string> = {
  authors: "Auteurs",
  bought: "Acheté",
  coverUrl: "Couverture",
  description: "Description",
  downloaded: "Téléchargé",
  isbn: "ISBN",
  isOneShot: "One-shot",
  latestPublishedIssue: "Dernier tome paru",
  number: "Numéro",
  onNas: "NAS",
  publisher: "Éditeur",
  read: "Lu",
  status: "Statut",
  title: "Titre",
  tomeEnd: "Fin",
  tomes: "Tomes",
  type: "Type",
};

function formatValue(value: unknown): string {
  if (value === null || value === undefined) return "—";
  if (typeof value === "boolean") return value ? "Oui" : "Non";
  if (Array.isArray(value)) return `${value.length} élément(s)`;
  return String(value);
}

function getEditLink(failure: SyncFailure): string | null {
  if (failure.operation === "delete") return null;
  if (failure.resourceType === "comic_series" && failure.resourceId) {
    return `/comic/${failure.resourceId}/edit?syncFailureId=${failure.id}`;
  }
  if (failure.resourceType === "tome" && failure.parentResourceId) {
    return `/comic/${failure.parentResourceId}/edit?syncFailureId=${failure.id}`;
  }
  return null;
}

function PayloadDetails({ payload }: { payload: Record<string, unknown> }) {
  const entries = Object.entries(payload)
    .filter(([key]) => !key.startsWith("_") && key !== "id")
    .sort(([a], [b]) => a.localeCompare(b));

  if (entries.length === 0) return null;

  return (
    <dl className="mt-1 grid grid-cols-[auto_1fr] gap-x-3 gap-y-0.5 text-xs">
      {entries.map(([key, value]) => (
        <div className="contents" key={key}>
          <dt className="font-medium text-red-700 dark:text-red-300">
            {fieldLabels[key] ?? key}
          </dt>
          <dd className="truncate text-red-600 dark:text-red-400">
            {formatValue(value)}
          </dd>
        </div>
      ))}
    </dl>
  );
}

export default function SyncErrorBanner() {
  const { failures, removeSyncFailure } = useSyncFailures();
  const [expandedId, setExpandedId] = useState<number | null>(null);

  if (failures.length === 0) return null;

  return (
    <div className="border-b border-red-200 bg-red-50 px-4 py-2 dark:border-red-900 dark:bg-red-950/30">
      <div className="flex items-center gap-2 text-sm font-medium text-red-700 dark:text-red-400">
        <AlertTriangle className="h-4 w-4 shrink-0" />
        <span>Erreurs de synchronisation ({failures.length})</span>
      </div>
      <ul className="mt-1 space-y-1">
        {failures.map((failure) => {
          const editLink = getEditLink(failure);
          const isExpanded = expandedId === failure.id;

          return (
            <li className="text-sm text-red-600 dark:text-red-400" key={failure.id}>
              <div className="flex items-center gap-2">
                <button
                  className="flex flex-1 items-center gap-1 text-left"
                  onClick={() => setExpandedId(isExpanded ? null : failure.id!)}
                  type="button"
                >
                  {isExpanded
                    ? <ChevronUp className="h-3.5 w-3.5 shrink-0" />
                    : <ChevronDown className="h-3.5 w-3.5 shrink-0" />}
                  <span className="truncate">
                    {operationLabels[failure.operation] ?? failure.operation}{" "}
                    {resourceLabels[failure.resourceType] ?? failure.resourceType}
                    {" — "}
                    {failure.error}
                  </span>
                </button>
                {editLink && (
                  <Link
                    className="shrink-0 text-xs font-medium text-red-700 underline hover:text-red-900 dark:text-red-300"
                    to={editLink}
                  >
                    Modifier
                  </Link>
                )}
                <button
                  className="shrink-0 rounded p-0.5 text-red-500 hover:bg-red-100 dark:hover:bg-red-900/40"
                  onClick={() => void removeSyncFailure(failure.id!)}
                  title="Ignorer"
                  type="button"
                >
                  <X className="h-3.5 w-3.5" />
                </button>
              </div>
              {isExpanded && <PayloadDetails payload={failure.payload} />}
            </li>
          );
        })}
      </ul>
    </div>
  );
}
