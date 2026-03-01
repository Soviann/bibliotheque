interface ProgressBarProps {
  color?: string;
  compact?: boolean;
  current: number;
  label: string;
  total: number;
}

export default function ProgressBar({ color = "bg-primary-600", compact = false, current, label, total }: ProgressBarProps) {
  const percentage = total > 0 ? (current / total) * 100 : 0;

  return (
    <div className="space-y-1">
      {!compact && (
        <div className="flex items-center justify-between text-xs text-text-secondary">
          <span>{label}</span>
          <span>{current} / {total}</span>
        </div>
      )}
      {compact && (
        <div className="flex items-center justify-between text-xs text-text-muted">
          <span>{current} / {total}</span>
        </div>
      )}
      <div
        aria-label={label}
        aria-valuemax={total}
        aria-valuemin={0}
        aria-valuenow={current}
        className={`overflow-hidden rounded-full bg-surface-tertiary ${compact ? "h-1.5" : "h-2"}`}
        role="progressbar"
      >
        <div
          className={`h-full rounded-full transition-all ${color}`}
          style={{ width: `${percentage}%` }}
        />
      </div>
    </div>
  );
}
