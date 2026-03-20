import { screen } from "@testing-library/react";
import NotFound from "../../../pages/NotFound";
import { renderWithProviders } from "../../helpers/test-utils";

describe("NotFound", () => {
  it("renders 404 message with themed subtitle", () => {
    renderWithProviders(<NotFound />);

    expect(screen.getByText("404")).toBeInTheDocument();
    expect(screen.getByText("Page introuvable")).toBeInTheDocument();
    expect(screen.getByText(/Cette page semble avoir disparu/)).toBeInTheDocument();
  });

  it("has a link back to home", () => {
    renderWithProviders(<NotFound />);

    const link = screen.getByText("Retour à l'accueil");
    expect(link).toBeInTheDocument();
    expect(link.closest("a")).toHaveAttribute("href", "/");
  });

  it("renders a themed icon", () => {
    renderWithProviders(<NotFound />);

    expect(screen.getByTestId("not-found-icon")).toBeInTheDocument();
  });
});
