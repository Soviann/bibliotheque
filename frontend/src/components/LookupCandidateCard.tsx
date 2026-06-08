import { useState } from "react";
import type { LookupCandidate } from "../types/api";
import { candidateVolumeChip, candidateYear } from "../utils/lookupCandidate";

interface LookupCandidateCardProps {
  candidate: LookupCandidate;
  onSelect: () => void;
}

type ChipTone = "neutral" | "accent" | "oneshot";

interface Chip {
  text: string;
  tone: ChipTone;
}

const toneClasses: Record<ChipTone, string> = {
  accent:
    "text-primary-600 bg-primary-50 dark:bg-primary-500/10",
  neutral: "text-text-secondary bg-surface-tertiary",
  oneshot:
    "text-amber-700 bg-amber-50 dark:text-amber-300 dark:bg-amber-500/10",
};

export default function LookupCandidateCard({
  candidate,
  onSelect,
}: LookupCandidateCardProps) {
  const [expanded, setExpanded] = useState(false);

  const chips: Chip[] = [];

  if (candidate.publisher) {
    chips.push({ text: candidate.publisher, tone: "neutral" });
  }

  const year = candidateYear(candidate.publishedDate);
  if (year !== null) {
    chips.push({ text: year, tone: "neutral" });
  }

  const volumeChip = candidateVolumeChip(candidate);
  if (volumeChip !== null) {
    if (volumeChip.kind === "oneshot") {
      chips.push({ text: "One-shot", tone: "oneshot" });
    } else {
      chips.push({ text: volumeChip.label, tone: "accent" });
    }
  }

  const hasDescription =
    typeof candidate.description === "string" &&
    candidate.description.length > 0;

  return (
    <div
      className="w-full cursor-pointer rounded-xl border border-surface-border bg-surface-primary p-3 text-left transition-transform active:scale-[0.98] hover:border-primary-400 dark:border-white/10 dark:bg-surface-secondary"
      onClick={onSelect}
      onKeyDown={(e) => {
        if (e.key === "Enter") {
          onSelect();
        } else if (e.key === " ") {
          e.preventDefault();
          onSelect();
        }
      }}
      role="button"
      tabIndex={0}
    >
      <div className="flex gap-3">
        {candidate.thumbnail ? (
          // Vignette externe (Google Books, Bédéthèque…) : <img> simple, sans
          // crossOrigin (contrairement à CoverImage réservé aux couvertures
          // locales), sinon le CORS bloque le chargement.
          <img
            alt={candidate.title ?? ""}
            className="h-24 w-16 shrink-0 rounded-lg object-cover"
            src={candidate.thumbnail}
          />
        ) : (
          <div className="flex h-24 w-16 shrink-0 items-center justify-center rounded-lg bg-surface-tertiary text-text-muted">
            ?
          </div>
        )}

        <div className="min-w-0 flex-1">
          <h4 className="truncate text-sm font-semibold text-text-primary">
            {candidate.title ?? "Sans titre"}
          </h4>

          {candidate.authors && (
            <p className="truncate text-xs text-text-muted">
              {candidate.authors}
            </p>
          )}

          {chips.length > 0 && (
            <div className="mt-2 flex flex-wrap gap-1.5">
              {chips.map((chip) => (
                <span
                  className={`rounded-md px-2 py-0.5 text-[11px] font-semibold whitespace-nowrap ${toneClasses[chip.tone]}`}
                  key={chip.text}
                >
                  {chip.text}
                </span>
              ))}
            </div>
          )}
        </div>
      </div>

      {hasDescription && (
        <>
          <p
            className={`mt-2.5 text-[13px] leading-relaxed text-text-secondary${expanded ? "" : " line-clamp-3"}`}
          >
            {candidate.description}
          </p>
          <button
            className="mt-1.5 text-xs font-semibold text-primary-600"
            onClick={(e) => {
              e.stopPropagation();
              setExpanded((v) => !v);
            }}
            type="button"
          >
            {expanded ? "moins" : "plus"}
          </button>
        </>
      )}
    </div>
  );
}
