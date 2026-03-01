interface SkeletonBoxProps {
  className?: string;
}

export default function SkeletonBox({ className = "" }: SkeletonBoxProps) {
  return (
    <div
      className={`animate-pulse rounded-lg bg-surface-tertiary ${className}`}
      data-testid="skeleton-box"
    />
  );
}
