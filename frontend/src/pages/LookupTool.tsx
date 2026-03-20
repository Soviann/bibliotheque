import { CheckCircle, Loader2, Search, Square } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";
import Breadcrumb from "../components/Breadcrumb";
import ProgressLog from "../components/ProgressLog";
import {
  useBatchLookup,
  useBatchLookupPreview,
} from "../hooks/useBatchLookup";
import { typeOptionsAll } from "../types/enums";

export default function LookupTool() {
  const [type, setType] = useState("");
  const [force, setForce] = useState(false);
  const [limit, setLimit] = useState(0);
  const [delay, setDelay] = useState(2);

  const { data: preview, isLoading: previewLoading } =
    useBatchLookupPreview(type || undefined, force);
  const { cancel, isRunning, progress, start, summary } = useBatchLookup();

  const handleStart = () => {
    start({
      delay,
      force,
      limit: limit > 0 ? limit : undefined,
      type: type || undefined,
    });
    toast.info("Lookup batch démarré");
  };

  const total = preview?.count ?? 0;

  return (
    <div className="mx-auto max-w-4xl px-4 py-6">
      <Breadcrumb items={[{ href: "/tools", label: "Outils" }, { label: "Lookup métadonnées" }]} />
      <h1 className="text-xl font-bold text-text-primary">
        Lookup métadonnées
      </h1>

      <div className="mt-4 space-y-3">
        <div className="flex flex-wrap items-center gap-3">
          <label className="text-sm text-text-secondary" htmlFor="lookup-type">
            Type
          </label>
          <select
            className="rounded-lg border border-surface-border bg-surface-primary px-3 py-1.5 text-sm text-text-primary focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
            disabled={isRunning}
            id="lookup-type"
            onChange={(e) => setType(e.target.value)}
            value={type}
          >
            {typeOptionsAll.map((opt) => (
              <option key={opt.value} value={opt.value}>
                {opt.label}
              </option>
            ))}
          </select>

          <label className="text-sm text-text-secondary" htmlFor="lookup-limit">
            Limite
          </label>
          <input
            className="w-20 rounded-lg border border-surface-border bg-surface-primary px-3 py-1.5 text-sm text-text-primary focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
            disabled={isRunning}
            id="lookup-limit"
            min={0}
            onChange={(e) => setLimit(Number(e.target.value))}
            type="number"
            value={limit}
          />
          <span className="text-xs text-text-muted">(0 = illimité)</span>
        </div>

        <div className="flex flex-wrap items-center gap-4">
          <label className="flex items-center gap-2 text-sm text-text-secondary">
            <input
              checked={force}
              className="rounded border-surface-border text-primary-600 focus:ring-primary-500"
              disabled={isRunning}
              onChange={(e) => setForce(e.target.checked)}
              type="checkbox"
            />
            Forcer le re-lookup
          </label>

          <label className="flex items-center gap-2 text-sm text-text-secondary" htmlFor="lookup-delay">
            Délai (s)
          </label>
          <input
            className="w-16 rounded-lg border border-surface-border bg-surface-primary px-3 py-1.5 text-sm text-text-primary focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
            disabled={isRunning}
            id="lookup-delay"
            min={0}
            onChange={(e) => setDelay(Number(e.target.value))}
            type="number"
            value={delay}
          />
        </div>

        <div className="flex items-center gap-3">
          {previewLoading ? (
            <Loader2 className="h-4 w-4 animate-spin text-primary-600" />
          ) : (
            <p className="text-sm text-text-secondary">
              <span className="font-semibold text-text-primary">{total}</span>{" "}
              série(s) à traiter
            </p>
          )}
        </div>

        <div className="flex items-center gap-2">
          {!isRunning ? (
            <button
              className="flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
              disabled={total === 0 || previewLoading}
              onClick={handleStart}
              type="button"
            >
              <Search className="h-4 w-4" />
              Lancer le lookup
            </button>
          ) : (
            <button
              className="flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
              onClick={cancel}
              type="button"
            >
              <Square className="h-4 w-4" />
              Arrêter
            </button>
          )}
        </div>
      </div>

      {(progress.length > 0 || isRunning) && (
        <div className="mt-6">
          <ProgressLog
            progress={progress}
            total={limit > 0 && limit < total ? limit : total}
          />
        </div>
      )}

      {summary && (
        <div className="mt-4 rounded-lg border border-surface-border bg-surface-secondary p-4">
          <div className="flex items-center gap-2 text-sm font-medium text-text-primary">
            <CheckCircle className="h-4 w-4 text-green-600" />
            Lookup terminé
          </div>
          <dl className="mt-2 grid grid-cols-2 gap-2 text-sm sm:grid-cols-4">
            <dt className="text-text-secondary">Traitées</dt>
            <dd className="text-text-primary">{summary.processed}</dd>
            <dt className="text-text-secondary">Mises à jour</dt>
            <dd className="text-text-primary">{summary.updated}</dd>
            <dt className="text-text-secondary">Ignorées</dt>
            <dd className="text-text-primary">{summary.skipped}</dd>
            <dt className="text-text-secondary">Erreurs</dt>
            <dd className="text-text-primary">{summary.failed}</dd>
          </dl>
        </div>
      )}
    </div>
  );
}
