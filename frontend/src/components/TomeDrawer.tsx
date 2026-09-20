import { Dialog, DialogBackdrop, DialogPanel } from "@headlessui/react";
import { Check, Euro, Eye, HardDrive, X } from "lucide-react";
import type { Tome } from "../types/api";

export interface TomeDrawerProps {
  isHorsSerie?: boolean;
  isOpen: boolean;
  onClose: () => void;
  onToggleField: (field: "bought" | "onNas" | "read") => void;
  seriesTitle?: string;
  tome?: Tome;
  tomeNumber: number;
}

export default function TomeDrawer({
  isHorsSerie = false,
  isOpen,
  onClose,
  onToggleField,
  seriesTitle,
  tome,
  tomeNumber,
}: TomeDrawerProps) {
  const label = isHorsSerie ? `HS ${tomeNumber}` : `Tome ${tomeNumber}`;
  const isBought = tome?.bought ?? false;
  const isOnNas = tome?.onNas ?? false;
  const isRead = tome?.read ?? false;

  return (
    <Dialog className="relative z-50" onClose={onClose} open={isOpen}>
      <DialogBackdrop className="fixed inset-0 bg-black/40 backdrop-blur-sm transition duration-200 ease-out data-closed:opacity-0" />

      <div className="fixed inset-x-0 bottom-0 z-50 flex justify-center">
        <DialogPanel className="w-full max-w-lg rounded-t-2xl border-t border-surface-border bg-surface-primary p-5 pb-8 shadow-layered-xl transition duration-200 ease-out data-closed:translate-y-full dark:border-white/10 dark:bg-surface-elevated">
          {/* Poignée tactile de glissement */}
          <div className="mx-auto mb-3 h-1.5 w-12 rounded-full bg-text-muted/20" />

          {/* En-tête */}
          <div className="mb-4 flex items-center justify-between border-b border-surface-border pb-3 dark:border-white/10">
            <div>
              <h3 className="font-display text-base font-bold text-text-primary">
                {label}
                {tome?.title ? ` — ${tome.title}` : seriesTitle ? ` — ${seriesTitle}` : ""}
              </h3>
              <p className="text-[11px] font-medium text-primary-600 dark:text-primary-400">
                Sauvegarde automatique au tap
              </p>
            </div>
            <button
              aria-label="Fermer"
              className="rounded-lg p-1.5 text-text-muted hover:bg-surface-secondary hover:text-text-primary"
              onClick={onClose}
              type="button"
            >
              <X className="h-5 w-5" strokeWidth={1.5} />
            </button>
          </div>

          {/* 3 Interrupteurs tactiles ergonomiques */}
          <div className="grid grid-cols-3 gap-2.5">
            {/* Acheté */}
            <button
              aria-label={`Acheté : ${isBought ? "oui" : "non"}`}
              className={`flex h-16 flex-col items-center justify-center gap-1 rounded-xl border text-xs font-semibold transition-all active:scale-95 ${
                isBought
                  ? "border-emerald-500/60 bg-emerald-500/20 text-emerald-600 shadow-sm dark:text-emerald-300"
                  : "border-surface-border bg-surface-secondary text-text-muted hover:border-surface-border/80 hover:text-text-primary dark:border-white/10"
              }`}
              onClick={() => onToggleField("bought")}
              type="button"
            >
              <div className="flex items-center gap-1">
                {isBought ? (
                  <Check className="h-4 w-4" strokeWidth={2.5} />
                ) : (
                  <Euro className="h-4 w-4" strokeWidth={1.5} />
                )}
                <span>Acheté</span>
              </div>
              <span className="text-[10px] font-normal opacity-80">
                {isBought ? "Dans la collection" : "À acheter"}
              </span>
            </button>

            {/* Sur NAS */}
            <button
              aria-label={`Sur NAS : ${isOnNas ? "oui" : "non"}`}
              className={`flex h-16 flex-col items-center justify-center gap-1 rounded-xl border text-xs font-semibold transition-all active:scale-95 ${
                isOnNas
                  ? "border-blue-500/60 bg-blue-500/20 text-blue-600 shadow-sm dark:text-blue-300"
                  : "border-surface-border bg-surface-secondary text-text-muted hover:border-surface-border/80 hover:text-text-primary dark:border-white/10"
              }`}
              onClick={() => onToggleField("onNas")}
              type="button"
            >
              <div className="flex items-center gap-1">
                {isOnNas ? (
                  <Check className="h-4 w-4" strokeWidth={2.5} />
                ) : (
                  <HardDrive className="h-4 w-4" strokeWidth={1.5} />
                )}
                <span>Sur NAS</span>
              </div>
              <span className="text-[10px] font-normal opacity-80">
                {isOnNas ? "Fichier présent" : "Non téléchargé"}
              </span>
            </button>

            {/* Lu */}
            <button
              aria-label={`Lu : ${isRead ? "oui" : "non"}`}
              className={`flex h-16 flex-col items-center justify-center gap-1 rounded-xl border text-xs font-semibold transition-all active:scale-95 ${
                isRead
                  ? "border-amber-500/60 bg-amber-500/20 text-amber-600 shadow-sm dark:text-amber-300"
                  : "border-surface-border bg-surface-secondary text-text-muted hover:border-surface-border/80 hover:text-text-primary dark:border-white/10"
              }`}
              onClick={() => onToggleField("read")}
              type="button"
            >
              <div className="flex items-center gap-1">
                {isRead ? (
                  <Check className="h-4 w-4" strokeWidth={2.5} />
                ) : (
                  <Eye className="h-4 w-4" strokeWidth={1.5} />
                )}
                <span>Lu</span>
              </div>
              <span className="text-[10px] font-normal opacity-80">
                {isRead ? "Tome terminé" : "Non lu"}
              </span>
            </button>
          </div>
        </DialogPanel>
      </div>
    </Dialog>
  );
}
