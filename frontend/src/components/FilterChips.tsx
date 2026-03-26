import { memo } from "react";
import { ComicStatus, ComicStatusShortLabel, ComicType, ComicTypeLabel } from "../types/enums";

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

const statusChips: ChipDef[] = Object.values(ComicStatus).map((value) => ({
  label: ComicStatusShortLabel[value],
  value,
}));

const Chip = memo(function Chip({ active, label, onClick }: { active: boolean; label: string; onClick: () => void }) {
  return (
    <button
      aria-pressed={active}
      className={`shrink-0 rounded-full border px-3 py-1 text-sm font-medium transition ${
        active
          ? "border-primary-500 bg-primary-500 text-white dark:border-primary-400 dark:bg-primary-500 dark:text-white"
          : "border-surface-border bg-surface-elevated text-text-secondary hover:border-primary-400 hover:text-text-primary dark:border-white/10 dark:bg-white/5 dark:hover:border-primary-400/30"
      }`}
      onClick={onClick}
      type="button"
    >
      {label}
    </button>
  );
});

export default function FilterChips({ onStatusChange, onTypeChange, status, type }: FilterChipsProps) {
  return (
    <div
      aria-label="Filtres rapides"
      className="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 scrollbar-none"
      data-testid="filter-chips"
      role="group"
    >
      {typeChips.map((chip) => (
        <Chip
          active={type === chip.value}
          key={chip.value}
          label={chip.label}
          onClick={() => onTypeChange(type === chip.value ? "" : chip.value)}
        />
      ))}
      <span className="mx-0.5 shrink-0 self-stretch border-l border-surface-border dark:border-white/10" aria-hidden="true" />
      {statusChips.map((chip) => (
        <Chip
          active={status === chip.value}
          key={chip.value}
          label={chip.label}
          onClick={() => onStatusChange(status === chip.value ? "" : chip.value)}
        />
      ))}
    </div>
  );
}
