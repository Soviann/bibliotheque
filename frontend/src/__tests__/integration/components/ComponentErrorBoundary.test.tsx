import { screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import ComponentErrorBoundary from "../../../components/ComponentErrorBoundary";
import { renderWithProviders } from "../../helpers/test-utils";

function BrokenComponent(): never {
  throw new Error("Composant cassé");
}

function WorkingComponent() {
  return <div>Contenu fonctionnel</div>;
}

describe("ComponentErrorBoundary", () => {
  beforeEach(() => {
    vi.spyOn(console, "error").mockImplementation(() => {});
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it("renders children when no error", () => {
    renderWithProviders(
      <ComponentErrorBoundary label="tomes">
        <WorkingComponent />
      </ComponentErrorBoundary>,
    );

    expect(screen.getByText("Contenu fonctionnel")).toBeInTheDocument();
  });

  it("renders contextual error fallback when child throws", () => {
    renderWithProviders(
      <ComponentErrorBoundary label="les tomes">
        <BrokenComponent />
      </ComponentErrorBoundary>,
    );

    expect(screen.getByText("Impossible de charger les tomes")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Réessayer" })).toBeInTheDocument();
  });

  it("renders error message in fallback", () => {
    renderWithProviders(
      <ComponentErrorBoundary label="la recherche">
        <BrokenComponent />
      </ComponentErrorBoundary>,
    );

    expect(screen.getByText("Composant cassé")).toBeInTheDocument();
  });

  it("resets error boundary on retry click", async () => {
    const user = userEvent.setup();
    let shouldThrow = true;

    function ConditionallyBroken() {
      if (shouldThrow) throw new Error("Erreur temporaire");
      return <div>Récupéré</div>;
    }

    renderWithProviders(
      <ComponentErrorBoundary label="la grille">
        <ConditionallyBroken />
      </ComponentErrorBoundary>,
    );

    expect(screen.getByText("Impossible de charger la grille")).toBeInTheDocument();

    shouldThrow = false;
    await user.click(screen.getByRole("button", { name: "Réessayer" }));

    expect(screen.getByText("Récupéré")).toBeInTheDocument();
  });

  it("uses compact inline layout, not full-page", () => {
    renderWithProviders(
      <ComponentErrorBoundary label="les tomes">
        <BrokenComponent />
      </ComponentErrorBoundary>,
    );

    const container = screen.getByTestId("component-error-boundary");
    expect(container).toBeInTheDocument();
    // Should NOT contain the full-page "Une erreur est survenue" title
    expect(screen.queryByText("Une erreur est survenue")).not.toBeInTheDocument();
  });
});
