import { screen } from "@testing-library/react";
import BottomNav from "../../../components/BottomNav";
import { renderWithProviders } from "../../helpers/test-utils";

describe("BottomNav", () => {
  it("renders all navigation links", () => {
    renderWithProviders(<BottomNav />);

    expect(screen.getByText("Accueil")).toBeInTheDocument();
    expect(screen.getByText("À acheter")).toBeInTheDocument();
    expect(screen.getByText("Ajouter")).toBeInTheDocument();
    expect(screen.getByText("Corbeille")).toBeInTheDocument();
  });

  it("renders correct link targets", () => {
    renderWithProviders(<BottomNav />);

    expect(screen.getByText("Accueil").closest("a")).toHaveAttribute("href", "/");
    expect(screen.getByText("À acheter").closest("a")).toHaveAttribute("href", "/to-buy");
    expect(screen.getByText("Ajouter").closest("a")).toHaveAttribute("href", "/quick-add");
    expect(screen.getByText("Corbeille").closest("a")).toHaveAttribute("href", "/trash");
  });

  it("highlights À acheter tab on /to-buy", () => {
    renderWithProviders(<BottomNav />, { initialEntries: ["/to-buy"] });

    const toBuyLink = screen.getByText("À acheter").closest("a");
    expect(toBuyLink?.className).toContain("text-accent-sage");
  });

  it("highlights Home tab on root", () => {
    renderWithProviders(<BottomNav />, { initialEntries: ["/"] });

    const homeLink = screen.getByText("Accueil").closest("a");
    expect(homeLink?.className).toContain("text-primary-600");
  });

  it("does not highlight Home tab on /to-buy", () => {
    renderWithProviders(<BottomNav />, { initialEntries: ["/to-buy"] });

    const homeLink = screen.getByText("Accueil").closest("a");
    expect(homeLink?.className).toContain("text-text-muted");
    expect(homeLink?.className).not.toContain("text-primary-600");
  });

  it("sets aria-current='page' on the active link only", () => {
    renderWithProviders(<BottomNav />, { initialEntries: ["/to-buy"] });

    const toBuyLink = screen.getByText("À acheter").closest("a");
    expect(toBuyLink).toHaveAttribute("aria-current", "page");

    const homeLink = screen.getByText("Accueil").closest("a");
    expect(homeLink).not.toHaveAttribute("aria-current");

    const addLink = screen.getByText("Ajouter").closest("a");
    expect(addLink).not.toHaveAttribute("aria-current");
  });
});
