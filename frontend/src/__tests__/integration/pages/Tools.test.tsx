import { screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";
import Tools from "../../../pages/Tools";
import { renderWithProviders } from "../../helpers/test-utils";

vi.mock("idb-keyval", () => ({
  del: vi.fn().mockResolvedValue(undefined),
}));

describe("Tools", () => {
  it("renders page title", () => {
    renderWithProviders(<Tools />);

    expect(screen.getByText("Outils")).toBeInTheDocument();
  });

  it("renders 4 tool cards", () => {
    renderWithProviders(<Tools />);

    expect(screen.getByText("Fusion de series")).toBeInTheDocument();
    expect(screen.getByText("Import Excel")).toBeInTheDocument();
    expect(screen.getByText("Lookup metadonnees")).toBeInTheDocument();
    expect(screen.getByText("Purge corbeille")).toBeInTheDocument();
  });

  it("cards link to correct routes", () => {
    renderWithProviders(<Tools />);

    const links = screen.getAllByRole("link");
    const hrefs = links.map((link) => link.getAttribute("href"));

    expect(hrefs).toContain("/tools/merge-series");
    expect(hrefs).toContain("/tools/import");
    expect(hrefs).toContain("/tools/lookup");
    expect(hrefs).toContain("/tools/purge");
  });

  it("renders cache clear button", () => {
    renderWithProviders(<Tools />);

    expect(screen.getByText("Vider le cache")).toBeInTheDocument();
  });

  it("clears cache when clicking clear button", async () => {
    const user = userEvent.setup();
    const { del } = await import("idb-keyval");

    renderWithProviders(<Tools />);

    await user.click(screen.getByText("Vider le cache"));

    expect(del).toHaveBeenCalledWith("bibliotheque-query-cache");
  });
});
