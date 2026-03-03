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
  const ctaClassName = "mt-4 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700";

  return (
    <div className="flex flex-col items-center justify-center py-16 text-center">
      <Icon
        className="mb-4 h-16 w-16 text-text-muted/40"
        data-testid="empty-state-icon"
        strokeWidth={1.5}
      />
      <h2 className="text-lg font-semibold text-text-primary">{title}</h2>
      {description && (
        <p className="mt-1 text-sm text-text-muted" data-testid="empty-state-description">
          {description}
        </p>
      )}
      {actionLabel && actionHref && (
        <Link className={ctaClassName} to={actionHref}>
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
