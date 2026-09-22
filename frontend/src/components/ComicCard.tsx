import { Menu, MenuButton, MenuItem, MenuItems } from "@headlessui/react";
import {
  Bell,
  Edit,
  EllipsisVertical,
  Trash2,
} from "lucide-react";
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
  acquisitionMode?: "all" | "tobuy" | "todownload";
  comic: ComicSeries;
  onBuyTome?: (seriesId: number, tomeId: number) => void;
  onDelete?: (comic: ComicSeries) => void;
  onMenuOpen?: (comic: ComicSeries) => void;
}

export default memo(function ComicCard({
  acquisitionMode = "all",
  comic,
  onBuyTome,
  onDelete,
  onMenuOpen,
}: ComicCardProps) {
  const navigate = useNavigate();
  const coverSrc = getCoverThumbnailSrc(comic) ?? getCoverSrc(comic);
  const total = Math.max(comic.latestPublishedIssue ?? 0, comic.coveredCount);
  const showStats = !comic.isOneShot && comic.tomesCount > 0;
  const unboughtCount = comic.unboughtTomes?.length ?? 0;
  const showMissingAlert =
    !comic.isOneShot && !comic.notInterestedBuy && unboughtCount > 0;
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
      className="card-glow group flex flex-col overflow-hidden rounded-xl border border-surface-border bg-surface-primary transition-all duration-200 hover:-translate-y-0.5 hover:border-primary-500/50 dark:border-transparent dark:bg-surface-secondary dark:hover:border-primary-400/30"
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

        {/* Micro-badge tomes manquants */}
        {showMissingAlert && (
          <span className="absolute bottom-1.5 right-1.5 rounded-md bg-amber-600/90 px-1.5 py-0.5 text-[10px] font-bold text-white shadow backdrop-blur-sm">
            -{unboughtCount} à acheter
          </span>
        )}
      </div>

      {/* Info Box — Mini-Dashboard */}
      <div className="flex flex-1 flex-col justify-between gap-1.5 p-2.5">
        <div className="flex items-start gap-1">
          <div className="min-w-0 flex-1">
            <h3 className="truncate font-display text-sm font-semibold text-text-primary group-hover:text-primary-600 dark:group-hover:text-amber-400">
              {comic._syncPending && <SyncPendingIndicator className="mr-1" />}
              {comic.title}
            </h3>
            {comic.authors && comic.authors.length > 0 ? (
              <p className="truncate text-[11px] text-text-secondary">
                {comic.authors.map((a) => a.name).join(", ")}
              </p>
            ) : !comic.isOneShot ? (
              <p className="font-mono-stats text-xs text-text-muted">
                {comic.tomesCount} t.
              </p>
            ) : null}
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
                <MenuItems
                  anchor="bottom end"
                  className="z-50 w-36 rounded-xl border border-surface-border bg-surface-primary py-1 shadow-layered-lg dark:border-white/10 dark:bg-surface-elevated"
                >
                  <MenuItem>
                    <button
                      className="flex w-full items-center gap-2 px-3 py-2 text-sm text-text-primary data-[focus]:bg-surface-tertiary"
                      onClick={(e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        navigate(`/comic/${comic.id}/edit`, {
                          viewTransition: true,
                        });
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

        {/* Operational Strip: À acheter */}
        {acquisitionMode === "tobuy" ? (
          comic.notInterestedBuy ? (
            <div className="card-tobuy-strip -mx-2.5 -mb-2.5 mt-auto rounded-b-xl border-t border-surface-border p-2 text-center text-[10px] text-text-muted dark:border-white/10">
              Série non suivie pour achat
            </div>
          ) : (
            <div className="card-tobuy-strip -mx-2.5 -mb-2.5 mt-auto space-y-1.5 rounded-b-xl border-t border-amber-500/30 bg-amber-500/10 p-2">
              <div className="flex items-center justify-between text-[10px] font-bold text-amber-700 dark:text-amber-400">
                <span>
                  {unboughtCount} tome{unboughtCount > 1 ? "s" : ""} à acheter :
                </span>
              </div>
              <div className="flex flex-wrap gap-1">
                {comic.unboughtTomes?.slice(0, 5).map((tome) => (
                  <button
                    key={tome.id}
                    aria-label={`Marquer le tome ${tome.number} comme acheté`}
                    className="rounded-md border border-amber-500/40 bg-surface-primary px-2 py-0.5 font-mono text-[10px] font-bold text-amber-700 transition active:scale-95 hover:bg-amber-500 hover:text-white dark:bg-surface-elevated dark:text-amber-300"
                    onClick={(e) => {
                      e.preventDefault();
                      e.stopPropagation();
                      onBuyTome?.(comic.id, tome.id);
                    }}
                    title={`Marquer le tome ${tome.number} comme acheté`}
                    type="button"
                  >
                    {tome.isHorsSerie ? "HS" : `T.${tome.number}`} +
                  </button>
                ))}
                {(comic.unboughtTomes?.length ?? 0) > 5 && (
                  <span className="self-center text-[9px] text-text-muted">
                    +{(comic.unboughtTomes?.length ?? 0) - 5}
                  </span>
                )}
              </div>
            </div>
          )
        ) : acquisitionMode === "todownload" ? (
          /* Operational Strip: À télécharger (NAS) */
          comic.notInterestedNas ? (
            <div className="card-todownload-strip -mx-2.5 -mb-2.5 mt-auto rounded-b-xl border-t border-surface-border p-2 text-center text-[10px] text-text-muted dark:border-white/10">
              Série non suivie sur le NAS
            </div>
          ) : (
            <div className="card-todownload-strip -mx-2.5 -mb-2.5 mt-auto rounded-b-xl border-t border-blue-500/30 bg-blue-500/10 p-2 text-xs">
              <div className="text-[10px] font-bold text-blue-700 dark:text-blue-400">
                {Math.max(0, total - comic.onNasCount)} tome
                {Math.max(0, total - comic.onNasCount) > 1 ? "s" : ""} manquant
                {Math.max(0, total - comic.onNasCount) > 1 ? "s" : ""} sur NAS
              </div>
            </div>
          )
        ) : (
          /* Default: Mini-dashboard 3-metric row */
          showStats && (
            <div className="card-metrics mt-auto space-y-1 border-t border-surface-border pt-1.5 dark:border-white/5">
              <div className="grid grid-cols-3 gap-1 rounded-lg bg-surface-elevated py-1 px-1.5 text-center font-mono-stats text-[10px] font-bold">
                {/* Acheté */}
                <div
                  className={
                    comic.notInterestedBuy
                      ? "text-text-muted dark:text-text-secondary"
                      : "text-emerald-700 dark:text-emerald-400"
                  }
                  title={
                    comic.notInterestedBuy
                      ? "Non suivi pour achat"
                      : "Achetés"
                  }
                >
                  <span className="block font-sans text-[8px] font-normal text-text-muted">
                    Acheté
                  </span>
                  {comic.notInterestedBuy ? (
                    <span className="text-xs">✕</span>
                  ) : (
                    `${comic.boughtCount}/${total}`
                  )}
                </div>

                {/* NAS */}
                <div
                  className={`border-x border-surface-border px-0.5 dark:border-white/10 ${
                    comic.notInterestedNas
                      ? "text-text-muted dark:text-text-secondary"
                      : "text-blue-700 dark:text-blue-400"
                  }`}
                  title={
                    comic.notInterestedNas ? "Non suivi sur le NAS" : "Sur NAS"
                  }
                >
                  <span className="block font-sans text-[8px] font-normal text-text-muted">
                    NAS
                  </span>
                  {comic.notInterestedNas ? (
                    <span className="text-xs">✕</span>
                  ) : (
                    `${comic.onNasCount}/${total}`
                  )}
                </div>

                {/* Lu */}
                <div
                  className="text-amber-700 dark:text-amber-400"
                  title="Lus"
                >
                  <span className="block font-sans text-[8px] font-normal text-text-muted">
                    Lu
                  </span>
                  {`${comic.readCount}/${total}`}
                </div>
              </div>
            </div>
          )
        )}
      </div>
    </Link>
  );
});
