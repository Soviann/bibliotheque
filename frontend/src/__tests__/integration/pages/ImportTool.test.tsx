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
          sheetDetails: { Mangas: { series: 3, tomes: 15 } },
          totalSeries: 3,
          totalTomes: 15,
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
});
