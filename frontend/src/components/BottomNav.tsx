import { BookOpen, HardDrive, Heart, Plus, ShoppingCart } from "lucide-react";
import type { ComponentType } from "react";
import { Link, useLocation } from "react-router-dom";

interface Tab {
  color: string;
  dotColor: string;
  icon: ComponentType<{ className?: string; strokeWidth?: number }>;
  isActive: (pathname: string, search: string) => boolean;
  isCenter?: boolean;
  label: string;
  to: string;
}

const tabs: Tab[] = [
  {
    color: "text-primary-600 dark:text-primary-400",
    dotColor: "bg-primary-500",
    icon: BookOpen,
    isActive: (pathname, search) =>
      pathname === "/" && !search.includes("status=wishlist"),
    label: "Collection",
    to: "/",
  },
  {
    color: "text-accent-sage dark:text-accent-sage",
    dotColor: "bg-accent-sage",
    icon: ShoppingCart,
    isActive: (pathname) => pathname === "/to-buy",
    label: "À acheter",
    to: "/to-buy",
  },
  {
    color: "text-white",
    dotColor: "bg-amber-500",
    icon: Plus,
    isActive: (pathname) => pathname === "/quick-add",
    isCenter: true,
    label: "Ajout",
    to: "/quick-add",
  },
  {
    color: "text-blue-600 dark:text-blue-400",
    dotColor: "bg-blue-500",
    icon: HardDrive,
    isActive: (pathname) => pathname === "/to-download",
    label: "Sur NAS",
    to: "/to-download",
  },
  {
    color: "text-rose-500 dark:text-rose-400",
    dotColor: "bg-rose-500",
    icon: Heart,
    isActive: (pathname, search) =>
      pathname === "/" && search.includes("status=wishlist"),
    label: "Envies",
    to: "/?status=wishlist",
  },
];

export default function BottomNav() {
  const { pathname, search } = useLocation();

  return (
    <nav className="grain fixed bottom-0 left-0 right-0 z-50 mx-auto h-[var(--bottom-nav-h)] border-t border-surface-border bg-surface-primary/95 backdrop-blur-md dark:border-white/10 dark:bg-surface-primary/85 dark:backdrop-blur-xl lg:left-1/2 lg:max-w-4xl lg:-translate-x-1/2 lg:rounded-t-xl lg:border-x">
      <div className="flex h-full items-center justify-around">
        {tabs.map(
          ({ color, dotColor, icon: Icon, isActive, isCenter, label, to }) => {
            const active = isActive(pathname, search);

            if (isCenter) {
              return (
                <Link
                  aria-current={active ? "page" : undefined}
                  className="group relative -mt-5 flex flex-col items-center justify-center"
                  key={label}
                  to={to}
                  viewTransition
                >
                  <div className="flex h-11 w-11 items-center justify-center rounded-full border-2 border-surface-primary bg-gradient-to-tr from-amber-600 to-amber-500 text-white shadow-lg shadow-amber-500/25 transition-transform duration-200 group-hover:scale-105 group-active:scale-95 dark:border-[#131929]">
                    <Icon className="h-5 w-5" strokeWidth={2.5} />
                  </div>
                  <span
                    className={`mt-0.5 text-[10px] font-medium transition-colors ${
                      active
                        ? "font-semibold text-amber-600 dark:text-amber-400"
                        : "text-text-muted dark:text-text-secondary"
                    }`}
                  >
                    {label}
                  </span>
                </Link>
              );
            }

            return (
              <Link
                aria-current={active ? "page" : undefined}
                className={`relative flex flex-col items-center justify-center gap-1 px-2.5 text-xs font-medium transition-colors ${
                  active ? color : "text-text-muted dark:text-text-secondary"
                }`}
                key={label}
                to={to}
                viewTransition
              >
                <Icon className="h-5 w-5" strokeWidth={1.5} />
                <span className="text-[10px]">{label}</span>
                {/* Active dot indicator */}
                {active && (
                  <span
                    className={`absolute -bottom-1 h-1 w-1 rounded-full ${dotColor} dark:shadow-[0_0_6px_currentColor]`}
                  />
                )}
              </Link>
            );
          },
        )}
      </div>
    </nav>
  );
}
