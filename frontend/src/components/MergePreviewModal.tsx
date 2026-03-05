import {
  Dialog,
  DialogBackdrop,
  DialogPanel,
  DialogTitle,
} from "@headlessui/react";
import { AlertTriangle, Loader2, X } from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import type { MergePreview, MergePreviewTome } from "../types/api";

interface MergePreviewModalProps {
  isExecuting: boolean;
  onClose: () => void;
  onConfirm: (preview: MergePreview) => void;
  open: boolean;
  preview: MergePreview | null;
}

export default function MergePreviewModal({
  isExecuting,
  onClose,
  onConfirm,
  open,
  preview,
}: MergePreviewModalProps) {
  const [editedTitle, setEditedTitle] = useState("");
  const [editedTomes, setEditedTomes] = useState<MergePreviewTome[]>([]);
  const [descExpanded, setDescExpanded] = useState(false);

  // Sync local state when preview changes
  useEffect(() => {
    if (preview) {
      setEditedTitle(preview.title);
      setEditedTomes(preview.tomes.map((t) => ({ ...t })));
      setDescExpanded(false);
    }
  }, [preview]);

  const duplicateNumbers = useMemo(() => {
    const counts = new Map<number, number>();
    for (const t of editedTomes) {
      counts.set(t.number, (counts.get(t.number) ?? 0) + 1);
    }
    const dupes = new Set<number>();
    for (const [num, count] of counts) {
      if (count > 1) dupes.add(num);
    }
    return dupes;
  }, [editedTomes]);

  const hasDuplicates = duplicateNumbers.size > 0;

  const updateTome = (index: number, patch: Partial<MergePreviewTome>) => {
    setEditedTomes((prev) => {
      const next = [...prev];
      next[index] = { ...next[index], ...patch };
      return next;
    });
  };

  const removeTome = (index: number) => {
    setEditedTomes((prev) => prev.filter((_, i) => i !== index));
  };

  if (!preview) return null;

  const description = preview.description ?? "";
  const shouldTruncate = description.length > 200;
  const displayedDescription =
    shouldTruncate && !descExpanded
      ? description.slice(0, 200) + "..."
      : description;

  const inputClass =
    "rounded border border-surface-border bg-surface-secondary px-1.5 py-0.5 text-sm text-text-primary focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500";

  return (
    <Dialog
      className="relative z-50"
      onClose={onClose}
      open={open}
    >
      <DialogBackdrop className="fixed inset-0 bg-black/30" />
      <div className="fixed inset-0 flex items-center justify-center p-4">
        <DialogPanel className="flex max-h-[90vh] w-full max-w-4xl flex-col rounded-xl bg-surface-primary shadow-lg">
          {/* Metadata (non-scrollable) */}
          <div className="shrink-0 px-6 pt-6">
            <DialogTitle className="text-lg font-semibold text-text-primary">
              Apercu de la fusion
            </DialogTitle>

            {/* Titre editable */}
            <div className="mt-4">
              <label
                className="block text-sm font-medium text-text-secondary"
                htmlFor="merge-title"
              >
                Titre
              </label>
              <input
                className="mt-1 w-full rounded-lg border border-surface-border bg-surface-secondary px-3 py-2 text-text-primary focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                id="merge-title"
                onChange={(e) => setEditedTitle(e.target.value)}
                type="text"
                value={editedTitle}
              />
            </div>

            {/* Metadata */}
            <div className="mt-4 flex flex-wrap items-center gap-2">
              <span className="rounded-full bg-primary-100 px-2.5 py-0.5 text-xs font-medium text-primary-700 dark:bg-primary-950/30 dark:text-primary-400">
                {preview.type}
              </span>
              {preview.publisher && (
                <span className="text-sm text-text-secondary">
                  {preview.publisher}
                </span>
              )}
            </div>

            {preview.authors.length > 0 && (
              <p className="mt-2 text-sm text-text-secondary">
                {preview.authors.join(", ")}
              </p>
            )}

            {description && (
              <div className="mt-3">
                <p className="text-sm text-text-secondary">{displayedDescription}</p>
                {shouldTruncate && (
                  <button
                    className="mt-1 text-xs font-medium text-primary-600 hover:text-primary-700"
                    onClick={() => setDescExpanded(!descExpanded)}
                    type="button"
                  >
                    {descExpanded ? "Voir moins" : "Voir plus"}
                  </button>
                )}
              </div>
            )}

            {/* Duplicate warning */}
            {hasDuplicates && (
              <div className="mt-4 flex items-center gap-2 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-700 dark:bg-amber-950/30 dark:text-amber-400">
                <AlertTriangle className="h-4 w-4 shrink-0" />
                Numeros de tomes en double detectes. Modifiez-les avant de confirmer.
              </div>
            )}
          </div>

          {/* Tome table (scrollable) */}
          <div className="mt-4 min-h-0 flex-1 overflow-auto px-6 pb-2">
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
                  {editedTomes.map((tome, index) => {
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
                            onChange={(e) => updateTome(index, { number: parseInt(e.target.value, 10) || 1 })}
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
                              updateTome(index, { tomeEnd: v ? parseInt(v, 10) || null : null });
                            }}
                            placeholder="-"
                            type="number"
                            value={tome.tomeEnd ?? ""}
                          />
                        </td>
                        <td className="px-2 py-1.5">
                          <input
                            className={`w-full ${inputClass}`}
                            onChange={(e) => updateTome(index, { title: e.target.value || null })}
                            placeholder="-"
                            type="text"
                            value={tome.title ?? ""}
                          />
                        </td>
                        <td className="px-2 py-1.5">
                          <input
                            className={`w-full ${inputClass}`}
                            onChange={(e) => updateTome(index, { isbn: e.target.value || null })}
                            placeholder="-"
                            type="text"
                            value={tome.isbn ?? ""}
                          />
                        </td>
                        <td className="px-2 py-1.5 text-center">
                          <input
                            checked={tome.bought}
                            className="h-4 w-4 rounded border-surface-border text-primary-600 focus:ring-primary-500"
                            onChange={(e) => updateTome(index, { bought: e.target.checked })}
                            type="checkbox"
                          />
                        </td>
                        <td className="px-2 py-1.5 text-center">
                          <input
                            checked={tome.downloaded}
                            className="h-4 w-4 rounded border-surface-border text-primary-600 focus:ring-primary-500"
                            onChange={(e) => updateTome(index, { downloaded: e.target.checked })}
                            type="checkbox"
                          />
                        </td>
                        <td className="px-2 py-1.5 text-center">
                          <input
                            checked={tome.read}
                            className="h-4 w-4 rounded border-surface-border text-primary-600 focus:ring-primary-500"
                            onChange={(e) => updateTome(index, { read: e.target.checked })}
                            type="checkbox"
                          />
                        </td>
                        <td className="px-2 py-1.5 text-center">
                          <input
                            checked={tome.onNas}
                            className="h-4 w-4 rounded border-surface-border text-primary-600 focus:ring-primary-500"
                            onChange={(e) => updateTome(index, { onNas: e.target.checked })}
                            type="checkbox"
                          />
                        </td>
                        <td className="px-2 py-1.5 text-center">
                          <button
                            className="rounded p-0.5 text-text-muted hover:bg-red-100 hover:text-red-600 dark:hover:bg-red-950/30 dark:hover:text-red-400"
                            onClick={() => removeTome(index)}
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
          </div>

          {/* Footer */}
          <div className="flex justify-end gap-3 border-t border-surface-border px-6 py-4">
            <button
              className="rounded-lg px-4 py-2 text-sm font-medium text-text-secondary hover:bg-surface-tertiary"
              disabled={isExecuting}
              onClick={onClose}
              type="button"
            >
              Annuler
            </button>
            <button
              className="flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
              disabled={isExecuting || hasDuplicates}
              onClick={() => {
                onConfirm({
                  ...preview,
                  title: editedTitle || preview.title,
                  tomes: editedTomes,
                });
              }}
              type="button"
            >
              {isExecuting && <Loader2 className="h-4 w-4 animate-spin" />}
              Confirmer la fusion
            </button>
          </div>
        </DialogPanel>
      </div>
    </Dialog>
  );
}
