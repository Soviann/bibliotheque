interface ProgressBarProps {
  color?: string;
  current: number;
  label: string;
  total: number;
}

export default function ProgressBar({ color = "bg-primary-600", current, label, total }: ProgressBarProps) {
  const percentage = total > 0 ? (current / total) * 100 : 0;

  return (
    <div className="space-y-1">
      <div className="flex items-center justify-between text-xs text-text-secondary">
        <span>{label}</span>
        <span>{current} / {total}</span>
      </div>
      <div
        aria-label={label}
        aria-valuemax={total}
        aria-valuemin={0}
        aria-valuenow={current}
        className="h-2 overflow-hidden rounded-full bg-surface-tertiary"
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
