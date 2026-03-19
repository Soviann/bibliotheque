import { ArrowLeft, Image, Loader2 } from "lucide-react";
import AuthorAutocomplete from "../components/AuthorAutocomplete";
import CollapsibleSection from "../components/CollapsibleSection";
import CoverSearchModal from "../components/CoverSearchModal";
import DatePartialSelect from "../components/DatePartialSelect";
import LookupSection from "../components/LookupSection";
import SelectListbox from "../components/SelectListbox";
import SkeletonBox from "../components/SkeletonBox";
import SyncFailureSection from "../components/SyncFailureSection";
import TomeTable from "../components/TomeTable";
import { useComicForm } from "../hooks/useComicForm";
import {
  formCheckboxClassName,
  formInputClassName,
  formLabelClassName,
  formListboxButtonClassName,
} from "../styles/formStyles";
import { statusOptions, typeOptions } from "../types/enums";

export default function ComicForm() {
  const {
    addAuthor,
    applyLookup,
    authorOptions,
    authorSearch,
    clearCandidate,
    coverSearchOpen,
    form,
    handleSubmit,
    initialized,
    isApplying,
    isEdit,
    isOnline,
    isSaving,
    lookupIsbn,
    lookupMode,
    lookupResult,
    lookupTitle,
    navigate,
    removeAuthor,
    resolveSyncFailure,
    selectCandidate,
    selectedCandidateTitle,
    setAuthorSearch,
    setCoverSearchOpen,
    setLookupIsbn,
    setLookupMode,
    setLookupTitle,
    syncFailure,
    titleCandidates,
    tomeManager,
    update,
  } = useComicForm();

  if (isEdit && !initialized) {
    return (
      <div className="mx-auto max-w-3xl space-y-6" data-testid="comic-form-skeleton">
        <div className="flex items-center gap-3">
          <SkeletonBox className="h-5 w-5" />
          <SkeletonBox className="h-6 w-40" />
        </div>
        <div className="space-y-5">
          {/* Title field */}
          <div>
            <SkeletonBox className="mb-1 h-4 w-16" />
            <SkeletonBox className="h-10 w-full" />
          </div>
          {/* Type + Status */}
          <div className="grid grid-cols-2 gap-4">
            <div>
              <SkeletonBox className="mb-1 h-4 w-12" />
              <SkeletonBox className="h-10 w-full" />
            </div>
            <div>
              <SkeletonBox className="mb-1 h-4 w-14" />
              <SkeletonBox className="h-10 w-full" />
            </div>
          </div>
          {/* Publisher + Cover */}
          <div>
            <SkeletonBox className="mb-1 h-4 w-16" />
            <SkeletonBox className="h-10 w-full" />
          </div>
          <div>
            <SkeletonBox className="mb-1 h-4 w-28" />
            <SkeletonBox className="h-10 w-full" />
          </div>
          {/* Description */}
          <div>
            <SkeletonBox className="mb-1 h-4 w-24" />
            <SkeletonBox className="h-20 w-full" />
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-3xl space-y-6">
      {/* Header */}
      <div className="flex items-center gap-3">
        <button aria-label="Retour" className="inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-lg text-text-muted hover:text-text-secondary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500" onClick={() => navigate(-1)} type="button">
          <ArrowLeft className="h-5 w-5" />
        </button>
        <h1 className="text-xl font-bold text-text-primary">
          {isEdit ? "Modifier la série" : "Nouvelle série"}
        </h1>
      </div>

      {/* Sync failure details */}
      {syncFailure && (
        <SyncFailureSection
          failure={syncFailure}
          onDismiss={() => void resolveSyncFailure(syncFailure.id!)}
        />
      )}

      {/* Lookup section */}
      <LookupSection
        applyLookup={applyLookup}
        clearCandidate={clearCandidate}
        formTitle={form.title}
        isApplying={isApplying}
        isOnline={isOnline}
        lookupIsbn={lookupIsbn}
        lookupMode={lookupMode}
        lookupResult={lookupResult}
        lookupTitle={lookupTitle}
        selectCandidate={selectCandidate}
        selectedCandidateTitle={selectedCandidateTitle}
        setLookupIsbn={setLookupIsbn}
        setLookupMode={setLookupMode}
        setLookupTitle={setLookupTitle}
        titleCandidates={titleCandidates}
      />

      {/* Form */}
      <form className="space-y-5" onSubmit={handleSubmit}>
        {/* Info générale */}
        <CollapsibleSection title="Info générale">
          <div>
            <label className={formLabelClassName} htmlFor="title">
              Titre *
            </label>
            <input
              className={`w-full ${formInputClassName}`}
              id="title"
              onChange={(e) => update("title", e.target.value)}
              required
              value={form.title}
            />
          </div>

          <div className="grid grid-cols-2 gap-4">
            <SelectListbox
              buttonClassName={formListboxButtonClassName}
              label="Type *"
              onChange={(v) => update("type", v)}
              options={typeOptions}
              value={form.type}
            />
            <SelectListbox
              buttonClassName={formListboxButtonClassName}
              label="Statut *"
              onChange={(v) => update("status", v)}
              options={statusOptions}
              value={form.status}
            />
          </div>

          <label className="flex items-center gap-2">
            <input
              checked={form.isOneShot}
              className={formCheckboxClassName}
              onChange={(e) => update("isOneShot", e.target.checked)}
              type="checkbox"
            />
            <span className="text-sm font-medium text-text-secondary">One-shot (pas de tomes)</span>
          </label>
        </CollapsibleSection>

        {/* Publication */}
        <CollapsibleSection title="Publication">
          <div>
            <label className={formLabelClassName} htmlFor="publisher">
              Éditeur
            </label>
            <input
              className={`w-full ${formInputClassName}`}
              id="publisher"
              onChange={(e) => update("publisher", e.target.value)}
              value={form.publisher}
            />
          </div>

          <DatePartialSelect
            label="Date de parution"
            onChange={(v) => update("publishedDate", v)}
            value={form.publishedDate}
          />

          <div className="flex flex-wrap items-end gap-x-6 gap-y-2">
            <div>
              <label className={formLabelClassName} htmlFor="latestPublishedIssue">
                Dernier tome paru
              </label>
              <input
                className={`w-32 ${formInputClassName}`}
                id="latestPublishedIssue"
                min="0"
                onChange={(e) => update("latestPublishedIssue", e.target.value)}
                type="number"
                value={form.latestPublishedIssue}
              />
            </div>
            <label className="flex items-center gap-2 pb-2">
              <input
                checked={form.latestPublishedIssueComplete}
                className={formCheckboxClassName}
                onChange={(e) => update("latestPublishedIssueComplete", e.target.checked)}
                type="checkbox"
              />
              <span className="text-sm font-medium text-text-secondary">Parution terminée</span>
            </label>
            <div className="flex items-center gap-4 pb-2">
              <span className="text-sm font-medium text-text-secondary">Flags par défaut :</span>
              <label className="flex items-center gap-1.5">
                <input
                  checked={form.defaultTomeBought}
                  className={formCheckboxClassName}
                  onChange={(e) => update("defaultTomeBought", e.target.checked)}
                  type="checkbox"
                />
                <span className="text-sm text-text-secondary">Achetés</span>
              </label>
              <label className="flex items-center gap-1.5">
                <input
                  checked={form.defaultTomeDownloaded}
                  className={formCheckboxClassName}
                  onChange={(e) => update("defaultTomeDownloaded", e.target.checked)}
                  type="checkbox"
                />
                <span className="text-sm text-text-secondary">Téléchargés</span>
              </label>
              <label className="flex items-center gap-1.5">
                <input
                  checked={form.defaultTomeRead}
                  className={formCheckboxClassName}
                  onChange={(e) => update("defaultTomeRead", e.target.checked)}
                  type="checkbox"
                />
                <span className="text-sm text-text-secondary">Lus</span>
              </label>
            </div>
          </div>
        </CollapsibleSection>

        {/* Média */}
        <CollapsibleSection title="Média">
          <div>
            <label className={formLabelClassName} htmlFor="coverUrl">
              URL de couverture
            </label>
            <div className="flex gap-2">
              <input
                className={`min-w-0 flex-1 ${formInputClassName}`}
                id="coverUrl"
                onChange={(e) => update("coverUrl", e.target.value)}
                placeholder="https://..."
                type="url"
                value={form.coverUrl}
              />
              <button
                className="shrink-0 rounded-lg border border-surface-border px-3 py-2 text-sm text-text-secondary hover:bg-surface-tertiary disabled:cursor-not-allowed disabled:opacity-50"
                disabled={!isOnline}
                onClick={() => setCoverSearchOpen(true)}
                title="Rechercher une couverture"
                type="button"
              >
                <Image className="h-4 w-4" />
              </button>
            </div>
            {form.coverUrl && (
              <img alt="Aperçu" className="mt-2 h-32 rounded-lg shadow" src={form.coverUrl} />
            )}
          </div>
          <CoverSearchModal
            defaultQuery={form.title}
            onClose={() => setCoverSearchOpen(false)}
            onSelect={(url) => {
              update("coverUrl", url);
              setCoverSearchOpen(false);
            }}
            open={coverSearchOpen}
            type={form.type}
          />

          <AuthorAutocomplete
            addAuthor={addAuthor}
            authorOptions={authorOptions}
            authorSearch={authorSearch}
            authors={form.authors}
            removeAuthor={removeAuthor}
            setAuthorSearch={setAuthorSearch}
          />

          <div>
            <label className={formLabelClassName} htmlFor="description">
              Description
            </label>
            <textarea
              className={`w-full ${formInputClassName}`}
              id="description"
              onChange={(e) => update("description", e.target.value)}
              rows={3}
              value={form.description}
            />
          </div>
        </CollapsibleSection>

        {/* Tomes */}
        {!form.isOneShot && (
          <TomeTable
            form={form}
            tomeManager={tomeManager}
          />
        )}
      </form>

      {/* Sticky save/cancel bar */}
      <div className="sticky bottom-[var(--bottom-nav-h)] z-40 flex justify-center gap-3 border-t border-surface-border bg-surface-primary px-4 py-3">
        <button
          className="rounded-lg px-5 py-2.5 text-base font-medium text-text-secondary hover:bg-surface-tertiary"
          onClick={() => navigate(-1)}
          type="button"
        >
          Annuler
        </button>
        <button
          className="flex items-center gap-2 rounded-lg bg-primary-600 px-5 py-2.5 text-base font-medium text-white hover:bg-primary-700 disabled:opacity-50"
          disabled={isSaving || !form.title}
          onClick={handleSubmit}
          type="button"
        >
          {isSaving && <Loader2 className="h-5 w-5 animate-spin" />}
          {isEdit ? "Enregistrer" : "Créer"}
        </button>
      </div>
    </div>
  );
}
