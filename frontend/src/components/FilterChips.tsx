import { ComicStatus, ComicType, ComicTypeLabel } from "../types/enums";

interface FilterChipsProps {
  onStatusChange: (status: string) => void;
  onTypeChange: (type: string) => void;
  status: string;
  type: string;
}

interface ChipDef {
  label: string;
  value: string;
}

const typeChips: ChipDef[] = Object.values(ComicType).map((value) => ({
  label: ComicTypeLabel[value],
  value,
}));

const statusChips: ChipDef[] = [
  { label: "En cours", value: ComicStatus.BUYING },
  { label: "Terminé", value: ComicStatus.FINISHED },
  { label: "Arrêté", value: ComicStatus.STOPPED },
  { label: "Souhaits", value: ComicStatus.WISHLIST },
];

export default function FilterChips({ onStatusChange, onTypeChange, status, type }: FilterChipsProps) {
  return (
    <div
      className="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 scrollbar-none"
      data-testid="filter-chips"
    >
      {typeChips.map((chip) => {
        const active = type === chip.value;
        return (
          <button
            aria-pressed={active}
            className={`shrink-0 rounded-full border px-3 py-1 text-sm font-medium transition ${
              active
                ? "border-primary-500 bg-primary-500 text-white dark:border-primary-400 dark:bg-primary-400 dark:text-gray-900"
                : "border-surface-border bg-surface-primary text-text-secondary hover:border-primary-400 hover:text-text-primary"
            }`}
            key={chip.value}
            onClick={() => onTypeChange(active ? "" : chip.value)}
            type="button"
          >
            {chip.label}
          </button>
        );
      })}
      <span className="mx-0.5 shrink-0 self-stretch border-l border-surface-border" aria-hidden="true" />
      {statusChips.map((chip) => {
        const active = status === chip.value;
        return (
          <button
            aria-pressed={active}
            className={`shrink-0 rounded-full border px-3 py-1 text-sm font-medium transition ${
              active
                ? "border-primary-500 bg-primary-500 text-white dark:border-primary-400 dark:bg-primary-400 dark:text-gray-900"
                : "border-surface-border bg-surface-primary text-text-secondary hover:border-primary-400 hover:text-text-primary"
            }`}
            key={chip.value}
            onClick={() => onStatusChange(active ? "" : chip.value)}
            type="button"
          >
            {chip.label}
          </button>
        );
      })}
    </div>
  );
}
