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

/**
 * Retourne l'URL de la miniature LiipImagine (300x450 WebP) pour les vues liste/grille.
 * Retourne null si pas de couverture locale (les URLs externes ne passent pas par LiipImagine).
 */
export function getCoverThumbnailSrc(comic: { coverImage: string | null; coverUrl?: string | null }): string | null {
  if (comic.coverImage) {
    return `/media/cache/cover_thumbnail/uploads/covers/${comic.coverImage}`;
  }
  return null;
}
