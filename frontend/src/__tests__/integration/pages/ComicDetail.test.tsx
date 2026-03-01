import { screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import { Route, Routes } from "react-router-dom";
import ComicDetail from "../../../pages/ComicDetail";
import {
  createMockAuthor,
  createMockComicSeries,
  createMockTome,
} from "../../helpers/factories";
import { server } from "../../helpers/server";
import { renderWithProviders } from "../../helpers/test-utils";
import { ComicStatus, ComicType } from "../../../types/enums";

function renderComicDetail(id: number = 1) {
  return renderWithProviders(
    <Routes>
      <Route element={<ComicDetail />} path="/comic/:id" />
    </Routes>,
    { initialEntries: [`/comic/${id}`] },
  );
}

describe("ComicDetail", () => {
  beforeEach(() => {
    localStorage.clear();
    localStorage.setItem("jwt_token", "fake-jwt-token");
  });

  it("shows loading state initially", () => {
    // Use a handler that delays response
    server.use(
      http.get("/api/comic_series/:id", async () => {
        await new Promise((resolve) => setTimeout(resolve, 100));
        return HttpResponse.json(createMockComicSeries({ id: 1 }));
      }),
    );

    renderComicDetail();

    expect(screen.getByText("Chargement…")).toBeInTheDocument();
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
});
