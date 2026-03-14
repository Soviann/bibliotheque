import { AlertTriangle, ArrowLeft, Image, Loader2, X } from "lucide-react";
import AuthorAutocomplete from "../components/AuthorAutocomplete";
import CoverSearchModal from "../components/CoverSearchModal";
import LookupSection from "../components/LookupSection";
import SelectListbox from "../components/SelectListbox";
import SkeletonBox from "../components/SkeletonBox";
import TomeTable from "../components/TomeTable";
import { useComicForm } from "../hooks/useComicForm";
import type { SyncFailure } from "../services/offlineQueue";
import { statusOptions, typeOptions } from "../types/enums";
import { fieldLabels, formatSyncValue, operationLabels } from "../utils/syncLabels";

function SyncFailureSection({ failure, onDismiss }: { failure: SyncFailure; onDismiss: () => void }) {
  const entries = Object.entries(failure.payload)
    .filter(([key]) => !key.startsWith("_") && key !== "id")
    .sort(([a], [b]) => a.localeCompare(b));

  return (
    <div className="rounded-lg border border-amber-300 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950/30">
      <div className="flex items-start gap-2">
        <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400" />
        <div className="min-w-0 flex-1">
          <p className="text-sm font-medium text-amber-800 dark:text-amber-300">
            {operationLabels[failure.operation] ?? failure.operation} échouée — {failure.error}
          </p>
          <p className="mt-1 text-xs text-amber-700 dark:text-amber-400">
            Modifications tentées hors ligne :
          </p>
          <dl className="mt-1 grid grid-cols-[auto_1fr] gap-x-3 gap-y-0.5 text-xs">
            {entries.map(([key, value]) => (
              <div className="contents" key={key}>
                <dt className="font-medium text-amber-800 dark:text-amber-300">
                  {fieldLabels[key] ?? key}
                </dt>
                <dd className="truncate text-amber-700 dark:text-amber-400">
                  {formatSyncValue(value)}
                </dd>
              </div>
            ))}
          </dl>
          <p className="mt-2 text-xs text-amber-600 dark:text-amber-500">
            Enregistrez le formulaire pour résoudre automatiquement cette erreur.
          </p>
        </div>
        <button
          className="shrink-0 rounded p-1 text-amber-500 hover:bg-amber-100 dark:hover:bg-amber-900/40"
          onClick={onDismiss}
          title="Ignorer"
          type="button"
        >
          <X className="h-4 w-4" />
        </button>
      </div>
    </div>
  );
}

const formListboxClassName = "flex w-full items-center justify-between gap-2 rounded-lg border border-surface-border bg-surface-primary px-3 py-2 text-sm text-text-primary transition hover:border-primary-400 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500";

