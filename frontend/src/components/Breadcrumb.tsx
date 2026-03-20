import { Link } from "react-router-dom";

interface BreadcrumbItem {
  href?: string;
  label: string;
}

interface BreadcrumbProps {
  items: BreadcrumbItem[];
}

export default function Breadcrumb({ items }: BreadcrumbProps) {
  return (
    <nav aria-label="Fil d'Ariane" className="mb-1 text-sm text-text-muted">
      <ol className="flex items-center gap-1.5">
        {items.map((item, index) => {
          const isLast = index === items.length - 1;
          return (
            <li className="flex items-center gap-1.5" key={item.label}>
              {index > 0 && <span aria-hidden="true">/</span>}
              {item.href && !isLast ? (
                <Link className="hover:text-text-secondary hover:underline" to={item.href} viewTransition>
                  {item.label}
                </Link>
              ) : (
                <span aria-current={isLast ? "page" : undefined}>{item.label}</span>
              )}
            </li>
          );
        })}
      </ol>
    </nav>
  );
}
