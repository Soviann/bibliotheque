import { Loader2, Trash2 } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";
import ConfirmModal from "../components/ConfirmModal";
import EmptyState from "../components/EmptyState";
import { useExecutePurge, usePurgePreview } from "../hooks/usePurge";

export default function PurgeTool() {
  const [days, setDays] = useState(30);
  const [confirmOpen, setConfirmOpen] = useState(false);

  const { data: purgeable, isLoading } = usePurgePreview(days);
  const executePurge = useExecutePurge();

  const handlePurge = () => {
    if (!purgeable || purgeable.length === 0) return;

    const ids = purgeable.map((s) => s.id);
    executePurge.mutate(ids, {
      onSuccess: (data) => {
        toast.success(`${data.purged} serie(s) purgee(s)`);
      },
    });
  };

  return (
    <div className="mx-auto max-w-4xl px-4 py-6">
      <h1 className="text-xl font-bold text-text-primary">
        Purge de la corbeille
      </h1>

      <div className="mt-4 flex items-center gap-3">
        <label className="text-sm text-text-secondary" htmlFor="purge-days">
          Series supprimees depuis plus de
        </label>
        <input
          className="w-20 rounded-lg border border-surface-border bg-surface-primary px-3 py-1.5 text-sm text-text-primary focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
          id="purge-days"
          min={1}
          onChange={(e) => setDays(Number(e.target.value))}
          type="number"
          value={days}
        />
        <span className="text-sm text-text-secondary">jours</span>
      </div>

      {isLoading && (
        <div className="mt-8 flex items-center justify-center">
          <Loader2 className="h-6 w-6 animate-spin text-primary-600" />
        </div>
      )}

      {!isLoading && purgeable && purgeable.length === 0 && (
        <EmptyState
          description={`Aucune serie dans la corbeille depuis plus de ${days} jours`}
          icon={Trash2}
          title="Aucune serie a purger"
        />
      )}

      {!isLoading && purgeable && purgeable.length > 0 && (
        <div className="mt-6">
          <div className="overflow-hidden rounded-lg border border-surface-border">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-surface-border bg-surface-secondary">
                  <th className="px-4 py-2 text-left font-medium text-text-secondary">
                    Titre
                  </th>
                  <th className="px-4 py-2 text-left font-medium text-text-secondary">
                    Supprimee le
                  </th>
                </tr>
              </thead>
              <tbody>
                {purgeable.map((series) => (
                  <tr
                    className="border-b border-surface-border last:border-0"
                    key={series.id}
                  >
                    <td className="px-4 py-2 text-text-primary">
                      {series.title}
                    </td>
                    <td className="px-4 py-2 text-text-secondary">
                      {new Date(series.deletedAt).toLocaleDateString("fr-FR")}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <div className="mt-4">
            <button
              className="flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
              disabled={executePurge.isPending}
              onClick={() => setConfirmOpen(true)}
              type="button"
            >
              {executePurge.isPending ? (
                <Loader2 className="h-4 w-4 animate-spin" />
              ) : (
                <Trash2 className="h-4 w-4" />
              )}
              Purger {purgeable.length} serie(s)
            </button>
          </div>
        </div>
      )}

      <ConfirmModal
        confirmLabel="Confirmer la purge"
        description={`${purgeable?.length ?? 0} serie(s) seront definitivement supprimees. Cette action est irreversible.`}
        onClose={() => setConfirmOpen(false)}
        onConfirm={handlePurge}
        open={confirmOpen}
        title="Confirmer la purge"
      />
    </div>
  );
}
