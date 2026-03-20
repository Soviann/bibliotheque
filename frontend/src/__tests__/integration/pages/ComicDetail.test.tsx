import { screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import { Route, Routes } from "react-router-dom";
import { toast } from "sonner";
import ComicDetail from "../../../pages/ComicDetail";
import {
  createMockAuthor,
  createMockComicSeries,
  createMockTome,
} from "../../helpers/factories";
import { server } from "../../helpers/server";
import { renderWithProviders } from "../../helpers/test-utils";
import { ComicStatus, ComicType } from "../../../types/enums";

vi.mock("sonner", async () => {
  const actual = await vi.importActual("sonner");
  return {
    ...actual,
    toast: Object.assign(vi.fn(), {
      error: vi.fn(),
      success: vi.fn(),
    }),
  };
});

function renderComicDetail(id: number = 1) {
  return renderWithProviders(
    <Routes>
      <Route element={<ComicDetail />} path="/comic/:id" />
      <Route element={<div>Home Page</div>} path="/" />
    </Routes>,
    { initialEntries: [`/comic/${id}`] },
  );
}

describe("ComicDetail", () => {
  beforeEach(() => {
    localStorage.clear();
    localStorage.setItem("jwt_token", "fake-jwt-token");
  });

  it("shows skeleton loader initially", () => {
    // Use a handler that delays response
    server.use(
      http.get("/api/comic_series/:id", async () => {
        await new Promise((resolve) => setTimeout(resolve, 100));
        return HttpResponse.json(createMockComicSeries({ id: 1 }));
      }),
    );

    renderComicDetail();

    expect(screen.getByTestId("comic-detail-skeleton")).toBeInTheDocument();
    expect(screen.getAllByTestId("skeleton-box").length).toBeGreaterThanOrEqual(5);
  });

  it("renders comic title", async () => {
    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(createMockComicSeries({ id: 1, title: "Dragon Ball" })),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("Dragon Ball")).toBeInTheDocument();
    });
  });

  it("renders comic type and status badges", async () => {
    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({
            id: 1,
            status: ComicStatus.BUYING,
            title: "Test",
            type: ComicType.MANGA,
          }),
        ),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("Manga")).toBeInTheDocument();
    });
    expect(screen.getByText("En cours d'achat")).toBeInTheDocument();
  });

  it("renders one-shot badge when applicable", async () => {
    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({ id: 1, isOneShot: true, title: "My Oneshot Comic" }),
        ),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("My Oneshot Comic")).toBeInTheDocument();
    });
    // The badge "One-shot" is in a span with specific class
    const badge = screen.getByText("One-shot");
    expect(badge.tagName).toBe("SPAN");
  });

  it("renders authors when available", async () => {
    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({
            authors: [
              createMockAuthor({ name: "Akira Toriyama" }),
              createMockAuthor({ name: "Eiichiro Oda" }),
            ],
            id: 1,
            title: "Test",
          }),
        ),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("Akira Toriyama, Eiichiro Oda")).toBeInTheDocument();
    });
  });

  it("renders publisher when available", async () => {
    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({ id: 1, publisher: "Glénat", title: "Test" }),
        ),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("Glénat")).toBeInTheDocument();
    });
  });

  it("renders description when available", async () => {
    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({
            description: "Un manga épique",
            id: 1,
            title: "Test",
          }),
        ),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("Un manga épique")).toBeInTheDocument();
    });
  });

  it("renders tomes table for non-oneshot", async () => {
    const tomes = [
      createMockTome({ bought: true, id: 1, number: 1, title: "Tome 1" }),
      createMockTome({ id: 2, number: 2, read: true, title: "Tome 2" }),
    ];

    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({ id: 1, isOneShot: false, title: "Series", tomes }),
        ),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("Tomes (2)")).toBeInTheDocument();
    });
    expect(screen.getByText("Tome 1")).toBeInTheDocument();
    expect(screen.getByText("Tome 2")).toBeInTheDocument();
  });

  it("does not render tomes table for oneshot", async () => {
    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({ id: 1, isOneShot: true, title: "Single Volume", tomes: [] }),
        ),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("Single Volume")).toBeInTheDocument();
    });
    expect(screen.queryByText(/Tomes/)).not.toBeInTheDocument();
  });

  it("shows edit and delete action buttons", async () => {
    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(createMockComicSeries({ id: 1, title: "Test" })),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("Modifier")).toBeInTheDocument();
    });
    expect(screen.getByText("Supprimer")).toBeInTheDocument();
  });

  it("shows edit link pointing to edit page", async () => {
    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(createMockComicSeries({ id: 1, title: "Test" })),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("Modifier")).toBeInTheDocument();
    });

    const editLink = screen.getByText("Modifier").closest("a");
    expect(editLink).toHaveAttribute("href", "/comic/1/edit");
  });

  it("immediately deletes and shows undo toast when clicking delete", async () => {
    const user = userEvent.setup();
    let deleteCalled = false;

    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(createMockComicSeries({ id: 1, title: "Test" })),
      ),
      http.delete("/api/comic_series/1", () => {
        deleteCalled = true;
        return new HttpResponse(null, { status: 204 });
      }),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("Supprimer")).toBeInTheDocument();
    });

    await user.click(screen.getByText("Supprimer"));

    // No confirmation modal — direct delete
    expect(screen.queryByText("Supprimer cette série ?")).not.toBeInTheDocument();

    await waitFor(() => {
      expect(deleteCalled).toBe(true);
    });
  });

  it("renders cover image", async () => {
    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({
            coverUrl: "https://example.com/cover.jpg",
            id: 1,
            title: "With Cover",
          }),
        ),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      const img = screen.getByAltText("With Cover");
      expect(img).toHaveAttribute("src", "https://example.com/cover.jpg");
    });
  });

  it("navigates to / after clicking delete", async () => {
    const user = userEvent.setup();

    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(createMockComicSeries({ id: 1, title: "To Delete" })),
      ),
      http.delete("/api/comic_series/1", () =>
        new HttpResponse(null, { status: 204 }),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("To Delete")).toBeInTheDocument();
    });

    await user.click(screen.getByText("Supprimer"));

    // After delete, navigates to /
    await waitFor(() => {
      expect(screen.getByText("Home Page")).toBeInTheDocument();
    });
  });

  it("navigates back in history when clicking back button", async () => {
    const user = userEvent.setup();

    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({ id: 1, status: ComicStatus.BUYING, title: "Back Nav Comic" }),
        ),
      ),
    );

    renderWithProviders(
      <Routes>
        <Route element={<ComicDetail />} path="/comic/:id" />
        <Route element={<div>Home Page</div>} path="/" />
      </Routes>,
      { initialEntries: ["/?search=aqua", "/comic/1"] },
    );

    await waitFor(() => {
      expect(screen.getByText("Back Nav Comic")).toBeInTheDocument();
    });

    await user.click(screen.getByLabelText("Retour"));

    await waitFor(() => {
      expect(screen.getByText("Home Page")).toBeInTheDocument();
    });
  });

  it("renders coverImage with priority over coverUrl (local first)", async () => {
    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({
            coverImage: "my-cover.webp",
            coverUrl: "https://example.com/cover.jpg",
            id: 1,
            title: "Cover Test",
          }),
        ),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      const img = screen.getByAltText("Cover Test");
      expect(img).toHaveAttribute("src", "/uploads/covers/my-cover.webp");
    });
  });

  it("shows dash placeholder for tomes with null title", async () => {
    const tomes = [
      createMockTome({ id: 1, number: 1, title: null }),
    ];

    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({ id: 1, isOneShot: false, title: "No Title Tome", tomes }),
        ),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("No Title Tome")).toBeInTheDocument();
    });

    // The cell for tome title should show the dash
    const cells = screen.getAllByText("\u2014");
    // At least one dash should appear (the tome title cell)
    expect(cells.length).toBeGreaterThanOrEqual(1);
  });

  it("shows not found message when comic does not exist", async () => {
    server.use(
      http.get("/api/comic_series/999", () =>
        new HttpResponse(null, { status: 404 }),
      ),
    );

    renderComicDetail(999);

    await waitFor(() => {
      expect(screen.getByText("Série introuvable")).toBeInTheDocument();
    });
    expect(screen.getByRole("link", { name: "Retour à la bibliothèque" })).toHaveAttribute("href", "/");
  });

  it("does not render authors section when no authors", async () => {
    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({ authors: [], id: 1, title: "No Authors" }),
        ),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("No Authors")).toBeInTheDocument();
    });
    expect(screen.queryByText("Auteurs")).not.toBeInTheDocument();
  });

  it("renders published date when available", async () => {
    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({ id: 1, publishedDate: "2020-03-15", title: "With Date" }),
        ),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("With Date")).toBeInTheDocument();
    });
    expect(screen.getByText("Parution")).toBeInTheDocument();
    expect(screen.getByText("15 mars 2020")).toBeInTheDocument();
  });

  it("does not render published date when null", async () => {
    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({ id: 1, publishedDate: null, title: "No Date" }),
        ),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("No Date")).toBeInTheDocument();
    });
    expect(screen.queryByText("Parution")).not.toBeInTheDocument();
  });

  it("does not render publisher section when publisher is null", async () => {
    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({ id: 1, publisher: null, title: "No Publisher" }),
        ),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("No Publisher")).toBeInTheDocument();
    });
    expect(screen.queryByText("Éditeur")).not.toBeInTheDocument();
  });

  it("renders metadata in a definition list grid", async () => {
    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({
            authors: [createMockAuthor({ name: "Toriyama" })],
            id: 1,
            publisher: "Glénat",
            publishedDate: "2020-03-15",
            title: "Grid Test",
          }),
        ),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("Grid Test")).toBeInTheDocument();
    });

    // Metadata should be in a <dl> element
    const dl = document.querySelector("dl");
    expect(dl).toBeInTheDocument();

    // Labels should be <dt> elements
    const dtElements = dl!.querySelectorAll("dt");
    const dtTexts = Array.from(dtElements).map((dt) => dt.textContent);
    expect(dtTexts).toContain("Auteurs");
    expect(dtTexts).toContain("Éditeur");
    expect(dtTexts).toContain("Parution");

    // Values should be <dd> elements
    const ddElements = dl!.querySelectorAll("dd");
    const ddTexts = Array.from(ddElements).map((dd) => dd.textContent);
    expect(ddTexts).toContain("Toriyama");
    expect(ddTexts).toContain("Glénat");
    expect(ddTexts).toContain("15 mars 2020");
  });

  it("renders description in a separate section below metadata", async () => {
    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({
            authors: [createMockAuthor({ name: "Author" })],
            description: "Un manga épique",
            id: 1,
            title: "Desc Section",
          }),
        ),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("Desc Section")).toBeInTheDocument();
    });

    // Description should NOT be inside the <dl>
    const dl = document.querySelector("dl");
    expect(dl).toBeInTheDocument();
    expect(dl!.textContent).not.toContain("Un manga épique");

    // Description should be in its own section with a heading
    expect(screen.getByText("Description")).toBeInTheDocument();
    expect(screen.getByText("Un manga épique")).toBeInTheDocument();
  });

  it("does not render description section when description is null", async () => {
    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({ description: null, id: 1, title: "No Desc" }),
        ),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("No Desc")).toBeInTheDocument();
    });
    expect(screen.queryByText("Description")).not.toBeInTheDocument();
  });

  it("shows placeholder cover when both coverUrl and coverImage are null", async () => {
    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({ coverImage: null, coverUrl: null, id: 1, title: "No Cover" }),
        ),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      const img = screen.getByAltText("No Cover");
      expect(img).toHaveAttribute("src", "/placeholder-bd.jpg");
    });
  });

  it("shows progress bars for bought, read, and downloaded", async () => {
    const tomes = [
      createMockTome({ bought: true, downloaded: true, id: 1, number: 1, read: true }),
      createMockTome({ bought: true, downloaded: false, id: 2, number: 2, read: false }),
      createMockTome({ bought: false, downloaded: false, id: 3, number: 3, read: false }),
    ];

    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({
            id: 1,
            isOneShot: false,
            latestPublishedIssue: 5,
            title: "Progress Test",
            tomes,
          }),
        ),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("Achetés")).toBeInTheDocument();
    });

    // Should show three progress bars
    const progressBars = screen.getAllByRole("progressbar");
    expect(progressBars.length).toBe(3);

    // Achetés: 2/5
    expect(screen.getByText("2 / 5 (40%)")).toBeInTheDocument();

    // Lus: 1/5, Téléchargés: 1/5
    expect(screen.getByText("Lus")).toBeInTheDocument();
    expect(screen.getByText("Téléchargés")).toBeInTheDocument();
    expect(screen.getAllByText("1 / 5 (20%)")).toHaveLength(2);
  });

  it("uses tome count as total when latestPublishedIssue is null", async () => {
    const tomes = [
      createMockTome({ bought: true, id: 1, number: 1 }),
      createMockTome({ bought: true, id: 2, number: 2 }),
      createMockTome({ bought: false, id: 3, number: 3 }),
    ];

    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({
            id: 1,
            isOneShot: false,
            latestPublishedIssue: null,
            title: "No Total",
            tomes,
          }),
        ),
      ),
    );

    renderComicDetail();

    // Achetés: 2/3 (covered tome count as total since latestPublishedIssue is null)
    await waitFor(() => {
      expect(screen.getByText("2 / 3 (67%)")).toBeInTheDocument();
    });
  });

  it("accounts for tome ranges in progress bars", async () => {
    const tomes = [
      createMockTome({ bought: true, downloaded: true, id: 1, number: 1, read: true, tomeEnd: 2 }), // covers 2
      createMockTome({ bought: true, downloaded: false, id: 2, number: 3, read: false }),            // covers 1
    ];

    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({
            id: 1,
            isOneShot: false,
            latestPublishedIssue: 5,
            title: "Range Progress",
            tomes,
          }),
        ),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("Achetés")).toBeInTheDocument();
    });

    // Achetés: tome 1-2 (bought) + tome 3 (bought) = 3 covered / 5 total
    expect(screen.getByText("3 / 5 (60%)")).toBeInTheDocument();
    // Lus: only tome 1-2 (read) = 2 covered / 5 total
    // Téléchargés: only tome 1-2 (downloaded) = 2 covered / 5 total
    expect(screen.getAllByText("2 / 5 (40%)")).toHaveLength(2);
  });

  it("uses covered tome count as fallback total when latestPublishedIssue is null", async () => {
    const tomes = [
      createMockTome({ bought: true, id: 1, number: 1, tomeEnd: 3 }), // covers 3
      createMockTome({ bought: false, id: 2, number: 4 }),            // covers 1
    ];

    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({
            id: 1,
            isOneShot: false,
            latestPublishedIssue: null,
            title: "Range Fallback",
            tomes,
          }),
        ),
      ),
    );

    renderComicDetail();

    // Total should be 4 (covered tomes), not 2 (entries count)
    // Achetés: 3 / 4
    await waitFor(() => {
      expect(screen.getByText("3 / 4 (75%)")).toBeInTheDocument();
    });
  });

  it("does not show progress bars for oneshot series", async () => {
    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({
            id: 1,
            isOneShot: true,
            title: "Oneshot Progress",
            tomes: [createMockTome({ bought: true, id: 1, number: 1 })],
          }),
        ),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("Oneshot Progress")).toBeInTheDocument();
    });

    expect(screen.queryByRole("progressbar")).not.toBeInTheDocument();
    expect(screen.queryByText("Achetés")).not.toBeInTheDocument();
  });

  it("does not show progress bars when no tomes", async () => {
    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({
            id: 1,
            isOneShot: false,
            title: "No Tomes Progress",
            tomes: [],
          }),
        ),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("No Tomes Progress")).toBeInTheDocument();
    });

    expect(screen.queryByRole("progressbar")).not.toBeInTheDocument();
  });

  it("does not render tomes section for non-oneshot with empty tomes", async () => {
    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({ id: 1, isOneShot: false, title: "No Volumes", tomes: [] }),
        ),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("No Volumes")).toBeInTheDocument();
    });
    expect(screen.queryByText(/Tomes \(/)).not.toBeInTheDocument();
  });

  it("displays tome range when tomeEnd is set", async () => {
    const tomes = [
      createMockTome({ id: 1, number: 1, title: null }),
      createMockTome({ id: 2, number: 4, title: null, tomeEnd: 6 }),
    ];

    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({ id: 1, isOneShot: false, title: "Intégrales", tomes }),
        ),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("Intégrales")).toBeInTheDocument();
    });

    // Tome 1 should show just "1"
    expect(screen.getByText("1")).toBeInTheDocument();
    // Tome 4-6 should show the range
    expect(screen.getByText("4-6")).toBeInTheDocument();
  });

  it("displays single tome number when tomeEnd is null", async () => {
    const tomes = [
      createMockTome({ id: 1, number: 3, title: null, tomeEnd: null }),
    ];

    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({ id: 1, isOneShot: false, title: "Single Tome", tomes }),
        ),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("Single Tome")).toBeInTheDocument();
    });

    expect(screen.getByText("3")).toBeInTheDocument();
    expect(screen.queryByText("3-")).not.toBeInTheDocument();
  });

  it("shows checked checkboxes for tome downloaded and onNas fields", async () => {
    const tomes = [
      createMockTome({ downloaded: true, id: 1, number: 1, onNas: true, title: "Full Tome" }),
    ];

    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({ id: 1, isOneShot: false, title: "Tome Checks", tomes }),
        ),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("Full Tome")).toBeInTheDocument();
    });

    expect(screen.getByRole("checkbox", { name: /tome 1.*téléchargé/i })).toBeChecked();
    expect(screen.getByRole("checkbox", { name: /tome 1.*nas/i })).toBeChecked();
    expect(screen.getByRole("checkbox", { name: /tome 1.*acheté/i })).not.toBeChecked();
    expect(screen.getByRole("checkbox", { name: /tome 1.*lu/i })).not.toBeChecked();
  });

  it("shows 'Parution terminée' badge when latestPublishedIssueComplete is true", async () => {
    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({ id: 1, latestPublishedIssueComplete: true, title: "Complete Series" }),
        ),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("Parution terminée")).toBeInTheDocument();
    });
  });

  it("does not show 'Parution terminée' badge when latestPublishedIssueComplete is false", async () => {
    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({ id: 1, latestPublishedIssueComplete: false, title: "Ongoing Series" }),
        ),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("Ongoing Series")).toBeInTheDocument();
    });

    expect(screen.queryByText("Parution terminée")).not.toBeInTheDocument();
  });

  it("shows default tome flags info when set", async () => {
    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({
            defaultTomeBought: true,
            defaultTomeDownloaded: true,
            defaultTomeRead: false,
            id: 1,
            title: "Flagged Series",
          }),
        ),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("Nouveaux tomes")).toBeInTheDocument();
    });

    expect(screen.getByText("achetés, téléchargés")).toBeInTheDocument();
  });

  it("shows latestPublishedIssueUpdatedAt as relative date", async () => {
    const yesterday = new Date(Date.now() - 86400000).toISOString();
    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({
            id: 1,
            latestPublishedIssue: 10,
            latestPublishedIssueUpdatedAt: yesterday,
            title: "Updated Series",
          }),
        ),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText(/mis à jour/)).toBeInTheDocument();
    });

    expect(screen.getByText(/hier/)).toBeInTheDocument();
  });

  it("shows Amazon button when status is BUYING and amazonUrl is set", async () => {
    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({
            amazonUrl: "https://www.amazon.fr/dp/B08N5WRWNW",
            id: 1,
            status: ComicStatus.BUYING,
            title: "Amazon Test",
          }),
        ),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("Amazon Test")).toBeInTheDocument();
    });

    const amazonLink = screen.getByRole("link", { name: /amazon/i });
    expect(amazonLink).toHaveAttribute("href", "https://www.amazon.fr/dp/B08N5WRWNW");
    expect(amazonLink).toHaveAttribute("target", "_blank");
    expect(amazonLink).toHaveAttribute("rel", "noopener noreferrer");
  });

  it("does not show Amazon button when status is not BUYING", async () => {
    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({
            amazonUrl: "https://www.amazon.fr/dp/B08N5WRWNW",
            id: 1,
            status: ComicStatus.FINISHED,
            title: "Not Buying",
          }),
        ),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("Not Buying")).toBeInTheDocument();
    });

    expect(screen.queryByRole("link", { name: /amazon/i })).not.toBeInTheDocument();
  });

  it("does not show Amazon button when amazonUrl is null", async () => {
    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({
            amazonUrl: null,
            id: 1,
            status: ComicStatus.BUYING,
            title: "No Amazon",
          }),
        ),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("No Amazon")).toBeInTheDocument();
    });

    expect(screen.queryByRole("link", { name: /amazon/i })).not.toBeInTheDocument();
  });

  it("renders action buttons in correct order: Modifier, Amazon, Supprimer", async () => {
    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({
            amazonUrl: "https://www.amazon.fr/dp/B08N5WRWNW",
            id: 1,
            status: ComicStatus.BUYING,
            title: "Button Order",
          }),
        ),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("Button Order")).toBeInTheDocument();
    });

    const actionBar = screen.getByText("Modifier").closest("div.sticky")!;
    const buttons = actionBar.querySelectorAll("a, button");
    expect(buttons).toHaveLength(3);
    expect(buttons[0]).toHaveTextContent("Modifier");
    expect(buttons[1]).toHaveTextContent("Amazon");
    expect(buttons[2]).toHaveTextContent("Supprimer");
  });

  it("opens lightbox when clicking on cover image", async () => {
    const user = userEvent.setup();

    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({
            coverUrl: "https://example.com/cover.jpg",
            id: 1,
            title: "Lightbox Test",
          }),
        ),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByAltText("Lightbox Test")).toBeInTheDocument();
    });

    await user.click(screen.getByAltText("Lightbox Test"));

    expect(screen.getByRole("dialog")).toBeInTheDocument();
  });

  it("does not open lightbox when clicking on placeholder cover", async () => {
    const user = userEvent.setup();

    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({
            coverImage: null,
            coverUrl: null,
            id: 1,
            title: "No Cover Lightbox",
          }),
        ),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByAltText("No Cover Lightbox")).toBeInTheDocument();
    });

    await user.click(screen.getByAltText("No Cover Lightbox"));

    expect(screen.queryByRole("dialog")).not.toBeInTheDocument();
  });

  it("limits cover height on mobile with max-h-64", async () => {
    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({
            coverUrl: "https://example.com/cover.jpg",
            id: 1,
            title: "Mobile Cover",
          }),
        ),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      const img = screen.getByAltText("Mobile Cover");
      expect(img.className).toContain("max-h-64");
      expect(img.className).toContain("md:max-h-none");
    });
  });

  it("renders delete button with outline/ghost red style instead of solid red", async () => {
    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(createMockComicSeries({ id: 1, title: "Ghost Delete" })),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("Ghost Delete")).toBeInTheDocument();
    });

    const deleteButton = screen.getByText("Supprimer").closest("button")!;
    expect(deleteButton.className).not.toContain("bg-red-600");
    expect(deleteButton.className).toContain("border");
    expect(deleteButton.className).toContain("text-red-600");
  });

  describe("tomes table sorting", () => {
    const sortableTomes = [
      createMockTome({ bought: true, downloaded: false, id: 1, number: 1, read: false, title: "Alpha" }),
      createMockTome({ bought: false, downloaded: true, id: 2, number: 3, read: true, title: "Gamma" }),
      createMockTome({ bought: true, downloaded: true, id: 3, number: 2, read: false, title: "Beta" }),
    ];

    function setupSortableTable() {
      server.use(
        http.get("/api/comic_series/1", () =>
          HttpResponse.json(
            createMockComicSeries({ id: 1, isOneShot: false, title: "Sort Test", tomes: sortableTomes }),
          ),
        ),
      );
    }

    it("renders clickable column headers with sort indicators", async () => {
      setupSortableTable();
      renderComicDetail();

      await waitFor(() => {
        expect(screen.getByText("Tomes (3)")).toBeInTheDocument();
      });

      // The # column header should be clickable and show a sort indicator (ascending by default)
      const numberHeader = screen.getByRole("columnheader", { name: /^#/ });
      expect(numberHeader).toBeInTheDocument();
      const sortButton = numberHeader.querySelector("button");
      expect(sortButton).toBeInTheDocument();
    });

    it("sorts tomes by number ascending by default", async () => {
      setupSortableTable();
      renderComicDetail();

      await waitFor(() => {
        expect(screen.getByText("Tomes (3)")).toBeInTheDocument();
      });

      // Default sort: by number ascending (1, 2, 3)
      const rows = screen.getAllByRole("row").slice(1); // skip header
      expect(rows[0]).toHaveTextContent("1");
      expect(rows[1]).toHaveTextContent("2");
      expect(rows[2]).toHaveTextContent("3");
    });

    it("sorts tomes by number descending when clicking # header", async () => {
      const user = userEvent.setup();
      setupSortableTable();
      renderComicDetail();

      await waitFor(() => {
        expect(screen.getByText("Tomes (3)")).toBeInTheDocument();
      });

      // Click # header to toggle to descending
      const numberHeader = screen.getByRole("columnheader", { name: /^#/ });
      await user.click(numberHeader.querySelector("button")!);

      const rows = screen.getAllByRole("row").slice(1);
      expect(rows[0]).toHaveTextContent("3");
      expect(rows[1]).toHaveTextContent("2");
      expect(rows[2]).toHaveTextContent("1");
    });

    it("sorts tomes by title when clicking Titre header", async () => {
      const user = userEvent.setup();
      setupSortableTable();
      renderComicDetail();

      await waitFor(() => {
        expect(screen.getByText("Tomes (3)")).toBeInTheDocument();
      });

      const titleHeader = screen.getByRole("columnheader", { name: /Titre/ });
      await user.click(titleHeader.querySelector("button")!);

      const rows = screen.getAllByRole("row").slice(1);
      // Ascending by title: Alpha, Beta, Gamma
      expect(rows[0]).toHaveTextContent("Alpha");
      expect(rows[1]).toHaveTextContent("Beta");
      expect(rows[2]).toHaveTextContent("Gamma");
    });

    it("sorts unread tomes first when clicking Lu header", async () => {
      const user = userEvent.setup();
      setupSortableTable();
      renderComicDetail();

      await waitFor(() => {
        expect(screen.getByText("Tomes (3)")).toBeInTheDocument();
      });

      // Click Lu header to sort by read status (unread first = ascending: false before true)
      const readHeader = screen.getByRole("columnheader", { name: /Lu/ });
      await user.click(readHeader.querySelector("button")!);

      const rows = screen.getAllByRole("row").slice(1);
      // Ascending boolean: false (unread) first, then true (read)
      // Among unread (#1 and #2): sorted by number
      expect(rows[0]).toHaveTextContent("1"); // unread
      expect(rows[1]).toHaveTextContent("2"); // unread
      expect(rows[2]).toHaveTextContent("3"); // read
    });

    it("toggles sort direction when clicking the same column twice", async () => {
      const user = userEvent.setup();
      setupSortableTable();
      renderComicDetail();

      await waitFor(() => {
        expect(screen.getByText("Tomes (3)")).toBeInTheDocument();
      });

      const numberHeader = screen.getByRole("columnheader", { name: /^#/ });
      // First click: descending (was already ascending by default)
      await user.click(numberHeader.querySelector("button")!);
      // Second click: back to ascending
      await user.click(numberHeader.querySelector("button")!);

      const rows = screen.getAllByRole("row").slice(1);
      expect(rows[0]).toHaveTextContent("1");
      expect(rows[1]).toHaveTextContent("2");
      expect(rows[2]).toHaveTextContent("3");
    });

    it("resets to ascending when switching to a different column", async () => {
      const user = userEvent.setup();
      setupSortableTable();
      renderComicDetail();

      await waitFor(() => {
        expect(screen.getByText("Tomes (3)")).toBeInTheDocument();
      });

      // Click # to go descending
      const numberHeader = screen.getByRole("columnheader", { name: /^#/ });
      await user.click(numberHeader.querySelector("button")!);

      // Click Titre → should sort ascending by title
      const titleHeader = screen.getByRole("columnheader", { name: /Titre/ });
      await user.click(titleHeader.querySelector("button")!);

      const rows = screen.getAllByRole("row").slice(1);
      expect(rows[0]).toHaveTextContent("Alpha");
      expect(rows[1]).toHaveTextContent("Beta");
      expect(rows[2]).toHaveTextContent("Gamma");
    });
  });

  describe("missing tomes banner", () => {
    it("shows banner when latestPublishedIssue > covered tomes count", async () => {
      const tomes = [
        createMockTome({ id: 1, number: 1 }),
        createMockTome({ id: 2, number: 2 }),
      ];

      server.use(
        http.get("/api/comic_series/1", () =>
          HttpResponse.json(
            createMockComicSeries({
              id: 1,
              isOneShot: false,
              latestPublishedIssue: 5,
              title: "Missing Tomes",
              tomes,
            }),
          ),
        ),
      );

      renderComicDetail();

      await waitFor(() => {
        expect(screen.getByText(/3 tomes parus non ajoutés/)).toBeInTheDocument();
      });
    });

    it("does not show banner when all published tomes are covered", async () => {
      const tomes = [
        createMockTome({ id: 1, number: 1 }),
        createMockTome({ id: 2, number: 2 }),
        createMockTome({ id: 3, number: 3 }),
      ];

      server.use(
        http.get("/api/comic_series/1", () =>
          HttpResponse.json(
            createMockComicSeries({
              id: 1,
              isOneShot: false,
              latestPublishedIssue: 3,
              title: "All Covered",
              tomes,
            }),
          ),
        ),
      );

      renderComicDetail();

      await waitFor(() => {
        expect(screen.getByText("All Covered")).toBeInTheDocument();
      });

      expect(screen.queryByText(/tomes? parus? non ajoutés?/)).not.toBeInTheDocument();
    });

    it("does not show banner when latestPublishedIssue is null", async () => {
      const tomes = [
        createMockTome({ id: 1, number: 1 }),
      ];

      server.use(
        http.get("/api/comic_series/1", () =>
          HttpResponse.json(
            createMockComicSeries({
              id: 1,
              isOneShot: false,
              latestPublishedIssue: null,
              title: "No Published Info",
              tomes,
            }),
          ),
        ),
      );

      renderComicDetail();

      await waitFor(() => {
        expect(screen.getByText("No Published Info")).toBeInTheDocument();
      });

      expect(screen.queryByText(/tomes? parus? non ajoutés?/)).not.toBeInTheDocument();
    });

    it("does not show banner for oneshot series", async () => {
      server.use(
        http.get("/api/comic_series/1", () =>
          HttpResponse.json(
            createMockComicSeries({
              id: 1,
              isOneShot: true,
              latestPublishedIssue: 1,
              title: "Oneshot Banner",
              tomes: [],
            }),
          ),
        ),
      );

      renderComicDetail();

      await waitFor(() => {
        expect(screen.getByText("Oneshot Banner")).toBeInTheDocument();
      });

      expect(screen.queryByText(/tomes? parus? non ajoutés?/)).not.toBeInTheDocument();
    });

    it("accounts for tome ranges in missing count", async () => {
      const tomes = [
        createMockTome({ id: 1, number: 1, tomeEnd: 3 }), // covers 3
        createMockTome({ id: 2, number: 4 }),               // covers 1
      ];

      server.use(
        http.get("/api/comic_series/1", () =>
          HttpResponse.json(
            createMockComicSeries({
              id: 1,
              isOneShot: false,
              latestPublishedIssue: 10,
              title: "Range Banner",
              tomes,
            }),
          ),
        ),
      );

      renderComicDetail();

      // 10 published - 4 covered = 6 missing
      await waitFor(() => {
        expect(screen.getByText(/6 tomes parus non ajoutés/)).toBeInTheDocument();
      });
    });

    it("shows singular form for 1 missing tome", async () => {
      const tomes = [
        createMockTome({ id: 1, number: 1 }),
        createMockTome({ id: 2, number: 2 }),
      ];

      server.use(
        http.get("/api/comic_series/1", () =>
          HttpResponse.json(
            createMockComicSeries({
              id: 1,
              isOneShot: false,
              latestPublishedIssue: 3,
              title: "One Missing",
              tomes,
            }),
          ),
        ),
      );

      renderComicDetail();

      await waitFor(() => {
        expect(screen.getByText(/1 tome paru non ajouté/)).toBeInTheDocument();
      });
    });

    it("links to the edit form", async () => {
      const tomes = [
        createMockTome({ id: 1, number: 1 }),
      ];

      server.use(
        http.get("/api/comic_series/1", () =>
          HttpResponse.json(
            createMockComicSeries({
              id: 1,
              isOneShot: false,
              latestPublishedIssue: 5,
              title: "Link Banner",
              tomes,
            }),
          ),
        ),
      );

      renderComicDetail();

      await waitFor(() => {
        expect(screen.getByText(/tomes parus non ajoutés/)).toBeInTheDocument();
      });

      const link = screen.getByRole("link", { name: /ajouter/i });
      expect(link).toHaveAttribute("href", "/comic/1/edit");
    });
  });

  it("shows undo toast after delete", async () => {
    const user = userEvent.setup();

    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(createMockComicSeries({ id: 1, title: "To Toast" })),
      ),
      http.delete("/api/comic_series/1", () =>
        new HttpResponse(null, { status: 204 }),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("To Toast")).toBeInTheDocument();
    });

    await user.click(screen.getByText("Supprimer"));

    await waitFor(() => {
      expect(toast.success).toHaveBeenCalledWith(
        "Série supprimée",
        expect.objectContaining({ action: expect.objectContaining({ label: "Annuler" }) }),
      );
    });
  });
});