export default function ComicForm() {
  const {
    addAuthor,
    addBatchTomes,
    addTome,
    applyLookup,
    authorOptions,
    authorSearch,
    batchFrom,
    batchSize,
    batchTo,
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
    lookupTomeIsbn,
    maxBatchSize,
    navigate,
    removeAuthor,
    removeTome,
    resolveSyncFailure,
    selectCandidate,
    selectedCandidateTitle,
    setAuthorSearch,
    setBatchFrom,
    setBatchTo,
    setCoverSearchOpen,
    setLookupIsbn,
    setLookupMode,
    setLookupTitle,
    syncFailure,
    titleCandidates,
    tomeLookupLoading,
    update,
    updateTome,
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
        <button className="text-text-muted hover:text-text-secondary" onClick={() => navigate(-1)} type="button">
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
        {/* Title */}
        <div>
          <label className="mb-1 block text-sm font-medium text-text-secondary" htmlFor="title">
            Titre *
          </label>
          <input
            className="w-full rounded-lg border border-surface-border bg-surface-primary px-3 py-2 text-sm text-text-primary"
            id="title"
            onChange={(e) => update("title", e.target.value)}
            required
            value={form.title}
          />
        </div>

        {/* Type + Status */}
        <div className="grid grid-cols-2 gap-4">
          <SelectListbox
            buttonClassName={formListboxClassName}
            label="Type *"
            onChange={(v) => update("type", v)}
            options={typeOptions}
            value={form.type}
          />
          <SelectListbox
            buttonClassName={formListboxClassName}
            label="Statut *"
            onChange={(v) => update("status", v)}
            options={statusOptions}
            value={form.status}
          />
        </div>

        {/* One-shot toggle */}
        <label className="flex items-center gap-2">
          <input
            checked={form.isOneShot}
            className="h-4 w-4 rounded border-surface-border text-primary-600"
            onChange={(e) => update("isOneShot", e.target.checked)}
            type="checkbox"
          />
          <span className="text-sm font-medium text-text-secondary">One-shot (pas de tomes)</span>
        </label>

        {/* Publisher */}
        <div>
          <label className="mb-1 block text-sm font-medium text-text-secondary" htmlFor="publisher">
            Éditeur
          </label>
          <input
            className="w-full rounded-lg border border-surface-border bg-surface-primary px-3 py-2 text-sm text-text-primary"
            id="publisher"
            onChange={(e) => update("publisher", e.target.value)}
            value={form.publisher}
          />
        </div>

        {/* Cover URL */}
        <div>
          <label className="mb-1 block text-sm font-medium text-text-secondary" htmlFor="coverUrl">
            URL de couverture
          </label>
          <div className="flex gap-2">
            <input
              className="min-w-0 flex-1 rounded-lg border border-surface-border bg-surface-primary px-3 py-2 text-sm text-text-primary"
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

        {/* Authors */}
        <AuthorAutocomplete
          addAuthor={addAuthor}
          authorOptions={authorOptions}
          authorSearch={authorSearch}
          authors={form.authors}
          removeAuthor={removeAuthor}
          setAuthorSearch={setAuthorSearch}
        />

        {/* Description */}
        <div>
          <label className="mb-1 block text-sm font-medium text-text-secondary" htmlFor="description">
            Description
          </label>
          <textarea
            className="w-full rounded-lg border border-surface-border bg-surface-primary px-3 py-2 text-sm text-text-primary"
            id="description"
            onChange={(e) => update("description", e.target.value)}
            rows={3}
            value={form.description}
          />
        </div>

        {/* Latest published issue + publication complete + default flags */}
        <div className="flex flex-wrap items-end gap-x-6 gap-y-2">
          <div>
            <label className="mb-1 block text-sm font-medium text-text-secondary" htmlFor="latestPublishedIssue">
              Dernier tome paru
            </label>
            <input
              className="w-32 rounded-lg border border-surface-border bg-surface-primary px-3 py-2 text-sm text-text-primary"
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
              className="h-4 w-4 rounded border-surface-border text-primary-600"
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
                className="h-4 w-4 rounded border-surface-border text-primary-600"
                onChange={(e) => update("defaultTomeBought", e.target.checked)}
                type="checkbox"
              />
              <span className="text-sm text-text-secondary">Achetés</span>
            </label>
            <label className="flex items-center gap-1.5">
              <input
                checked={form.defaultTomeDownloaded}
                className="h-4 w-4 rounded border-surface-border text-primary-600"
                onChange={(e) => update("defaultTomeDownloaded", e.target.checked)}
                type="checkbox"
              />
              <span className="text-sm text-text-secondary">Téléchargés</span>
            </label>
            <label className="flex items-center gap-1.5">
              <input
                checked={form.defaultTomeRead}
                className="h-4 w-4 rounded border-surface-border text-primary-600"
                onChange={(e) => update("defaultTomeRead", e.target.checked)}
                type="checkbox"
              />
              <span className="text-sm text-text-secondary">Lus</span>
            </label>
          </div>
        </div>

        {/* Tomes */}
        {!form.isOneShot && (
          <TomeTable
            addBatchTomes={addBatchTomes}
            addTome={addTome}
            batchFrom={batchFrom}
            batchSize={batchSize}
            batchTo={batchTo}
            form={form}
            lookupTomeIsbn={lookupTomeIsbn}
            maxBatchSize={maxBatchSize}
            removeTome={removeTome}
            setBatchFrom={setBatchFrom}
            setBatchTo={setBatchTo}
            tomeLookupLoading={tomeLookupLoading}
            updateTome={updateTome}
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
