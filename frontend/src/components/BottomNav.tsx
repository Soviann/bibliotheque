import { Heart, Home, Plus, Trash2 } from "lucide-react";
import type { ComponentType } from "react";
import { Link, useLocation, useSearchParams } from "react-router-dom";

interface Tab {
  activeColor: string;
  icon: ComponentType<{ className?: string }>;
  isActive: (pathname: string, status: string) => boolean;
  label: string;
  to: string;
}

const tabs: Tab[] = [
  {
    activeColor: "border-primary-500 text-primary-600",
    icon: Home,
    isActive: (pathname, status) => pathname === "/" && status !== "wishlist",
    label: "Accueil",
    to: "/",
  },
  {
    activeColor: "border-pink-500 text-pink-600",
    icon: Heart,
    isActive: (pathname, status) => pathname === "/" && status === "wishlist",
    label: "Wishlist",
    to: "/?status=wishlist",
  },
  {
    activeColor: "border-emerald-500 text-emerald-600",
    icon: Plus,
    isActive: (pathname) => pathname === "/comic/new",
    label: "Ajouter",
    to: "/comic/new",
  },
  {
    activeColor: "border-amber-500 text-amber-600",
    icon: Trash2,
    isActive: (pathname) => pathname === "/trash",
    label: "Corbeille",
    to: "/trash",
  },
];

export default function BottomNav() {
  const { pathname } = useLocation();
  const [searchParams] = useSearchParams();
  const status = searchParams.get("status") ?? "";

  return (
    <nav className="fixed bottom-0 left-0 right-0 z-50 mx-auto h-[var(--bottom-nav-h)] border-t border-surface-border bg-surface-primary pb-safe lg:max-w-4xl lg:left-1/2 lg:-translate-x-1/2 lg:rounded-t-xl lg:border-x">
      <div className="flex h-full items-center justify-around">
        {tabs.map(({ activeColor, icon: Icon, isActive, label, to }) => {
          const active = isActive(pathname, status);
          return (
            <Link
              className={`flex flex-col items-center justify-center gap-0.5 px-3 text-xs font-medium transition-colors ${
                active ? activeColor : "text-text-secondary"
              }`}
              key={label}
              to={to}
            >
              <Icon className="h-5 w-5" />
              {label}
            </Link>
          );
        })}
      </div>
    </nav>
  );
}
