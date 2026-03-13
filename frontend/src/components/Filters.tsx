import {
  Dialog,
  DialogPanel,
  Listbox,
  ListboxButton,
  ListboxOption,
  ListboxOptions,
} from "@headlessui/react";
import { Check, ChevronDown, SlidersHorizontal, X } from "lucide-react";
import { useState } from "react";
import { useMediaQuery } from "../hooks/useMediaQuery";
import {
  ComicStatus,
  ComicStatusLabel,
  ComicType,
  ComicTypeLabel,
} from "../types/enums";
import type { SortOption } from "../utils/sortComics";

interface FiltersProps {
  onSortChange: (sort: SortOption) => void;
  onStatusChange: (status: string) => void;
  onTypeChange: (type: string) => void;
  sort: SortOption;
  status: string;
  type: string;
}

interface Option {
  label: string;
  value: string;
}

const typeOptions: Option[] = [
  { label: "Tous les types", value: "" },
  ...Object.entries(ComicType).map(([, value]) => ({
    label: ComicTypeLabel[value],
    value,
  })),
];

const sortOptions: Option[] = [
  { label: "Titre A→Z", value: "title-asc" },
  { label: "Titre Z→A", value: "title-desc" },
  { label: "Plus récent", value: "createdAt-desc" },
  { label: "Plus ancien", value: "createdAt-asc" },
  { label: "Plus de tomes", value: "tomes-desc" },
  { label: "Moins de tomes", value: "tomes-asc" },
];

const statusOptions: Option[] = [
  { label: "Tous les statuts", value: "" },
  ...Object.entries(ComicStatus)
    .map(([, value]) => ({
      label: ComicStatusLabel[value],
      value,
    }))
    .sort((a, b) => a.label.localeCompare(b.label)),
];

function SelectListbox({
  onChange,
  options,
  value,
}: {
  onChange: (v: string) => void;
  options: Option[];
  value: string;
}) {
  const selected = options.find((o) => o.value === value) ?? options[0];

  return (
    <Listbox onChange={onChange} value={value}>
      <div className="relative">
        <ListboxButton className="flex w-full items-center justify-between gap-2 rounded-lg border border-surface-border bg-surface-primary px-3 py-1.5 text-sm text-text-primary transition hover:border-primary-400 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
          <span className="truncate">{selected.label}</span>
          <ChevronDown className="h-4 w-4 text-text-muted" />
        </ListboxButton>
        <ListboxOptions className="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-surface-border bg-surface-primary py-1 shadow-lg transition focus:outline-none">
          {options.map((option) => (
            <ListboxOption
              className="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm text-text-primary data-[focus]:bg-primary-50 dark:data-[focus]:bg-primary-950/30"
              key={option.value}
              value={option.value}
            >
              <Check
                className={`h-4 w-4 shrink-0 ${option.value === value ? "text-primary-600" : "invisible"}`}
              />
              {option.label}
            </ListboxOption>
          ))}
        </ListboxOptions>
      </div>
    </Listbox>
  );
}

function FilterSelect({
  label,
  onChange,
  options,
  value,
}: {
  label: string;
  onChange: (v: string) => void;
  options: Option[];
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
      <div className="fixed inset-0 z-40 bg-black/40" aria-hidden="true" />
      <DialogPanel className="fixed inset-x-0 bottom-0 z-50 rounded-t-2xl bg-surface-primary p-5 shadow-xl">
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
            options={typeOptions}
            value={type}
          />
          <FilterSelect
            label="Statut"
            onChange={onStatusChange}
            options={statusOptions}
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
        <SelectListbox onChange={onTypeChange} options={typeOptions} value={type} />
      </div>
      <div className="min-w-0 flex-1">
        <SelectListbox onChange={onStatusChange} options={statusOptions} value={status} />
      </div>
      <div className="min-w-0 flex-1">
        <SelectListbox onChange={(v) => onSortChange(v as SortOption)} options={sortOptions} value={sort} />
      </div>
    </div>
  );
}
