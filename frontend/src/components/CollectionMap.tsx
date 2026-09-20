import { Check } from "lucide-react";
import { useMemo } from "react";
import type { Tome } from "../types/api";
import { countCoveredTomes } from "../utils/tomeUtils";

interface CollectionMapProps {
  latestPublishedIssue: number | null;
  onSelectTome?: (
    tomeNumber: number,
    tome?: Tome,
    isHorsSerie?: boolean,
  ) => void;
  tomes: Tome[];
}

/** Construit une map numéro → tome, en développant les plages tomeEnd. */
function buildTomeMap(tomes: Tome[]): Map<number, Tome> {
  const map = new Map<number, Tome>();
  for (const tome of tomes) {
    if (tome.isHorsSerie) continue;
    const end = tome.tomeEnd ?? tome.number;
    for (let n = tome.number; n <= end; n++) {
      if (!map.has(n)) map.set(n, tome);
    }
  }
  return map;
}

function cellClasses(tome: Tome | undefined): string {
  const base =
    "flex aspect-square items-center justify-center rounded text-xs font-medium transition-all hover:scale-105 active:scale-95 focus:outline-none focus:ring-2 focus:ring-primary-500/50 cursor-pointer";

  if (!tome) {
    return `${base} border border-dashed border-text-muted/30 text-text-muted/50 hover:border-text-muted/60`;
  }

  if (tome.bought) {
    return `${base} bg-[rgb(var(--series-color))] text-white hover:brightness-110`;
  }

  if (tome.onNas) {
    return `${base} border-2 border-[rgb(var(--series-color))] text-[rgb(var(--series-color))] hover:opacity-80`;
  }

  // Tome exists but neither bought nor on NAS
  return `${base} border border-dashed border-text-muted/30 text-text-muted/50 hover:border-text-muted/60`;
}

function cellTitle(number: number, tome: Tome | undefined, hs = false): string {
  const prefix = hs ? `HS ${number}` : `Tome ${number}`;
  if (!tome) return `${prefix} — manquant`;

  const states: string[] = [];
  if (tome.bought) states.push("acheté");
  if (tome.onNas && !tome.bought) states.push("sur NAS");
  if (tome.read) states.push("lu");
  if (states.length === 0) return `${prefix} — en base`;

  return `${prefix} — ${states.join(", ")}`;
}

export default function CollectionMap({
  latestPublishedIssue,
  onSelectTome,
  tomes,
}: CollectionMapProps) {
  const tomeMap = useMemo(() => buildTomeMap(tomes), [tomes]);
  const hsTomes = useMemo(() => tomes.filter((t) => t.isHorsSerie), [tomes]);

  if (latestPublishedIssue === null) return null;

  const regularTomes = tomes.filter((t) => !t.isHorsSerie);
  const boughtCount = countCoveredTomes(regularTomes, (t) => t.bought);
  const readCount = countCoveredTomes(regularTomes, (t) => t.read);
  const ariaLabel = `Carte de collection : ${boughtCount} acheté${boughtCount > 1 ? "s" : ""}, ${readCount} lu${readCount > 1 ? "s" : ""} sur ${latestPublishedIssue} parus`;

  const cells = Array.from({ length: latestPublishedIssue }, (_, i) => i + 1);

  return (
    <div className="space-y-4">
      <div
        aria-label={ariaLabel}
        className="grid grid-cols-[repeat(auto-fill,minmax(2.25rem,1fr))] gap-1.5"
        role="img"
      >
        {cells.map((n) => {
          const tome = tomeMap.get(n);
          return (
            <button
              className={cellClasses(tome)}
              key={n}
              onClick={() => onSelectTome?.(n, tome, false)}
              title={cellTitle(n, tome)}
              type="button"
            >
              {tome?.read ? (
                <Check className="h-3.5 w-3.5" strokeWidth={3} />
              ) : (
                n
              )}
            </button>
          );
        })}
      </div>

      {hsTomes.length > 0 && (
        <>
          <p className="text-xs font-medium text-text-muted">Hors-série</p>
          <div className="grid grid-cols-[repeat(auto-fill,minmax(2.25rem,1fr))] gap-1.5">
            {hsTomes.map((tome) => (
              <button
                className={cellClasses(tome)}
                key={tome.id}
                onClick={() => onSelectTome?.(tome.number, tome, true)}
                title={cellTitle(tome.number, tome, true)}
                type="button"
              >
                {tome.read ? (
                  <Check className="h-3.5 w-3.5" strokeWidth={3} />
                ) : (
                  `HS${tome.number}`
                )}
              </button>
            ))}
          </div>
        </>
      )}
    </div>
  );
}
