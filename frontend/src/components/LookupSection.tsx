import { ArrowLeft, Layers, Loader2 } from "lucide-react";
import type { UseQueryResult } from "@tanstack/react-query";
import BarcodeScanner from "./BarcodeScanner";
import type { LookupCandidatesResponse, LookupResult } from "../types/api";

interface LookupSectionProps {
  applyLookup: () => void;
  clearCandidate: () => void;
  formTitle: string;
  isApplying: boolean;
  isOnline: boolean;
  lookupIsbn: string;
  lookupMode: "isbn" | "title";
  lookupResult: UseQueryResult<LookupResult>;
  lookupTitle: string;
  selectCandidate: (title: string) => void;
  selectedCandidateTitle: string | null;
  setLookupIsbn: (v: string) => void;
  setLookupMode: (v: "isbn" | "title") => void;
  setLookupTitle: (v: string) => void;
  titleCandidates: UseQueryResult<LookupCandidatesResponse>;
}

export default function LookupSection({
  applyLookup,
  clearCandidate,
  formTitle,
  isApplying,
  isOnline,
  lookupIsbn,
  lookupMode,
  lookupResult,
  lookupTitle,
  selectCandidate,
  selectedCandidateTitle,
  setLookupIsbn,
  setLookupMode,
  setLookupTitle,
  titleCandidates,
}: LookupSectionProps) {
  if (!isOnline) {
    return (
      <div className="rounded-lg border border-surface-border bg-surface-tertiary p-4">
        <p className="text-sm text-text-muted">Recherche indisponible hors ligne</p>
      </div>
    );
  }

  const isTitleMode = lookupMode === "title";
  const showCandidates = isTitleMode && !selectedCandidateTitle;
  const showTargeted = isTitleMode && selectedCandidateTitle !== null;
  const isSearching = showCandidates ? titleCandidates.isFetching : lookupResult.isFetching;

  return (
    <div className="rounded-lg border border-surface-border bg-surface-tertiary p-4 space-y-3">
      <div className="flex items-center justify-between">
        <h2 className="text-sm font-semibold text-text-secondary">Recherche automatique</h2>
        <div className="flex rounded-lg bg-surface-primary p-0.5 border border-surface-border">
          <button
            className={`rounded-md px-3 py-1 text-sm font-medium transition ${lookupMode === "isbn" ? "bg-primary-600 text-white shadow-sm" : "text-text-muted hover:text-text-secondary"}`}
            onClick={() => setLookupMode("isbn")}
            type="button"
          >
            ISBN
          </button>
          <button
            className={`rounded-md px-3 py-1 text-sm font-medium transition ${lookupMode === "title" ? "bg-primary-600 text-white shadow-sm" : "text-text-muted hover:text-text-secondary"}`}
            onClick={() => setLookupMode("title")}
            type="button"
          >
            Titre
          </button>
        </div>
      </div>

      {lookupMode === "isbn" ? (
        <div className="flex gap-2">
          <input
            className="flex-1 rounded-lg border border-surface-border bg-surface-primary px-3 py-2 text-sm text-text-primary"
            onChange={(e) => setLookupIsbn(e.target.value)}
            placeholder="ISBN (10 ou 13 chiffres)"
            value={lookupIsbn}
          />
          <BarcodeScanner onScan={setLookupIsbn} />
        </div>
      ) : (
        <div className="flex gap-2">
          <input
            className="flex-1 rounded-lg border border-surface-border bg-surface-primary px-3 py-2 text-sm text-text-primary"
            onChange={(e) => {
              setLookupTitle(e.target.value);
              clearCandidate();
            }}
            placeholder="Titre de la série"
            value={lookupTitle}
          />
          {formTitle && formTitle !== lookupTitle && (
            <button
              className="flex shrink-0 items-center gap-1.5 rounded-lg border border-surface-border bg-surface-primary px-3 py-1.5 text-sm text-text-muted hover:border-primary-400 hover:text-primary-600 transition"
              onClick={() => {
                setLookupTitle(formTitle);
                clearCandidate();
              }}
              title="Utiliser le titre de la série"
              type="button"
            >
              <Layers className="h-4 w-4" />
            </button>
          )}
        </div>
      )}

      {isSearching && (
        <div className="flex items-center gap-2 text-sm text-text-muted">
          <Loader2 className="h-4 w-4 animate-spin" /> Recherche en cours…
        </div>
      )}

      {/* Candidates list (title mode, no selection yet) */}
      {showCandidates && titleCandidates.data && !titleCandidates.isFetching && (
        <div className="space-y-2">
          {titleCandidates.data.results.length === 0 ? (
            <p className="text-sm text-text-muted">Aucun résultat trouvé</p>
          ) : (
            <>
              <p className="text-xs text-text-muted">
                {titleCandidates.data.results.length} résultat(s) — sélectionnez une série :
              </p>
              <div className="space-y-1.5">
                {titleCandidates.data.results.map((candidate, index) => (
                  <button
                    className="flex w-full items-center gap-3 rounded-lg bg-surface-primary p-2.5 border border-surface-border text-left hover:border-primary-400 transition"
                    key={index}
                    onClick={() => candidate.title && selectCandidate(candidate.title)}
                    type="button"
                  >
                    {candidate.thumbnail ? (
                      <img
                        alt=""
                        className="h-12 w-9 shrink-0 rounded object-cover"
                        src={candidate.thumbnail}
                      />
                    ) : (
                      <div className="flex h-12 w-9 shrink-0 items-center justify-center rounded bg-surface-tertiary text-text-muted">
                        <Layers className="h-4 w-4" />
                      </div>
                    )}
                    <div className="min-w-0 text-sm">
                      <p className="truncate font-medium text-text-primary">{candidate.title}</p>
                      <p className="truncate text-text-muted">
                        {candidate.authors ?? ""}
                        {candidate.publisher && ` — ${candidate.publisher}`}
                      </p>
                    </div>
                  </button>
                ))}
              </div>
            </>
          )}
          {titleCandidates.data.sources.length > 0 && (
            <p className="text-xs text-text-muted">
              Sources : {titleCandidates.data.sources.join(", ")}
            </p>
          )}
        </div>
      )}

      {/* Targeted result (title mode, candidate selected) */}
      {showTargeted && (
        <div className="space-y-2">
          <button
            className="flex items-center gap-1 text-xs text-text-muted hover:text-primary-600 transition"
            onClick={clearCandidate}
            type="button"
          >
            <ArrowLeft className="h-3 w-3" />
            Retour aux résultats
          </button>
          {lookupResult.isFetching && (
            <div className="flex items-center gap-2 text-sm text-text-muted">
              <Loader2 className="h-4 w-4 animate-spin" /> Chargement des détails…
            </div>
          )}
          {lookupResult.data && !lookupResult.isFetching && (
            <LookupResultCard
              applyLookup={applyLookup}
              isApplying={isApplying}
              result={lookupResult.data}
            />
          )}
        </div>
      )}

      {/* ISBN result (isbn mode) */}
      {lookupMode === "isbn" && lookupResult.data && !lookupResult.isFetching && (
        <LookupResultCard
          applyLookup={applyLookup}
          isApplying={isApplying}
          result={lookupResult.data}
        />
      )}
    </div>
  );
}

