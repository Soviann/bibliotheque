import { Link } from "react-router-dom";
import type { ComicSeries } from "../types/api";
import { ComicStatusLabel, ComicTypeLabel } from "../types/enums";

interface ComicCardProps {
  comic: ComicSeries;
}

export default function ComicCard({ comic }: ComicCardProps) {
  const coverSrc = comic.coverUrl ?? (comic.coverImage ? `/uploads/covers/${comic.coverImage}` : null);
  const tomeCount = comic.tomes?.length ?? 0;

  return (
    <Link
      className="group block overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition hover:shadow-md"
      to={`/comic/${comic.id}`}
    >
      {/* Cover */}
      <div className="aspect-[2/3] overflow-hidden bg-slate-100">
        {coverSrc ? (
          <img
            alt={comic.title}
            className="h-full w-full object-cover transition group-hover:scale-105"
            loading="lazy"
            src={coverSrc}
          />
        ) : (
          <div className="flex h-full items-center justify-center text-4xl text-slate-300">
            📚
          </div>
        )}
      </div>

      {/* Info */}
      <div className="p-3">
        <h3 className="truncate text-sm font-semibold text-slate-900">{comic.title}</h3>
        <p className="truncate text-xs text-slate-500">
          {comic.authors?.map((a) => a.name).join(", ") || "—"}
        </p>
        <div className="mt-2 flex items-center justify-between gap-1">
          <span className="rounded-full bg-primary-100 px-2 py-0.5 text-xs font-medium text-primary-700">
            {ComicTypeLabel[comic.type]}
          </span>
          <span className="text-xs text-slate-400">
            {comic.isOneShot ? "One-shot" : `${tomeCount} t.`}
          </span>
        </div>
        <span className="mt-1 block text-xs text-slate-400">
          {ComicStatusLabel[comic.status]}
        </span>
      </div>
    </Link>
  );
}
