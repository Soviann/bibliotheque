import { screen } from "@testing-library/react";
import BottomNav from "../../../components/BottomNav";
import { renderWithProviders } from "../../helpers/test-utils";

describe("BottomNav", () => {
  it("renders all navigation links", () => {
    renderWithProviders(<BottomNav />);

    expect(screen.getByText("Collection")).toBeInTheDocument();
    expect(screen.getByText("À acheter")).toBeInTheDocument();
    expect(screen.getByText("Ajout")).toBeInTheDocument();
    expect(screen.getByText("Sur NAS")).toBeInTheDocument();
    expect(screen.getByText("Envies")).toBeInTheDocument();
  });

  it("renders correct link targets", () => {
    renderWithProviders(<BottomNav />);

    expect(screen.getByText("Collection").closest("a")).toHaveAttribute(
      "href",
      "/",
    );
    expect(screen.getByText("À acheter").closest("a")).toHaveAttribute(
      "href",
      "/to-buy",
    );
    expect(screen.getByText("Ajout").closest("a")).toHaveAttribute(
      "href",
      "/quick-add",
    );
    expect(screen.getByText("Sur NAS").closest("a")).toHaveAttribute(
      "href",
      "/to-download",
    );
    expect(screen.getByText("Envies").closest("a")).toHaveAttribute(
      "href",
      "/?status=wishlist",
    );
  });

  it("highlights À acheter tab on /to-buy", () => {
    renderWithProviders(<BottomNav />, { initialEntries: ["/to-buy"] });

    const toBuyLink = screen.getByText("À acheter").closest("a");
    expect(toBuyLink?.className).toContain("text-accent-sage");
  });

  it("highlights Collection tab on root", () => {
    renderWithProviders(<BottomNav />, { initialEntries: ["/"] });

    const homeLink = screen.getByText("Collection").closest("a");
    expect(homeLink?.className).toContain("text-primary-600");
  });

  it("does not highlight Collection tab on /to-buy", () => {
    renderWithProviders(<BottomNav />, { initialEntries: ["/to-buy"] });

    const homeLink = screen.getByText("Collection").closest("a");
    expect(homeLink?.className).toContain("text-text-muted");
    expect(homeLink?.className).not.toContain("text-primary-600");
  });

  it("highlights Envies tab on /?status=wishlist", () => {
    renderWithProviders(<BottomNav />, {
      initialEntries: ["/?status=wishlist"],
    });

    const wishlistLink = screen.getByText("Envies").closest("a");
    expect(wishlistLink?.className).toContain("text-rose-500");
    expect(wishlistLink).toHaveAttribute("aria-current", "page");
  });

  it("highlights Sur NAS tab on /to-download", () => {
    renderWithProviders(<BottomNav />, { initialEntries: ["/to-download"] });

    const nasLink = screen.getByText("Sur NAS").closest("a");
    expect(nasLink?.className).toContain("text-blue-600");
    expect(nasLink).toHaveAttribute("aria-current", "page");
  });

  it("sets aria-current='page' on the active link only", () => {
    renderWithProviders(<BottomNav />, { initialEntries: ["/to-buy"] });

    const toBuyLink = screen.getByText("À acheter").closest("a");
    expect(toBuyLink).toHaveAttribute("aria-current", "page");

    const homeLink = screen.getByText("Collection").closest("a");
    expect(homeLink).not.toHaveAttribute("aria-current");

    const addLink = screen.getByText("Ajout").closest("a");
    expect(addLink).not.toHaveAttribute("aria-current");
  });
});
