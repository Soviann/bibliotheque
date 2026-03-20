import { AlertTriangle, RefreshCw } from "lucide-react";
import type { ReactNode } from "react";
import { ErrorBoundary } from "react-error-boundary";
import type { FallbackProps } from "react-error-boundary";
import { getErrorMessage } from "../services/api";

function ComponentErrorFallback({ error, label, resetErrorBoundary }: FallbackProps & { label: string }) {
  return (
    <div
      className="flex flex-col items-center gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-6 text-center dark:border-red-900 dark:bg-red-950/20"
      data-testid="component-error-fallback"
    >
      <AlertTriangle className="h-8 w-8 text-red-500" />
      <p className="text-sm font-medium text-red-700 dark:text-red-400">
        Impossible de charger {label}
      </p>
      <p className="text-xs text-red-600 dark:text-red-500">{getErrorMessage(error)}</p>
      <button
        className="inline-flex items-center gap-1.5 rounded-md bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700"
        onClick={resetErrorBoundary}
        type="button"
      >
        <RefreshCw className="h-3.5 w-3.5" />
        Réessayer
      </button>
    </div>
  );
}

interface ComponentErrorBoundaryProps {
  children: ReactNode;
  label: string;
  onReset?: () => void;
  resetKeys?: unknown[];
}

export default function ComponentErrorBoundary({ children, label, onReset, resetKeys }: ComponentErrorBoundaryProps) {
  return (
    <ErrorBoundary
      fallbackRender={(props) => <ComponentErrorFallback {...props} label={label} />}
      onReset={onReset}
      resetKeys={resetKeys}
    >
      {children}
    </ErrorBoundary>
  );
}
