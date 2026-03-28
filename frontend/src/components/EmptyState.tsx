import type { LucideIcon } from "lucide-react";
import { Link } from "react-router-dom";

interface EmptyStateProps {
  actionHref?: string;
  actionLabel?: string;
  description?: string;
  icon: LucideIcon;
  onAction?: () => void;
  title: string;
}

export default function EmptyState({
  actionHref,
  actionLabel,
  description,
  icon: Icon,
  onAction,
  title,
}: EmptyStateProps) {
  const ctaClassName = "mt-4 rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-primary-700 transition-colors";

  return (
    <div className="flex animate-fade-in-up flex-col items-center justify-center py-16 text-center motion-reduce:animate-none">
      <div
        className="mb-4 flex h-20 w-20 items-center justify-center rounded-2xl bg-gradient-to-br from-primary-100 to-primary-200 dark:from-primary-950/40 dark:to-primary-900/30"
        data-testid="empty-state-icon-wrapper"
      >
        <Icon
          className="h-10 w-10 text-primary-500 dark:text-primary-400"
          data-testid="empty-state-icon"
          strokeWidth={1.5}
        />
      </div>
      <h2 className="font-display text-lg font-semibold text-text-primary">{title}</h2>
      {description && (
        <p className="mt-1 text-sm text-text-muted" data-testid="empty-state-description">
          {description}
        </p>
      )}
      {actionLabel && actionHref && (
        <Link className={ctaClassName} to={actionHref} viewTransition>
          {actionLabel}
        </Link>
      )}
      {actionLabel && onAction && !actionHref && (
        <button className={ctaClassName} onClick={onAction} type="button">
          {actionLabel}
        </button>
      )}
    </div>
  );
}
