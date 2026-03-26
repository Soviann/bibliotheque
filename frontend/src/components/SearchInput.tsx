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
      <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" strokeWidth={1.5} />
      <input
        aria-label={ariaLabel}
        className="w-full rounded-xl border border-surface-border bg-surface-elevated px-4 py-2.5 pl-10 text-sm text-text-primary placeholder:text-text-muted focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:backdrop-blur-sm"
        onChange={(e) => onChange(e.target.value)}
        placeholder={placeholder}
        type="search"
        value={value}
      />
    </div>
  );
}
