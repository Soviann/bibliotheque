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
      <DialogBackdrop className="fixed inset-0 bg-black/30 transition duration-200 ease-out data-closed:opacity-0" />
      <div className="fixed inset-0 flex items-center justify-center p-4">
        <DialogPanel className="w-full max-w-sm rounded-xl bg-surface-primary p-6 shadow-lg transition duration-200 ease-out data-closed:scale-95 data-closed:opacity-0">
          <div className="flex items-start gap-3">
            <AlertTriangle className="mt-0.5 h-6 w-6 shrink-0 text-red-500" />
            <div>
              <DialogTitle className="text-lg font-semibold text-text-primary">
                {title}
              </DialogTitle>
              <Description className="mt-1 text-sm text-text-secondary">
                {description}
              </Description>
            </div>
          </div>
          <div className="mt-6 flex justify-end gap-3">
            <button
              className="rounded-lg px-4 py-2 text-sm font-medium text-text-secondary hover:bg-surface-tertiary"
              onClick={onClose}
              type="button"
            >
              Annuler
            </button>
            <button
              className="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
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
