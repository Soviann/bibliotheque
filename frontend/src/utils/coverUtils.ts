function isValidCoverUrl(url: string): boolean {
  return url.startsWith("http://") || url.startsWith("https://");
}

export function getCoverSrc(comic: { coverImage: string | null; coverUrl?: string | null }): string | null {
  if (comic.coverImage) {
    return `/uploads/covers/${comic.coverImage}`;
  }
  if (comic.coverUrl && isValidCoverUrl(comic.coverUrl)) {
    return comic.coverUrl;
  }
  return null;
}
