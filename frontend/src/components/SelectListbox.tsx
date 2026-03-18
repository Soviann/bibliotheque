import {
  Listbox,
  ListboxButton,
  ListboxOption,
  ListboxOptions,
} from "@headlessui/react";
import { Check, ChevronDown } from "lucide-react";
import type { SelectOption } from "../types/enums";

interface SelectListboxProps {
  buttonClassName?: string;
  label?: string;
  onChange: (v: string) => void;
  options: SelectOption[];
  placeholder?: string;
  value: string;
}

export default function SelectListbox({
  buttonClassName,
  label,
  onChange,
  options,
  placeholder,
  value,
}: SelectListboxProps) {
  const selected = options.find((o) => o.value === value);
  const displayLabel = selected?.label ?? placeholder ?? options[0]?.label;

  return (
    <div>
      {label && (
        <span className="mb-1 block text-sm font-medium text-text-secondary">
          {label}
        </span>
      )}
      <Listbox onChange={onChange} value={value}>
        <div className="relative">
          <ListboxButton
            aria-label={!label ? (placeholder ?? options[0]?.label) : undefined}
            className={
              buttonClassName ??
              "flex w-full items-center justify-between gap-2 rounded-lg border border-surface-border bg-surface-primary px-3 py-1.5 text-sm text-text-primary transition hover:border-primary-400 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
            }
          >
            <span
              className={`truncate ${!selected && placeholder ? "text-text-muted" : ""}`}
            >
              {displayLabel}
            </span>
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
    </div>
  );
}
