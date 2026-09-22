import { screen } from "@testing-library/react";
import BottomNav from "../../../components/BottomNav";
import { renderWithProviders } from "../../helpers/test-utils";

describe("BottomNav", () => {
  it("renders all 4 navigation links", () => {
    renderWithProviders(<BottomNav />);

    expect(screen.getByText("Collection")).toBeInTheDocument();
    expect(screen.getByText("Recherche")).toBeInTheDocument();
    expect(screen.getByText("Scanner")).toBeInTheDocument();
    expect(screen.getByText("Outils")).toBeInTheDocument();
  });

  it("renders correct link targets", () => {
    renderWithProviders(<BottomNav />);

    expect(screen.getByText("Collection").closest("a")).toHaveAttribute(
      "href",
      "/",
    );
    expect(screen.getByText("Recherche").closest("a")).toHaveAttribute(
      "href",
      "/search",
    );
    expect(screen.getByText("Scanner").closest("a")).toHaveAttribute(
      "href",
      "/quick-add",
    );
    expect(screen.getByText("Outils").closest("a")).toHaveAttribute(
      "href",
      "/tools",
    );
  });

  it("highlights Collection tab on root", () => {
    renderWithProviders(<BottomNav />, { initialEntries: ["/"] });

    const homeLink = screen.getByText("Collection").closest("a");
    expect(homeLink?.className).toContain("text-amber-600");
    expect(homeLink).toHaveAttribute("aria-current", "page");
  });

  it("highlights Recherche tab on /search", () => {
    renderWithProviders(<BottomNav />, { initialEntries: ["/search"] });

    const searchLink = screen.getByText("Recherche").closest("a");
    expect(searchLink?.className).toContain("text-amber-600");
    expect(searchLink).toHaveAttribute("aria-current", "page");
  });

  it("highlights Scanner tab on /quick-add", () => {
    renderWithProviders(<BottomNav />, { initialEntries: ["/quick-add"] });

    const scanLink = screen.getByText("Scanner").closest("a");
    expect(scanLink?.className).toContain("text-amber-600");
    expect(scanLink).toHaveAttribute("aria-current", "page");
  });

  it("highlights Outils tab on /tools", () => {
    renderWithProviders(<BottomNav />, { initialEntries: ["/tools"] });

    const toolsLink = screen.getByText("Outils").closest("a");
    expect(toolsLink?.className).toContain("text-amber-600");
    expect(toolsLink).toHaveAttribute("aria-current", "page");
  });

  it("sets aria-current='page' on the active link only", () => {
    renderWithProviders(<BottomNav />, { initialEntries: ["/search"] });

    const searchLink = screen.getByText("Recherche").closest("a");
    expect(searchLink).toHaveAttribute("aria-current", "page");

    const homeLink = screen.getByText("Collection").closest("a");
    expect(homeLink).not.toHaveAttribute("aria-current");

    const scanLink = screen.getByText("Scanner").closest("a");
    expect(scanLink).not.toHaveAttribute("aria-current");

    const toolsLink = screen.getByText("Outils").closest("a");
    expect(toolsLink).not.toHaveAttribute("aria-current");
  });
});
