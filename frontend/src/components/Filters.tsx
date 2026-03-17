import { Dialog, DialogBackdrop, DialogPanel } from "@headlessui/react";
import { SlidersHorizontal, X } from "lucide-react";
import { useState } from "react";
import { useMediaQuery } from "../hooks/useMediaQuery";
import {
  type SelectOption,
  statusOptionsAll,
  typeOptionsAll,
} from "../types/enums";
import type { SortOption } from "../utils/sortComics";
import SelectListbox from "./SelectListbox";

interface FiltersProps {
  onSortChange: (sort: SortOption) => void;
  onStatusChange: (status: string) => void;
  onTypeChange: (type: string) => void;
  sort: SortOption;
  status: string;
  type: string;
}

const sortOptions: SelectOption[] = [
  { label: "Titre A→Z", value: "title-asc" },
  { label: "Titre Z→A", value: "title-desc" },
  { label: "Plus récent", value: "createdAt-desc" },
  { label: "Plus ancien", value: "createdAt-asc" },
  { label: "Plus de tomes", value: "tomes-desc" },
  { label: "Moins de tomes", value: "tomes-asc" },
];

function FilterSelect({
  label,
  onChange,
  options,
  value,
}: {
  label: string;
  onChange: (v: string) => void;
  options: SelectOption[];
  value: string;
}) {
  return (
    <label className="flex flex-col gap-1.5">
      <span className="text-sm font-medium text-text-primary">{label}</span>
      <select
        className="w-full rounded-lg border border-surface-border bg-surface-primary px-3 py-2.5 text-sm text-text-primary focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
        onChange={(e) => onChange(e.target.value)}
        value={value}
      >
        {options.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>
    </label>
  );
}

function FilterDrawer({
  onClose,
  onSortChange,
  onStatusChange,
  onTypeChange,
  open,
  sort,
  status,
  type,
}: FiltersProps & { onClose: () => void; open: boolean }) {
  return (
    <Dialog onClose={onClose} open={open}>
      <DialogBackdrop className="fixed inset-0 z-40 bg-black/40 transition duration-300 ease-out data-closed:opacity-0" />
      <DialogPanel className="fixed inset-x-0 bottom-0 z-50 rounded-t-2xl bg-surface-primary p-5 shadow-xl transition duration-300 ease-out data-closed:translate-y-full">
        <div className="mb-4 flex items-center justify-between">
          <h2 className="text-lg font-semibold text-text-primary">Filtres</h2>
          <button
            aria-label="Fermer"
            className="rounded-lg p-1.5 text-text-muted hover:bg-surface-secondary"
            onClick={onClose}
            type="button"
          >
            <X className="h-5 w-5" />
          </button>
        </div>
        <div className="flex flex-col gap-4">
          <FilterSelect
            label="Type"
            onChange={onTypeChange}
            options={typeOptionsAll}
            value={type}
          />
          <FilterSelect
            label="Statut"
            onChange={onStatusChange}
            options={statusOptionsAll}
            value={status}
          />
          <FilterSelect
            label="Tri"
            onChange={(v) => onSortChange(v as SortOption)}
            options={sortOptions}
            value={sort}
          />
        </div>
      </DialogPanel>
    </Dialog>
  );
}

export default function Filters({
  onSortChange,
  onStatusChange,
  onTypeChange,
  sort,
  status,
  type,
}: FiltersProps) {
  const isMobile = useMediaQuery("(max-width: 639px)");
  const [drawerOpen, setDrawerOpen] = useState(false);

  if (isMobile) {
    const hasActiveFilters = type !== "" || status !== "";

    return (
      <>
        <button
          aria-label="Filtres"
          className="relative shrink-0 rounded-lg border border-surface-border bg-surface-primary p-2 text-text-muted transition hover:border-primary-400 hover:text-text-primary"
          data-testid="filters-button"
          onClick={() => setDrawerOpen(true)}
          type="button"
        >
          <SlidersHorizontal className="h-5 w-5" />
          {hasActiveFilters && (
            <span
              className="absolute -right-0.5 -top-0.5 h-2.5 w-2.5 rounded-full bg-primary-500"
              data-testid="filters-indicator"
            />
          )}
        </button>
        <FilterDrawer
          onClose={() => setDrawerOpen(false)}
          onSortChange={onSortChange}
          onStatusChange={onStatusChange}
          onTypeChange={onTypeChange}
          open={drawerOpen}
          sort={sort}
          status={status}
          type={type}
        />
      </>
    );
  }

  return (
    <div className="flex min-w-0 flex-1 items-center gap-3">
      <div className="min-w-0 flex-1">
        <SelectListbox onChange={onTypeChange} options={typeOptionsAll} value={type} />
      </div>
      <div className="min-w-0 flex-1">
        <SelectListbox onChange={onStatusChange} options={statusOptionsAll} value={status} />
      </div>
      <div className="min-w-0 flex-1">
        <SelectListbox onChange={(v) => onSortChange(v as SortOption)} options={sortOptions} value={sort} />
      </div>
    </div>
  );
}
