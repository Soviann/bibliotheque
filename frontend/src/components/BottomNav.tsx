import { Heart, Home, Plus, Trash2 } from "lucide-react";
import { NavLink } from "react-router-dom";

const tabs = [
  { activeColor: "border-primary-500 text-primary-600", icon: Home, label: "Accueil", to: "/" },
  { activeColor: "border-pink-500 text-pink-600", icon: Heart, label: "Wishlist", to: "/wishlist" },
  { activeColor: "border-emerald-500 text-emerald-600", icon: Plus, label: "Ajouter", to: "/comic/new" },
  { activeColor: "border-amber-500 text-amber-600", icon: Trash2, label: "Corbeille", to: "/trash" },
];

export default function BottomNav() {
  return (
    <nav className="fixed bottom-0 left-0 right-0 z-50 mx-auto border-t border-surface-border bg-surface-primary pb-safe lg:max-w-4xl lg:left-1/2 lg:-translate-x-1/2 lg:rounded-t-xl lg:border-x">
      <div className="flex items-end justify-around">
        {tabs.map(({ activeColor, icon: Icon, label, to }) => (
          <NavLink
            className={({ isActive }) =>
              `flex flex-col items-center gap-0.5 px-3 pt-1 pb-2 text-xs font-medium border-t-2 transition-colors ${
                isActive
                  ? activeColor
                  : "border-transparent text-text-secondary"
              }`
            }
            key={to}
            to={to}
          >
            <Icon className="h-5 w-5 -mt-3.5" />
            {label}
          </NavLink>
        ))}
      </div>
    </nav>
  );
}
