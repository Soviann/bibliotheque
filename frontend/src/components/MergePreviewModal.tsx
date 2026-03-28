import {
  Dialog,
  DialogBackdrop,
  DialogPanel,
  DialogTitle,
} from "@headlessui/react";
import { Loader2 } from "lucide-react";
import { useMergePreviewForm } from "../hooks/useMergePreviewForm";
import type { MergePreview, MergeSuggestion } from "../types/api";
import MergeMetadataForm from "./MergeMetadataForm";
import MergeTomeTable from "./MergeTomeTable";

interface MergePreviewModalProps {
  isExecuting: boolean;
  isSuggesting?: boolean;
  onClose: () => void;
  onConfirm: (preview: MergePreview) => void;
  open: boolean;
  preview: MergePreview | null;
  suggestion?: MergeSuggestion | null;
}

export default function MergePreviewModal({
  isExecuting,
  isSuggesting = false,
  onClose,
  onConfirm,
  open,
  preview,
  suggestion,
}: MergePreviewModalProps) {
  const { buildConfirmPayload, dispatch, duplicateNumbers, hasDuplicates, state } =
    useMergePreviewForm(preview, suggestion);

  if (!preview) return null;

  return (
    <Dialog className="relative z-50" onClose={onClose} open={open}>
      <DialogBackdrop className="fixed inset-0 bg-black/30 transition duration-200 ease-out data-closed:opacity-0" />
      <div className="fixed inset-0 flex items-center justify-center p-4">
        <DialogPanel className="flex max-h-[90vh] w-full max-w-4xl flex-col rounded-xl bg-surface-primary shadow-layered-xl transition duration-200 ease-out data-closed:scale-95 data-closed:opacity-0">
          {/* Header + metadata (non-scrollable) */}
          <div className="shrink-0 px-6 pt-6">
            <DialogTitle className="font-display text-lg font-semibold text-text-primary">
              Aperçu de la fusion
            </DialogTitle>
          </div>

          {/* Scrollable content */}
          <div className="min-h-0 flex-1 overflow-auto px-6 pb-2">
            <MergeMetadataForm
              dispatch={dispatch}
              isSuggesting={isSuggesting}
              state={state}
            />
            <MergeTomeTable
              dispatch={dispatch}
              duplicateNumbers={duplicateNumbers}
              tomes={state.tomes}
            />
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
              onClick={() => onConfirm(buildConfirmPayload(preview))}
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
