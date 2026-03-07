import { Search, X } from "lucide-react";
import { useDeferredValue, useMemo, useState } from "react";
import { useComics } from "../hooks/useComics";

interface SeriesMultiSelectProps {
  onSelectionChange: (ids: number[]) => void;
  selectedIds: number[];
}

export default function SeriesMultiSelect({
  onSelectionChange,
  selectedIds,
}: SeriesMultiSelectProps) {
  const [search, setSearch] = useState("");
  const deferredSearch = useDeferredValue(search);
  const { data } = useComics();

  const comics = data?.member ?? [];

  const selectedComics = useMemo(
    () => comics.filter((c) => selectedIds.includes(c.id)),
    [comics, selectedIds],
  );

  const filteredComics = useMemo(() => {
    const term = deferredSearch.toLowerCase().trim();
    if (!term) return comics;
    return comics.filter((c) => c.title.toLowerCase().includes(term));
  }, [comics, deferredSearch]);

  const toggleSelection = (id: number) => {
    if (selectedIds.includes(id)) {
      onSelectionChange(selectedIds.filter((s) => s !== id));
    } else {
      onSelectionChange([...selectedIds, id]);
    }
  };

  const removeSelection = (id: number) => {
    onSelectionChange(selectedIds.filter((s) => s !== id));
  };

  return (
    <div className="space-y-3">
      {/* Selected chips */}
      {selectedComics.length > 0 && (
        <div className="flex flex-wrap gap-2">
          {selectedComics.map((comic) => (
            <span
              className="inline-flex items-center gap-1 rounded-full bg-primary-100 px-3 py-1 text-sm font-medium text-primary-700 dark:bg-primary-950/30 dark:text-primary-400"
              key={comic.id}
            >
              {comic.title}
              <button
                className="ml-0.5 rounded-full p-0.5 hover:bg-primary-200 dark:hover:bg-primary-900/40"
                onClick={() => removeSelection(comic.id)}
                type="button"
              >
                <X className="h-3 w-3" />
              </button>
            </span>
          ))}
        </div>
      )}

      {/* Search input */}
      <div className="relative">
        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
        <input
          className="w-full rounded-lg border border-surface-border bg-surface-secondary py-2 pl-9 pr-3 text-sm text-text-primary placeholder:text-text-muted focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
          onChange={(e) => setSearch(e.target.value)}
          placeholder="Rechercher une série..."
          type="text"
          value={search}
        />
      </div>

      {/* Result count */}
      <p className="text-xs text-text-muted">
        {filteredComics.length} série{filteredComics.length !== 1 ? "s" : ""} affichée{filteredComics.length !== 1 ? "s" : ""}
        {selectedIds.length > 0 && ` · ${selectedIds.length} sélectionnée${selectedIds.length !== 1 ? "s" : ""}`}
      </p>

      {/* Scrollable list */}
      <div className="max-h-[60vh] overflow-y-auto rounded-lg border border-surface-border">
        {filteredComics.length === 0 ? (
          <p className="px-3 py-4 text-center text-sm text-text-muted">
            Aucune série trouvée
          </p>
        ) : (
          filteredComics.map((comic) => {
            const isSelected = selectedIds.includes(comic.id);
            return (
              <label
                className={`flex cursor-pointer items-center gap-3 border-b border-surface-border px-3 py-2 last:border-0 hover:bg-surface-secondary ${
                  isSelected ? "bg-primary-50 dark:bg-primary-950/20" : ""
                }`}
                key={comic.id}
              >
                <input
                  checked={isSelected}
                  className="h-4 w-4 rounded border-surface-border text-primary-600 focus:ring-primary-500"
                  onChange={() => toggleSelection(comic.id)}
                  type="checkbox"
                />
                <span className="text-sm text-text-primary">{comic.title}</span>
                <span className="ml-auto text-xs text-text-muted">
                  {comic.tomes.length} tome{comic.tomes.length !== 1 ? "s" : ""}
                </span>
              </label>
            );
          })
        )}
      </div>
    </div>
  );
}
