import { screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import Trash from "../../../pages/Trash";
import {
  createMockComicSeries,
  createMockHydraCollection,
} from "../../helpers/factories";
import { server } from "../../helpers/server";
import { renderWithProviders } from "../../helpers/test-utils";

describe("Trash", () => {
  beforeEach(() => {
    localStorage.clear();
    localStorage.setItem("jwt_token", "fake-jwt-token");
  });

  it("renders the page title", () => {
    renderWithProviders(<Trash />);

    expect(screen.getByText("Corbeille")).toBeInTheDocument();
  });

  it("shows loading state initially", () => {
    renderWithProviders(<Trash />);

    expect(screen.getByText("Chargement…")).toBeInTheDocument();
  });

  it("shows empty state when no trashed comics", async () => {
    server.use(
      http.get("/api/trash", () =>
        HttpResponse.json(createMockHydraCollection([], "/api/trash")),
      ),
    );

    renderWithProviders(<Trash />);

    await waitFor(() => {
      expect(screen.getByText("La corbeille est vide")).toBeInTheDocument();
    });
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
});
