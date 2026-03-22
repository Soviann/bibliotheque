import { screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import { toast } from "sonner";
import Trash from "../../../pages/Trash";
import {
  createMockComicSeries,
  createMockHydraCollection,
} from "../../helpers/factories";
import { server } from "../../helpers/server";
import { renderWithProviders } from "../../helpers/test-utils";

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

describe("Trash", () => {
  beforeEach(() => {
    localStorage.clear();
    localStorage.setItem("jwt_token", "fake-jwt-token");
  });

  it("renders the page title", () => {
    renderWithProviders(<Trash />);

    expect(screen.getByText("Corbeille")).toBeInTheDocument();
  });

  it("shows skeleton loader initially", () => {
    renderWithProviders(<Trash />);

    expect(screen.getByTestId("trash-skeleton")).toBeInTheDocument();
    expect(screen.getAllByTestId("skeleton-box").length).toBeGreaterThanOrEqual(4);
  });

  it("shows empty state with icon and description when no trashed comics", async () => {
    server.use(
      http.get("/api/trash", () =>
        HttpResponse.json(createMockHydraCollection([], "/api/trash")),
      ),
    );

    renderWithProviders(<Trash />);

    await waitFor(() => {
      expect(screen.getByText("La corbeille est vide")).toBeInTheDocument();
    });
    expect(screen.getByText("Les séries supprimées apparaîtront ici")).toBeInTheDocument();
    expect(screen.getByTestId("empty-state-icon")).toBeInTheDocument();
  });

  it("renders trashed comics", async () => {
    const comics = [
      createMockComicSeries({ id: 1, title: "Deleted Comic 1" }),
      createMockComicSeries({ id: 2, title: "Deleted Comic 2" }),
    ];

    server.use(
      http.get("/api/trash", () =>
        HttpResponse.json(createMockHydraCollection(comics, "/api/trash")),
      ),
    );

    renderWithProviders(<Trash />);

    await waitFor(() => {
      expect(screen.getByText("Deleted Comic 1")).toBeInTheDocument();
    });
    expect(screen.getByText("Deleted Comic 2")).toBeInTheDocument();
  });

  it("has restore buttons for each trashed comic", async () => {
    const comics = [
      createMockComicSeries({ id: 1, title: "Trashed" }),
    ];

    server.use(
      http.get("/api/trash", () =>
        HttpResponse.json(createMockHydraCollection(comics, "/api/trash")),
      ),
    );

    renderWithProviders(<Trash />);

    await waitFor(() => {
      expect(screen.getByTitle("Restaurer")).toBeInTheDocument();
    });
  });

  it("has permanent delete buttons for each trashed comic", async () => {
    const comics = [
      createMockComicSeries({ id: 1, title: "Trashed" }),
    ];

    server.use(
      http.get("/api/trash", () =>
        HttpResponse.json(createMockHydraCollection(comics, "/api/trash")),
      ),
    );

    renderWithProviders(<Trash />);

    await waitFor(() => {
      expect(screen.getByTitle("Supprimer définitivement")).toBeInTheDocument();
    });
  });

  it("shows confirm modal when permanent delete is clicked", async () => {
    const user = userEvent.setup();
    const comics = [
      createMockComicSeries({ id: 1, title: "To Delete" }),
    ];

    server.use(
      http.get("/api/trash", () =>
        HttpResponse.json(createMockHydraCollection(comics, "/api/trash")),
      ),
    );

    renderWithProviders(<Trash />);

    await waitFor(() => {
      expect(screen.getByText("To Delete")).toBeInTheDocument();
    });

    await user.click(screen.getByTitle("Supprimer définitivement"));

    expect(screen.getByText("Cette action est irréversible. La série sera définitivement supprimée.")).toBeInTheDocument();
  });

  it("fires DELETE API call when confirming permanent delete", async () => {
    const user = userEvent.setup();
    let deleteCalled = false;

    const comics = [
      createMockComicSeries({ id: 1, title: "Perm Delete" }),
    ];

    server.use(
      http.get("/api/trash", () =>
        HttpResponse.json(createMockHydraCollection(comics, "/api/trash")),
      ),
      http.delete("/api/trash/1/permanent", () => {
        deleteCalled = true;
        return new HttpResponse(null, { status: 204 });
      }),
    );

    renderWithProviders(<Trash />);

    await waitFor(() => {
      expect(screen.getByText("Perm Delete")).toBeInTheDocument();
    });

    // Click permanent delete button
    await user.click(screen.getByTitle("Supprimer définitivement"));

    // Confirm in modal
    const confirmButton = screen.getByRole("button", { name: "Supprimer définitivement" });
    await user.click(confirmButton);

    await waitFor(() => {
      expect(deleteCalled).toBe(true);
    });
  });

  it("triggers restore when restore button is clicked", async () => {
    const user = userEvent.setup();
    const comics = [
      createMockComicSeries({ id: 1, title: "Restore Me" }),
    ];
    let restoreCalled = false;

    server.use(
      http.get("/api/trash", () =>
        HttpResponse.json(createMockHydraCollection(comics, "/api/trash")),
      ),
      http.put("/api/comic_series/1/restore", () => {
        restoreCalled = true;
        return HttpResponse.json(createMockComicSeries({ id: 1, title: "Restore Me" }));
      }),
    );

    renderWithProviders(<Trash />);

    await waitFor(() => {
      expect(screen.getByText("Restore Me")).toBeInTheDocument();
    });

    await user.click(screen.getByTitle("Restaurer"));

    await waitFor(() => {
      expect(restoreCalled).toBe(true);
    });
  });

  it("shows success toast after restore", async () => {
    const user = userEvent.setup();
    const comics = [
      createMockComicSeries({ id: 1, title: "Restored Comic" }),
    ];

    server.use(
      http.get("/api/trash", () =>
        HttpResponse.json(createMockHydraCollection(comics, "/api/trash")),
      ),
      http.put("/api/comic_series/1/restore", () =>
        HttpResponse.json(createMockComicSeries({ id: 1, title: "Restored Comic" })),
      ),
    );

    renderWithProviders(<Trash />);

    await waitFor(() => {
      expect(screen.getByText("Restored Comic")).toBeInTheDocument();
    });

    await user.click(screen.getByTitle("Restaurer"));

    await waitFor(() => {
      expect(toast.success).toHaveBeenCalledWith("Restored Comic restaurée");
    });
  });

  it("shows success toast after permanent delete", async () => {
    const user = userEvent.setup();
    const comics = [
      createMockComicSeries({ id: 1, title: "Gone Forever" }),
    ];

    server.use(
      http.get("/api/trash", () =>
        HttpResponse.json(createMockHydraCollection(comics, "/api/trash")),
      ),
      http.delete("/api/trash/1/permanent", () =>
        new HttpResponse(null, { status: 204 }),
      ),
    );

    renderWithProviders(<Trash />);

    await waitFor(() => {
      expect(screen.getByText("Gone Forever")).toBeInTheDocument();
    });

    await user.click(screen.getByTitle("Supprimer définitivement"));

    const confirmButton = screen.getByRole("button", { name: "Supprimer définitivement" });
    await user.click(confirmButton);

    await waitFor(() => {
      expect(toast.success).toHaveBeenCalledWith("Gone Forever supprimée définitivement");
    });
  });

  it("renders coverImage with priority over coverUrl (local first)", async () => {
    const comics = [
      createMockComicSeries({ coverImage: "local.webp", coverUrl: "https://example.com/cover.jpg", id: 1, title: "Local Cover" }),
    ];

    server.use(
      http.get("/api/trash", () =>
        HttpResponse.json(createMockHydraCollection(comics, "/api/trash")),
      ),
    );

    renderWithProviders(<Trash />);

    await waitFor(() => {
      const img = screen.getByAltText("Local Cover");
      expect(img).toHaveAttribute("src", "/media/cache/cover_thumbnail/uploads/covers/local.webp");
    });
  });

  it("falls back to coverUrl when coverImage is null", async () => {
    const comics = [
      createMockComicSeries({ coverImage: null, coverUrl: "https://example.com/cover.jpg", id: 1, title: "URL Cover" }),
    ];

    server.use(
      http.get("/api/trash", () =>
        HttpResponse.json(createMockHydraCollection(comics, "/api/trash")),
      ),
    );

    renderWithProviders(<Trash />);

    await waitFor(() => {
      const img = screen.getByAltText("URL Cover");
      expect(img).toHaveAttribute("src", "https://example.com/cover.jpg");
    });
  });

  it("renders placeholder cover when both coverUrl and coverImage are null", async () => {
    const comics = [
      createMockComicSeries({ coverImage: null, coverUrl: null, id: 1, title: "No Cover" }),
    ];

    server.use(
      http.get("/api/trash", () =>
        HttpResponse.json(createMockHydraCollection(comics, "/api/trash")),
      ),
    );

    renderWithProviders(<Trash />);

    await waitFor(() => {
      const img = screen.getByAltText("No Cover");
      expect(img).toHaveAttribute("src", "/placeholder-bd.jpg");
    });
  });
});
