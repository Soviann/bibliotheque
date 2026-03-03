import { screen } from "@testing-library/react";
import { BookOpen, Heart, Search, Trash2 } from "lucide-react";
import EmptyState from "../../../components/EmptyState";
import { renderWithProviders } from "../../helpers/test-utils";

describe("EmptyState", () => {
  it("renders icon, title and description", () => {
    renderWithProviders(
      <EmptyState
        description="Les séries supprimées apparaîtront ici"
        icon={Trash2}
        title="La corbeille est vide"
      />,
    );

    expect(screen.getByText("La corbeille est vide")).toBeInTheDocument();
    expect(screen.getByText("Les séries supprimées apparaîtront ici")).toBeInTheDocument();
    expect(screen.getByTestId("empty-state-icon")).toBeInTheDocument();
  });

  it("renders without description when not provided", () => {
    renderWithProviders(
      <EmptyState icon={Search} title="Aucun résultat" />,
    );

    expect(screen.getByText("Aucun résultat")).toBeInTheDocument();
    expect(screen.queryByTestId("empty-state-description")).not.toBeInTheDocument();
  });

  it("renders a link CTA when actionHref is provided", () => {
    renderWithProviders(
      <EmptyState
        actionHref="/comic/new"
        actionLabel="Ajouter une série"
        icon={BookOpen}
        title="Votre bibliothèque est vide"
      />,
    );

    const link = screen.getByRole("link", { name: "Ajouter une série" });
    expect(link).toBeInTheDocument();
    expect(link).toHaveAttribute("href", "/comic/new");
  });

  it("renders a button CTA when onAction is provided", () => {
    const onAction = vi.fn();
    renderWithProviders(
      <EmptyState
        actionLabel="Réinitialiser les filtres"
        icon={Search}
        onAction={onAction}
        title="Aucune série avec ces filtres"
      />,
    );

    expect(screen.getByRole("button", { name: "Réinitialiser les filtres" })).toBeInTheDocument();
  });

  it("does not render CTA when no actionLabel is provided", () => {
    renderWithProviders(
      <EmptyState icon={Heart} title="Liste vide" />,
    );

    expect(screen.queryByRole("link")).not.toBeInTheDocument();
    expect(screen.queryByRole("button")).not.toBeInTheDocument();
  });
});
