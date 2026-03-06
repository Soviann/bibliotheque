import {
  Dialog,
  DialogBackdrop,
  DialogPanel,
  DialogTitle,
} from "@headlessui/react";
import { Search, X } from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { useCoverSearch } from "../hooks/useCoverSearch";
import SkeletonBox from "./SkeletonBox";

interface CoverSearchModalProps {
  defaultQuery: string;
  onClose: () => void;
  onSelect: (url: string) => void;
  open: boolean;
  type?: string;
}

export default function CoverSearchModal({
  defaultQuery,
  onClose,
  onSelect,
  open,
  type,
}: CoverSearchModalProps) {
  const [searchQuery, setSearchQuery] = useState(defaultQuery);
  const [debouncedQuery, setDebouncedQuery] = useState(defaultQuery);
  const inputRef = useRef<HTMLInputElement>(null);

  const { data: results, isLoading } = useCoverSearch(debouncedQuery, type);

  useEffect(() => {
    setSearchQuery(defaultQuery);
    setDebouncedQuery(defaultQuery);
  }, [defaultQuery]);

  useEffect(() => {
    const timer = setTimeout(() => setDebouncedQuery(searchQuery), 300);
    return () => clearTimeout(timer);
  }, [searchQuery]);

  useEffect(() => {
    if (open) {
      setTimeout(() => inputRef.current?.select(), 50);
    }
  }, [open]);

  return (
    <Dialog className="relative z-50" onClose={onClose} open={open}>
      <DialogBackdrop className="fixed inset-0 bg-black/30" />
      <div className="fixed inset-0 flex items-center justify-center p-4">
        <DialogPanel className="flex max-h-[80vh] w-full max-w-2xl flex-col rounded-xl bg-surface-primary shadow-lg">
          <div className="flex items-center justify-between border-b border-surface-border px-4 py-3">
            <DialogTitle className="text-lg font-semibold text-text-primary">
              Rechercher une couverture
            </DialogTitle>
            <button
              className="rounded-lg p-1 text-text-secondary hover:bg-surface-tertiary"
              onClick={onClose}
              type="button"
            >
              <X className="h-5 w-5" />
            </button>
          </div>

          <div className="border-b border-surface-border px-4 py-3">
            <div className="relative">
              <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-secondary" />
              <input
                className="w-full rounded-lg border border-surface-border bg-surface-primary py-2 pl-9 pr-3 text-sm text-text-primary placeholder:text-text-secondary"
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Rechercher..."
                ref={inputRef}
                type="text"
                value={searchQuery}
              />
            </div>
          </div>

          <div className="overflow-y-auto p-4">
            {isLoading && debouncedQuery.length >= 2 && (
              <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                {Array.from({ length: 8 }).map((_, i) => (
                  <SkeletonBox className="aspect-[2/3]" key={i} />
                ))}
              </div>
            )}

            {!isLoading && results && results.length === 0 && debouncedQuery.length >= 2 && (
              <p className="py-8 text-center text-sm text-text-secondary">
                Aucune image trouvée
              </p>
            )}

            {!isLoading && results && results.length > 0 && (
              <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                {results.map((result) => (
                  <button
                    className="group relative overflow-hidden rounded-lg border border-surface-border transition hover:ring-2 hover:ring-accent-primary"
                    key={result.url}
                    onClick={() => onSelect(result.url)}
                    type="button"
                  >
                    <img
                      alt={result.title}
                      className="aspect-[2/3] w-full object-cover"
                      loading="lazy"
                      src={result.thumbnail}
                    />
                    <span className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100">
                      {result.width}×{result.height}
                    </span>
                  </button>
                ))}
              </div>
            )}

            {debouncedQuery.length < 2 && (
              <p className="py-8 text-center text-sm text-text-secondary">
                Saisissez au moins 2 caractères
              </p>
            )}
          </div>
        </DialogPanel>
      </div>
    </Dialog>
  );
}
