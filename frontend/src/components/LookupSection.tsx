import { Layers, Loader2 } from "lucide-react";
import type { UseQueryResult } from "@tanstack/react-query";
import BarcodeScanner from "./BarcodeScanner";
import type { LookupResult } from "../types/api";

interface LookupSectionProps {
  applyLookup: () => void;
  formTitle: string;
  isApplying: boolean;
  isOnline: boolean;
  lookupIsbn: string;
  lookupMode: "isbn" | "title";
  lookupResult: UseQueryResult<LookupResult>;
  lookupTitle: string;
  setLookupIsbn: (v: string) => void;
  setLookupMode: (v: "isbn" | "title") => void;
  setLookupTitle: (v: string) => void;
}

export default function LookupSection({
  applyLookup,
  formTitle,
  isApplying,
  isOnline,
  lookupIsbn,
  lookupMode,
  lookupResult,
  lookupTitle,
  setLookupIsbn,
  setLookupMode,
  setLookupTitle,
}: LookupSectionProps) {
  if (!isOnline) {
    return (
      <div className="rounded-lg border border-surface-border bg-surface-tertiary p-4">
        <p className="text-sm text-text-muted">Recherche indisponible hors ligne</p>
      </div>
    );
  }

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
            onChange={(e) => setLookupTitle(e.target.value)}
            placeholder="Titre de la série"
            value={lookupTitle}
          />
          {formTitle && formTitle !== lookupTitle && (
            <button
              className="flex shrink-0 items-center gap-1.5 rounded-lg border border-surface-border bg-surface-primary px-3 py-1.5 text-sm text-text-muted hover:border-primary-400 hover:text-primary-600 transition"
              onClick={() => setLookupTitle(formTitle)}
              title="Utiliser le titre de la série"
              type="button"
            >
              <Layers className="h-4 w-4" />
            </button>
          )}
        </div>
      )}

      {lookupResult.isFetching && (
        <div className="flex items-center gap-2 text-sm text-text-muted">
          <Loader2 className="h-4 w-4 animate-spin" /> Recherche en cours…
        </div>
      )}

      {lookupResult.data && !lookupResult.isFetching && (
        <div className="rounded-lg bg-surface-primary p-3 border border-surface-border space-y-2">
          <div className="flex items-center justify-between gap-3">
            <div className="min-w-0 text-sm">
              <p className="truncate font-medium text-text-primary">{lookupResult.data.title}</p>
              <p className="truncate text-text-muted">
                {lookupResult.data.authors ?? ""}
                {lookupResult.data.publisher && ` — ${lookupResult.data.publisher}`}
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
          {lookupResult.data.sources.length > 0 && (
            <p className="text-xs text-text-muted">
              Sources : {lookupResult.data.sources.join(", ")}
            </p>
          )}
          {Object.entries(lookupResult.data.apiMessages).filter(([, m]) => m.status !== "success").length > 0 && (
            <p className="text-xs text-text-muted">
              {Object.entries(lookupResult.data.apiMessages)
                .filter(([, m]) => m.status !== "success")
                .map(([provider, m]) => `${provider}: ${m.message}`)
                .join(" · ")}
            </p>
          )}
        </div>
      )}
    </div>
  );
}
