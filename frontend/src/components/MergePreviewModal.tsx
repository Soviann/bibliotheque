import {
  Dialog,
  DialogBackdrop,
  DialogPanel,
  DialogTitle,
} from "@headlessui/react";
import { AlertTriangle, Loader2, Plus, Sparkles, X } from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import type {
  MergePreview,
  MergePreviewTome,
  MergeSuggestion,
} from "../types/api";
import { statusOptions, typeOptions } from "../types/enums";
import DatePartialSelect from "./DatePartialSelect";
import SelectListbox from "./SelectListbox";

const mergeInputClassName =
  "rounded-lg border border-surface-border bg-surface-secondary px-3 py-2 text-sm text-text-primary focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500";

const mergeListboxClassName =
  "flex w-full items-center justify-between gap-2 rounded-lg border border-surface-border bg-surface-secondary px-3 py-2 text-sm text-text-primary transition hover:border-primary-400 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500";

interface MergePreviewModalProps {
  isExecuting: boolean;
  isSuggesting?: boolean;
  onClose: () => void;
  onConfirm: (preview: MergePreview) => void;
  open: boolean;
  preview: MergePreview | null;
  suggestion?: MergeSuggestion | null;
}

export default function MergePreviewModal({
  isExecuting,
  isSuggesting = false,
  onClose,
  onConfirm,
  open,
  preview,
  suggestion,
}: MergePreviewModalProps) {
  const [editedAmazonUrl, setEditedAmazonUrl] = useState("");
  const [editedAuthors, setEditedAuthors] = useState("");
  const [editedCoverUrl, setEditedCoverUrl] = useState("");
  const [editedDefaultTomeBought, setEditedDefaultTomeBought] = useState(false);
  const [editedDefaultTomeDownloaded, setEditedDefaultTomeDownloaded] = useState(false);
  const [editedDefaultTomeRead, setEditedDefaultTomeRead] = useState(false);
  const [editedDescription, setEditedDescription] = useState("");
  const [editedIsOneShot, setEditedIsOneShot] = useState(false);
  const [editedLatestPublishedIssue, setEditedLatestPublishedIssue] = useState("");
  const [editedLatestPublishedIssueComplete, setEditedLatestPublishedIssueComplete] = useState(false);
  const [editedNotInterestedBuy, setEditedNotInterestedBuy] = useState(false);
  const [editedNotInterestedNas, setEditedNotInterestedNas] = useState(false);
  const [editedPublishedDate, setEditedPublishedDate] = useState("");
  const [editedPublisher, setEditedPublisher] = useState("");
  const [editedStatus, setEditedStatus] = useState("buying");
  const [editedTitle, setEditedTitle] = useState("");
  const [editedTomes, setEditedTomes] = useState<MergePreviewTome[]>([]);
  const [editedType, setEditedType] = useState("bd");

  const [suggestionApplied, setSuggestionApplied] = useState(false);

  // Sync local state when preview changes
  useEffect(() => {
    if (preview) {
      setEditedAmazonUrl(preview.amazonUrl ?? "");
      setEditedAuthors(preview.authors.join(", "));
      setEditedCoverUrl(preview.coverUrl ?? "");
      setEditedDefaultTomeBought(preview.defaultTomeBought);
      setEditedDefaultTomeDownloaded(preview.defaultTomeDownloaded);
      setEditedDefaultTomeRead(preview.defaultTomeRead);
      setEditedDescription(preview.description ?? "");
      setEditedIsOneShot(preview.isOneShot);
      setEditedLatestPublishedIssue(preview.latestPublishedIssue?.toString() ?? "");
      setEditedLatestPublishedIssueComplete(preview.latestPublishedIssueComplete);
      setEditedNotInterestedBuy(preview.notInterestedBuy);
      setEditedNotInterestedNas(preview.notInterestedNas);
      setEditedPublishedDate(preview.publishedDate ?? "");
      setEditedPublisher(preview.publisher ?? "");
      setEditedStatus(preview.status);
      setEditedTitle(preview.title);
      setEditedTomes(preview.tomes.map((t) => ({ ...t })));
      setEditedType(preview.type);
      setSuggestionApplied(false);
    }
  }, [preview]);

  // Appliquer les suggestions Gemini quand elles arrivent
  useEffect(() => {
    if (!suggestion || suggestionApplied) return;

    setEditedTitle(suggestion.title);

    // Construire une map ID → tomeNumber pour les suggestions
    const tomeNumberMap = new Map(
      suggestion.entries.map((e) => [e.id, e.tomeNumber]),
    );

    // Mettre à jour les numéros de tomes en se basant sur sourceSeriesIds
    if (preview) {
      const newTomes = preview.tomes.map((tome, index) => {
        const seriesId = preview.sourceSeriesIds[index];
        const suggestedNumber = seriesId !== undefined ? tomeNumberMap.get(seriesId) : undefined;
        return {
          ...tome,
          number: suggestedNumber ?? tome.number,
        };
      });
      // Trier par numéro
      newTomes.sort((a, b) => a.number - b.number);
      setEditedTomes(newTomes);
    }

    setSuggestionApplied(true);
  }, [suggestion, suggestionApplied, preview]);

  const duplicateNumbers = useMemo(() => {
    const counts = new Map<number, number>();
    for (const t of editedTomes) {
      counts.set(t.number, (counts.get(t.number) ?? 0) + 1);
    }
    const dupes = new Set<number>();
    for (const [num, count] of counts) {
      if (count > 1) dupes.add(num);
    }
    return dupes;
  }, [editedTomes]);

  const hasDuplicates = duplicateNumbers.size > 0;

  const updateTome = (index: number, patch: Partial<MergePreviewTome>) => {
    setEditedTomes((prev) => {
      const next = [...prev];
      next[index] = { ...next[index], ...patch };
      return next;
    });
  };

  const removeTome = (index: number) => {
    setEditedTomes((prev) => prev.filter((_, i) => i !== index));
  };

  const addTome = () => {
    setEditedTomes((prev) => {
      const maxNumber = prev.reduce((max, t) => Math.max(max, t.number, t.tomeEnd ?? 0), 0);
      return [
        ...prev,
        {
          bought: false,
          downloaded: false,
          isbn: null,
          number: maxNumber + 1,
          onNas: false,
          read: false,
          title: null,
          tomeEnd: null,
        },
      ];
    });
  };

  if (!preview) return null;

  const inputClass =
    "rounded border border-surface-border bg-surface-secondary px-1.5 py-0.5 text-sm text-text-primary focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500";

  return (
    <Dialog
      className="relative z-50"
      onClose={onClose}
      open={open}
    >
      <DialogBackdrop className="fixed inset-0 bg-black/30" />
      <div className="fixed inset-0 flex items-center justify-center p-4">
        <DialogPanel className="flex max-h-[90vh] w-full max-w-4xl flex-col rounded-xl bg-surface-primary shadow-lg">
          {/* Metadata (non-scrollable) */}
          <div className="shrink-0 px-6 pt-6">
            <DialogTitle className="text-lg font-semibold text-text-primary">
              Aperçu de la fusion
            </DialogTitle>

            {/* Titre editable */}
            <div className="mt-4">
              <label
                className="block text-sm font-medium text-text-secondary"
                htmlFor="merge-title"
              >
                Titre
              </label>
              <input
                className="mt-1 w-full rounded-lg border border-surface-border bg-surface-secondary px-3 py-2 text-text-primary focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                id="merge-title"
                onChange={(e) => setEditedTitle(e.target.value)}
                type="text"
                value={editedTitle}
              />
            </div>

            {isSuggesting && (
              <div className="mt-2 flex items-center gap-1.5 text-xs text-text-muted">
                <Sparkles className="h-3.5 w-3.5 animate-pulse text-amber-500" />
                Suggestions IA en cours...
              </div>
            )}
          </div>

          {/* Scrollable content: metadata fields + tome table */}
          <div className="min-h-0 flex-1 overflow-auto px-6 pb-2">
            {/* Type + Status */}
            <div className="mt-4 grid grid-cols-2 gap-4">
              <SelectListbox
                buttonClassName={mergeListboxClassName}
                label="Type"
                onChange={setEditedType}
                options={typeOptions}
                value={editedType}
              />
              <SelectListbox
                buttonClassName={mergeListboxClassName}
                label="Statut"
                onChange={setEditedStatus}
                options={statusOptions}
                value={editedStatus}
              />
            </div>

            {/* One-shot */}
            <label className="mt-3 flex items-center gap-2">
              <input
                checked={editedIsOneShot}
                className="h-4 w-4 rounded border-surface-border text-primary-600"
                onChange={(e) => setEditedIsOneShot(e.target.checked)}
                type="checkbox"
              />
              <span className="text-sm font-medium text-text-secondary">One-shot</span>
            </label>

            {/* Publisher */}
            <div className="mt-3">
              <label className="block text-sm font-medium text-text-secondary" htmlFor="merge-publisher">
                Éditeur
              </label>
              <input
                className={`mt-1 w-full ${mergeInputClassName}`}
                id="merge-publisher"
                onChange={(e) => setEditedPublisher(e.target.value)}
                type="text"
                value={editedPublisher}
              />
            </div>

            {/* Published date */}
            <div className="mt-3">
              <DatePartialSelect
                label="Date de parution"
                onChange={setEditedPublishedDate}
                value={editedPublishedDate}
              />
            </div>

            {/* Cover URL */}
            <div className="mt-3">
              <label className="block text-sm font-medium text-text-secondary" htmlFor="merge-coverUrl">
                URL de couverture
              </label>
              <input
                className={`mt-1 w-full ${mergeInputClassName}`}
                id="merge-coverUrl"
                onChange={(e) => setEditedCoverUrl(e.target.value)}
                placeholder="https://..."
                type="url"
                value={editedCoverUrl}
              />
              {editedCoverUrl && (
                <img alt="Aperçu" className="mt-2 h-24 rounded-lg shadow" src={editedCoverUrl} />
              )}
            </div>

            {/* Authors */}
            <div className="mt-3">
              <label className="block text-sm font-medium text-text-secondary" htmlFor="merge-authors">
                Auteurs (séparés par des virgules)
              </label>
              <input
                className={`mt-1 w-full ${mergeInputClassName}`}
                id="merge-authors"
                onChange={(e) => setEditedAuthors(e.target.value)}
                type="text"
                value={editedAuthors}
              />
            </div>

            {/* Description */}
            <div className="mt-3">
              <label className="block text-sm font-medium text-text-secondary" htmlFor="merge-description">
                Description
              </label>
              <textarea
                className={`mt-1 w-full ${mergeInputClassName}`}
                id="merge-description"
                onChange={(e) => setEditedDescription(e.target.value)}
                rows={3}
                value={editedDescription}
              />
            </div>

            {/* Latest published issue + flags */}
            <div className="mt-3 flex flex-wrap items-end gap-x-4 gap-y-2">
              <div>
                <label className="block text-sm font-medium text-text-secondary" htmlFor="merge-latestIssue">
                  Dernier tome paru
                </label>
                <input
                  className={`mt-1 w-24 ${mergeInputClassName}`}
                  id="merge-latestIssue"
                  min="0"
                  onChange={(e) => setEditedLatestPublishedIssue(e.target.value)}
                  type="number"
                  value={editedLatestPublishedIssue}
                />
              </div>
              <label className="flex items-center gap-2 pb-2">
                <input
                  checked={editedLatestPublishedIssueComplete}
                  className="h-4 w-4 rounded border-surface-border text-primary-600"
                  onChange={(e) => setEditedLatestPublishedIssueComplete(e.target.checked)}
                  type="checkbox"
                />
                <span className="text-sm text-text-secondary">Parution terminée</span>
              </label>
            </div>

            {/* Default tome flags */}
            <div className="mt-3 flex items-center gap-4">
              <span className="text-sm font-medium text-text-secondary">Flags par défaut :</span>
              <label className="flex items-center gap-1.5">
                <input
                  checked={editedDefaultTomeBought}
                  className="h-4 w-4 rounded border-surface-border text-primary-600"
                  onChange={(e) => setEditedDefaultTomeBought(e.target.checked)}
                  type="checkbox"
                />
                <span className="text-sm text-text-secondary">Achetés</span>
              </label>
              <label className="flex items-center gap-1.5">
                <input
                  checked={editedDefaultTomeDownloaded}
                  className="h-4 w-4 rounded border-surface-border text-primary-600"
                  onChange={(e) => setEditedDefaultTomeDownloaded(e.target.checked)}
                  type="checkbox"
                />
                <span className="text-sm text-text-secondary">Téléchargés</span>
              </label>
              <label className="flex items-center gap-1.5">
                <input
                  checked={editedDefaultTomeRead}
                  className="h-4 w-4 rounded border-surface-border text-primary-600"
                  onChange={(e) => setEditedDefaultTomeRead(e.target.checked)}
                  type="checkbox"
                />
                <span className="text-sm text-text-secondary">Lus</span>
              </label>
            </div>

            {/* Amazon URL */}
            <div className="mt-3">
              <label className="block text-sm font-medium text-text-secondary" htmlFor="merge-amazonUrl">
                URL Amazon
              </label>
              <input
                className={`mt-1 w-full ${mergeInputClassName}`}
                id="merge-amazonUrl"
                onChange={(e) => setEditedAmazonUrl(e.target.value)}
                placeholder="https://..."
                type="url"
                value={editedAmazonUrl}
              />
            </div>

            {/* Not interested flags */}
            <div className="mt-3 flex items-center gap-4">
              <span className="text-sm font-medium text-text-secondary">Pas intéressé :</span>
              <label className="flex items-center gap-1.5">
                <input
                  checked={editedNotInterestedBuy}
                  className="h-4 w-4 rounded border-surface-border text-primary-600"
                  onChange={(e) => setEditedNotInterestedBuy(e.target.checked)}
                  type="checkbox"
                />
                <span className="text-sm text-text-secondary">Achat</span>
              </label>
              <label className="flex items-center gap-1.5">
                <input
                  checked={editedNotInterestedNas}
                  className="h-4 w-4 rounded border-surface-border text-primary-600"
                  onChange={(e) => setEditedNotInterestedNas(e.target.checked)}
                  type="checkbox"
                />
                <span className="text-sm text-text-secondary">NAS</span>
              </label>
            </div>

            {/* Duplicate warning */}
            {hasDuplicates && (
              <div className="mt-4 flex items-center gap-2 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-700 dark:bg-amber-950/30 dark:text-amber-400">
                <AlertTriangle className="h-4 w-4 shrink-0" />
                Numéros de tomes en double détectés. Modifiez-les avant de confirmer.
              </div>
            )}

            {/* Tome table */}
            <table className="w-full text-left text-sm">
              <thead className="sticky top-0 z-10 bg-surface-primary">
                <tr className="border-b border-surface-border text-text-muted">
                    <th className="w-16 px-2 py-2 font-medium">#</th>
                    <th className="w-16 px-2 py-2 font-medium">Fin</th>
                    <th className="min-w-[140px] px-2 py-2 font-medium">Titre</th>
                    <th className="min-w-[120px] px-2 py-2 font-medium">ISBN</th>
                    <th className="px-2 py-2 text-center font-medium">Achat</th>
                    <th className="px-2 py-2 text-center font-medium">DL</th>
                    <th className="px-2 py-2 text-center font-medium">Lu</th>
                    <th className="px-2 py-2 text-center font-medium">NAS</th>
                    <th className="w-10 px-2 py-2" />
                  </tr>
                </thead>
                <tbody>
                  {editedTomes.map((tome, index) => {
                    const isDuplicate = duplicateNumbers.has(tome.number);
                    return (
                      <tr
                        className={`border-b border-surface-border last:border-0 ${isDuplicate ? "bg-amber-50 dark:bg-amber-950/20" : ""}`}
                        key={index}
                      >
                        <td className="px-2 py-1.5">
                          <input
                            className={`w-14 ${inputClass} ${isDuplicate ? "!border-amber-400 !bg-amber-50 dark:!border-amber-600 dark:!bg-amber-950/30" : ""}`}
                            min={1}
                            onChange={(e) => updateTome(index, { number: parseInt(e.target.value, 10) || 1 })}
                            type="number"
                            value={tome.number}
                          />
                        </td>
                        <td className="px-2 py-1.5">
                          <input
                            className={`w-14 ${inputClass}`}
                            min={1}
                            onChange={(e) => {
                              const v = e.target.value;
                              updateTome(index, { tomeEnd: v ? parseInt(v, 10) || null : null });
                            }}
                            placeholder="-"
                            type="number"
                            value={tome.tomeEnd ?? ""}
                          />
                        </td>
                        <td className="px-2 py-1.5">
                          <input
                            className={`w-full ${inputClass}`}
                            onChange={(e) => updateTome(index, { title: e.target.value || null })}
                            placeholder="-"
                            type="text"
                            value={tome.title ?? ""}
                          />
                        </td>
                        <td className="px-2 py-1.5">
                          <input
                            className={`w-full ${inputClass}`}
                            onChange={(e) => updateTome(index, { isbn: e.target.value || null })}
                            placeholder="-"
                            type="text"
                            value={tome.isbn ?? ""}
                          />
                        </td>
                        <td className="px-2 py-1.5 text-center">
                          <input
                            aria-label={`Tome ${tome.number} acheté`}
                            checked={tome.bought}
                            className="h-4 w-4 rounded border-surface-border text-primary-600 focus:ring-primary-500"
                            onChange={(e) => updateTome(index, { bought: e.target.checked })}
                            type="checkbox"
                          />
                        </td>
                        <td className="px-2 py-1.5 text-center">
                          <input
                            aria-label={`Tome ${tome.number} téléchargé`}
                            checked={tome.downloaded}
                            className="h-4 w-4 rounded border-surface-border text-primary-600 focus:ring-primary-500"
                            onChange={(e) => updateTome(index, { downloaded: e.target.checked })}
                            type="checkbox"
                          />
                        </td>
                        <td className="px-2 py-1.5 text-center">
                          <input
                            aria-label={`Tome ${tome.number} lu`}
                            checked={tome.read}
                            className="h-4 w-4 rounded border-surface-border text-primary-600 focus:ring-primary-500"
                            onChange={(e) => updateTome(index, { read: e.target.checked })}
                            type="checkbox"
                          />
                        </td>
                        <td className="px-2 py-1.5 text-center">
                          <input
                            aria-label={`Tome ${tome.number} sur NAS`}
                            checked={tome.onNas}
                            className="h-4 w-4 rounded border-surface-border text-primary-600 focus:ring-primary-500"
                            onChange={(e) => updateTome(index, { onNas: e.target.checked })}
                            type="checkbox"
                          />
                        </td>
                        <td className="px-2 py-1.5 text-center">
                          <button
                            className="rounded p-0.5 text-text-muted hover:bg-red-100 hover:text-red-600 dark:hover:bg-red-950/30 dark:hover:text-red-400"
                            onClick={() => removeTome(index)}
                            title="Retirer ce tome"
                            type="button"
                          >
                            <X className="h-3.5 w-3.5" />
                          </button>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
              <button
                className="mt-2 flex items-center gap-1 rounded-lg px-3 py-1.5 text-sm font-medium text-primary-600 hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-primary-950/30"
                onClick={addTome}
                type="button"
              >
                <Plus className="h-4 w-4" />
                Ajouter un tome
              </button>
          </div>

          {/* Footer */}
          <div className="flex justify-end gap-3 border-t border-surface-border px-6 py-4">
            <button
              className="rounded-lg px-4 py-2 text-sm font-medium text-text-secondary hover:bg-surface-tertiary"
              disabled={isExecuting}
              onClick={onClose}
              type="button"
            >
              Annuler
            </button>
            <button
              className="flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
              disabled={isExecuting || hasDuplicates}
              onClick={() => {
                const authors = editedAuthors
                  .split(",")
                  .map((a) => a.trim())
                  .filter(Boolean);
                onConfirm({
                  ...preview,
                  amazonUrl: editedAmazonUrl || null,
                  authors,
                  coverUrl: editedCoverUrl || null,
                  defaultTomeBought: editedDefaultTomeBought,
                  defaultTomeDownloaded: editedDefaultTomeDownloaded,
                  defaultTomeRead: editedDefaultTomeRead,
                  description: editedDescription || null,
                  isOneShot: editedIsOneShot,
                  latestPublishedIssue: editedLatestPublishedIssue ? Number(editedLatestPublishedIssue) : null,
                  latestPublishedIssueComplete: editedLatestPublishedIssueComplete,
                  notInterestedBuy: editedNotInterestedBuy,
                  notInterestedNas: editedNotInterestedNas,
                  publishedDate: editedPublishedDate || null,
                  publisher: editedPublisher || null,
                  status: editedStatus,
                  title: editedTitle || preview.title,
                  tomes: editedTomes,
                  type: editedType,
                });
              }}
              type="button"
            >
              {isExecuting && <Loader2 className="h-4 w-4 animate-spin" />}
              Confirmer la fusion
            </button>
          </div>
        </DialogPanel>
      </div>
    </Dialog>
  );
}
