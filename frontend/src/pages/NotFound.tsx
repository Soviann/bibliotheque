import { Link } from "react-router-dom";

export default function NotFound() {
  return (
    <div className="flex min-h-[50vh] flex-col items-center justify-center gap-4 text-center">
      <h1 className="text-6xl font-bold text-slate-300">404</h1>
      <p className="text-lg text-slate-600">Page introuvable</p>
      <Link
        className="rounded-lg bg-primary-600 px-4 py-2 text-white hover:bg-primary-700"
        to="/"
      >
        Retour à l'accueil
      </Link>
    </div>
  );
}
