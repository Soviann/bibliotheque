import { Search } from "lucide-react";

interface SearchInputProps {
  ariaLabel?: string;
  onChange: (value: string) => void;
  placeholder?: string;
  value: string;
}

export default function SearchInput({
  ariaLabel,
  onChange,
  placeholder = "Rechercher…",
  value,
}: SearchInputProps) {
  return (
    <div className="relative min-w-0 flex-1">
      <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
      <input
        aria-label={ariaLabel}
        className="w-full rounded-lg border border-surface-border bg-surface-primary py-2 pl-10 pr-4 text-sm text-text-primary placeholder:text-text-muted focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
        onChange={(e) => onChange(e.target.value)}
        placeholder={placeholder}
        type="search"
        value={value}
      />
    </div>
  );
}
