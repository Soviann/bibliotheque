import { Layers, Loader2, Plus, Search, Trash2 } from "lucide-react";
import { compareTomes } from "../hooks/useComicForm";
import type { FormData, TomeFormData } from "../hooks/useComicForm";

interface TomeTableProps {
  addBatchTomes: () => void;
  addTome: () => void;
  batchFrom: number;
  batchSize: number;
  batchTo: number;
  form: FormData;
  lookupTomeIsbn: (index: number) => void;
  maxBatchSize: number;
  removeTome: (index: number) => void;
  setBatchFrom: (v: number) => void;
  setBatchTo: (v: number) => void;
  tomeLookupLoading: number | null;
  updateTome: <K extends keyof TomeFormData>(index: number, key: K, value: TomeFormData[K]) => void;
}

export default function TomeTable({
  addBatchTomes,
  addTome,
  batchFrom,
  batchSize,
  batchTo,
  form,
  lookupTomeIsbn,
  maxBatchSize,
  removeTome,
  setBatchFrom,
  setBatchTo,
  tomeLookupLoading,
  updateTome,
}: TomeTableProps) {
  return (
    <div>
      <div className="mb-2 flex items-center justify-between">
        <h2 className="text-sm font-semibold text-text-secondary">
          Tomes ({form.tomes.length})
        </h2>
        <button
          className="flex items-center gap-1 rounded-lg bg-primary-100 px-3 py-1.5 text-sm font-medium text-primary-700 hover:bg-primary-200 dark:bg-primary-950/30 dark:text-primary-400 dark:hover:bg-primary-900/40"
          onClick={addTome}
          type="button"
        >
          <Plus className="h-4 w-4" /> Ajouter
        </button>
      </div>
      <div className="mb-3 flex flex-wrap items-end gap-2 rounded-lg border border-surface-border bg-surface-tertiary p-3">
        <div>
          <label className="mb-1 block text-xs font-medium text-text-muted" htmlFor="batch-from">Du tome</label>
          <input
            className="w-16 rounded border border-surface-border bg-surface-primary px-2 py-1 text-center text-sm text-text-primary"
            id="batch-from"
            min="1"
            onChange={(e) => setBatchFrom(Number(e.target.value))}
            type="number"
            value={batchFrom}
          />
        </div>
        <div>
          <label className="mb-1 block text-xs font-medium text-text-muted" htmlFor="batch-to">au tome</label>
          <input
            className="w-16 rounded border border-surface-border bg-surface-primary px-2 py-1 text-center text-sm text-text-primary"
            id="batch-to"
            min="1"
            onChange={(e) => setBatchTo(Number(e.target.value))}
            type="number"
            value={batchTo}
          />
        </div>
        <button
          className="flex items-center gap-1 rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
          disabled={batchFrom < 1 || batchFrom > batchTo || batchSize > maxBatchSize}
          onClick={addBatchTomes}
          type="button"
        >
          <Layers className="h-4 w-4" /> Générer
        </button>
        {batchSize > maxBatchSize && (
          <span className="text-xs text-red-500">Max {maxBatchSize} tomes</span>
        )}
      </div>
      {/* Mobile: card layout */}
      <div className="space-y-3 sm:hidden" data-testid="tomes-cards">
        {form.tomes
          .map((tome, i) => ({ tome, originalIndex: i }))
          .sort((a, b) => compareTomes(a.tome, b.tome))
          .map(({ tome, originalIndex: i }) => (
          <div className={`rounded-lg border p-3 space-y-2 ${tome.id ? "border-surface-border bg-surface-primary" : "border-emerald-300 bg-emerald-50 dark:border-emerald-700 dark:bg-emerald-950/30"}`} key={i}>
            <div className="flex items-center gap-2">
              {!tome.id && <span className="rounded bg-emerald-100 px-1.5 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300">Nouveau</span>}
              <label className="flex items-center gap-1 text-xs font-medium text-text-muted">
                <input
                  checked={tome.isHorsSerie}
                  className="h-3.5 w-3.5 rounded border-surface-border text-amber-600"
                  onChange={(e) => updateTome(i, "isHorsSerie", e.target.checked)}
                  type="checkbox"
                />
                HS
              </label>
              <input
                className="w-14 rounded border border-surface-border bg-surface-tertiary px-2 py-1 text-center text-sm font-medium text-text-primary"
                min="0"
                onChange={(e) => updateTome(i, "number", Number(e.target.value))}
                type="number"
                value={tome.number}
              />
              <input
                className="w-14 rounded border border-surface-border bg-surface-tertiary px-2 py-1 text-center text-sm text-text-primary"
                min="0"
                onChange={(e) => updateTome(i, "tomeEnd", e.target.value)}
                placeholder="Fin"
                type="number"
                value={tome.tomeEnd}
              />
              <input
                className="flex-1 rounded border border-surface-border bg-surface-tertiary px-2 py-1 text-sm text-text-primary"
                onChange={(e) => updateTome(i, "title", e.target.value)}
                placeholder="Titre"
                value={tome.title}
              />
              <button
                aria-label={`Supprimer tome ${tome.number}`}
                className="shrink-0 rounded p-1 text-red-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/30"
                onClick={() => removeTome(i)}
                type="button"
              >
                <Trash2 className="h-4 w-4" />
              </button>
            </div>
            <div className="flex items-center gap-1">
              <input
                className="flex-1 rounded border border-surface-border bg-surface-tertiary px-2 py-1 text-sm text-text-primary"
                onChange={(e) => updateTome(i, "isbn", e.target.value)}
                placeholder="ISBN"
                value={tome.isbn}
              />
              <button
                className="shrink-0 rounded p-1 text-text-muted hover:bg-surface-tertiary hover:text-primary-600 disabled:opacity-50"
                disabled={tome.isbn.length < 10 || tomeLookupLoading === i}
                onClick={() => lookupTomeIsbn(i)}
                title="Rechercher par ISBN"
                type="button"
              >
                {tomeLookupLoading === i
                  ? <Loader2 className="h-4 w-4 animate-spin" />
                  : <Search className="h-4 w-4" />}
              </button>
            </div>
            <div className="grid grid-cols-2 gap-x-4 gap-y-1">
              <label className="flex items-center gap-2 text-sm text-text-secondary">
                <input
                  checked={tome.bought}
                  className="h-4 w-4 rounded border-surface-border text-primary-600"
                  onChange={(e) => updateTome(i, "bought", e.target.checked)}
                  type="checkbox"
                />
                Acheté
              </label>
              <label className="flex items-center gap-2 text-sm text-text-secondary">
                <input
                  checked={tome.downloaded}
                  className="h-4 w-4 rounded border-surface-border text-primary-600"
                  onChange={(e) => updateTome(i, "downloaded", e.target.checked)}
                  type="checkbox"
                />
                DL
              </label>
              <label className="flex items-center gap-2 text-sm text-text-secondary">
                <input
                  checked={tome.read}
                  className="h-4 w-4 rounded border-surface-border text-primary-600"
                  onChange={(e) => updateTome(i, "read", e.target.checked)}
                  type="checkbox"
                />
                Lu
              </label>
              <label className="flex items-center gap-2 text-sm text-text-secondary">
                <input
                  checked={tome.onNas}
                  className="h-4 w-4 rounded border-surface-border text-primary-600"
                  onChange={(e) => updateTome(i, "onNas", e.target.checked)}
                  type="checkbox"
                />
                NAS
              </label>
            </div>
          </div>
        ))}
      </div>

      {/* Desktop: table layout */}
      <div className="hidden overflow-x-auto rounded-lg border border-surface-border sm:block" data-testid="tomes-table">
        <table className="w-full text-sm">
          <thead className="bg-surface-tertiary">
            <tr>
              <th className="px-3 py-2 text-center font-medium text-text-secondary">HS</th>
              <th className="px-3 py-2 text-left font-medium text-text-secondary">#</th>
              <th className="px-3 py-2 text-left font-medium text-text-secondary">Fin</th>
              <th className="px-3 py-2 text-left font-medium text-text-secondary">Titre</th>
              <th className="px-3 py-2 text-left font-medium text-text-secondary">ISBN</th>
              <th className="px-3 py-2 text-center font-medium text-text-secondary">Acheté</th>
              <th className="px-3 py-2 text-center font-medium text-text-secondary">DL</th>
              <th className="px-3 py-2 text-center font-medium text-text-secondary">Lu</th>
              <th className="px-3 py-2 text-center font-medium text-text-secondary">NAS</th>
              <th className="px-3 py-2" />
            </tr>
          </thead>
          <tbody className="divide-y divide-surface-border">
            {form.tomes
              .map((tome, i) => ({ tome, originalIndex: i }))
              .sort((a, b) => compareTomes(a.tome, b.tome))
              .map(({ tome, originalIndex: i }) => (
              <tr className={tome.id ? "" : "bg-emerald-50 dark:bg-emerald-950/20"} key={i}>
                <td className="px-3 py-1.5 text-center">
                  <input
                    checked={tome.isHorsSerie}
                    className="h-4 w-4 rounded border-surface-border text-amber-600"
                    onChange={(e) => updateTome(i, "isHorsSerie", e.target.checked)}
                    type="checkbox"
                  />
                </td>
                <td className="px-3 py-1.5">
                  <div className="flex items-center gap-1">
                    <input
                      className="w-14 rounded border border-surface-border bg-surface-primary px-2 py-1 text-center text-sm text-text-primary"
                      min="0"
                      onChange={(e) => updateTome(i, "number", Number(e.target.value))}
                      type="number"
                      value={tome.number}
                    />
                    {!tome.id && <span className="rounded bg-emerald-100 px-1 py-0.5 text-[10px] font-medium text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300">Nouveau</span>}
                  </div>
                </td>
                <td className="px-3 py-1.5">
                  <input
                    className="w-14 rounded border border-surface-border bg-surface-primary px-2 py-1 text-center text-sm text-text-primary"
                    min="0"
                    onChange={(e) => updateTome(i, "tomeEnd", e.target.value)}
                    placeholder="Fin"
                    type="number"
                    value={tome.tomeEnd}
                  />
                </td>
                <td className="px-3 py-1.5">
                  <input
                    className="w-full min-w-[100px] rounded border border-surface-border bg-surface-primary px-2 py-1 text-sm text-text-primary"
                    onChange={(e) => updateTome(i, "title", e.target.value)}
                    placeholder="Titre"
                    value={tome.title}
                  />
                </td>
                <td className="px-3 py-1.5">
                  <div className="flex items-center gap-1">
                    <input
                      className="w-full min-w-[120px] rounded border border-surface-border bg-surface-primary px-2 py-1 text-sm text-text-primary"
                      onChange={(e) => updateTome(i, "isbn", e.target.value)}
                      placeholder="ISBN"
                      value={tome.isbn}
                    />
                    <button
                      className="shrink-0 rounded p-1 text-text-muted hover:bg-surface-tertiary hover:text-primary-600 disabled:opacity-50"
                      disabled={tome.isbn.length < 10 || tomeLookupLoading === i}
                      onClick={() => lookupTomeIsbn(i)}
                      title="Rechercher par ISBN"
                      type="button"
                    >
                      {tomeLookupLoading === i
                        ? <Loader2 className="h-4 w-4 animate-spin" />
                        : <Search className="h-4 w-4" />}
                    </button>
                  </div>
                </td>
                <td className="px-3 py-1.5 text-center">
                  <input
                    checked={tome.bought}
                    className="h-4 w-4 rounded border-surface-border text-primary-600"
                    onChange={(e) => updateTome(i, "bought", e.target.checked)}
                    type="checkbox"
                  />
                </td>
                <td className="px-3 py-1.5 text-center">
                  <input
                    checked={tome.downloaded}
                    className="h-4 w-4 rounded border-surface-border text-primary-600"
                    onChange={(e) => updateTome(i, "downloaded", e.target.checked)}
                    type="checkbox"
                  />
                </td>
                <td className="px-3 py-1.5 text-center">
                  <input
                    checked={tome.read}
                    className="h-4 w-4 rounded border-surface-border text-primary-600"
                    onChange={(e) => updateTome(i, "read", e.target.checked)}
                    type="checkbox"
                  />
                </td>
                <td className="px-3 py-1.5 text-center">
                  <input
                    checked={tome.onNas}
                    className="h-4 w-4 rounded border-surface-border text-primary-600"
                    onChange={(e) => updateTome(i, "onNas", e.target.checked)}
                    type="checkbox"
                  />
                </td>
                <td className="px-3 py-1.5">
                  <button
                    className="rounded p-1 text-red-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/30"
                    onClick={() => removeTome(i)}
                    type="button"
                  >
                    <Trash2 className="h-4 w-4" />
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
