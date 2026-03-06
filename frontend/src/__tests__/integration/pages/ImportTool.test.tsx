import { fireEvent, screen, waitFor } from "@testing-library/react";
import { http, HttpResponse } from "msw";
import { describe, expect, it } from "vitest";
import ImportTool from "../../../pages/ImportTool";
import { server } from "../../helpers/server";
import { renderWithProviders } from "../../helpers/test-utils";

const API_BASE = "/api";

describe("ImportTool", () => {
  it("renders the page title and tabs", () => {
    renderWithProviders(<ImportTool />);

    expect(screen.getByText("Import Excel")).toBeInTheDocument();
    expect(screen.getByText("Excel suivi")).toBeInTheDocument();
    expect(screen.getByText("Livres")).toBeInTheDocument();
  });

  it("shows file drop zone on Excel suivi tab", () => {
    renderWithProviders(<ImportTool />);

    expect(screen.getByText(/glisser-deposer/i)).toBeInTheDocument();
  });

  it("disables import button when no file is selected", () => {
    renderWithProviders(<ImportTool />);

    const button = screen.getByRole("button", { name: /simuler/i });
    expect(button).toBeDisabled();
  });

  it("enables import button after file selection", () => {
    renderWithProviders(<ImportTool />);

    const file = new File(["data"], "test.xlsx");
    const input = screen.getByTestId("file-input");
    fireEvent.change(input, { target: { files: [file] } });

    const button = screen.getByRole("button", { name: /simuler/i });
    expect(button).toBeEnabled();
  });

  it("shows results after successful Excel import", async () => {
    server.use(
      http.post(`${API_BASE}/tools/import/excel`, () =>
        HttpResponse.json({
          sheetDetails: { Mangas: { created: 3, tomes: 15, updated: 0 } },
          totalCreated: 3,
          totalTomes: 15,
          totalUpdated: 0,
        }),
      ),
    );

    renderWithProviders(<ImportTool />);

    const file = new File(["data"], "test.xlsx");
    const input = screen.getByTestId("file-input");
    fireEvent.change(input, { target: { files: [file] } });

    const button = screen.getByRole("button", { name: /simuler/i });
    fireEvent.click(button);

    await waitFor(() => {
      expect(screen.getByText("Simulation terminee")).toBeInTheDocument();
    });

    expect(screen.getByText("3")).toBeInTheDocument();
    expect(screen.getByText("15")).toBeInTheDocument();
  });

  it("switches to Books tab and shows results", async () => {
    server.use(
      http.post(`${API_BASE}/tools/import/books`, () =>
        HttpResponse.json({
          created: 5,
          enriched: 2,
          groupCount: 7,
        }),
      ),
    );

    renderWithProviders(<ImportTool />);

    // Switch to Books tab
    fireEvent.click(screen.getByText("Livres"));

    // Select a file
    const file = new File(["data"], "livres.xlsx");
    const input = screen.getByTestId("file-input");
    fireEvent.change(input, { target: { files: [file] } });

    const button = screen.getByRole("button", { name: /simuler/i });
    fireEvent.click(button);

    await waitFor(() => {
      expect(screen.getByText("Simulation terminee")).toBeInTheDocument();
    });

    expect(screen.getByText("5")).toBeInTheDocument();
    expect(screen.getByText("2")).toBeInTheDocument();
  });

  it("changes button label when dry-run is unchecked", () => {
    renderWithProviders(<ImportTool />);

    const file = new File(["data"], "test.xlsx");
    const input = screen.getByTestId("file-input");
    fireEvent.change(input, { target: { files: [file] } });

    // Initially dry-run is checked → "Simuler"
    expect(screen.getByRole("button", { name: /simuler/i })).toBeInTheDocument();

    // Uncheck dry-run
    const checkbox = screen.getByRole("checkbox");
    fireEvent.click(checkbox);

    // Now button says "Importer"
    expect(screen.getByRole("button", { name: /importer/i })).toBeInTheDocument();
  });

  it("shows 'Import termine' when dry-run is off", async () => {
    server.use(
      http.post(`${API_BASE}/tools/import/excel`, () =>
        HttpResponse.json({
          sheetDetails: { BD: { created: 1, tomes: 5, updated: 0 } },
          totalCreated: 1,
          totalTomes: 5,
          totalUpdated: 0,
        }),
      ),
    );

    renderWithProviders(<ImportTool />);

    const file = new File(["data"], "test.xlsx");
    const input = screen.getByTestId("file-input");
    fireEvent.change(input, { target: { files: [file] } });

    // Uncheck dry-run
    fireEvent.click(screen.getByRole("checkbox"));

    fireEvent.click(screen.getByRole("button", { name: /importer/i }));

    await waitFor(() => {
      expect(screen.getByText("Import termine")).toBeInTheDocument();
    });
  });

  it("shows sheet details in Excel results", async () => {
    server.use(
      http.post(`${API_BASE}/tools/import/excel`, () =>
        HttpResponse.json({
          sheetDetails: {
            BD: { created: 2, tomes: 8, updated: 0 },
            Mangas: { created: 4, tomes: 20, updated: 0 },
          },
          totalCreated: 6,
          totalTomes: 28,
          totalUpdated: 0,
        }),
      ),
    );

    renderWithProviders(<ImportTool />);

    const file = new File(["data"], "test.xlsx");
    fireEvent.change(screen.getByTestId("file-input"), {
      target: { files: [file] },
    });
    fireEvent.click(screen.getByRole("button", { name: /simuler/i }));

    await waitFor(() => {
      expect(screen.getByText("Simulation terminee")).toBeInTheDocument();
    });

    // Check sheet details are displayed
    expect(screen.getByText(/BD:/)).toBeInTheDocument();
    expect(screen.getByText(/Mangas:/)).toBeInTheDocument();
  });

  it("shows books-specific fields (groupes, crees, enrichis)", async () => {
    server.use(
      http.post(`${API_BASE}/tools/import/books`, () =>
        HttpResponse.json({
          created: 10,
          enriched: 3,
          groupCount: 13,
        }),
      ),
    );

    renderWithProviders(<ImportTool />);

    fireEvent.click(screen.getByText("Livres"));

    const file = new File(["data"], "livres.xlsx");
    fireEvent.change(screen.getByTestId("file-input"), {
      target: { files: [file] },
    });
    fireEvent.click(screen.getByRole("button", { name: /simuler/i }));

    await waitFor(() => {
      expect(screen.getByText("Simulation terminee")).toBeInTheDocument();
    });

    expect(screen.getByText("Groupes")).toBeInTheDocument();
    expect(screen.getByText("Crees")).toBeInTheDocument();
    expect(screen.getByText("Enrichis")).toBeInTheDocument();
    expect(screen.getByText("13")).toBeInTheDocument();
    expect(screen.getByText("10")).toBeInTheDocument();
    expect(screen.getByText("3")).toBeInTheDocument();
  });
});
