import { Menu, MenuButton, MenuItem, MenuItems } from "@headlessui/react";
import { Bell, Edit, EllipsisVertical, Euro, Eye, HardDrive, Trash2 } from "lucide-react";
import { memo } from "react";
import { Link, useNavigate } from "react-router-dom";
import type { ComicSeries } from "../types/api";
import { ComicTypeLabel, ComicTypePlaceholder } from "../types/enums";
import { getCoverSrc } from "../utils/coverUtils";
import { hasNewRelease } from "../utils/releaseUtils";
import { countCoveredTomes } from "../utils/tomeUtils";
import SyncPendingIndicator from "./SyncPendingIndicator";

interface ComicCardProps {
  comic: ComicSeries;
  onDelete?: (comic: ComicSeries) => void;
  onMenuOpen?: (comic: ComicSeries) => void;
}

export default memo(function ComicCard({ comic, onDelete, onMenuOpen }: ComicCardProps) {
  const navigate = useNavigate();
  const coverSrc = getCoverSrc(comic);
  const tomes = comic.tomes ?? [];
  const coveredCount = countCoveredTomes(tomes);
  const boughtCount = countCoveredTomes(tomes, (t) => t.bought);
  const readCount = countCoveredTomes(tomes, (t) => t.read);
  const downloadedCount = countCoveredTomes(tomes, (t) => t.downloaded);
  const total = Math.max(comic.latestPublishedIssue ?? 0, coveredCount);
  const showStats = !comic.isOneShot && tomes.length > 0;
  const hasActions = !!onDelete;
  const isNewRelease = hasNewRelease(comic);

  // Bloquer la navigation uniquement pour les créations offline (ID temporaire négatif)
  if (comic._syncPending && comic.id < 0) {
    return (
      <div className="group block overflow-hidden rounded-xl border border-surface-border bg-surface-primary opacity-75 shadow-sm">
        {/* Cover */}
        <div className="aspect-[3/4] overflow-hidden bg-surface-tertiary">
          <img
            alt={comic.title}
            className="h-full w-full object-cover"
            loading="lazy"
            src={coverSrc ?? ComicTypePlaceholder[comic.type]}
          />
        </div>
        {/* Info */}
        <div className="p-2">
          <div className="flex items-start gap-1">
            <div className="min-w-0 flex-1">
              <h3 className="truncate text-sm font-semibold text-text-primary">
                <SyncPendingIndicator className="mr-1" />
                {comic.title}
              </h3>
              <p className="truncate text-xs text-text-muted">
                {ComicTypeLabel[comic.type]}
                {!comic.isOneShot && ` · ${tomes.length} t.`}
              </p>
              <p className="text-xs text-amber-600 dark:text-amber-400">
                En attente de synchronisation
              </p>
            </div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <Link
      className="group block overflow-hidden rounded-xl border border-surface-border bg-surface-primary shadow-sm transition hover:shadow-md"
      to={`/comic/${comic.id}`}
      viewTransition
    >
      {/* Cover */}
      <div className="relative aspect-[3/4] overflow-hidden bg-surface-tertiary">
        <img
          alt={comic.title}
          className="h-full w-full object-cover transition group-hover:scale-105"
          loading="lazy"
          src={coverSrc ?? ComicTypePlaceholder[comic.type]}
        />
        {isNewRelease && (
          <span
            className="absolute top-1.5 left-1.5 flex items-center gap-0.5 rounded-full bg-amber-500 px-1.5 py-0.5 text-[10px] font-semibold text-white shadow-sm"
            title="Nouveau(x) tome(s) détecté(s)"
          >
            <Bell className="h-2.5 w-2.5" />
            Nouveau
          </span>
        )}
      </div>

      {/* Info */}
      <div className="p-2">
        <div className="flex items-start gap-1">
          <div className="min-w-0 flex-1">
            <h3 className="truncate text-sm font-semibold text-text-primary">
              {comic._syncPending && <SyncPendingIndicator className="mr-1" />}
              {comic.title}
            </h3>
            <p className="truncate text-xs text-text-muted">
              {ComicTypeLabel[comic.type]}
              {!comic.isOneShot && ` · ${tomes.length} t.`}
            </p>
          </div>

          {hasActions && (
            <>
              {/* Mobile: simple button → CardActionBar */}
              <button
                aria-label="Actions"
                className="shrink-0 rounded-lg p-2.5 text-text-muted hover:bg-surface-tertiary lg:hidden"
                onClick={(e) => {
                  e.preventDefault();
                  e.stopPropagation();
                  onMenuOpen?.(comic);
                }}
                title="Actions"
                type="button"
              >
                <EllipsisVertical className="h-4 w-4" />
              </button>

              {/* Desktop: Headless UI dropdown */}
              <Menu as="div" className="relative hidden shrink-0 lg:block">
                <MenuButton
                  aria-label="Actions"
                  className="rounded-lg p-2.5 text-text-muted hover:bg-surface-tertiary"
                  onClick={(e: React.MouseEvent) => {
                    e.preventDefault();
                    e.stopPropagation();
                  }}
                  title="Actions"
                >
                  <EllipsisVertical className="h-4 w-4" />
                </MenuButton>
                <MenuItems anchor="bottom end" className="z-50 w-36 rounded-lg border border-surface-border bg-surface-primary py-1 shadow-lg">
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
                      <Edit className="h-4 w-4" />
                      Modifier
                    </button>
                  </MenuItem>
                  <MenuItem>
                    <button
                      className="flex w-full items-center gap-2 px-3 py-2 text-sm text-red-500 data-[focus]:bg-surface-tertiary"
                      onClick={(e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        onDelete?.(comic);
                      }}
                      type="button"
                    >
                      <Trash2 className="h-4 w-4" />
                      Supprimer
                    </button>
                  </MenuItem>
                </MenuItems>
              </Menu>
            </>
          )}
        </div>

        {showStats && (
          <div className="mt-1.5 flex items-center justify-between text-xs text-text-muted">
            <span className="flex items-center gap-0.5" title="Achetés">
              <Euro className="h-3 w-3" />
              {boughtCount}/{total}
            </span>
            <span className="flex items-center gap-0.5" title="Lus">
              <Eye className="h-3 w-3" />
              {readCount}/{total}
            </span>
            <span className="flex items-center gap-0.5" title="Téléchargés">
              <HardDrive className="h-3 w-3" />
              {downloadedCount}/{total}
            </span>
          </div>
        )}
      </div>
    </Link>
  );
});
