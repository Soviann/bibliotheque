import { screen } from "@testing-library/react";
import BottomNav from "../../../components/BottomNav";
import { renderWithProviders } from "../../helpers/test-utils";

describe("BottomNav", () => {
  it("renders all navigation links", () => {
    renderWithProviders(<BottomNav />);

    expect(screen.getByText("Accueil")).toBeInTheDocument();
    expect(screen.getByText("Wishlist")).toBeInTheDocument();
    expect(screen.getByText("Ajouter")).toBeInTheDocument();
    expect(screen.getByText("Corbeille")).toBeInTheDocument();
  });

  it("renders correct link targets", () => {
    renderWithProviders(<BottomNav />);

    expect(screen.getByText("Accueil").closest("a")).toHaveAttribute("href", "/");
    expect(screen.getByText("Wishlist").closest("a")).toHaveAttribute("href", "/?status=wishlist");
    expect(screen.getByText("Ajouter").closest("a")).toHaveAttribute("href", "/comic/new");
    expect(screen.getByText("Corbeille").closest("a")).toHaveAttribute("href", "/trash");
  });

  it("highlights Wishlist tab when status=wishlist in URL", () => {
    renderWithProviders(<BottomNav />, { initialEntries: ["/?status=wishlist"] });

    const wishlistLink = screen.getByText("Wishlist").closest("a");
    expect(wishlistLink?.className).toContain("border-pink-500");
  });

  it("does not highlight Home tab when status=wishlist", () => {
    renderWithProviders(<BottomNav />, { initialEntries: ["/?status=wishlist"] });

    const homeLink = screen.getByText("Accueil").closest("a");
    expect(homeLink?.className).toContain("text-text-secondary");
    expect(homeLink?.className).not.toContain("border-primary-500");
  });

  it("highlights Home tab on root without status param", () => {
    renderWithProviders(<BottomNav />, { initialEntries: ["/"] });

    const homeLink = screen.getByText("Accueil").closest("a");
    expect(homeLink?.className).toContain("border-primary-500");
  });

  it("does not highlight Wishlist tab on root without status param", () => {
    renderWithProviders(<BottomNav />, { initialEntries: ["/"] });

    const wishlistLink = screen.getByText("Wishlist").closest("a");
    expect(wishlistLink?.className).toContain("text-text-secondary");
    expect(wishlistLink?.className).not.toContain("border-pink-500");
  });
});
