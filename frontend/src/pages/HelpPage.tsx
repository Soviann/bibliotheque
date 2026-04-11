import { Bell, Bot, ChevronDown, Clock, Search, WifiOff } from "lucide-react";
import { useState } from "react";
import Breadcrumb from "../components/Breadcrumb";

interface HelpSection {
  content: React.ReactNode;
  icon: React.ReactNode;
  title: string;
}

function Accordion({ content, icon, title }: HelpSection) {
  const [open, setOpen] = useState(false);

  return (
    <div className="rounded-xl border border-surface-border bg-surface-primary dark:border-white/10 dark:bg-surface-secondary">
      <button
        className="flex w-full items-center gap-3 p-4 text-left"
        onClick={() => setOpen((prev) => !prev)}
        type="button"
      >
        <div className="rounded-xl bg-primary-50 p-2.5 dark:bg-primary-950/30">
          {icon}
        </div>
        <h2 className="flex-1 font-semibold text-text-primary">{title}</h2>
        <ChevronDown
          className={`h-5 w-5 text-text-muted transition-transform ${open ? "rotate-180" : ""}`}
          strokeWidth={1.5}
        />
      </button>
      {open && (
        <div className="border-t border-surface-border px-4 pb-4 pt-3 text-sm leading-relaxed text-text-secondary dark:border-white/10">
          {content}
        </div>
      )}
    </div>
  );
}

const iconClass = "h-5 w-5 text-primary-600 dark:text-primary-400";

