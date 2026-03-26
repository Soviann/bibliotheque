import { ArrowLeft, Camera, FilePlus2, Repeat, Search } from "lucide-react";
import { useCallback, useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { toast } from "sonner";
import AddedStack from "../components/AddedStack";
import QuickAddScan from "../components/QuickAddScan";
import QuickAddSearch from "../components/QuickAddSearch";
import { useQuickAdd } from "../hooks/useQuickAdd";

/** Hauteur approximative de la tab bar scan/recherche */
const TAB_BAR_HEIGHT = "3rem";

export default function QuickAdd() {
  const navigate = useNavigate();
  const [tab, setTab] = useState<"scan" | "search">("scan");
  const { addItem, addedItems, batchMode, toggleBatchMode } = useQuickAdd();
  const [searchQuery, setSearchQuery] = useState("");
  const [searchType, setSearchType] = useState("bd");

  const handleAdd = useCallback(
    (result: { coverUrl: string | null; title: string; tomeNumber: number }) => {
      addItem(result);
      toast.success(`${result.title} T${result.tomeNumber} ajouté`);
    },
    [addItem],
  );

  return (
    <>
      {/* Conteneur principal — remplit l'espace entre le header Layout et la tab bar fixée */}
      <div
        className="-mx-4 -my-4 flex flex-col overflow-hidden"
        style={{ height: `calc(100dvh - var(--bottom-nav-h) - ${TAB_BAR_HEIGHT} - 53px)` }}
      >
        {/* Header */}
        <div className="flex shrink-0 items-center justify-between border-b border-surface-border px-4 py-3 dark:border-white/10">
          <button
            aria-label="Retour"
            className="rounded-lg p-2 text-text-muted hover:text-text-secondary"
            onClick={() => navigate(-1)}
            type="button"
          >
            <ArrowLeft className="h-5 w-5" />
          </button>
          <div className="flex flex-col items-center">
            <h1 className="text-base font-semibold text-text-primary">Ajout rapide</h1>
            <Link
              className="flex items-center gap-1 text-xs text-primary-600 dark:text-primary-400"
              to={`/comic/new${searchQuery || searchType !== "bd" ? `?${new URLSearchParams({ ...(searchQuery ? { title: searchQuery } : {}), ...(searchType !== "bd" ? { type: searchType } : {}) })}` : ""}`}
              viewTransition
            >
              <FilePlus2 className="h-3 w-3" />
              Ajout détaillé
            </Link>
          </div>
          <label className="flex cursor-pointer items-center gap-1.5 text-xs text-text-muted">
            <Repeat className="h-3.5 w-3.5" />
            Batch
            <input
              checked={batchMode}
              className="h-4 w-4 accent-primary-600"
              onChange={toggleBatchMode}
              type="checkbox"
            />
          </label>
        </div>

        {/* Content — prend tout l'espace restant */}
        <div className="flex min-h-0 flex-1 flex-col overflow-hidden">
          {tab === "scan" ? (
            <QuickAddScan batchMode={batchMode} onAdd={handleAdd} />
          ) : (
            <QuickAddSearch onAdd={handleAdd} onQueryChange={setSearchQuery} onTypeChange={setSearchType} />
          )}
        </div>
      </div>

      {/* Bottom bar — fixée au-dessus de la BottomNav */}
      <div className="fixed inset-x-0 bottom-[var(--bottom-nav-h)] z-40 border-t border-surface-border bg-surface-primary/95 backdrop-blur-md dark:border-white/10 dark:bg-surface-primary/70">
        {/* Added stack */}
        {addedItems.length > 0 && (
          <div className="px-4 py-2">
            <AddedStack items={addedItems} />
          </div>
        )}

        {/* Tab bar */}
        <div className="flex border-t border-surface-border dark:border-white/10">
          <button
            className={`flex flex-1 items-center justify-center gap-2 py-3 text-sm font-medium transition-colors ${
              tab === "scan"
                ? "text-primary-600 dark:text-primary-400"
                : "text-text-muted"
            }`}
            onClick={() => setTab("scan")}
            type="button"
          >
            <Camera className="h-5 w-5" />
            Scanner
          </button>
          <button
            className={`flex flex-1 items-center justify-center gap-2 py-3 text-sm font-medium transition-colors ${
              tab === "search"
                ? "text-primary-600 dark:text-primary-400"
                : "text-text-muted"
            }`}
            onClick={() => setTab("search")}
            type="button"
          >
            <Search className="h-5 w-5" />
            Rechercher
          </button>
        </div>
      </div>
    </>
  );
}
