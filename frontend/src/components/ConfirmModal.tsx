import {
  Description,
  Dialog,
  DialogBackdrop,
  DialogPanel,
  DialogTitle,
} from "@headlessui/react";
import { AlertTriangle } from "lucide-react";

interface ConfirmModalProps {
  confirmLabel?: string;
  description: string;
  onClose: () => void;
  onConfirm: () => void;
  open: boolean;
  title: string;
}

export default function ConfirmModal({
  confirmLabel = "Confirmer",
  description,
  onClose,
  onConfirm,
  open,
  title,
}: ConfirmModalProps) {
  return (
    <Dialog className="relative z-50" onClose={onClose} open={open}>
      <DialogBackdrop className="fixed inset-0 bg-black/30 backdrop-blur-sm transition duration-200 ease-out data-closed:opacity-0" />
      <div className="fixed inset-0 flex items-center justify-center p-4">
        <DialogPanel className="w-full max-w-sm rounded-2xl bg-surface-primary p-6 shadow-layered-xl transition duration-200 ease-out data-closed:scale-95 data-closed:opacity-0 dark:border dark:border-white/10 dark:bg-surface-elevated">
          <div className="flex items-start gap-3">
            <AlertTriangle
              className="mt-0.5 h-6 w-6 shrink-0 text-accent-danger"
              strokeWidth={1.5}
            />
            <div>
              <DialogTitle className="font-display text-lg font-semibold text-text-primary">
                {title}
              </DialogTitle>
              <Description className="mt-1 text-sm text-text-secondary">
                {description}
              </Description>
            </div>
          </div>
          <div className="mt-6 flex justify-end gap-3">
            <button
              className="rounded-xl px-4 py-2 text-sm font-medium text-text-secondary hover:bg-surface-tertiary"
              onClick={onClose}
              type="button"
            >
              Annuler
            </button>
            <button
              className="rounded-xl bg-accent-danger px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-red-700 dark:hover:bg-red-500"
              onClick={() => {
                onConfirm();
                onClose();
              }}
              type="button"
            >
              {confirmLabel}
            </button>
          </div>
        </DialogPanel>
      </div>
    </Dialog>
  );
}