const sections: HelpSection[] = [
  {
    content: (
      <>
        <p>
          Quand une série est ajoutée ou qu'un champ est vidé manuellement,
          l'application enrichit automatiquement les métadonnées manquantes en
          interrogeant plusieurs sources (Google Books, BnF, AniList, Wikipedia,
          Gemini, Bedetheque…).
        </p>
        <p className="mt-3 font-medium text-text-primary">
          Scoring de confiance
        </p>
        <ul className="mt-1 list-inside list-disc space-y-1">
          <li>
            <span className="font-medium text-green-600 dark:text-green-400">
              HIGH
            </span>{" "}
            — Auto-appliqué immédiatement et journalisé dans l'historique de la
            série.
          </li>
          <li>
            <span className="font-medium text-amber-600 dark:text-amber-400">
              MEDIUM
            </span>{" "}
            — Envoyé dans la{" "}
            <span className="font-medium text-text-primary">file de revue</span>{" "}
            (Outils → Revue d'enrichissement) pour validation manuelle.
          </li>
          <li>
            <span className="font-medium text-red-600 dark:text-red-400">
              LOW
            </span>{" "}
            — Ignoré et journalisé.
          </li>
        </ul>
        <p className="mt-3">
          L'enrichissement est désactivé automatiquement pendant les imports
          Excel pour éviter les conflits. L'historique complet est consultable
          sur chaque fiche série via le panneau « Historique d'enrichissement ».
        </p>
      </>
    ),
    icon: <Bot className={iconClass} strokeWidth={1.5} />,
    title: "Enrichissement automatique",
  },
  {
    content: (
      <>
        <p>
          L'application exécute automatiquement des tâches récurrentes. Les
          tâches utilisant l'IA (Gemini) sont réparties sur des jours différents
          pour optimiser le quota (reset à 9h, heure de Paris).
        </p>
        <div className="mt-3 overflow-x-auto">
          <table className="w-full text-left text-sm">
            <thead>
              <tr className="border-b border-surface-border text-xs uppercase text-text-muted dark:border-white/10">
                <th className="pb-2 pr-4">Tâche</th>
                <th className="pb-2 pr-4">Fréquence</th>
                <th className="pb-2">Description</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-surface-border dark:divide-white/10">
              <tr>
                <td className="py-2 pr-4 font-medium text-text-primary">
                  Enrichissement
                </td>
                <td className="whitespace-nowrap py-2 pr-4">Mar–Sam, 3h–8h</td>
                <td className="py-2">
                  Enrichit les séries aux métadonnées incomplètes.
                </td>
              </tr>
              <tr>
                <td className="py-2 pr-4 font-medium text-text-primary">
                  Nouvelles parutions
                </td>
                <td className="whitespace-nowrap py-2 pr-4">Quotidien, 4h</td>
                <td className="py-2">
                  Vérifie si de nouveaux tomes sont sortis pour les séries en
                  cours.
                </td>
              </tr>
              <tr>
                <td className="py-2 pr-4 font-medium text-text-primary">
                  Couvertures
                </td>
                <td className="whitespace-nowrap py-2 pr-4">Quotidien, 5h</td>
                <td className="py-2">Télécharge les couvertures manquantes.</td>
              </tr>
              <tr>
                <td className="py-2 pr-4 font-medium text-text-primary">
                  Tomes manquants
                </td>
                <td className="whitespace-nowrap py-2 pr-4">Dimanche, 3h–8h</td>
                <td className="py-2">
                  Détecte les tomes non ajoutés pour les séries en cours ou
                  terminées.
                </td>
              </tr>
              <tr>
                <td className="py-2 pr-4 font-medium text-text-primary">
                  Auteurs suivis
                </td>
                <td className="whitespace-nowrap py-2 pr-4">Lundi, 3h–8h</td>
                <td className="py-2">
                  Vérifie les nouvelles publications des auteurs suivis.
                </td>
              </tr>
              <tr>
                <td className="py-2 pr-4 font-medium text-text-primary">
                  Purge corbeille
                </td>
                <td className="whitespace-nowrap py-2 pr-4">1er du mois</td>
                <td className="py-2">
                  Supprime définitivement les séries en corbeille depuis plus de
                  30 jours.
                </td>
              </tr>
              <tr>
                <td className="py-2 pr-4 font-medium text-text-primary">
                  Purge notifications
                </td>
                <td className="whitespace-nowrap py-2 pr-4">1er du mois</td>
                <td className="py-2">
                  Supprime les notifications de plus de 90 jours.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </>
    ),
    icon: <Clock className={iconClass} strokeWidth={1.5} />,
    title: "Tâches planifiées",
  },
  {
    content: (
      <>
        <p>
          L'application envoie des notifications pour vous tenir informé de
          l'activité de votre collection.
        </p>
        <p className="mt-3 font-medium text-text-primary">
          Types de notifications
        </p>
        <ul className="mt-1 list-inside list-disc space-y-1">
          <li>
            <span className="font-medium text-text-primary">
              Tomes manquants
            </span>{" "}
            — Des tomes parus n'ont pas été ajoutés à une série en cours ou
            terminée.
          </li>
          <li>
            <span className="font-medium text-text-primary">
              Nouvelles parutions
            </span>{" "}
            — Un nouveau tome est sorti pour une série suivie.
          </li>
          <li>
            <span className="font-medium text-text-primary">
              Auteurs suivis
            </span>{" "}
            — Un auteur que vous suivez a publié une nouvelle série (bouton
            follow sur la fiche série).
          </li>
        </ul>
        <p className="mt-3 font-medium text-text-primary">Canaux</p>
        <p className="mt-1">
          Chaque type peut être configuré indépendamment dans{" "}
          <span className="font-medium text-text-primary">
            Paramètres → Notifications
          </span>{" "}
          : in-app (cloche dans le header), push (notification navigateur), les
          deux, ou désactivé.
        </p>
        <p className="mt-3">
          Les notifications push nécessitent l'autorisation du navigateur et
          fonctionnent même quand l'application est fermée.
        </p>
      </>
    ),
    icon: <Bell className={iconClass} strokeWidth={1.5} />,
    title: "Notifications",
  },
  {
    content: (
      <>
        <p>
          L'application fonctionne en mode hors-ligne une fois installée comme
          PWA. Les données sont mises en cache localement et les modifications
          sont synchronisées automatiquement au retour en ligne.
        </p>
        <p className="mt-3 font-medium text-text-primary">Fonctionnement</p>
        <ul className="mt-1 list-inside list-disc space-y-1">
          <li>
            Les pages visitées et les couvertures sont mises en cache par le
            service worker.
          </li>
          <li>Les données API sont disponibles pendant 7 jours en cache.</li>
          <li>
            Les modifications (ajout, édition, suppression) sont enregistrées
            dans une file d'attente locale et synchronisées automatiquement au
            retour en ligne.
          </li>
          <li>
            Une bannière en haut de page indique le nombre d'opérations en
            attente — cliquez dessus pour voir le détail.
          </li>
          <li>
            La recherche automatique (ISBN/titre) et le scanner sont
            indisponibles hors-ligne.
          </li>
        </ul>
      </>
    ),
    icon: <WifiOff className={iconClass} strokeWidth={1.5} />,
    title: "Mode hors-ligne",
  },
  {
    content: (
      <>
        <p>
          Lors de l'ajout ou de la modification d'une série, la recherche
          automatique interroge en parallèle plusieurs sources pour pré-remplir
          les métadonnées. Chaque source a une spécialité et une priorité par
          champ — le résultat avec la plus haute priorité l'emporte.
        </p>
        <div className="mt-3 overflow-x-auto">
          <table className="w-full text-left text-sm">
            <thead>
              <tr className="border-b border-surface-border text-xs uppercase text-text-muted dark:border-white/10">
                <th className="pb-2 pr-4">Source</th>
                <th className="pb-2">Spécialité</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-surface-border dark:divide-white/10">
              <tr>
                <td className="py-2 pr-4 font-medium text-text-primary">
                  Google Books
                </td>
                <td className="py-2">
                  ISBN, couverture (source principale pour les BD)
                </td>
              </tr>
              <tr>
                <td className="py-2 pr-4 font-medium text-text-primary">BnF</td>
                <td className="py-2">ISBN, métadonnées françaises</td>
              </tr>
              <tr>
                <td className="py-2 pr-4 font-medium text-text-primary">
                  OpenLibrary
                </td>
                <td className="py-2">ISBN, couverture alternative</td>
              </tr>
              <tr>
                <td className="py-2 pr-4 font-medium text-text-primary">
                  AniList
                </td>
                <td className="py-2">
                  Manga : couverture, one-shot, nombre de tomes
                </td>
              </tr>
              <tr>
                <td className="py-2 pr-4 font-medium text-text-primary">
                  Wikipedia
                </td>
                <td className="py-2">
                  Description (priorité basse, dernier recours)
                </td>
              </tr>
              <tr>
                <td className="py-2 pr-4 font-medium text-text-primary">
                  Gemini AI
                </td>
                <td className="py-2">
                  Informations complémentaires (éditeur, auteur, date…)
                </td>
              </tr>
              <tr>
                <td className="py-2 pr-4 font-medium text-text-primary">
                  Bedetheque
                </td>
                <td className="py-2">Référence francophone BD (via Gemini)</td>
              </tr>
            </tbody>
          </table>
        </div>
        <p className="mt-3">
          La recherche est disponible par ISBN, par titre, ou par scan de
          code-barres (caméra).
        </p>
      </>
    ),
    icon: <Search className={iconClass} strokeWidth={1.5} />,
    title: "Recherche de métadonnées (Lookup)",
  },
];

export default function HelpPage() {
  return (
    <div className="mx-auto max-w-4xl px-4 py-6">
      <Breadcrumb
        items={[{ href: "/tools", label: "Outils" }, { label: "Aide" }]}
      />
      <h1 className="mb-6 font-display text-2xl font-bold text-text-primary">
        Aide
      </h1>
      <div className="space-y-3">
        {sections.map((section) => (
          <Accordion key={section.title} {...section} />
        ))}
      </div>
    </div>
  );
}
