import {
  ComicStatus,
  ComicStatusLabel,
  ComicType,
  ComicTypeLabel,
} from "../types/enums";

interface FiltersProps {
  onStatusChange: (status: string) => void;
  onTypeChange: (type: string) => void;
  status: string;
  type: string;
}

export default function Filters({
  onStatusChange,
  onTypeChange,
  status,
  type,
}: FiltersProps) {
  return (
    <div className="flex flex-wrap items-center gap-3">
      <select
        className="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-700"
        onChange={(e) => onTypeChange(e.target.value)}
        value={type}
      >
        <option value="">Tous les types</option>
        {Object.entries(ComicType).map(([key, value]) => (
          <option key={key} value={value}>
            {ComicTypeLabel[value]}
          </option>
        ))}
      </select>

      <select
        className="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-700"
        onChange={(e) => onStatusChange(e.target.value)}
        value={status}
      >
        <option value="">Tous les statuts</option>
        {Object.entries(ComicStatus).map(([key, value]) => (
          <option key={key} value={value}>
            {ComicStatusLabel[value]}
          </option>
        ))}
      </select>
    </div>
  );
}
