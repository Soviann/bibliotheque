import { Loader2, Search } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";
import Breadcrumb from "../components/Breadcrumb";
import { useBatchLookup, useBatchLookupPreview } from "../hooks/useBatchLookup";
import { getErrorMessage } from "../services/api";
import { typeOptionsAll } from "../types/enums";

export default function LookupTool() {
  const [type, setType] = useState("");
  const [force, setForce] = useState(false);
  const [limit, setLimit] = useState(0);

  const { data: preview, isLoading: previewLoading } = useBatchLookupPreview(
    type || undefined,
    force,
  );
  const lookup = useBatchLookup();

  const handleStart = () => {
    lookup.mutate(
      {
        force,
        limit: limit > 0 ? limit : undefined,
        type: type || undefined,
      },
      {
        onError: (error) =>
          toast.error(getErrorMessage(error, "Erreur lors de la mise en file")),
        onSuccess: (data) =>
          toast.success(
            `${data.queued} série(s) mises en file pour enrichissement`,
          ),
      },
    );
  };

  const total = preview?.count ?? 0;

  return (
    <div className="mx-auto max-w-4xl px-4 py-6">
      <Breadcrumb
        items={[
          { href: "/tools", label: "Outils" },
          { label: "Lookup métadonnées" },
        ]}
      />
      <h1 className="font-display text-xl font-bold text-text-primary">
        Lookup métadonnées
      </h1>
      <p className="mt-1 text-sm text-text-secondary">
        L'enrichissement s'exécute en arrière-plan. Les correspondances
        incertaines sont déposées dans la file de revue.
      </p>

      <div className="mt-4 space-y-3">
        <div className="flex flex-wrap items-center gap-3">
          <label className="text-sm text-text-secondary" htmlFor="lookup-type">
            Type
          </label>
          <select
            className="rounded-lg border border-surface-border bg-surface-primary px-3 py-1.5 text-sm text-text-primary focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
            disabled={lookup.isPending}
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
            disabled={lookup.isPending}
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
              disabled={lookup.isPending}
              onChange={(e) => setForce(e.target.checked)}
              type="checkbox"
            />
            Forcer le re-lookup
          </label>
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
          <button
            className="flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
            disabled={total === 0 || previewLoading || lookup.isPending}
            onClick={handleStart}
            type="button"
          >
            {lookup.isPending ? (
              <Loader2 className="h-4 w-4 animate-spin" />
            ) : (
              <Search className="h-4 w-4" />
            )}
            Lancer le lookup
          </button>
        </div>
      </div>
    </div>
  );
}
