import { BookX } from "lucide-react";
import { Link } from "react-router-dom";

export default function NotFound() {
  return (
    <div className="flex min-h-[60vh] flex-col items-center justify-center gap-6 px-4 text-center">
      <div
        className="flex h-24 w-24 items-center justify-center rounded-2xl bg-gradient-to-br from-primary-100 to-primary-200 dark:from-primary-950/40 dark:to-primary-900/30"
        data-testid="not-found-icon"
      >
        <BookX className="h-12 w-12 text-primary-500 dark:text-primary-400" strokeWidth={1.5} />
      </div>
      <div>
        <h1 className="text-7xl font-extrabold tracking-tight text-text-muted/30">404</h1>
        <p className="mt-2 text-xl font-semibold text-text-primary">Page introuvable</p>
        <p className="mt-1 text-sm text-text-muted">
          Cette page semble avoir disparu de la collection.
        </p>
      </div>
      <Link
        className="rounded-lg bg-primary-600 px-5 py-2.5 text-base font-medium text-white hover:bg-primary-700"
        to="/"
        viewTransition
      >
        Retour à l'accueil
      </Link>
    </div>
  );
}
