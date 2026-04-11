import { ChevronDown } from "lucide-react";
import { useMemo, useState } from "react";
import { Link } from "react-router-dom";
import type { ComicSeries } from "../types/api";
import { ComicTypePlaceholder } from "../types/enums";
import { getCoverSrc, getCoverThumbnailSrc } from "../utils/coverUtils";
import CoverImage from "./CoverImage";

interface ContinueReadingProps {
  comics: ComicSeries[];
}

export default function ContinueReading({ comics }: ContinueReadingProps) {
  const [isOpen, setIsOpen] = useState(false);
  const toRead = useMemo(
    () =>
      comics.filter(
        (c) =>
          !c.isOneShot && c.readCount < Math.max(c.boughtCount, c.onNasCount),
      ),
    [comics],
  );

  if (toRead.length === 0) return null;

  return (
    <section className="space-y-2">
      <button
        aria-controls="continue-reading-list"
        aria-expanded={isOpen}
        className="group flex w-full items-center gap-2 font-display text-sm font-semibold text-text-secondary"
        onClick={() => setIsOpen((v) => !v)}
        type="button"
      >
        <ChevronDown
          className={`h-4 w-4 shrink-0 transition-transform duration-200 ${isOpen ? "rotate-0" : "-rotate-90"}`}
        />
        <span>Continuer la lecture</span>
        <span className="rounded-full bg-surface-muted px-2 py-0.5 font-mono-stats text-xs font-medium text-text-muted dark:bg-white/5">
          {toRead.length}
        </span>
      </button>
      {isOpen && (
        <div
          className="-mx-4 -mt-2 flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 pb-2 pt-2 scrollbar-none"
          id="continue-reading-list"
        >
          {toRead.map((comic) => {
            const src = getCoverThumbnailSrc(comic) ?? getCoverSrc(comic);
            const nextTome = comic.readCount + 1;
            return (
              <Link
                className="group flex w-[140px] shrink-0 snap-center flex-col gap-1.5 transition-transform duration-200 hover:-translate-y-1 hover:scale-[1.02] sm:w-[160px]"
                key={comic.id}
                to={`/comic/${comic.id}`}
                viewTransition
              >
                <div
                  className="card-glow overflow-hidden rounded-xl"
                  style={{ ["--glow-rgb" as string]: "99, 102, 241" }}
                >
                  <CoverImage
                    alt={comic.title}
                    className="aspect-[3/4]"
                    fallbackSrc={ComicTypePlaceholder[comic.type]}
                    height={240}
                    src={src ?? ComicTypePlaceholder[comic.type]}
                    width={180}
                  />
                </div>
                <h3 className="truncate font-display text-sm font-medium text-text-primary">
                  {comic.title}
                </h3>
                <p className="font-mono-stats text-xs text-text-muted">
                  Tome {nextTome}
                </p>
              </Link>
            );
          })}
        </div>
      )}
    </section>
  );
}
