import { ArrowLeft, Camera, Repeat, Search } from "lucide-react";
import { useCallback, useState } from "react";
import { useNavigate } from "react-router-dom";
import { toast } from "sonner";
import AddedStack from "../components/AddedStack";
import QuickAddScan from "../components/QuickAddScan";
import QuickAddSearch from "../components/QuickAddSearch";
import { useQuickAdd } from "../hooks/useQuickAdd";

export default function QuickAdd() {
  const navigate = useNavigate();
  const [tab, setTab] = useState<"scan" | "search">("scan");
  const { addItem, addedItems, batchMode, toggleBatchMode } = useQuickAdd();

  const handleAdd = useCallback(
    (result: { coverUrl: string | null; title: string; tomeNumber: number }) => {
      addItem(result);
      toast.success(`${result.title} T${result.tomeNumber} ajouté`);
    },
    [addItem],
  );

  return (
    <div className="flex h-[calc(100dvh-var(--bottom-nav-h))] flex-col">
      {/* Header */}
      <div className="flex items-center justify-between border-b border-surface-border px-4 py-3 dark:border-white/10">
        <button
          aria-label="Retour"
          className="rounded-lg p-2 text-text-muted hover:text-text-secondary"
          onClick={() => navigate(-1)}
          type="button"
        >
          <ArrowLeft className="h-5 w-5" />
        </button>
        <h1 className="text-base font-semibold text-text-primary">Ajout rapide</h1>
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

      {/* Content */}
      <div className="flex flex-1 flex-col overflow-hidden">
        {tab === "scan" ? (
          <QuickAddScan batchMode={batchMode} onAdd={handleAdd} />
        ) : (
          <QuickAddSearch onAdd={handleAdd} />
        )}
      </div>

      {/* Added stack */}
      <div className="px-4">
        <AddedStack items={addedItems} />
      </div>

      {/* Tab bar — bas de l'écran */}
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
  );
}
