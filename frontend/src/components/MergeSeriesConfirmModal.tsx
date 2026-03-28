import {
  Dialog,
  DialogBackdrop,
  DialogPanel,
  DialogTitle,
} from "@headlessui/react";
import { useEffect, useState } from "react";
import { formCheckboxFocusClassName } from "../styles/formStyles";

export interface MergeSeriesEntry {
  id: number;
  title: string;
}

interface MergeSeriesConfirmModalProps {
  entries: MergeSeriesEntry[];
  onClose: () => void;
  onConfirm: (selectedIds: number[]) => void;
  open: boolean;
}

export default function MergeSeriesConfirmModal({
  entries,
  onClose,
  onConfirm,
  open,
}: MergeSeriesConfirmModalProps) {
  const [checkedIds, setCheckedIds] = useState<Set<number>>(new Set());

  useEffect(() => {
    setCheckedIds(new Set(entries.map((e) => e.id)));
  }, [entries]);

  const toggleEntry = (id: number) => {
    setCheckedIds((prev) => {
      const next = new Set(prev);
      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });
  };

  return (
    <Dialog className="relative z-50" onClose={onClose} open={open}>
      <DialogBackdrop className="fixed inset-0 bg-black/30 transition duration-200 ease-out data-closed:opacity-0" />
      <div className="fixed inset-0 flex items-center justify-center p-4">
        <DialogPanel className="w-full max-w-lg rounded-xl bg-surface-primary shadow-layered-xl transition duration-200 ease-out data-closed:scale-95 data-closed:opacity-0">
          <div className="px-6 pt-6">
            <DialogTitle className="font-display text-lg font-semibold text-text-primary">
              Confirmer les séries à fusionner
            </DialogTitle>
            <p className="mt-1 text-sm text-text-secondary">
              Décochez les séries à exclure de la fusion.
            </p>
          </div>

          <div className="mt-4 px-6">
            <div className="space-y-1">
              {entries.map((entry) => (
                <label
                  className={`flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2 hover:bg-surface-secondary ${
                    checkedIds.has(entry.id)
                      ? "bg-primary-50 dark:bg-primary-950/20"
                      : ""
                  }`}
                  key={entry.id}
                >
                  <input
                    checked={checkedIds.has(entry.id)}
                    className={formCheckboxFocusClassName}
                    onChange={() => toggleEntry(entry.id)}
                    type="checkbox"
                  />
                  <span className="text-sm text-text-primary">
                    {entry.title}
                  </span>
                </label>
              ))}
            </div>
          </div>

          <div className="flex justify-end gap-3 border-t border-surface-border px-6 py-4 mt-4">
            <button
              className="rounded-lg px-4 py-2 text-sm font-medium text-text-secondary hover:bg-surface-tertiary"
              onClick={onClose}
              type="button"
            >
              Annuler
            </button>
            <button
              className="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
              disabled={checkedIds.size < 2}
              onClick={() => onConfirm(entries.filter((e) => checkedIds.has(e.id)).map((e) => e.id))}
              type="button"
            >
              Continuer
            </button>
          </div>
        </DialogPanel>
      </div>
    </Dialog>
  );
}
