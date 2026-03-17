import { AlertTriangle } from "lucide-react";
import type { FallbackProps } from "react-error-boundary";
import { getErrorMessage } from "../services/api";

export default function ErrorFallback({ error, resetErrorBoundary }: FallbackProps) {
  return (
    <div className="flex min-h-[50vh] flex-col items-center justify-center gap-4 px-4 text-center">
      <AlertTriangle className="h-12 w-12 text-red-500" />
      <h2 className="text-xl font-bold text-text-primary">Une erreur est survenue</h2>
      <p className="max-w-md text-text-secondary">{getErrorMessage(error)}</p>
      <button
        className="rounded-lg bg-primary-600 px-4 py-2 text-white hover:bg-primary-700"
        onClick={resetErrorBoundary}
        type="button"
      >
        Réessayer
      </button>
    </div>
  );
}
