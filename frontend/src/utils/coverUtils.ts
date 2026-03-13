export function getCoverSrc(comic: { coverImage: string | null; coverUrl?: string | null }): string | null {
  if (comic.coverImage) {
    return `/uploads/covers/${comic.coverImage}`;
  }
  return comic.coverUrl ?? null;
}
