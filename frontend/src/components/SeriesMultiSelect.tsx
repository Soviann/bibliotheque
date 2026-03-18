import {
  Dialog,
  DialogBackdrop,
  DialogPanel,
  DialogTitle,
} from "@headlessui/react";
import { Eye, Search, X } from "lucide-react";
import { useDeferredValue, useMemo, useState } from "react";
import { useComics } from "../hooks/useComics";
import { formCheckboxFocusClassName } from "../styles/formStyles";
import type { ComicSeries } from "../types/api";
import { ComicStatusLabel, ComicTypeLabel } from "../types/enums";

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
  const [detailComic, setDetailComic] = useState<ComicSeries | null>(null);

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
          aria-label="Rechercher une série"
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
                  className={formCheckboxFocusClassName}
                  onChange={() => toggleSelection(comic.id)}
                  type="checkbox"
                />
                <span className="text-sm text-text-primary">{comic.title}</span>
                <span className="rounded-md bg-surface-tertiary px-1.5 py-0.5 text-xs text-text-secondary">
                  {ComicTypeLabel[comic.type]}
                </span>
                <button
                  aria-label={`Détail de ${comic.title}`}
                  className="ml-auto rounded p-1 text-text-muted hover:bg-surface-tertiary hover:text-text-primary"
                  onClick={(e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    setDetailComic(comic);
                  }}
                  type="button"
                >
                  <Eye className="h-3.5 w-3.5" />
                </button>
                <span className="text-xs text-text-muted">
                  {comic.tomes.length} tome{comic.tomes.length !== 1 ? "s" : ""}
                </span>
              </label>
            );
          })
        )}
      </div>

      {/* Série detail modal */}
      <Dialog
        className="relative z-50"
        onClose={() => setDetailComic(null)}
        open={detailComic !== null}
      >
        <DialogBackdrop className="fixed inset-0 bg-black/30" />
        <div className="fixed inset-0 flex items-center justify-center p-4">
          <DialogPanel className="w-full max-w-md rounded-xl bg-surface-primary p-6 shadow-lg">
            {detailComic && (
              <>
                <DialogTitle className="text-lg font-semibold text-text-primary">
                  {detailComic.title}
                </DialogTitle>
                <dl className="mt-4 space-y-2 text-sm">
                  <div className="flex justify-between">
                    <dt className="text-text-muted">Type</dt>
                    <dd className="text-text-primary">{ComicTypeLabel[detailComic.type]}</dd>
                  </div>
                  <div className="flex justify-between">
                    <dt className="text-text-muted">Statut</dt>
                    <dd className="text-text-primary">{ComicStatusLabel[detailComic.status]}</dd>
                  </div>
                  <div className="flex justify-between">
                    <dt className="text-text-muted">Tomes</dt>
                    <dd className="text-text-primary">{detailComic.tomes.length}</dd>
                  </div>
                  {detailComic.authors.length > 0 && (
                    <div className="flex justify-between">
                      <dt className="text-text-muted">Auteurs</dt>
                      <dd className="text-text-primary">
                        {detailComic.authors.map((a) => a.name).join(", ")}
                      </dd>
                    </div>
                  )}
                  {detailComic.publisher && (
                    <div className="flex justify-between">
                      <dt className="text-text-muted">Éditeur</dt>
                      <dd className="text-text-primary">{detailComic.publisher}</dd>
                    </div>
                  )}
                  {detailComic.description && (
                    <div>
                      <dt className="text-text-muted">Description</dt>
                      <dd className="mt-1 text-text-secondary">{detailComic.description}</dd>
                    </div>
                  )}
                </dl>
                <div className="mt-6 flex justify-end">
                  <button
                    className="rounded-lg bg-surface-secondary px-4 py-2 text-sm font-medium text-text-primary hover:bg-surface-tertiary"
                    onClick={() => setDetailComic(null)}
                    type="button"
                  >
                    Fermer
                  </button>
                </div>
              </>
            )}
          </DialogPanel>
        </div>
      </Dialog>
    </div>
  );
}
