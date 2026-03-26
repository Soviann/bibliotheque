import { Link } from "react-router-dom";
import type { ComicSeries } from "../types/api";
import { ComicTypePlaceholder } from "../types/enums";
import { getCoverSrc, getCoverThumbnailSrc } from "../utils/coverUtils";
import CoverImage from "./CoverImage";

interface HeroCarouselProps {
  comics: ComicSeries[];
}

export default function HeroCarousel({ comics }: HeroCarouselProps) {
  return (
    <section className="space-y-2">
      <h2 className="font-display text-sm font-semibold text-text-secondary dark:font-body dark:text-xs dark:uppercase dark:tracking-widest dark:text-text-muted">
        Récemment ajoutés
      </h2>
      <div className="-mx-4 flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 pb-2 scrollbar-none">
        {comics.map((comic, index) => {
          const src = getCoverThumbnailSrc(comic) ?? getCoverSrc(comic);
          const isCenter = index === 0;
          return (
            <Link
              className={`group flex shrink-0 snap-center flex-col gap-1.5 transition-all duration-300 ${
                isCenter ? "w-[160px] sm:w-[180px]" : "w-[140px] sm:w-[160px]"
              }`}
              key={comic.id}
              to={`/comic/${comic.id}`}
              viewTransition
            >
              <div className="card-glow overflow-hidden rounded-xl transition-transform duration-200 group-hover:-translate-y-1 group-hover:scale-[1.02]"
                style={{ ["--glow-rgb" as string]: "99, 102, 241" }}
              >
                <CoverImage
                  alt={comic.title}
                  className="aspect-[3/4]"
                  fallbackSrc={ComicTypePlaceholder[comic.type]}
                  height={240}
                  src={src ?? ComicTypePlaceholder[comic.type]}
                  viewTransitionName={`comic-cover-${comic.id}`}
                  width={180}
                />
              </div>
              <h3 className="truncate font-display text-sm font-medium text-text-primary dark:font-body dark:text-xs">
                {comic.title}
              </h3>
              {!comic.isOneShot && (
                <p className="font-mono-stats text-xs text-text-muted">
                  {comic.tomesCount} t.
                </p>
              )}
            </Link>
          );
        })}
      </div>
    </section>
  );
}
