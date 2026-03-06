import { Menu, MenuButton, MenuItem, MenuItems } from "@headlessui/react";
import { Edit, EllipsisVertical, Trash2 } from "lucide-react";
import { Link, useNavigate } from "react-router-dom";
import type { ComicSeries } from "../types/api";
import { ComicTypeLabel, ComicTypePlaceholder } from "../types/enums";
import { countCoveredTomes } from "../utils/tomeUtils";
import ProgressBar from "./ProgressBar";
import SyncPendingIndicator from "./SyncPendingIndicator";

interface ComicCardProps {
  comic: ComicSeries;
  onDelete?: (comic: ComicSeries) => void;
  onMenuOpen?: (comic: ComicSeries) => void;
}

export default function ComicCard({ comic, onDelete, onMenuOpen }: ComicCardProps) {
  const navigate = useNavigate();
  const coverSrc = comic.coverUrl ?? (comic.coverImage ? `/uploads/covers/${comic.coverImage}` : null);
  const tomes = comic.tomes ?? [];
  const coveredCount = countCoveredTomes(tomes);
  const boughtCount = countCoveredTomes(tomes, (t) => t.bought);
  const total = comic.latestPublishedIssue ?? coveredCount;
  const showProgress = !comic.isOneShot && tomes.length > 0;
  const hasActions = !!onDelete;

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
      <div className="aspect-[3/4] overflow-hidden bg-surface-tertiary">
        <img
          alt={comic.title}
          className="h-full w-full object-cover transition group-hover:scale-105"
          loading="lazy"
          src={coverSrc ?? ComicTypePlaceholder[comic.type]}
        />
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
                className="shrink-0 rounded-lg p-1 text-text-muted hover:bg-surface-tertiary lg:hidden"
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
                  className="rounded-lg p-1 text-text-muted hover:bg-surface-tertiary"
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

        {showProgress && (
          <div className="mt-1.5">
            <ProgressBar compact current={boughtCount} label="Progression d'achat" total={total} />
          </div>
        )}
      </div>
    </Link>
  );
}
