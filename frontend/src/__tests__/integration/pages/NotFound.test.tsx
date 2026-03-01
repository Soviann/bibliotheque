import { screen } from "@testing-library/react";
import NotFound from "../../../pages/NotFound";
import { renderWithProviders } from "../../helpers/test-utils";

describe("NotFound", () => {
  it("renders 404 message", () => {
    renderWithProviders(<NotFound />);

    expect(screen.getByText("404")).toBeInTheDocument();
    expect(screen.getByText("Page introuvable")).toBeInTheDocument();
  });

  it("has a link back to home", () => {
    renderWithProviders(<NotFound />);

    const link = screen.getByText("Retour à l'accueil");
    expect(link).toBeInTheDocument();
    expect(link.closest("a")).toHaveAttribute("href", "/");
  });
});
