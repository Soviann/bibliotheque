interface ProgressBarProps {
  color?: string;
  compact?: boolean;
  current: number;
  label: string;
  total: number;
}

export default function ProgressBar({
  color = "bg-primary-600",
  compact = false,
  current,
  label,
  total,
}: ProgressBarProps) {
  const percentage = total > 0 ? (current / total) * 100 : 0;
  const percentRounded = Math.round(percentage);
  const countText = `${current} / ${total}`;

  return (
    <div className="space-y-1">
      {!compact && (
        <div className="flex items-center justify-between text-xs">
          <span className="font-medium text-text-secondary">{label}</span>
          <span className="font-mono-stats text-text-muted">
            {countText}{" "}
            <span className="text-text-muted/60">({percentRounded}%)</span>
          </span>
        </div>
      )}
      {compact && (
        <div className="flex items-center justify-between text-xs text-text-muted">
          <span className="font-mono-stats">{countText}</span>
        </div>
      )}
      <div
        aria-label={label}
        aria-valuemax={total}
        aria-valuemin={0}
        aria-valuenow={current}
        className={`overflow-hidden rounded-full bg-surface-tertiary dark:bg-white/10 ${compact ? "h-1.5" : "h-2"}`}
        role="progressbar"
      >
        <div
          className={`h-full rounded-full transition-all duration-700 ease-out ${color}`}
          style={{ width: `${percentage}%` }}
        />
      </div>
    </div>
  );
}
