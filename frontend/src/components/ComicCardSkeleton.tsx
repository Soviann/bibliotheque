import SkeletonBox from "./SkeletonBox";

export default function ComicCardSkeleton() {
  return (
    <div
      className="overflow-hidden rounded-xl border border-surface-border bg-surface-primary"
      data-testid="comic-card-skeleton"
    >
      {/* Cover */}
      <SkeletonBox className="aspect-[3/4] !rounded-none" />

      {/* Info */}
      <div className="space-y-2 p-2">
        <SkeletonBox className="h-4 w-3/4" />
        <SkeletonBox className="h-3 w-1/2" />
      </div>
    </div>
  );
}
