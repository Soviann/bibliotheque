import { Link } from "react-router-dom";
import type { ComicSeries } from "../types/api";
import { ComicTypePlaceholder } from "../types/enums";
import { getCoverSrc, getCoverThumbnailSrc } from "../utils/coverUtils";
import CoverImage from "./CoverImage";

interface ShelfRowProps {
  comics: ComicSeries[];
  onSeeAll: () => void;
  title: string;
}

export default function ShelfRow({ comics, onSeeAll, title }: ShelfRowProps) {
  if (comics.length === 0) return null;

  return (
    <section className="space-y-2">
      <div className="flex items-center justify-between">
        <h3 className="text-sm font-semibold text-text-primary">
          {title}{" "}
          <span className="font-normal text-text-muted">({comics.length})</span>
        </h3>
        <button
          className="text-xs font-medium text-primary-600 dark:text-primary-400"
          onClick={onSeeAll}
          type="button"
        >
          Tout voir
        </button>
      </div>
      <div className="-mx-4 flex snap-x snap-mandatory gap-3 overflow-x-auto px-4 pb-2 scrollbar-none">
        {comics.map((comic) => {
          const src = getCoverThumbnailSrc(comic) ?? getCoverSrc(comic);
          return (
            <Link
              className="group flex w-[100px] shrink-0 snap-start flex-col gap-1"
              key={comic.id}
              to={`/comic/${comic.id}`}
              viewTransition
            >
              <div className="card-glow overflow-hidden rounded-lg">
                <CoverImage
                  alt={comic.title}
                  className="aspect-[3/4]"
                  fallbackSrc={ComicTypePlaceholder[comic.type]}
                  height={133}
                  src={src ?? ComicTypePlaceholder[comic.type]}
                  width={100}
                />
              </div>
              <h4 className="truncate text-xs font-medium text-text-primary">
                {comic.title}
              </h4>
            </Link>
          );
        })}
      </div>
    </section>
  );
}
