import {
  ArrowDown,
  ArrowUp,
  ArrowUpDown,
  ChevronDown,
  LayoutGrid,
  Table2,
} from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";
import CollectionMap from "./CollectionMap";
import SyncPendingIndicator from "./SyncPendingIndicator";
import type { Tome } from "../types/api";
import { countCoveredTomes } from "../utils/tomeUtils";

export type BooleanField = "bought" | "onNas" | "read";
export type SortKey = "number" | "title" | BooleanField;
export type SortDirection = "asc" | "desc";

const FIELD_LABELS: Record<BooleanField, string> = {
  bought: "acheté",
  onNas: "NAS",
  read: "lu",
};

function HeaderCheckbox({
  field,
  onChange,
  tomes,
}: {
  field: BooleanField;
  onChange: () => void;
  tomes: Tome[];
}) {
  const ref = useRef<HTMLInputElement>(null);
  const checkedCount = tomes.filter((t) => t[field]).length;
  const allChecked = checkedCount === tomes.length && tomes.length > 0;
  const someChecked = checkedCount > 0 && !allChecked;

  useEffect(() => {
    if (ref.current) {
      ref.current.indeterminate = someChecked;
    }
  }, [someChecked]);

  return (
    <input
      aria-label={`Tout cocher ${FIELD_LABELS[field]}`}
      checked={allChecked}
      className="h-4 w-4 cursor-pointer accent-primary-600"
      onChange={onChange}
      ref={ref}
      type="checkbox"
    />
  );
}

function SortIcon({
  active,
  direction,
}: {
  active: boolean;
  direction: SortDirection;
}) {
  if (!active) return <ArrowUpDown className="h-3.5 w-3.5 text-text-muted" />;
  return direction === "asc" ? (
    <ArrowUp className="h-3.5 w-3.5" />
  ) : (
    <ArrowDown className="h-3.5 w-3.5" />
  );
}

export interface VolumeMatrixAccordionProps {
  latestPublishedIssue: number | null;
  onSelectMapTome?: (
    tomeNumber: number,
    tome?: Tome,
    isHorsSerie?: boolean,
  ) => void;
  onSort: (key: SortKey) => void;
  onToggleAllTomes: (field: BooleanField) => void;
  onToggleTome: (tome: Tome, field: BooleanField) => void;
  onTomeViewChange: (mode: "map" | "table") => void;
  sort: { direction: SortDirection; key: SortKey };
  sortedTomes: Tome[];
  tomes: Tome[];
  tomeView: "map" | "table";
}

