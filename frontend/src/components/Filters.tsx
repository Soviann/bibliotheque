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

interface FiltersProps {
  hideStatus?: boolean;
  onStatusChange: (status: string) => void;
  onTypeChange: (type: string) => void;
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

const statusOptions: Option[] = [
  { label: "Tous les statuts", value: "" },
  ...Object.entries(ComicStatus).map(([, value]) => ({
    label: ComicStatusLabel[value],
    value,
  })),
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
          <span>{selected.label}</span>
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
  onStatusChange,
  onTypeChange,
  status,
  type,
}: FiltersProps) {
  return (
    <div className="flex flex-wrap items-center gap-3">
      <div className="w-44">
        <SelectListbox onChange={onTypeChange} options={typeOptions} value={type} />
      </div>
      {!hideStatus && (
        <div className="w-48">
          <SelectListbox onChange={onStatusChange} options={statusOptions} value={status} />
        </div>
      )}
    </div>
  );
}
