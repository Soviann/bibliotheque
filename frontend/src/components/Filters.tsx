import {
  Listbox,
  ListboxButton,
  ListboxOption,
  ListboxOptions,
} from "@headlessui/react";
import { Check, ChevronDown } from "lucide-react";
import {
  ComicStatus,
  ComicStatusLabel,
  ComicType,
  ComicTypeLabel,
} from "../types/enums";
import type { SortOption } from "../utils/sortComics";

interface FiltersProps {
  hideStatus?: boolean;
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

export default function Filters({
  hideStatus,
  onSortChange,
  onStatusChange,
  onTypeChange,
  sort,
  status,
  type,
}: FiltersProps) {
  return (
    <div className="flex min-w-0 flex-1 items-center gap-3">
      <div className="min-w-0 flex-1">
        <SelectListbox onChange={onTypeChange} options={typeOptions} value={type} />
      </div>
      {!hideStatus && (
        <div className="min-w-0 flex-1">
          <SelectListbox onChange={onStatusChange} options={statusOptions} value={status} />
        </div>
      )}
      <div className="min-w-0 flex-1">
        <SelectListbox onChange={(v) => onSortChange(v as SortOption)} options={sortOptions} value={sort} />
      </div>
    </div>
  );
}