export default function VolumeMatrixAccordion({
  latestPublishedIssue,
  onSelectMapTome,
  onSort,
  onToggleAllTomes,
  onToggleTome,
  onTomeViewChange,
  sort,
  sortedTomes,
  tomes,
  tomeView,
}: VolumeMatrixAccordionProps) {
  const isLongSeries = Math.max(latestPublishedIssue ?? 0, tomes.length) > 12;
  const [isOpen, setIsOpen] = useState(() => !isLongSeries);

  const { boughtCount, missingBuy, missingNas, onNasCount } = useMemo(() => {
    const covered = countCoveredTomes(tomes);
    const published = latestPublishedIssue ?? 0;
    const progressTotal = Math.max(published, covered);
    const bought = countCoveredTomes(tomes, (t) => t.bought);
    const onNas = countCoveredTomes(tomes, (t) => t.onNas);
    return {
      boughtCount: bought,
      missingBuy: Math.max(0, progressTotal - bought),
      missingNas: Math.max(0, progressTotal - onNas),
      onNasCount: onNas,
    };
  }, [tomes, latestPublishedIssue]);

  return (
    <details
      className="group rounded-2xl border border-surface-border bg-surface-secondary/40 shadow-xs overflow-hidden dark:border-white/10 dark:bg-surface-elevated/30"
      onToggle={(e) => setIsOpen(e.currentTarget.open)}
      open={isOpen}
    >
      <summary className="flex cursor-pointer items-center justify-between px-4 py-3.5 transition hover:bg-neutral-500/5">
        <div className="flex items-center gap-2.5">
          <div className="flex h-7 w-7 items-center justify-center rounded-lg bg-amber-500/15 text-amber-600 dark:text-amber-400">
            <LayoutGrid className="h-4 w-4" />
          </div>
          <div>
            <div className="flex items-center gap-2">
              <h3 className="font-serif text-sm font-bold text-text-primary">
                Matrice des volumes
              </h3>
              <span className="text-xs text-text-muted font-medium">
                Tomes ({tomes.length})
              </span>
            </div>
            <p className="font-mono text-[10px] text-text-muted">
              {tomes.length} tomes · {boughtCount} achetés
              {missingBuy > 0 ? ` (${missingBuy} à acheter)` : ""} · {onNasCount}{" "}
              sur NAS{missingNas > 0 ? ` (${missingNas} à DL)` : ""}
            </p>
          </div>
        </div>

        <div className="flex items-center gap-2">
          <ChevronDown className="h-4 w-4 text-text-muted transition-transform group-open:rotate-180" />
        </div>
      </summary>

      <div className="space-y-3 border-t border-surface-border px-4 pb-4 pt-3 dark:border-white/10">
        {/* Controls: Legend & View Switch */}
        <div className="flex flex-wrap items-center justify-between gap-2 text-[11px] text-text-muted">
          <div className="flex flex-wrap items-center gap-2.5">
            <span className="flex items-center gap-1">
              <span className="h-2.5 w-2.5 rounded-sm bg-emerald-500" /> Acheté
            </span>
            <span className="flex items-center gap-1">
              <span className="h-2.5 w-2.5 rounded-sm border-2 border-blue-500" />{" "}
              NAS
            </span>
            <span className="flex items-center gap-1">
              <span className="flex h-2.5 w-2.5 items-center justify-center rounded-sm bg-amber-500 text-[8px] font-black text-black">
                ✓
              </span>{" "}
              Lu
            </span>
            <span className="flex items-center gap-1">
              <span className="h-2.5 w-2.5 rounded-sm border border-dashed border-amber-500" />{" "}
              Manque
            </span>
          </div>

          <div className="flex rounded-lg border border-surface-border p-0.5 dark:border-white/10">
            <button
              aria-label="Vue carte"
              className={`rounded-md px-2 py-1 transition ${
                tomeView === "map"
                  ? "bg-primary-100 text-primary-700 dark:bg-primary-950/40 dark:text-primary-400"
                  : "text-text-muted hover:text-text-secondary"
              }`}
              onClick={() => onTomeViewChange("map")}
              type="button"
            >
              <LayoutGrid className="h-4 w-4" />
            </button>
            <button
              aria-label="Vue tableau"
              className={`rounded-md px-2 py-1 transition ${
                tomeView === "table"
                  ? "bg-primary-100 text-primary-700 dark:bg-primary-950/40 dark:text-primary-400"
                  : "text-text-muted hover:text-text-secondary"
              }`}
              onClick={() => onTomeViewChange("table")}
              type="button"
            >
              <Table2 className="h-4 w-4" />
            </button>
          </div>
        </div>

        {/* View Mode Content */}
        {tomeView === "map" && (
          <CollectionMap
            latestPublishedIssue={latestPublishedIssue}
            onSelectTome={onSelectMapTome}
            tomes={tomes}
          />
        )}

        {tomeView === "table" && (
          <div className="overflow-x-auto rounded-xl border border-surface-border dark:border-white/10">
            <table className="w-full text-sm">
              <thead className="bg-surface-elevated dark:bg-surface-elevated/50">
                <tr>
                  <th className="px-4 py-2 text-left font-medium text-text-secondary">
                    <button
                      className="inline-flex items-center gap-1"
                      onClick={() => onSort("number")}
                      type="button"
                    >
                      #
                      <SortIcon
                        active={sort.key === "number"}
                        direction={sort.direction}
                      />
                    </button>
                  </th>
                  <th className="px-4 py-2 text-left font-medium text-text-secondary">
                    <button
                      className="inline-flex items-center gap-1"
                      onClick={() => onSort("title")}
                      type="button"
                    >
                      Titre
                      <SortIcon
                        active={sort.key === "title"}
                        direction={sort.direction}
                      />
                    </button>
                  </th>
                  {(["bought", "read", "onNas"] as const).map((field) => (
                    <th
                      className="px-4 py-2 text-center font-medium text-text-secondary"
                      key={field}
                    >
                      <div className="flex flex-col items-center gap-1">
                        <button
                          className="inline-flex items-center gap-1"
                          onClick={() => onSort(field)}
                          type="button"
                        >
                          <span>
                            {field === "bought"
                              ? "Acheté"
                              : field === "read"
                                ? "Lu"
                                : "NAS"}
                          </span>
                          <SortIcon
                            active={sort.key === field}
                            direction={sort.direction}
                          />
                        </button>
                        <HeaderCheckbox
                          field={field}
                          onChange={() => onToggleAllTomes(field)}
                          tomes={tomes}
                        />
                      </div>
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-surface-border dark:divide-white/5">
                {sortedTomes.map((tome, index) => (
                  <tr
                    className={`transition-colors hover:bg-surface-tertiary/50 dark:hover:bg-primary-950/20 ${
                      index % 2 === 1
                        ? "bg-surface-secondary/50 dark:bg-surface-elevated/30"
                        : ""
                    }`}
                    key={tome.id}
                  >
                    <td className="px-4 py-2 font-medium text-text-primary">
                      {tome._syncPending && (
                        <SyncPendingIndicator className="mr-1" />
                      )}
                      {tome.isHorsSerie ? "HS " : ""}
                      {tome.tomeEnd
                        ? `${tome.number}-${tome.tomeEnd}`
                        : tome.number}
                    </td>
                    <td className="px-4 py-2 text-text-secondary">
                      {tome.title ?? "\u2014"}
                    </td>
                    {(["bought", "read", "onNas"] as const).map((field) => (
                      <td className="px-4 py-2 text-center" key={field}>
                        <label className="inline-flex min-h-[44px] min-w-[44px] cursor-pointer items-center justify-center">
                          <input
                            aria-label={`Tome ${
                              tome.tomeEnd
                                ? `${tome.number}-${tome.tomeEnd}`
                                : tome.number
                            } ${
                              field === "bought"
                                ? "acheté"
                                : field === "read"
                                  ? "lu"
                                  : "NAS"
                            }`}
                            checked={tome[field]}
                            className="h-5 w-5 cursor-pointer accent-primary-600"
                            onChange={() => onToggleTome(tome, field)}
                            type="checkbox"
                          />
                        </label>
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </details>
  );
}
