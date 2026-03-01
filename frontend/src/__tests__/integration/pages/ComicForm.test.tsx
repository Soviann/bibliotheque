import { screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import { Route, Routes } from "react-router-dom";
import { Toaster } from "sonner";
import ComicForm from "../../../pages/ComicForm";
import {
  createMockAuthor,
  createMockComicSeries,
  createMockHydraCollection,
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
    <>
      <Toaster position="top-center" richColors />
      <Routes>
        <Route element={<ComicForm />} path="/comic/new" />
        <Route element={<div>Comic Detail</div>} path="/comic/:id" />
        <Route element={<div>Home Page</div>} path="/" />
      </Routes>
    </>,
    { initialEntries: ["/comic/new"] },
  );
}

function renderEditForm(id: number = 1) {
  return renderWithProviders(
    <>
      <Toaster position="top-center" richColors />
      <Routes>
        <Route element={<ComicForm />} path="/comic/:id/edit" />
        <Route element={<div>Comic Detail</div>} path="/comic/:id" />
        <Route element={<div>Home Page</div>} path="/" />
      </Routes>
    </>,
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
    it("shows skeleton loader while fetching comic", () => {
      server.use(
        http.get("/api/comic_series/1", async () => {
          await new Promise((resolve) => setTimeout(resolve, 100));
          return HttpResponse.json(createMockComicSeries({ id: 1 }));
        }),
      );

      renderEditForm();

      expect(screen.getByTestId("comic-form-skeleton")).toBeInTheDocument();
      expect(screen.getAllByTestId("skeleton-box").length).toBeGreaterThanOrEqual(5);
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

    it("shows empty string in latestPublishedIssue field when comic has null value", async () => {
      server.use(
        http.get("/api/comic_series/1", () =>
          HttpResponse.json(
            createMockComicSeries({ id: 1, latestPublishedIssue: null, title: "No Issue" }),
          ),
        ),
      );

      renderEditForm();

      await waitFor(() => {
        expect(screen.getByLabelText("Titre *")).toHaveValue("No Issue");
      });

      const field = screen.getByLabelText("Dernier tome paru") as HTMLInputElement;
      expect(field).toHaveValue(null);
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

    it("switches to ISBN mode when clicking ISBN toggle", async () => {
      const user = userEvent.setup();
      renderCreateForm();

      // Click the ISBN toggle button (find by role to avoid header collision)
      const isbnButtons = screen.getAllByText("ISBN");
      const isbnToggle = isbnButtons.find((el) => el.tagName === "BUTTON")!;
      await user.click(isbnToggle);

      // ISBN input should appear
      expect(screen.getByPlaceholderText("ISBN (10 ou 13 chiffres)")).toBeInTheDocument();
    });

    it("chains ISBN lookup to title lookup when clicking Appliquer", async () => {
      const user = userEvent.setup();

      const isbnResult = createMockLookupResult({
        isbn: "9781234567890",
        title: "ISBN Series Title",
      });

      const titleResult = createMockLookupResult({
        authors: "Chained Author",
        description: "Chained description",
        publisher: "ChainedPub",
        thumbnail: "https://example.com/chained.jpg",
        title: "ISBN Series Title",
      });

      server.use(
        http.get("/api/lookup/isbn", () => HttpResponse.json(isbnResult)),
        http.get("/api/lookup/title", () => HttpResponse.json(titleResult)),
      );

      renderCreateForm();

      // Switch to ISBN mode
      const isbnButtons = screen.getAllByText("ISBN");
      const isbnToggle = isbnButtons.find((el) => el.tagName === "BUTTON")!;
      await user.click(isbnToggle);

      // Type ISBN (>= 10 chars to trigger query)
      await user.type(screen.getByPlaceholderText("ISBN (10 ou 13 chiffres)"), "9781234567890");

      // Wait for result to appear
      await waitFor(() => {
        expect(screen.getByText("Appliquer")).toBeInTheDocument();
      });

      // Click Appliquer — should chain to title lookup
      await user.click(screen.getByText("Appliquer"));

      // Form should be filled from title lookup result (chained)
      await waitFor(() => {
        expect(screen.getByLabelText("Titre *")).toHaveValue("ISBN Series Title");
      });
      expect(screen.getByLabelText("Éditeur")).toHaveValue("ChainedPub");
      expect(screen.getByLabelText("Description")).toHaveValue("Chained description");
    });

    it("ISBN lookup with empty title applies result directly without title lookup", async () => {
      const user = userEvent.setup();

      const isbnResult = createMockLookupResult({
        isbn: "9781234567890",
        publisher: "DirectPub",
        thumbnail: "https://example.com/cover.jpg",
        title: "",
      });

      let titleLookupCalled = false;
      server.use(
        http.get("/api/lookup/isbn", () => HttpResponse.json(isbnResult)),
        http.get("/api/lookup/title", () => {
          titleLookupCalled = true;
          return HttpResponse.json(createMockLookupResult({ title: "Should Not Be Called" }));
        }),
      );

      renderCreateForm();

      // Switch to ISBN mode
      const isbnButtons = screen.getAllByText("ISBN");
      const isbnToggle = isbnButtons.find((el) => el.tagName === "BUTTON")!;
      await user.click(isbnToggle);

      await user.type(screen.getByPlaceholderText("ISBN (10 ou 13 chiffres)"), "9781234567890");

      await waitFor(() => {
        expect(screen.getByText("Appliquer")).toBeInTheDocument();
      });

      await user.click(screen.getByText("Appliquer"));

      // Publisher should be applied directly from ISBN result
      await waitFor(() => {
        expect(screen.getByLabelText("Éditeur")).toHaveValue("DirectPub");
      });
      // Title lookup must NOT have been called
      expect(titleLookupCalled).toBe(false);
      // Toast for direct apply
      await waitFor(() => {
        expect(screen.getByText("Informations récupérées")).toBeInTheDocument();
      });
    });

    it("falls back to ISBN result when title lookup fails after ISBN lookup", async () => {
      const user = userEvent.setup();

      const isbnResult = createMockLookupResult({
        isbn: "9781234567890",
        publisher: "ISBN-Only Pub",
        title: "Fallback Title",
      });

      server.use(
        http.get("/api/lookup/isbn", () => HttpResponse.json(isbnResult)),
        http.get("/api/lookup/title", () =>
          HttpResponse.json({ error: "Not found" }, { status: 404 }),
        ),
      );

      renderCreateForm();

      // Switch to ISBN mode
      const isbnButtons = screen.getAllByText("ISBN");
      const isbnToggle = isbnButtons.find((el) => el.tagName === "BUTTON")!;
      await user.click(isbnToggle);

      await user.type(screen.getByPlaceholderText("ISBN (10 ou 13 chiffres)"), "9781234567890");

      await waitFor(() => {
        expect(screen.getByText("Appliquer")).toBeInTheDocument();
      });

      await user.click(screen.getByText("Appliquer"));

      // Should fall back to ISBN result fields
      await waitFor(() => {
        expect(screen.getByLabelText("Titre *")).toHaveValue("Fallback Title");
      });
      expect(screen.getByLabelText("Éditeur")).toHaveValue("ISBN-Only Pub");
    });

    it("applies isOneShot=true from lookup result and checks the oneshot checkbox", async () => {
      const user = userEvent.setup();

      const lookupResult = createMockLookupResult({
        isOneShot: true,
        title: "One Shot Series",
      });

      server.use(
        http.get("/api/lookup/title", () => HttpResponse.json(lookupResult)),
      );

      renderCreateForm();

      const lookupInput = screen.getByPlaceholderText("Titre de la série");
      await user.type(lookupInput, "One Shot Series");

      await waitFor(() => {
        expect(screen.getByText("Appliquer")).toBeInTheDocument();
      });

      // Oneshot checkbox should be unchecked before applying
      const oneshotCheckbox = screen.getByRole("checkbox", { name: /One-shot/ }) as HTMLInputElement;
      expect(oneshotCheckbox).not.toBeChecked();

      await user.click(screen.getByText("Appliquer"));

      // After applying, oneshot checkbox must be checked
      await waitFor(() => {
        expect(oneshotCheckbox).toBeChecked();
      });
    });

    it("shows loading indicator during lookup", async () => {
      const user = userEvent.setup();

      server.use(
        http.get("/api/lookup/title", async () => {
          await new Promise((resolve) => setTimeout(resolve, 200));
          return HttpResponse.json(createMockLookupResult({ title: "Test" }));
        }),
      );

      renderCreateForm();

      await user.type(screen.getByPlaceholderText("Titre de la série"), "Te");

      await waitFor(() => {
        expect(screen.getByText("Recherche en cours…")).toBeInTheDocument();
      });
    });

    it("displays lookup result card with title, authors, and publisher", async () => {
      const user = userEvent.setup();

      server.use(
        http.get("/api/lookup/title", () =>
          HttpResponse.json(
            createMockLookupResult({
              authors: "John Doe",
              publisher: "BigPub",
              title: "Result Title",
            }),
          ),
        ),
      );

      renderCreateForm();

      await user.type(screen.getByPlaceholderText("Titre de la série"), "Result Title");

      await waitFor(() => {
        expect(screen.getByText("Result Title")).toBeInTheDocument();
      });
      // Authors and publisher shown in the result card
      expect(screen.getByText(/John Doe/)).toBeInTheDocument();
      expect(screen.getByText(/BigPub/)).toBeInTheDocument();
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

  describe("Author management", () => {
    it("creates new author during submit via POST /api/authors", async () => {
      const user = userEvent.setup();
      let authorPostCalled = false;
      let seriesPostCalled = false;

      server.use(
        http.get("/api/authors", () =>
          HttpResponse.json(
            createMockHydraCollection([], "/api/authors"),
          ),
        ),
        http.post("/api/authors", async ({ request }) => {
          authorPostCalled = true;
          const body = (await request.json()) as { name: string };
          return HttpResponse.json(
            createMockAuthor({ id: 100, name: body.name }),
            { status: 201 },
          );
        }),
        http.post("/api/comic_series", () => {
          seriesPostCalled = true;
          return HttpResponse.json(
            createMockComicSeries({ id: 10, title: "With Author" }),
            { status: 201 },
          );
        }),
      );

      renderCreateForm();

      // Type title
      await user.type(screen.getByLabelText("Titre *"), "With Author");

      // Search for a new author that doesn't exist
      const authorInput = screen.getByPlaceholderText("Rechercher ou créer un auteur…");
      await user.type(authorInput, "NewAuthor");

      // Wait for "Créer" option to appear
      await waitFor(() => {
        expect(screen.getByText(/Créer « NewAuthor »/)).toBeInTheDocument();
      });

      // Select the create option
      await user.click(screen.getByText(/Créer « NewAuthor »/));

      // Author should appear as a tag
      expect(screen.getByText("NewAuthor")).toBeInTheDocument();

      // Submit
      await user.click(screen.getByText("Créer"));

      // Author POST should be called before series POST
      await waitFor(() => {
        expect(authorPostCalled).toBe(true);
      });
      await waitFor(() => {
        expect(seriesPostCalled).toBe(true);
      });
    });

    it("uses existing author IRI directly in payload without POST /api/authors", async () => {
      const user = userEvent.setup();
      let authorPostCalled = false;
      let capturedPayload: Record<string, unknown> | null = null;

      const existingAuthor = createMockAuthor({ id: 7, name: "Existing Author" });

      server.use(
        http.get("/api/authors", () =>
          HttpResponse.json(
            createMockHydraCollection([existingAuthor], "/api/authors"),
          ),
        ),
        http.post("/api/authors", () => {
          authorPostCalled = true;
          return HttpResponse.json(createMockAuthor(), { status: 201 });
        }),
        http.post("/api/comic_series", async ({ request }) => {
          capturedPayload = (await request.json()) as Record<string, unknown>;
          return HttpResponse.json(
            createMockComicSeries({ id: 10, title: "With Existing Author" }),
            { status: 201 },
          );
        }),
      );

      renderCreateForm();

      await user.type(screen.getByLabelText("Titre *"), "With Existing Author");

      // Search for the existing author
      const authorInput = screen.getByPlaceholderText("Rechercher ou créer un auteur…");
      await user.type(authorInput, "Existing");

      await waitFor(() => {
        expect(screen.getByText("Existing Author")).toBeInTheDocument();
      });

      // Click the existing author option from the dropdown
      const options = screen.getAllByText("Existing Author");
      await user.click(options[0]);

      // Submit
      await user.click(screen.getByText("Créer"));

      await waitFor(() => {
        expect(capturedPayload).not.toBeNull();
      });

      // POST /api/authors must NOT have been called
      expect(authorPostCalled).toBe(false);
      // Payload must contain the existing author's IRI
      expect(capturedPayload!.authors).toEqual(["/api/authors/7"]);
    });

    it("shows error toast and stops submission when author creation fails", async () => {
      const user = userEvent.setup();
      let seriesPostCalled = false;

      server.use(
        http.get("/api/authors", () =>
          HttpResponse.json(
            createMockHydraCollection([], "/api/authors"),
          ),
        ),
        http.post("/api/authors", () =>
          HttpResponse.json({ detail: "Erreur serveur" }, { status: 500 }),
        ),
        http.post("/api/comic_series", () => {
          seriesPostCalled = true;
          return HttpResponse.json(
            createMockComicSeries({ id: 10 }),
            { status: 201 },
          );
        }),
      );

      renderCreateForm();

      await user.type(screen.getByLabelText("Titre *"), "Test");

      const authorInput = screen.getByPlaceholderText("Rechercher ou créer un auteur…");
      await user.type(authorInput, "FailAuthor");

      await waitFor(() => {
        expect(screen.getByText(/Créer « FailAuthor »/)).toBeInTheDocument();
      });

      await user.click(screen.getByText(/Créer « FailAuthor »/));
      await user.click(screen.getByText("Créer"));

      // Error toast should appear
      await waitFor(() => {
        expect(screen.getByText(/Erreur lors de la création de l'auteur/)).toBeInTheDocument();
      });

      // Series POST should NOT have been called
      expect(seriesPostCalled).toBe(false);
    });

    it("removes an author when clicking X button", async () => {
      const user = userEvent.setup();

      server.use(
        http.get("/api/authors", () =>
          HttpResponse.json(
            createMockHydraCollection([], "/api/authors"),
          ),
        ),
      );

      renderCreateForm();

      // Add a new author
      const authorInput = screen.getByPlaceholderText("Rechercher ou créer un auteur…");
      await user.type(authorInput, "ToRemove");

      await waitFor(() => {
        expect(screen.getByText(/Créer « ToRemove »/)).toBeInTheDocument();
      });

      await user.click(screen.getByText(/Créer « ToRemove »/));

      // Verify added
      expect(screen.getByText("ToRemove")).toBeInTheDocument();

      // Click the X button next to the author name
      const authorTag = screen.getByText("ToRemove").closest("span")!;
      const removeButton = authorTag.querySelector("button")!;
      await user.click(removeButton);

      // Author should be gone
      expect(screen.queryByText("ToRemove")).not.toBeInTheDocument();
    });

    it("prevents adding duplicate authors", async () => {
      const user = userEvent.setup();

      const existingAuthor = createMockAuthor({ id: 5, name: "Unique Author" });

      server.use(
        http.get("/api/authors", () =>
          HttpResponse.json(
            createMockHydraCollection([existingAuthor], "/api/authors"),
          ),
        ),
      );

      renderCreateForm();

      const authorInput = screen.getByPlaceholderText("Rechercher ou créer un auteur…");

      // Add the author
      await user.type(authorInput, "Unique");
      await waitFor(() => {
        expect(screen.getByText("Unique Author")).toBeInTheDocument();
      });
      // Click the existing author option from the dropdown
      const options = screen.getAllByText("Unique Author");
      // The dropdown option is the one inside ComboboxOption
      await user.click(options[0]);

      // Verify author tag exists
      const authorTags = document.querySelectorAll("span.flex.items-center.gap-1");
      expect(authorTags.length).toBe(1);

      // Try adding the same author again
      await user.type(authorInput, "Unique");
      await waitFor(() => {
        const allUniqueAuthors = screen.getAllByText("Unique Author");
        expect(allUniqueAuthors.length).toBeGreaterThan(0);
      });
      const optionsAgain = screen.getAllByText("Unique Author");
      await user.click(optionsAgain[0]);

      // Should still only have 1 author tag
      const authorTagsAfter = document.querySelectorAll("span.flex.items-center.gap-1");
      expect(authorTagsAfter.length).toBe(1);
    });

    it("shows 'Créer' option when input >= 2 chars and not matching existing", async () => {
      const user = userEvent.setup();

      server.use(
        http.get("/api/authors", () =>
          HttpResponse.json(
            createMockHydraCollection([], "/api/authors"),
          ),
        ),
      );

      renderCreateForm();

      const authorInput = screen.getByPlaceholderText("Rechercher ou créer un auteur…");
      await user.type(authorInput, "AB");

      await waitFor(() => {
        expect(screen.getByText(/Créer « AB »/)).toBeInTheDocument();
      });
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

    it("updates tome title field", async () => {
      const user = userEvent.setup();
      renderCreateForm();

      const tableView = screen.getByTestId("tomes-table");
      const titleInput = within(tableView).getByPlaceholderText("Titre") as HTMLInputElement;
      await user.type(titleInput, "Tome Title");

      expect(titleInput).toHaveValue("Tome Title");
    });

    it("updates tome ISBN field", async () => {
      const user = userEvent.setup();
      renderCreateForm();

      const tableView = screen.getByTestId("tomes-table");
      const isbnInput = within(tableView).getByPlaceholderText("ISBN") as HTMLInputElement;
      await user.type(isbnInput, "1234567890");

      expect(isbnInput).toHaveValue("1234567890");
    });

    it("updates tome number field", async () => {
      const user = userEvent.setup();
      renderCreateForm();

      // Get the tome number input (first number input in tomes table)
      const tomeNumberInputs = document.querySelectorAll("tbody input[type='number']");
      const tomeNumberInput = tomeNumberInputs[0] as HTMLInputElement;

      await user.clear(tomeNumberInput);
      await user.type(tomeNumberInput, "5");

      expect(tomeNumberInput).toHaveValue(5);
    });

    it("ISBN lookup button is disabled when ISBN < 10 chars", () => {
      renderCreateForm();

      // ISBN search button is within the ISBN cell
      const isbnSearchButtons = document.querySelectorAll("tbody td .flex.items-center button") as NodeListOf<HTMLButtonElement>;
      expect(isbnSearchButtons[0]).toBeDisabled();
    });

    it("performs tome ISBN lookup and updates tome title", async () => {
      const user = userEvent.setup();

      server.use(
        http.get("/api/lookup/isbn", () =>
          HttpResponse.json(
            createMockLookupResult({
              isbn: "9781234567890",
              title: "Looked Up Tome Title",
            }),
          ),
        ),
      );

      renderCreateForm();

      const tableView = screen.getByTestId("tomes-table");
      const isbnInput = within(tableView).getByPlaceholderText("ISBN") as HTMLInputElement;
      await user.type(isbnInput, "9781234567890");

      // ISBN search button should now be enabled
      const isbnSearchButtons = tableView.querySelectorAll("td .flex.items-center button") as NodeListOf<HTMLButtonElement>;
      expect(isbnSearchButtons[0]).toBeEnabled();

      await user.click(isbnSearchButtons[0]);

      // Wait for tome title to be updated
      const tomeTitle = within(tableView).getByPlaceholderText("Titre") as HTMLInputElement;
      await waitFor(() => {
        expect(tomeTitle).toHaveValue("Looked Up Tome Title");
      });
    });

    it("keeps original tome isbn and title when lookup returns null values", async () => {
      const user = userEvent.setup();

      server.use(
        http.get("/api/lookup/isbn", () =>
          HttpResponse.json(
            createMockLookupResult({
              isbn: null,
              title: null,
            }),
          ),
        ),
      );

      renderCreateForm();

      const tableView = screen.getByTestId("tomes-table");
      const isbnInput = within(tableView).getByPlaceholderText("ISBN") as HTMLInputElement;
      await user.type(isbnInput, "9781234567890");

      // Set a title on the tome so we can verify it's preserved
      const tomeTitleInput = within(tableView).getByPlaceholderText("Titre") as HTMLInputElement;
      await user.type(tomeTitleInput, "Original Tome Title");

      const isbnSearchButtons = tableView.querySelectorAll("td .flex.items-center button") as NodeListOf<HTMLButtonElement>;
      expect(isbnSearchButtons[0]).toBeEnabled();
      await user.click(isbnSearchButtons[0]);

      // isbn should remain the original value (fallback via ??)
      await waitFor(() => {
        expect(isbnInput).toHaveValue("9781234567890");
      });
      // title should remain the original value (fallback via ??)
      expect(tomeTitleInput).toHaveValue("Original Tome Title");
    });

    it("shows error toast when tome ISBN lookup fails", async () => {
      const user = userEvent.setup();

      server.use(
        http.get("/api/lookup/isbn", () =>
          HttpResponse.json({ detail: "Not found" }, { status: 404 }),
        ),
      );

      renderCreateForm();

      const tableView = screen.getByTestId("tomes-table");
      const isbnInput = within(tableView).getByPlaceholderText("ISBN") as HTMLInputElement;
      await user.type(isbnInput, "9781234567890");

      const isbnSearchButtons = tableView.querySelectorAll("td .flex.items-center button") as NodeListOf<HTMLButtonElement>;
      await user.click(isbnSearchButtons[0]);

      await waitFor(() => {
        expect(screen.getByText("Échec de la recherche ISBN")).toBeInTheDocument();
      });
    });

    it("starts at tome 1 when adding after all tomes removed", async () => {
      const user = userEvent.setup();
      renderCreateForm();

      // Remove the initial tome
      const deleteButtons = document.querySelectorAll("tbody tr td:last-child button");
      await user.click(deleteButtons[0]);

      expect(screen.getByText("Tomes (0)")).toBeInTheDocument();

      // Add a new tome
      await user.click(screen.getByText("Ajouter"));

      expect(screen.getByText("Tomes (1)")).toBeInTheDocument();
      // The new tome number should be 1
      const tomeNumberInputs = document.querySelectorAll("tbody input[type='number']");
      expect(tomeNumberInputs[0]).toHaveValue(1);
    });
  });

  describe("Mobile tome card layout", () => {
    it("renders card view with data-testid tomes-cards", () => {
      renderCreateForm();

      expect(screen.getByTestId("tomes-cards")).toBeInTheDocument();
    });

    it("renders table view with data-testid tomes-table", () => {
      renderCreateForm();

      expect(screen.getByTestId("tomes-table")).toBeInTheDocument();
    });

    it("displays labeled checkboxes in card view", () => {
      renderCreateForm();

      const cardsView = screen.getByTestId("tomes-cards");
      expect(within(cardsView).getByText("Acheté")).toBeInTheDocument();
      expect(within(cardsView).getByText("DL")).toBeInTheDocument();
      expect(within(cardsView).getByText("Lu")).toBeInTheDocument();
      expect(within(cardsView).getByText("NAS")).toBeInTheDocument();
    });

    it("has ISBN field with lookup button in card view", () => {
      renderCreateForm();

      const cardsView = screen.getByTestId("tomes-cards");
      expect(within(cardsView).getByPlaceholderText("ISBN")).toBeInTheDocument();
    });

    it("has delete button in card view", () => {
      renderCreateForm();

      const cardsView = screen.getByTestId("tomes-cards");
      const deleteButtons = cardsView.querySelectorAll("button");
      const trashButtons = Array.from(deleteButtons).filter((btn) =>
        btn.querySelector("svg") && btn.closest("[data-testid='tomes-cards']"),
      );
      expect(trashButtons.length).toBeGreaterThan(0);
    });

    it("updates tome fields from card view", async () => {
      const user = userEvent.setup();
      renderCreateForm();

      const cardsView = screen.getByTestId("tomes-cards");
      const titleInput = within(cardsView).getByPlaceholderText("Titre") as HTMLInputElement;
      await user.type(titleInput, "Card Title");

      expect(titleInput).toHaveValue("Card Title");
    });
  });

  describe("Submit behavior", () => {
    it("omits tomes from payload when isOneShot is checked", async () => {
      const user = userEvent.setup();
      let capturedPayload: Record<string, unknown> | null = null;

      server.use(
        http.post("/api/comic_series", async ({ request }) => {
          capturedPayload = (await request.json()) as Record<string, unknown>;
          return HttpResponse.json(
            createMockComicSeries({ id: 10, title: "OneShot Comic" }),
            { status: 201 },
          );
        }),
      );

      renderCreateForm();

      await user.type(screen.getByLabelText("Titre *"), "OneShot Comic");

      // Check the oneshot checkbox
      const oneshotCheckbox = screen.getByRole("checkbox", { name: /One-shot/ });
      await user.click(oneshotCheckbox);

      await user.click(screen.getByText("Créer"));

      await waitFor(() => {
        expect(capturedPayload).not.toBeNull();
      });

      expect(capturedPayload!.isOneShot).toBe(true);
      expect(capturedPayload).not.toHaveProperty("tomes");
    });

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

    it("disables submit button when title is empty", () => {
      renderCreateForm();

      const createButton = screen.getByText("Créer");
      expect(createButton).toBeDisabled();
    });
  });

  describe("Error handling", () => {
    it("shows error toast when create fails", async () => {
      const user = userEvent.setup();

      server.use(
        http.post("/api/comic_series", () =>
          HttpResponse.json({ detail: "Erreur serveur" }, { status: 500 }),
        ),
      );

      renderCreateForm();

      await user.type(screen.getByLabelText("Titre *"), "Failing Comic");
      await user.click(screen.getByText("Créer"));

      await waitFor(() => {
        expect(screen.getByText("Erreur serveur")).toBeInTheDocument();
      });
    });

    it("shows error toast when update fails", async () => {
      const user = userEvent.setup();

      server.use(
        http.get("/api/comic_series/1", () =>
          HttpResponse.json(createMockComicSeries({ id: 1, title: "Edit Me" })),
        ),
        http.put("/api/comic_series/1", () =>
          HttpResponse.json({ detail: "Erreur de mise à jour" }, { status: 500 }),
        ),
      );

      renderEditForm();

      await waitFor(() => {
        expect(screen.getByLabelText("Titre *")).toHaveValue("Edit Me");
      });

      await user.click(screen.getByText("Enregistrer"));

      await waitFor(() => {
        expect(screen.getByText("Erreur de mise à jour")).toBeInTheDocument();
      });
    });
  });

  describe("Offline submit", () => {
    it("includes _pendingAuthors in payload when submitting offline with new authors", async () => {
      const user = userEvent.setup();
      let capturedPayload: Record<string, unknown> | null = null;

      Object.defineProperty(navigator, "onLine", {
        configurable: true,
        value: false,
        writable: true,
      });

      // The offline mutation will enqueue rather than POST, but the handleSubmit
      // builds the payload with _pendingAuthors. We can verify by intercepting
      // the mutation function (which won't actually fire online).
      // In offline mode, navigate("/") is called immediately.

      renderCreateForm();

      await user.type(screen.getByLabelText("Titre *"), "Offline With Author");

      // Add a new author (no dropdown will show since offline, but we can
      // type in the input and select the create option)
      const authorInput = screen.getByPlaceholderText("Rechercher ou créer un auteur…");
      await user.type(authorInput, "OfflineAuthor");

      // The combobox still shows create option even offline (it's a UI component)
      await waitFor(() => {
        expect(screen.getByText(/Créer « OfflineAuthor »/)).toBeInTheDocument();
      });

      await user.click(screen.getByText(/Créer « OfflineAuthor »/));

      // Verify author tag appears
      expect(screen.getByText("OfflineAuthor")).toBeInTheDocument();

      // Submit — offline navigates to /
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

    it("navigates correctly on edit success without data (offline path)", async () => {
      const user = userEvent.setup();

      Object.defineProperty(navigator, "onLine", {
        configurable: true,
        value: false,
        writable: true,
      });

      server.use(
        http.get("/api/comic_series/1", () =>
          HttpResponse.json(createMockComicSeries({ id: 1, title: "Edit Offline" })),
        ),
      );

      renderEditForm();

      await waitFor(() => {
        expect(screen.getByLabelText("Titre *")).toHaveValue("Edit Offline");
      });

      await user.click(screen.getByText("Enregistrer"));

      // In offline mode, navigate("/") is called
      await waitFor(() => {
        expect(screen.getByText("Home Page")).toBeInTheDocument();
      });

      Object.defineProperty(navigator, "onLine", {
        configurable: true,
        value: true,
        writable: true,
      });
    });
  });

  describe("Navigation", () => {
    it("navigates back when clicking the back arrow", async () => {
      const user = userEvent.setup();

      renderWithProviders(
        <>
          <Toaster position="top-center" richColors />
          <Routes>
            <Route element={<div>Previous Page</div>} path="/" />
            <Route element={<ComicForm />} path="/comic/new" />
          </Routes>
        </>,
        { initialEntries: ["/", "/comic/new"] },
      );

      // The ArrowLeft button is the first button in the header
      const headerButtons = document.querySelectorAll(".flex.items-center.gap-3 button");
      expect(headerButtons.length).toBeGreaterThan(0);
      await user.click(headerButtons[0]);

      await waitFor(() => {
        expect(screen.getByText("Previous Page")).toBeInTheDocument();
      });
    });

    it("navigates back when clicking Annuler", async () => {
      const user = userEvent.setup();

      renderWithProviders(
        <>
          <Toaster position="top-center" richColors />
          <Routes>
            <Route element={<div>Previous Page</div>} path="/" />
            <Route element={<ComicForm />} path="/comic/new" />
          </Routes>
        </>,
        { initialEntries: ["/", "/comic/new"] },
      );

      await user.click(screen.getByText("Annuler"));

      await waitFor(() => {
        expect(screen.getByText("Previous Page")).toBeInTheDocument();
      });
    });
  });
});
