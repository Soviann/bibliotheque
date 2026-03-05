import {
  Dialog,
  DialogBackdrop,
  DialogPanel,
  DialogTitle,
} from "@headlessui/react";
import { Check, Loader2 } from "lucide-react";
import { useState } from "react";
import type { MergePreview } from "../types/api";

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
  const [descExpanded, setDescExpanded] = useState(false);

  // Sync local title when preview changes
  const handleAfterOpen = () => {
    if (preview) {
      setEditedTitle(preview.title);
      setDescExpanded(false);
    }
  };

  if (!preview) return null;

  const description = preview.description ?? "";
  const shouldTruncate = description.length > 200;
  const displayedDescription =
    shouldTruncate && !descExpanded
      ? description.slice(0, 200) + "..."
      : description;

  return (
    <Dialog
      className="relative z-50"
      onClose={onClose}
      open={open}
    >
      <DialogBackdrop
        className="fixed inset-0 bg-black/30"
        onTransitionEnd={() => {
          if (open) handleAfterOpen();
        }}
      />
      <div className="fixed inset-0 flex items-center justify-center p-4">
        <DialogPanel
          className="flex max-h-[90vh] w-full max-w-2xl flex-col rounded-xl bg-surface-primary shadow-lg"
          onFocus={() => {
            // Ensure title is set when panel receives focus (first render)
            if (preview && !editedTitle) {
              setEditedTitle(preview.title);
            }
          }}
        >
          <div className="overflow-y-auto p-6">
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
                value={editedTitle || preview.title}
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

            {/* Tome table */}
            <div className="mt-4 overflow-x-auto">
              <table className="w-full text-left text-sm">
                <thead>
                  <tr className="border-b border-surface-border text-text-muted">
                    <th className="px-2 py-2 font-medium">#</th>
                    <th className="px-2 py-2 font-medium">Titre</th>
                    <th className="px-2 py-2 font-medium">ISBN</th>
                    <th className="px-2 py-2 text-center font-medium">Achat</th>
                    <th className="px-2 py-2 text-center font-medium">DL</th>
                    <th className="px-2 py-2 text-center font-medium">Lu</th>
                    <th className="px-2 py-2 text-center font-medium">NAS</th>
                  </tr>
                </thead>
                <tbody>
                  {preview.tomes.map((tome) => (
                    <tr
                      className="border-b border-surface-border last:border-0"
                      key={tome.number}
                    >
                      <td className="px-2 py-2 text-text-primary">{tome.number}</td>
                      <td className="px-2 py-2 text-text-primary">
                        {tome.title ?? "-"}
                      </td>
                      <td className="px-2 py-2 text-text-muted">
                        {tome.isbn ?? "-"}
                      </td>
                      <td className="px-2 py-2 text-center">
                        {tome.bought && (
                          <Check className="mx-auto h-4 w-4 text-green-600" />
                        )}
                      </td>
                      <td className="px-2 py-2 text-center">
                        {tome.downloaded && (
                          <Check className="mx-auto h-4 w-4 text-green-600" />
                        )}
                      </td>
                      <td className="px-2 py-2 text-center">
                        {tome.read && (
                          <Check className="mx-auto h-4 w-4 text-green-600" />
                        )}
                      </td>
                      <td className="px-2 py-2 text-center">
                        {tome.onNas && (
                          <Check className="mx-auto h-4 w-4 text-green-600" />
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
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
              disabled={isExecuting}
              onClick={() => {
                onConfirm({
                  ...preview,
                  title: editedTitle || preview.title,
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
