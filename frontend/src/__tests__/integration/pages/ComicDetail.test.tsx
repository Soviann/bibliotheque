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
      expect(screen.getByText(/Akira Toriyama, Eiichiro Oda/)).toBeInTheDocument();
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

  it("shows confirm modal when delete is clicked", async () => {
    const user = userEvent.setup();

    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(createMockComicSeries({ id: 1, title: "Test" })),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("Supprimer")).toBeInTheDocument();
    });

    await user.click(screen.getByText("Supprimer"));

    expect(screen.getByText("Supprimer cette série ?")).toBeInTheDocument();
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

  it("fires DELETE request and navigates to / when confirming delete", async () => {
    const user = userEvent.setup();
    let deleteCalled = false;

    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(createMockComicSeries({ id: 1, title: "To Delete" })),
      ),
      http.delete("/api/comic_series/1", () => {
        deleteCalled = true;
        return new HttpResponse(null, { status: 204 });
      }),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("To Delete")).toBeInTheDocument();
    });

    // Click Supprimer to open modal
    await user.click(screen.getByText("Supprimer"));

    // Confirm button in modal has confirmLabel "Supprimer"
    const confirmButton = screen.getByText("Supprimer cette série ?").closest("[role='dialog']")?.querySelector("button.bg-red-600");
    expect(confirmButton).toBeInTheDocument();
    await user.click(confirmButton!);

    await waitFor(() => {
      expect(deleteCalled).toBe(true);
    });

    // After success, navigates to /
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

  it("renders coverImage fallback when coverUrl is null", async () => {
    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({
            coverImage: "my-cover.jpg",
            coverUrl: null,
            id: 1,
            title: "Cover Test",
          }),
        ),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      const img = screen.getByAltText("Cover Test");
      expect(img).toHaveAttribute("src", "/uploads/covers/my-cover.jpg");
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
    expect(screen.queryByText("Auteurs :")).not.toBeInTheDocument();
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
    expect(screen.queryByText("Éditeur :")).not.toBeInTheDocument();
  });

  it("does not render description when description is null", async () => {
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
    // Only type/status badges + title should be present, no description paragraph
    const paragraphs = document.querySelectorAll("p.leading-relaxed");
    expect(paragraphs.length).toBe(0);
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
    expect(screen.getByText("2 / 5")).toBeInTheDocument();

    // Lus: 1/5, Téléchargés: 1/5
    expect(screen.getByText("Lus")).toBeInTheDocument();
    expect(screen.getByText("Téléchargés")).toBeInTheDocument();
    expect(screen.getAllByText("1 / 5")).toHaveLength(2);
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

    // Achetés: 2/3 (tomes.length as total since latestPublishedIssue is null)
    await waitFor(() => {
      expect(screen.getByText("2 / 3")).toBeInTheDocument();
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

  it("shows success toast after delete confirmation", async () => {
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

    const confirmButton = screen.getByText("Supprimer cette série ?").closest("[role='dialog']")?.querySelector("button.bg-red-600");
    await user.click(confirmButton!);

    await waitFor(() => {
      expect(toast.success).toHaveBeenCalledWith("Série supprimée");
    });
  });
});
