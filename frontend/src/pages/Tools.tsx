import { useQueryClient } from "@tanstack/react-query";
import { del } from "idb-keyval";
import { ArrowRight, DatabaseZap, FileSpreadsheet, HelpCircle, Lightbulb, LoaderCircle, Merge, Search, Sparkles, Trash2 } from "lucide-react";
import { type ComponentType, useState } from "react";
import { Link } from "react-router-dom";
import { toast } from "sonner";

interface ToolCard {
  description: string;
  icon: ComponentType<{ className?: string; strokeWidth?: number }>;
  title: string;
  to: string;
}

const tools: ToolCard[] = [
  {
    description: "Valider ou rejeter les propositions d'enrichissement automatique.",
    icon: Sparkles,
    title: "Revue d'enrichissement",
    to: "/tools/enrichment-review",
  },
  {
    description: "Detecter et fusionner les series dupliquees (tomes d'une meme serie importes separement).",
    icon: Merge,
    title: "Fusion de series",
    to: "/tools/merge-series",
  },
  {
    description: "Importer des series depuis un fichier Excel (format Livres.xlsx ou suivi).",
    icon: FileSpreadsheet,
    title: "Import Excel",
    to: "/tools/import",
  },
  {
    description: "Rechercher automatiquement les metadonnees manquantes pour les series.",
    icon: Search,
    title: "Lookup metadonnees",
    to: "/tools/lookup",
  },
  {
    description: "Supprimer definitivement les series dans la corbeille depuis plus de 30 jours.",
    icon: Trash2,
    title: "Purge corbeille",
    to: "/tools/purge",
  },
  {
    description: "Découvrir des séries similaires à votre collection, suggérées par IA.",
    icon: Lightbulb,
    title: "Suggestions",
    to: "/tools/suggestions",
  },
  {
    description: "Comprendre les automatismes, les tâches planifiées et le fonctionnement de l'application.",
    icon: HelpCircle,
    title: "Aide",
    to: "/tools/help",
  },
];

export default function Tools() {
  const queryClient = useQueryClient();

  const [clearing, setClearing] = useState(false);

  const handleClearCache = async () => {
    setClearing(true);
    try {
      queryClient.clear();
      await del("bibliotheque-query-cache");
      await queryClient.refetchQueries();
      toast.success("Cache vidé — les données ont été rechargées depuis le serveur.");
    } finally {
      setClearing(false);
    }
  };

  return (
    <div className="mx-auto max-w-4xl px-4 py-6">
      <h1 className="mb-6 font-display text-2xl font-bold text-text-primary">
        Outils
      </h1>

      <div className="grid gap-4 sm:grid-cols-2">
        {tools.map(({ description, icon: Icon, title, to }) => (
          <Link
            className="group flex items-start gap-4 rounded-xl border border-surface-border bg-surface-primary p-4 transition-all hover:-translate-y-0.5 hover:border-primary-400 hover:shadow-md dark:border-white/10 dark:bg-surface-secondary dark:hover:border-primary-400/30"
            key={to}
            to={to}
            viewTransition
          >
            <div className="rounded-xl bg-primary-50 p-2.5 dark:bg-primary-950/30">
              <Icon className="h-5 w-5 text-primary-600 dark:text-primary-400" strokeWidth={1.5} />
            </div>
            <div className="min-w-0 flex-1">
              <div className="flex items-center gap-2">
                <h2 className="font-display font-semibold text-text-primary">{title}</h2>
                <ArrowRight className="h-4 w-4 text-text-muted opacity-0 transition group-hover:opacity-100" strokeWidth={1.5} />
              </div>
              <p className="mt-1 text-sm text-text-secondary">{description}</p>
            </div>
          </Link>
        ))}

        <button
          className="group flex cursor-pointer items-start gap-4 rounded-xl border border-surface-border bg-surface-primary p-4 text-left transition-all hover:-translate-y-0.5 hover:border-accent-danger/50 hover:shadow-md disabled:opacity-60 dark:border-white/10 dark:bg-surface-secondary dark:hover:border-accent-danger/30"
          disabled={clearing}
          onClick={handleClearCache}
          type="button"
        >
          <div className="rounded-xl bg-red-50 p-2.5 dark:bg-red-950/30">
            {clearing
              ? <LoaderCircle className="h-5 w-5 animate-spin text-accent-danger" />
              : <DatabaseZap className="h-5 w-5 text-accent-danger" strokeWidth={1.5} />}
          </div>
          <div className="min-w-0 flex-1">
            <h2 className="font-display font-semibold text-text-primary">{clearing ? "Vidage en cours…" : "Vider le cache"}</h2>
            <p className="mt-1 text-sm text-text-secondary">Supprimer le cache local et recharger les données depuis le serveur.</p>
          </div>
        </button>
      </div>
    </div>
  );
}
