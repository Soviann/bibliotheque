import { screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import { Route, Routes } from "react-router-dom";
import ComicForm from "../../../pages/ComicForm";
import {
  createMockAuthor,
  createMockComicSeries,
  createMockLookupResult,
  createMockTome,
} from "../../helpers/factories";
import { server } from "../../helpers/server";
import { renderWithProviders } from "../../helpers/test-utils";
import { ComicStatus, ComicType } from "../../../types/enums";

// Mock html5-qrcode for BarcodeScanner component
vi.mock("html5-qrcode", () => ({
  Html5Qrcode: vi.fn().mockImplementation(() => ({
    start: vi.fn().mockRejectedValue(new Error("Camera not available")),
    stop: vi.fn().mockResolvedValue(undefined),
  })),
}));

function renderCreateForm() {
  return renderWithProviders(
    <Routes>
      <Route element={<ComicForm />} path="/comic/new" />
      <Route element={<div>Comic Detail</div>} path="/comic/:id" />
      <Route element={<div>Home Page</div>} path="/" />
    </Routes>,
    { initialEntries: ["/comic/new"] },
  );
}

function renderEditForm(id: number = 1) {
  return renderWithProviders(
    <Routes>
      <Route element={<ComicForm />} path="/comic/:id/edit" />
      <Route element={<div>Comic Detail</div>} path="/comic/:id" />
    </Routes>,
    { initialEntries: [`/comic/${id}/edit`] },
  );
}

describe("ComicForm", () => {
  beforeEach(() => {
    localStorage.clear();
    localStorage.setItem("jwt_token", "fake-jwt-token");
  });

  describe("Create mode", () => {
    it("renders page title for new comic", () => {
      renderCreateForm();

      expect(screen.getByText("Nouvelle série")).toBeInTheDocument();
    });

    it("renders empty form fields", () => {
      renderCreateForm();

      const titleInput = screen.getByLabelText("Titre *");
      expect(titleInput).toHaveValue("");
    });

    it("renders create button", () => {
      renderCreateForm();

      expect(screen.getByText("Créer")).toBeInTheDocument();
    });

    it("has create button disabled when title is empty", () => {
      renderCreateForm();

      const createButton = screen.getByText("Créer");
      expect(createButton).toBeDisabled();
    });

    it("enables create button when title is filled", async () => {
      const user = userEvent.setup();
      renderCreateForm();

      await user.type(screen.getByLabelText("Titre *"), "New Comic");

      const createButton = screen.getByText("Créer");
      expect(createButton).toBeEnabled();
    });

    it("submits to create endpoint", async () => {
      const user = userEvent.setup();
      let createCalled = false;

      server.use(
        http.post("/api/comic_series", () => {
          createCalled = true;
          return HttpResponse.json(
            createMockComicSeries({ id: 10, title: "New Comic" }),
            { status: 201 },
          );
        }),
      );

      renderCreateForm();

      await user.type(screen.getByLabelText("Titre *"), "New Comic");
      await user.click(screen.getByText("Créer"));

      await waitFor(() => {
        expect(createCalled).toBe(true);
      });
    });

    it("renders publisher field", () => {
      renderCreateForm();

      expect(screen.getByLabelText("Éditeur")).toBeInTheDocument();
    });

    it("renders description field", () => {
      renderCreateForm();

      expect(screen.getByLabelText("Description")).toBeInTheDocument();
    });

    it("renders cover URL field", () => {
      renderCreateForm();

      expect(screen.getByLabelText("URL de couverture")).toBeInTheDocument();
    });

    it("renders oneshot checkbox", () => {
      renderCreateForm();

      expect(screen.getByText("One-shot (pas de tomes)")).toBeInTheDocument();
    });

    it("renders tomes section by default", () => {
      renderCreateForm();

      expect(screen.getByText(/Tomes \(/)).toBeInTheDocument();
    });

    it("hides tomes section when oneshot is checked", async () => {
      const user = userEvent.setup();
      renderCreateForm();

      const checkbox = screen.getByRole("checkbox", { name: /One-shot/ });
      await user.click(checkbox);

      expect(screen.queryByText(/Tomes \(/)).not.toBeInTheDocument();
    });

    it("renders cancel button", () => {
      renderCreateForm();

      expect(screen.getByText("Annuler")).toBeInTheDocument();
    });
  });

  describe("Edit mode", () => {
    it("shows loading state while fetching comic", () => {
      server.use(
        http.get("/api/comic_series/1", async () => {
          await new Promise((resolve) => setTimeout(resolve, 100));
          return HttpResponse.json(createMockComicSeries({ id: 1 }));
        }),
      );

      renderEditForm();

      expect(screen.getByText("Chargement…")).toBeInTheDocument();
    });

    it("renders page title for edit", async () => {
      server.use(
        http.get("/api/comic_series/1", () =>
          HttpResponse.json(
            createMockComicSeries({ id: 1, title: "Existing Comic" }),
          ),
        ),
      );

      renderEditForm();

      await waitFor(() => {
        expect(screen.getByText("Modifier la série")).toBeInTheDocument();
      });
    });

    it("pre-fills form with existing data", async () => {
      server.use(
        http.get("/api/comic_series/1", () =>
          HttpResponse.json(
            createMockComicSeries({
              description: "A great series",
              id: 1,
              publisher: "Glénat",
              title: "Existing Comic",
            }),
          ),
        ),
      );

      renderEditForm();

      await waitFor(() => {
        expect(screen.getByLabelText("Titre *")).toHaveValue("Existing Comic");
      });
      expect(screen.getByLabelText("Éditeur")).toHaveValue("Glénat");
      expect(screen.getByLabelText("Description")).toHaveValue("A great series");
    });

    it("renders save button instead of create", async () => {
      server.use(
        http.get("/api/comic_series/1", () =>
          HttpResponse.json(createMockComicSeries({ id: 1, title: "Test" })),
        ),
      );

      renderEditForm();

      await waitFor(() => {
        expect(screen.getByText("Enregistrer")).toBeInTheDocument();
      });
      expect(screen.queryByText("Créer")).not.toBeInTheDocument();
    });

    it("submits to update endpoint", async () => {
      const user = userEvent.setup();
      let updateCalled = false;

      server.use(
        http.get("/api/comic_series/1", () =>
          HttpResponse.json(createMockComicSeries({ id: 1, title: "Test" })),
        ),
        http.put("/api/comic_series/1", () => {
          updateCalled = true;
          return HttpResponse.json(
            createMockComicSeries({ id: 1, title: "Updated" }),
          );
        }),
      );

      renderEditForm();

      await waitFor(() => {
        expect(screen.getByLabelText("Titre *")).toHaveValue("Test");
      });

      await user.click(screen.getByText("Enregistrer"));

      await waitFor(() => {
        expect(updateCalled).toBe(true);
      });
    });
  });

  describe("Lookup section", () => {
    it("renders lookup section when online", () => {
      renderCreateForm();

      expect(screen.getByText("Recherche automatique")).toBeInTheDocument();
    });

    it("renders ISBN and title toggle buttons", () => {
      renderCreateForm();

      // "ISBN" appears both in the lookup toggle and the tomes table header,
      // so use getAllByText and check at least one button exists
      const isbnElements = screen.getAllByText("ISBN");
      const isbnButton = isbnElements.find((el) => el.tagName === "BUTTON");
      expect(isbnButton).toBeDefined();

      // "Titre" also appears in both lookup toggle and tomes table header
      const titreElements = screen.getAllByText("Titre");
      const titreButton = titreElements.find((el) => el.tagName === "BUTTON");
      expect(titreButton).toBeDefined();
    });

    it("shows offline message when offline", () => {
      Object.defineProperty(navigator, "onLine", {
        configurable: true,
        value: false,
        writable: true,
      });

      renderCreateForm();

      expect(screen.getByText("Recherche indisponible hors ligne")).toBeInTheDocument();

      Object.defineProperty(navigator, "onLine", {
        configurable: true,
        value: true,
        writable: true,
      });
    });

    it("fills form fields when clicking Appliquer on title lookup result", async () => {
      const user = userEvent.setup();

      const lookupResult = createMockLookupResult({
        authors: "Test Author",
        description: "A great description",
        publisher: "TestPub",
        thumbnail: "https://example.com/cover.jpg",
        title: "Lookup Title",
      });

      server.use(
        http.get("/api/lookup/title", () => HttpResponse.json(lookupResult)),
      );

      renderCreateForm();

      // Type a title in the lookup field (title mode is default)
      const lookupInput = screen.getByPlaceholderText("Titre de la série");
      await user.type(lookupInput, "Lookup Title");

      // Wait for lookup result to appear
      await waitFor(() => {
        expect(screen.getByText("Appliquer")).toBeInTheDocument();
      });

      // Click Appliquer
      await user.click(screen.getByText("Appliquer"));

      // Form fields should be filled
      await waitFor(() => {
        expect(screen.getByLabelText("Titre *")).toHaveValue("Lookup Title");
      });
      expect(screen.getByLabelText("Éditeur")).toHaveValue("TestPub");
      expect(screen.getByLabelText("Description")).toHaveValue("A great description");
      expect(screen.getByLabelText("URL de couverture")).toHaveValue("https://example.com/cover.jpg");
    });
  });

  describe("Cover URL preview", () => {
    it("shows preview image when cover URL is set", async () => {
      const user = userEvent.setup();
      renderCreateForm();

      await user.type(screen.getByLabelText("URL de couverture"), "https://example.com/cover.jpg");

      const preview = screen.getByAltText("Aperçu");
      expect(preview).toHaveAttribute("src", "https://example.com/cover.jpg");
    });
  });

  describe("Latest published issue field", () => {
    it("renders and accepts numeric input", async () => {
      const user = userEvent.setup();
      renderCreateForm();

      const field = screen.getByLabelText("Dernier tome paru");
      expect(field).toBeInTheDocument();

      await user.type(field, "42");
      expect(field).toHaveValue(42);
    });
  });

  describe("Tome operations", () => {
    it("adds a new tome row when clicking Ajouter", async () => {
      const user = userEvent.setup();
      renderCreateForm();

      // Initially 1 tome
      expect(screen.getByText("Tomes (1)")).toBeInTheDocument();

      await user.click(screen.getByText("Ajouter"));

      expect(screen.getByText("Tomes (2)")).toBeInTheDocument();
    });

    it("removes a tome row when clicking the trash icon", async () => {
      const user = userEvent.setup();
      renderCreateForm();

      // Add a second tome first
      await user.click(screen.getByText("Ajouter"));
      expect(screen.getByText("Tomes (2)")).toBeInTheDocument();

      // Each tome row has a red delete button as the last cell
      // Select only delete buttons in the tbody (not ISBN search buttons)
      const deleteButtons = document.querySelectorAll("tbody tr td:last-child button");
      expect(deleteButtons.length).toBe(2);
      await user.click(deleteButtons[0]);

      expect(screen.getByText("Tomes (1)")).toBeInTheDocument();
    });
  });

  describe("Submit behavior", () => {
    it("navigates to /comic/:id after successful create", async () => {
      const user = userEvent.setup();

      server.use(
        http.post("/api/comic_series", () =>
          HttpResponse.json(
            createMockComicSeries({ id: 42, title: "Created Comic" }),
            { status: 201 },
          ),
        ),
      );

      renderCreateForm();

      await user.type(screen.getByLabelText("Titre *"), "Created Comic");
      await user.click(screen.getByText("Créer"));

      await waitFor(() => {
        expect(screen.getByText("Comic Detail")).toBeInTheDocument();
      });
    });

    it("navigates to /comic/:id after successful edit", async () => {
      const user = userEvent.setup();

      server.use(
        http.get("/api/comic_series/1", () =>
          HttpResponse.json(createMockComicSeries({ id: 1, title: "Edit Me" })),
        ),
        http.put("/api/comic_series/1", () =>
          HttpResponse.json(createMockComicSeries({ id: 1, title: "Edited" })),
        ),
      );

      renderEditForm();

      await waitFor(() => {
        expect(screen.getByLabelText("Titre *")).toHaveValue("Edit Me");
      });

      await user.click(screen.getByText("Enregistrer"));

      await waitFor(() => {
        expect(screen.getByText("Comic Detail")).toBeInTheDocument();
      });
    });

    it("navigates to / when submitting offline", async () => {
      const user = userEvent.setup();

      Object.defineProperty(navigator, "onLine", {
        configurable: true,
        value: false,
        writable: true,
      });

      renderCreateForm();

      await user.type(screen.getByLabelText("Titre *"), "Offline Comic");
      await user.click(screen.getByText("Créer"));

      await waitFor(() => {
        expect(screen.getByText("Home Page")).toBeInTheDocument();
      });

      Object.defineProperty(navigator, "onLine", {
        configurable: true,
        value: true,
        writable: true,
      });
    });

    it("disables submit button while saving (isSaving)", async () => {
      const user = userEvent.setup();

      server.use(
        http.post("/api/comic_series", async () => {
          await new Promise((resolve) => setTimeout(resolve, 100));
          return HttpResponse.json(
            createMockComicSeries({ id: 1, title: "Slow" }),
            { status: 201 },
          );
        }),
      );

      renderCreateForm();

      await user.type(screen.getByLabelText("Titre *"), "Slow Comic");

      const createButton = screen.getByText("Créer");
      await user.click(createButton);

      // Button should be disabled while mutation is pending
      await waitFor(() => {
        expect(createButton).toBeDisabled();
      });
    });
  });
});
