import { Download, ShoppingCart } from "lucide-react";
import { NavLink } from "react-router-dom";

const tabs = [
  { icon: ShoppingCart, label: "À acheter", to: "/to-buy" },
  { icon: Download, label: "À télécharger", to: "/to-download" },
] as const;

export default function AcquisitionTabs() {
  return (
    <nav
      aria-label="Mode d'acquisition"
      className="inline-flex items-center gap-1 rounded-xl border border-surface-border bg-surface-elevated/50 p-1 dark:border-white/10 dark:bg-white/[0.03]"
    >
      {tabs.map(({ icon: Icon, label, to }) => (
        <NavLink
          className={({ isActive }) =>
            `flex items-center gap-1.5 rounded-lg px-3 py-1.5 font-display text-sm font-medium transition-colors ${
              isActive
                ? "bg-surface-primary text-text-primary shadow-sm dark:bg-white/10"
                : "text-text-muted hover:text-text-primary"
            }`
          }
          end
          key={to}
          to={to}
          viewTransition
        >
          <Icon className="h-4 w-4" strokeWidth={1.5} />
          {label}
        </NavLink>
      ))}
    </nav>
  );
}
