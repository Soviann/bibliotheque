import { Menu, MenuButton, MenuItem, MenuItems } from "@headlessui/react";
import { Bell, Edit, EllipsisVertical, Euro, Eye, HardDrive, Trash2 } from "lucide-react";
import { memo } from "react";
import { Link, useNavigate } from "react-router-dom";
import { useDominantColor } from "../hooks/useDominantColor";
import type { ComicSeries } from "../types/api";
import { ComicTypeLabel, ComicTypePlaceholder } from "../types/enums";
import { getCoverSrc, getCoverThumbnailSrc } from "../utils/coverUtils";
import { hasNewRelease } from "../utils/releaseUtils";
import CoverImage from "./CoverImage";
import SyncPendingIndicator from "./SyncPendingIndicator";

interface ComicCardProps {
  comic: ComicSeries;
  onDelete?: (comic: ComicSeries) => void;
  onMenuOpen?: (comic: ComicSeries) => void;
}

export default memo(function ComicCard({ comic, onDelete, onMenuOpen }: ComicCardProps) {
  const navigate = useNavigate();
  const coverSrc = getCoverThumbnailSrc(comic) ?? getCoverSrc(comic);
  const total = Math.max(comic.latestPublishedIssue ?? 0, comic.coveredCount);
  const showStats = !comic.isOneShot && comic.tomesCount > 0;
  const hasActions = !!onDelete;
  const isNewRelease = hasNewRelease(comic);
  const [dominantColor, extractColor] = useDominantColor(coverSrc);

  // Bloquer la navigation uniquement pour les créations offline (ID temporaire négatif)
  if (comic._syncPending && comic.id < 0) {
    return (
      <div className="group block overflow-hidden rounded-xl border border-surface-border bg-surface-primary opacity-75 shadow-sm">
        <CoverImage
          alt={comic.title}
          className="aspect-[3/4]"
          fallbackSrc={ComicTypePlaceholder[comic.type]}
          height={200}
          src={coverSrc ?? ComicTypePlaceholder[comic.type]}
          width={150}
        />
        <div className="px-2 py-1.5">
          <h3 className="truncate text-sm font-semibold text-text-primary">
            <SyncPendingIndicator className="mr-1" />
            {comic.title}
          </h3>
          <p className="text-xs text-amber-600 dark:text-amber-400">
            En attente de synchronisation
          </p>
        </div>
      </div>
    );
  }

  return (
    <Link
      className="card-glow group block overflow-hidden rounded-xl border border-surface-border bg-surface-primary transition-all duration-200 hover:-translate-y-0.5 hover:border-primary-500/50 dark:border-transparent dark:bg-surface-secondary dark:hover:border-primary-400/30"
      style={{
        // Ambient glow en dark mode — couleur dominante de la couverture
        ["--glow-rgb" as string]: dominantColor,
      }}
      to={`/comic/${comic.id}`}
      viewTransition
    >
      {/* Cover */}
      <div className="relative aspect-[3/4] overflow-hidden bg-surface-tertiary">
        <CoverImage
          alt={comic.title}
          className="h-full w-full transition-transform duration-300 group-hover:scale-105"
          fallbackSrc={ComicTypePlaceholder[comic.type]}
          height={200}
          onImageLoad={extractColor}
          src={coverSrc ?? ComicTypePlaceholder[comic.type]}
          viewTransitionName={`comic-cover-${comic.id}`}
          width={150}
        />

        {/* Badge Nouveau — top-right, sticker style */}
        {isNewRelease && (
          <span
            className="absolute top-1.5 right-1.5 flex -rotate-2 items-center gap-0.5 rounded-full bg-primary-500 px-2 py-0.5 text-[10px] font-bold text-white shadow-sm dark:bg-accent-sage dark:text-surface-primary dark:animate-glow-pulse"
            title="Nouveau(x) tome(s) détecté(s)"
          >
            <Bell className="h-2.5 w-2.5" strokeWidth={2.5} />
            Nouveau
          </span>
        )}

        {/* Badge Type — top-left, semi-transparent */}
        <span className="absolute top-1.5 left-1.5 rounded-md bg-black/50 px-1.5 py-0.5 text-[9px] font-medium text-white backdrop-blur-sm">
          {ComicTypeLabel[comic.type]}
        </span>

        {/* Stats overlay — toujours visible, hover effect sur desktop */}
        {showStats && (
          <div className="absolute inset-x-0 bottom-0 flex items-center justify-around bg-black/60 px-2 py-1.5 text-[10px] text-white/90 backdrop-blur-sm lg:translate-y-full lg:transition-transform lg:duration-200 lg:group-hover:translate-y-0">
            <span className="flex items-center gap-0.5" title="Achetés">
              <Euro className="h-3 w-3" strokeWidth={1.5} />
              {comic.boughtCount}/{total}
            </span>
            <span className="flex items-center gap-0.5" title="Lus">
              <Eye className="h-3 w-3" strokeWidth={1.5} />
              {comic.readCount}/{total}
            </span>
            <span className="flex items-center gap-0.5" title="Téléchargés">
              <HardDrive className="h-3 w-3" strokeWidth={1.5} />
              {comic.downloadedCount}/{total}
            </span>
          </div>
        )}
      </div>

      {/* Info — minimal: title + tome count */}
      <div className="flex items-start gap-1 px-2 py-1.5">
        <div className="min-w-0 flex-1">
          <h3 className="truncate font-display text-sm font-semibold text-text-primary dark:font-body dark:font-medium">
            {comic._syncPending && <SyncPendingIndicator className="mr-1" />}
            {comic.title}
          </h3>
          {!comic.isOneShot && (
            <p className="font-mono-stats text-xs text-text-muted">
              {comic.tomesCount} t.
            </p>
          )}
        </div>

        {hasActions && (
          <>
            {/* Mobile: simple button → CardActionBar */}
            <button
              aria-label="Actions"
              className="shrink-0 rounded-lg p-2 text-text-muted hover:bg-surface-tertiary lg:hidden"
              onClick={(e) => {
                e.preventDefault();
                e.stopPropagation();
                onMenuOpen?.(comic);
              }}
              title="Actions"
              type="button"
            >
              <EllipsisVertical className="h-4 w-4" strokeWidth={1.5} />
            </button>

            {/* Desktop: Headless UI dropdown */}
            <Menu as="div" className="relative hidden shrink-0 lg:block">
              <MenuButton
                aria-label="Actions"
                className="rounded-lg p-2 text-text-muted hover:bg-surface-tertiary"
                onClick={(e: React.MouseEvent) => {
                  e.preventDefault();
                  e.stopPropagation();
                }}
                title="Actions"
              >
                <EllipsisVertical className="h-4 w-4" strokeWidth={1.5} />
              </MenuButton>
              <MenuItems anchor="bottom end" className="z-50 w-36 rounded-xl border border-surface-border bg-surface-primary py-1 shadow-lg dark:border-white/10 dark:bg-surface-elevated">
                <MenuItem>
                  <button
                    className="flex w-full items-center gap-2 px-3 py-2 text-sm text-text-primary data-[focus]:bg-surface-tertiary"
                    onClick={(e) => {
                      e.preventDefault();
                      e.stopPropagation();
                      navigate(`/comic/${comic.id}/edit`, { viewTransition: true });
                    }}
                    type="button"
                  >
                    <Edit className="h-4 w-4" strokeWidth={1.5} />
                    Modifier
                  </button>
                </MenuItem>
                <MenuItem>
                  <button
                    className="flex w-full items-center gap-2 px-3 py-2 text-sm text-accent-danger data-[focus]:bg-surface-tertiary"
                    onClick={(e) => {
                      e.preventDefault();
                      e.stopPropagation();
                      onDelete?.(comic);
                    }}
                    type="button"
                  >
                    <Trash2 className="h-4 w-4" strokeWidth={1.5} />
                    Supprimer
                  </button>
                </MenuItem>
              </MenuItems>
            </Menu>
          </>
        )}
      </div>
    </Link>
  );
});
