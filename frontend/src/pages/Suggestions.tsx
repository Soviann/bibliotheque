import { Lightbulb, Loader2, Plus, X } from "lucide-react";
import { Link } from "react-router-dom";
import { toast } from "sonner";
import Breadcrumb from "../components/Breadcrumb";
import EmptyState from "../components/EmptyState";
import { useAddSuggestion, useDismissSuggestion, useSuggestions } from "../hooks/useSuggestions";
import { ComicTypeLabel, type ComicType } from "../types/enums";

export default function Suggestions() {
  const { data: suggestions, isLoading } = useSuggestions();
  const dismissMutation = useDismissSuggestion();
  const addMutation = useAddSuggestion();

  const handleDismiss = (id: number) => {
    dismissMutation.mutate(id, {
      onError: () => toast.error("Erreur"),
      onSuccess: () => toast.success("Suggestion ignorée"),
    });
  };

  const handleAdd = (id: number) => {
    addMutation.mutate(id, {
      onError: () => toast.error("Erreur"),
    });
  };

  return (
    <div className="mx-auto max-w-4xl px-4 py-6">
      <Breadcrumb items={[{ href: "/tools", label: "Outils" }, { label: "Suggestions" }]} />
      <h1 className="text-xl font-bold text-text-primary">Suggestions de séries</h1>
      <p className="mt-1 text-sm text-text-secondary">
        Séries similaires à votre collection, suggérées par IA.
      </p>

      {isLoading && (
        <div className="mt-8 flex items-center justify-center">
          <Loader2 className="h-6 w-6 animate-spin text-primary-600" />
        </div>
      )}

      {!isLoading && (!suggestions || suggestions.length === 0) && (
        <EmptyState
          description="Aucune suggestion en attente. Les suggestions sont générées automatiquement."
          icon={Lightbulb}
          title="Aucune suggestion"
        />
      )}

      {!isLoading && suggestions && suggestions.length > 0 && (
        <div className="mt-4 grid gap-4 sm:grid-cols-2">
          {suggestions.map((suggestion) => (
            <div
              className="rounded-xl border border-surface-border bg-surface-primary p-4"
              key={suggestion.id}
            >
              <div className="flex items-start justify-between gap-2">
                <div>
                  <h3 className="font-semibold text-text-primary">{suggestion.title}</h3>
                  <span className="mt-1 inline-block rounded-full bg-primary-100 px-2 py-0.5 text-xs font-medium text-primary-700 dark:bg-primary-950/30 dark:text-primary-400">
                    {ComicTypeLabel[suggestion.type as ComicType]}
                  </span>
                </div>
              </div>
              {suggestion.authors.length > 0 && (
                <p className="mt-2 text-sm text-text-secondary">
                  {suggestion.authors.join(", ")}
                </p>
              )}
              <p className="mt-2 text-sm text-text-tertiary">{suggestion.reason}</p>
              {suggestion.sourceSeries && (
                <p className="mt-1 text-xs text-text-muted">
                  Basé sur{" "}
                  <Link className="text-primary-600 hover:underline" to={`/comic/${suggestion.sourceSeries.id}`}>
                    {suggestion.sourceSeries.title}
                  </Link>
                </p>
              )}
              <div className="mt-3 flex gap-2">
                <button
                  className="inline-flex items-center gap-1 rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-700"
                  onClick={() => handleAdd(suggestion.id)}
                  type="button"
                >
                  <Plus className="h-4 w-4" />
                  Ajouter
                </button>
                <button
                  className="inline-flex items-center gap-1 rounded-lg border border-surface-border px-3 py-1.5 text-sm text-text-secondary hover:bg-surface-tertiary"
                  onClick={() => handleDismiss(suggestion.id)}
                  type="button"
                >
                  <X className="h-4 w-4" />
                  Ignorer
                </button>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
