import { ArrowRight, FileSpreadsheet, Merge, Search, Trash2 } from "lucide-react";
import type { ComponentType } from "react";
import { Link } from "react-router-dom";

interface ToolCard {
  description: string;
  icon: ComponentType<{ className?: string }>;
  title: string;
  to: string;
}

const tools: ToolCard[] = [
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
];

export default function Tools() {
  return (
    <div className="mx-auto max-w-4xl px-4 py-6">
      <h1 className="mb-6 text-2xl font-bold text-text-primary">Outils</h1>

      <div className="grid gap-4 sm:grid-cols-2">
        {tools.map(({ description, icon: Icon, title, to }) => (
          <Link
            className="group flex items-start gap-4 rounded-xl border border-surface-border bg-surface-primary p-4 transition hover:border-primary-400 hover:shadow-sm"
            key={to}
            to={to}
            viewTransition
          >
            <div className="rounded-lg bg-primary-50 p-2.5 dark:bg-primary-950/30">
              <Icon className="h-5 w-5 text-primary-600 dark:text-primary-400" />
            </div>
            <div className="min-w-0 flex-1">
              <div className="flex items-center gap-2">
                <h2 className="font-semibold text-text-primary">{title}</h2>
                <ArrowRight className="h-4 w-4 text-text-muted opacity-0 transition group-hover:opacity-100" />
              </div>
              <p className="mt-1 text-sm text-text-secondary">{description}</p>
            </div>
          </Link>
        ))}
      </div>
    </div>
  );
}
