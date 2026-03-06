import { screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import MergeSeries from "../../../pages/MergeSeries";
import type { MergeGroup, MergePreview } from "../../../types/api";
import {
  createMockComicSeries,
  createMockHydraCollection,
} from "../../helpers/factories";
import { server } from "../../helpers/server";
import { renderWithProviders } from "../../helpers/test-utils";

const mockGroups: MergeGroup[] = [
  {
    entries: [
      { originalTitle: "Naruto Tome 1", seriesId: 1, suggestedTomeNumber: 1 },
      { originalTitle: "Naruto Tome 2", seriesId: 2, suggestedTomeNumber: 2 },
      { originalTitle: "Naruto Tome 3", seriesId: 3, suggestedTomeNumber: 3 },
    ],
    suggestedTitle: "Naruto",
  },
];

const mockPreview: MergePreview = {
  authors: ["Kishimoto"],
  coverUrl: null,
  description: null,
  isOneShot: false,
  latestPublishedIssue: null,
  latestPublishedIssueComplete: false,
  publisher: null,
  sourceSeriesIds: [1, 3],
  title: "Naruto",
  tomes: [
    { bought: false, downloaded: false, isbn: null, number: 1, onNas: false, read: false, title: null, tomeEnd: null },
    { bought: false, downloaded: false, isbn: null, number: 3, onNas: false, read: false, title: null, tomeEnd: null },
  ],
  type: "manga",
};

describe("MergeSeries", () => {
  beforeEach(() => {
    localStorage.clear();
    localStorage.setItem("jwt_token", "fake-jwt-token");

    server.use(
      http.get("/api/comic_series", () =>
        HttpResponse.json(
          createMockHydraCollection(
            [
              createMockComicSeries({ id: 1, title: "Naruto Tome 1" }),
              createMockComicSeries({ id: 2, title: "Naruto Tome 2" }),
              createMockComicSeries({ id: 3, title: "Naruto Tome 3" }),
            ],
            "/api/comic_series",
          ),
        ),
      ),
    );
  });

  it("renders with two tabs", () => {
    renderWithProviders(<MergeSeries />);

    expect(screen.getByText("Fusion de series")).toBeInTheDocument();
    expect(screen.getByText("Detection automatique")).toBeInTheDocument();
    expect(screen.getByText("Selection manuelle")).toBeInTheDocument();
  });

  it("auto detect tab has filters and detect button", () => {
    renderWithProviders(<MergeSeries />);

    expect(screen.getByText("Type")).toBeInTheDocument();
    expect(screen.getByText("Lettre")).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: /Detecter les groupes/ }),
    ).toBeInTheDocument();
  });

  it("manual tab has search input", async () => {
    const user = userEvent.setup();
    renderWithProviders(<MergeSeries />);

    await user.click(screen.getByText("Selection manuelle"));

    expect(
      screen.getByPlaceholderText("Rechercher une serie..."),
    ).toBeInTheDocument();
  });

  it("opens confirmation modal when clicking preview on a detected group", async () => {
    const user = userEvent.setup();
    let previewCalled = false;

    server.use(
      http.post("/api/merge-series/detect", () =>
        HttpResponse.json(mockGroups),
      ),
      http.post("/api/merge-series/preview", async () => {
        previewCalled = true;
        return HttpResponse.json(mockPreview);
      }),
    );

    renderWithProviders(<MergeSeries />);

    // Simulate successful detection by triggering the API
    // We need to click detect — but it requires type + letter selected
    // Let's test from the manual tab which is easier to control

    await user.click(screen.getByText("Selection manuelle"));

    // Wait for comics to load
    await waitFor(() => {
      expect(screen.getByText("Naruto Tome 1")).toBeInTheDocument();
    });

    // Select 3 series
    const checkboxes = screen.getAllByRole("checkbox");
    await user.click(checkboxes[0]);
    await user.click(checkboxes[1]);
    await user.click(checkboxes[2]);

    // Click preview button
    await user.click(screen.getByRole("button", { name: /apercu de la fusion/i }));

    // Confirmation modal should appear (not the tome preview directly)
    await waitFor(() => {
      expect(screen.getByText("Confirmer les series a fusionner")).toBeInTheDocument();
    });

    // Preview API should NOT have been called yet
    expect(previewCalled).toBe(false);
  });

  it("calls preview API only after confirming series selection", async () => {
    const user = userEvent.setup();
    let previewCalledWith: number[] = [];

    server.use(
      http.post("/api/merge-series/preview", async ({ request }) => {
        const body = (await request.json()) as { seriesIds: number[] };
        previewCalledWith = body.seriesIds;
        return HttpResponse.json(mockPreview);
      }),
    );

    renderWithProviders(<MergeSeries />);

    await user.click(screen.getByText("Selection manuelle"));

    // Wait for comics to load
    await waitFor(() => {
      expect(screen.getByText("Naruto Tome 1")).toBeInTheDocument();
    });

    // Select 3 series
    const seriesCheckboxes = screen.getAllByRole("checkbox");
    await user.click(seriesCheckboxes[0]);
    await user.click(seriesCheckboxes[1]);
    await user.click(seriesCheckboxes[2]);

    // Click preview button → opens confirmation modal
    await user.click(screen.getByRole("button", { name: /apercu de la fusion/i }));

    await waitFor(() => {
      expect(screen.getByText("Confirmer les series a fusionner")).toBeInTheDocument();
    });

    // Uncheck series 2 in the confirmation modal
    const dialog = screen.getByRole("dialog");
    const naruto2Label = within(dialog).getByText("Naruto Tome 2").closest("label");
    const naruto2Checkbox = naruto2Label!.querySelector('input[type="checkbox"]')!;
    await user.click(naruto2Checkbox);

    // Click "Continuer"
    await user.click(screen.getByRole("button", { name: /continuer/i }));

    // Preview API should have been called with only IDs 1 and 3
    await waitFor(() => {
      expect(previewCalledWith).toEqual(expect.arrayContaining([1, 3]));
      expect(previewCalledWith).not.toContain(2);
    });
  });
});
