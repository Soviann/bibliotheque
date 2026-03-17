import { AlertTriangle, Plus, X } from "lucide-react";
import type { MergeFormAction } from "../hooks/useMergePreviewForm";
import type { MergePreviewTome } from "../types/api";

const inputClass =
  "rounded border border-surface-border bg-surface-secondary px-1.5 py-0.5 text-sm text-text-primary focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500";

interface MergeTomeTableProps {
  dispatch: React.Dispatch<MergeFormAction>;
  duplicateNumbers: Set<number>;
  tomes: MergePreviewTome[];
}

export default function MergeTomeTable({ dispatch, duplicateNumbers, tomes }: MergeTomeTableProps) {
  const hasDuplicates = duplicateNumbers.size > 0;

  return (
    <>
      {/* Duplicate warning */}
      {hasDuplicates && (
        <div className="mt-4 flex items-center gap-2 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-700 dark:bg-amber-950/30 dark:text-amber-400">
          <AlertTriangle className="h-4 w-4 shrink-0" />
          Numéros de tomes en double détectés. Modifiez-les avant de confirmer.
        </div>
      )}

      {/* Tome table */}
      <table className="w-full text-left text-sm">
        <thead className="sticky top-0 z-10 bg-surface-primary">
          <tr className="border-b border-surface-border text-text-muted">
            <th className="w-16 px-2 py-2 font-medium">#</th>
            <th className="w-16 px-2 py-2 font-medium">Fin</th>
            <th className="min-w-[140px] px-2 py-2 font-medium">Titre</th>
            <th className="min-w-[120px] px-2 py-2 font-medium">ISBN</th>
            <th className="px-2 py-2 text-center font-medium">Achat</th>
            <th className="px-2 py-2 text-center font-medium">DL</th>
            <th className="px-2 py-2 text-center font-medium">Lu</th>
            <th className="px-2 py-2 text-center font-medium">NAS</th>
            <th className="w-10 px-2 py-2" />
          </tr>
        </thead>
        <tbody>
          {tomes.map((tome, index) => {
            const isDuplicate = duplicateNumbers.has(tome.number);
            return (
              <tr
                className={`border-b border-surface-border last:border-0 ${isDuplicate ? "bg-amber-50 dark:bg-amber-950/20" : ""}`}
                key={index}
              >
                <td className="px-2 py-1.5">
                  <input
                    className={`w-14 ${inputClass} ${isDuplicate ? "!border-amber-400 !bg-amber-50 dark:!border-amber-600 dark:!bg-amber-950/30" : ""}`}
                    min={1}
                    onChange={(e) => dispatch({ type: "UPDATE_TOME", index, patch: { number: parseInt(e.target.value, 10) || 1 } })}
                    type="number"
                    value={tome.number}
                  />
                </td>
                <td className="px-2 py-1.5">
                  <input
                    className={`w-14 ${inputClass}`}
                    min={1}
                    onChange={(e) => {
                      const v = e.target.value;
                      dispatch({ type: "UPDATE_TOME", index, patch: { tomeEnd: v ? parseInt(v, 10) || null : null } });
                    }}
                    placeholder="-"
                    type="number"
                    value={tome.tomeEnd ?? ""}
                  />
                </td>
                <td className="px-2 py-1.5">
                  <input
                    className={`w-full ${inputClass}`}
                    onChange={(e) => dispatch({ type: "UPDATE_TOME", index, patch: { title: e.target.value || null } })}
                    placeholder="-"
                    type="text"
                    value={tome.title ?? ""}
                  />
                </td>
                <td className="px-2 py-1.5">
                  <input
                    className={`w-full ${inputClass}`}
                    onChange={(e) => dispatch({ type: "UPDATE_TOME", index, patch: { isbn: e.target.value || null } })}
                    placeholder="-"
                    type="text"
                    value={tome.isbn ?? ""}
                  />
                </td>
                <td className="px-2 py-1.5 text-center">
                  <input
                    aria-label={`Tome ${tome.number} acheté`}
                    checked={tome.bought}
                    className="h-4 w-4 rounded border-surface-border text-primary-600 focus:ring-primary-500"
                    onChange={(e) => dispatch({ type: "UPDATE_TOME", index, patch: { bought: e.target.checked } })}
                    type="checkbox"
                  />
                </td>
                <td className="px-2 py-1.5 text-center">
                  <input
                    aria-label={`Tome ${tome.number} téléchargé`}
                    checked={tome.downloaded}
                    className="h-4 w-4 rounded border-surface-border text-primary-600 focus:ring-primary-500"
                    onChange={(e) => dispatch({ type: "UPDATE_TOME", index, patch: { downloaded: e.target.checked } })}
                    type="checkbox"
                  />
                </td>
                <td className="px-2 py-1.5 text-center">
                  <input
                    aria-label={`Tome ${tome.number} lu`}
                    checked={tome.read}
                    className="h-4 w-4 rounded border-surface-border text-primary-600 focus:ring-primary-500"
                    onChange={(e) => dispatch({ type: "UPDATE_TOME", index, patch: { read: e.target.checked } })}
                    type="checkbox"
                  />
                </td>
                <td className="px-2 py-1.5 text-center">
                  <input
                    aria-label={`Tome ${tome.number} sur NAS`}
                    checked={tome.onNas}
                    className="h-4 w-4 rounded border-surface-border text-primary-600 focus:ring-primary-500"
                    onChange={(e) => dispatch({ type: "UPDATE_TOME", index, patch: { onNas: e.target.checked } })}
                    type="checkbox"
                  />
                </td>
                <td className="px-2 py-1.5 text-center">
                  <button
                    className="rounded p-0.5 text-text-muted hover:bg-red-100 hover:text-red-600 dark:hover:bg-red-950/30 dark:hover:text-red-400"
                    onClick={() => dispatch({ type: "REMOVE_TOME", index })}
                    title="Retirer ce tome"
                    type="button"
                  >
                    <X className="h-3.5 w-3.5" />
                  </button>
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
      <button
        className="mt-2 flex items-center gap-1 rounded-lg px-3 py-1.5 text-sm font-medium text-primary-600 hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-primary-950/30"
        onClick={() => dispatch({ type: "ADD_TOME" })}
        type="button"
      >
        <Plus className="h-4 w-4" />
        Ajouter un tome
      </button>
    </>
  );
}
