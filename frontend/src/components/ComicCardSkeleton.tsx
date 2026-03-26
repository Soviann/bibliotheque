import SkeletonBox from "./SkeletonBox";

export default function ComicCardSkeleton() {
  return (
    <div
      className="overflow-hidden rounded-xl border border-surface-border bg-surface-primary dark:border-white/10 dark:bg-surface-secondary"
      data-testid="comic-card-skeleton"
    >
      {/* Cover */}
      <SkeletonBox className="aspect-[3/4] !rounded-none" />

      {/* Info */}
      <div className="space-y-2 px-2 py-1.5">
        <SkeletonBox className="h-4 w-3/4" />
        <SkeletonBox className="h-3 w-1/3" />
      </div>
    </div>
  );
}
