import {
  formInputCompactClassName,
  formLabelClassName,
} from "../styles/formStyles";

interface DatePartialSelectProps {
  label?: string;
  onChange: (value: string) => void;
  value: string;
}

const currentYear = new Date().getFullYear();
const years = Array.from(
  { length: currentYear - 1900 + 2 },
  (_, i) => currentYear + 1 - i,
);
const months = Array.from({ length: 12 }, (_, i) =>
  String(i + 1).padStart(2, "0"),
);

function daysInMonth(year: number, month: number): number {
  return new Date(year, month, 0).getDate();
}

export default function DatePartialSelect({
  label,
  onChange,
  value,
}: DatePartialSelectProps) {
  const parts = value.split("-");
  const year = parts[0] || "";
  const month = parts[1] || "";
  const day = parts[2] || "";

  const dayCount =
    year && month ? daysInMonth(Number(year), Number(month)) : 31;
  const days = Array.from({ length: dayCount }, (_, i) =>
    String(i + 1).padStart(2, "0"),
  );

  const buildValue = (y: string, m: string, d: string): string => {
    if (!y) return "";
    if (!m) return y;
    if (!d) return `${y}-${m}`;
    return `${y}-${m}-${d}`;
  };

  return (
    <div>
      {label && <span className={formLabelClassName}>{label}</span>}
      <div className="flex gap-2">
        <select
          aria-label="Année"
          className={`${formInputCompactClassName} w-24`}
          onChange={(e) => {
            const y = e.target.value;
            onChange(buildValue(y, y ? month : "", ""));
          }}
          value={year}
        >
          <option value="">Année</option>
          {years.map((y) => (
            <option key={y} value={String(y)}>
              {y}
            </option>
          ))}
        </select>
        <select
          aria-label="Mois"
          className={`${formInputCompactClassName} w-20`}
          disabled={!year}
          onChange={(e) => {
            const m = e.target.value;
            onChange(buildValue(year, m, m ? day : ""));
          }}
          value={month}
        >
          <option value="">Mois</option>
          {months.map((m) => (
            <option key={m} value={m}>
              {m}
            </option>
          ))}
        </select>
        <select
          aria-label="Jour"
          className={`${formInputCompactClassName} w-20`}
          disabled={!month}
          onChange={(e) => onChange(buildValue(year, month, e.target.value))}
          value={day}
        >
          <option value="">Jour</option>
          {days.map((d) => (
            <option key={d} value={d}>
              {d}
            </option>
          ))}
        </select>
      </div>
    </div>
  );
}