function LookupResultCard({
  applyLookup,
  isApplying,
  result,
}: {
  applyLookup: () => void;
  isApplying: boolean;
  result: LookupResult;
}) {
  return (
    <div className="rounded-lg bg-surface-primary p-3 border border-surface-border space-y-2">
      <div className="flex items-center justify-between gap-3">
        <div className="min-w-0 text-sm">
          <p className="truncate font-medium text-text-primary">{result.title}</p>
          <p className="truncate text-text-muted">
            {result.authors ?? ""}
            {result.publisher && ` — ${result.publisher}`}
          </p>
        </div>
        <button
          className="flex shrink-0 items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
          disabled={isApplying}
          onClick={applyLookup}
          type="button"
        >
          {isApplying && <Loader2 className="h-3.5 w-3.5 animate-spin" />}
          Appliquer
        </button>
      </div>
      {result.sources.length > 0 && (
        <p className="text-xs text-text-muted">
          Sources : {result.sources.join(", ")}
        </p>
      )}
      {Object.entries(result.apiMessages).filter(([, m]) => m.status !== "success").length > 0 && (
        <p className="text-xs text-text-muted">
          {Object.entries(result.apiMessages)
            .filter(([, m]) => m.status !== "success")
            .map(([provider, m]) => `${provider}: ${m.message}`)
            .join(" · ")}
        </p>
      )}
    </div>
  );
}
