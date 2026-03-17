import { Sparkles } from "lucide-react";
import type { MergeFormAction, MergeFormState } from "../hooks/useMergePreviewForm";
import { statusOptions, typeOptions } from "../types/enums";
import DatePartialSelect from "./DatePartialSelect";
import SelectListbox from "./SelectListbox";

const mergeInputClassName =
  "rounded-lg border border-surface-border bg-surface-secondary px-3 py-2 text-sm text-text-primary focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500";

const mergeListboxClassName =
  "flex w-full items-center justify-between gap-2 rounded-lg border border-surface-border bg-surface-secondary px-3 py-2 text-sm text-text-primary transition hover:border-primary-400 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500";

interface MergeMetadataFormProps {
  dispatch: React.Dispatch<MergeFormAction>;
  isSuggesting: boolean;
  state: MergeFormState;
}

export default function MergeMetadataForm({ dispatch, isSuggesting, state }: MergeMetadataFormProps) {
  const setField = (field: keyof MergeFormState, value: MergeFormState[keyof MergeFormState]) => {
    dispatch({ type: "SET_FIELD", field, value });
  };

  return (
    <>
      {/* Titre */}
      <div className="mt-4">
        <label className="block text-sm font-medium text-text-secondary" htmlFor="merge-title">
          Titre
        </label>
        <input
          className="mt-1 w-full rounded-lg border border-surface-border bg-surface-secondary px-3 py-2 text-text-primary focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
          id="merge-title"
          onChange={(e) => setField("title", e.target.value)}
          type="text"
          value={state.title}
        />
      </div>

      {isSuggesting && (
        <div className="mt-2 flex items-center gap-1.5 text-xs text-text-muted">
          <Sparkles className="h-3.5 w-3.5 animate-pulse text-amber-500" />
          Suggestions IA en cours...
        </div>
      )}

      {/* Type + Status */}
      <div className="mt-4 grid grid-cols-2 gap-4">
        <SelectListbox
          buttonClassName={mergeListboxClassName}
          label="Type"
          onChange={(v) => setField("type", v)}
          options={typeOptions}
          value={state.type}
        />
        <SelectListbox
          buttonClassName={mergeListboxClassName}
          label="Statut"
          onChange={(v) => setField("status", v)}
          options={statusOptions}
          value={state.status}
        />
      </div>

      {/* One-shot */}
      <label className="mt-3 flex items-center gap-2">
        <input
          checked={state.isOneShot}
          className="h-4 w-4 rounded border-surface-border text-primary-600"
          onChange={(e) => setField("isOneShot", e.target.checked)}
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
          onChange={(e) => setField("publisher", e.target.value)}
          type="text"
          value={state.publisher}
        />
      </div>

      {/* Published date */}
      <div className="mt-3">
        <DatePartialSelect
          label="Date de parution"
          onChange={(v) => setField("publishedDate", v)}
          value={state.publishedDate}
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
          onChange={(e) => setField("coverUrl", e.target.value)}
          placeholder="https://..."
          type="url"
          value={state.coverUrl}
        />
        {state.coverUrl && (
          <img alt="Aperçu" className="mt-2 h-24 rounded-lg shadow" src={state.coverUrl} />
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
          onChange={(e) => setField("authors", e.target.value)}
          type="text"
          value={state.authors}
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
          onChange={(e) => setField("description", e.target.value)}
          rows={3}
          value={state.description}
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
            onChange={(e) => setField("latestPublishedIssue", e.target.value)}
            type="number"
            value={state.latestPublishedIssue}
          />
        </div>
        <label className="flex items-center gap-2 pb-2">
          <input
            checked={state.latestPublishedIssueComplete}
            className="h-4 w-4 rounded border-surface-border text-primary-600"
            onChange={(e) => setField("latestPublishedIssueComplete", e.target.checked)}
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
            checked={state.defaultTomeBought}
            className="h-4 w-4 rounded border-surface-border text-primary-600"
            onChange={(e) => setField("defaultTomeBought", e.target.checked)}
            type="checkbox"
          />
          <span className="text-sm text-text-secondary">Achetés</span>
        </label>
        <label className="flex items-center gap-1.5">
          <input
            checked={state.defaultTomeDownloaded}
            className="h-4 w-4 rounded border-surface-border text-primary-600"
            onChange={(e) => setField("defaultTomeDownloaded", e.target.checked)}
            type="checkbox"
          />
          <span className="text-sm text-text-secondary">Téléchargés</span>
        </label>
        <label className="flex items-center gap-1.5">
          <input
            checked={state.defaultTomeRead}
            className="h-4 w-4 rounded border-surface-border text-primary-600"
            onChange={(e) => setField("defaultTomeRead", e.target.checked)}
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
          onChange={(e) => setField("amazonUrl", e.target.value)}
          placeholder="https://..."
          type="url"
          value={state.amazonUrl}
        />
      </div>

      {/* Not interested flags */}
      <div className="mt-3 flex items-center gap-4">
        <span className="text-sm font-medium text-text-secondary">Pas intéressé :</span>
        <label className="flex items-center gap-1.5">
          <input
            checked={state.notInterestedBuy}
            className="h-4 w-4 rounded border-surface-border text-primary-600"
            onChange={(e) => setField("notInterestedBuy", e.target.checked)}
            type="checkbox"
          />
          <span className="text-sm text-text-secondary">Achat</span>
        </label>
        <label className="flex items-center gap-1.5">
          <input
            checked={state.notInterestedNas}
            className="h-4 w-4 rounded border-surface-border text-primary-600"
            onChange={(e) => setField("notInterestedNas", e.target.checked)}
            type="checkbox"
          />
          <span className="text-sm text-text-secondary">NAS</span>
        </label>
      </div>
    </>
  );
}
