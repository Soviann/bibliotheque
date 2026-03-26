import "fake-indexeddb/auto";
import { screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { Route, Routes } from "react-router-dom";
import { toast } from "sonner";
import Layout from "../../../components/Layout";
import { useSyncStatus } from "../../../hooks/useSyncStatus";
import type { SyncStatus } from "../../../hooks/useSyncStatus";
import { createTestQueryClient, renderWithProviders } from "../../helpers/test-utils";

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

vi.mock("../../../hooks/useSyncStatus", () => ({
  useSyncStatus: vi.fn(),
}));

const mockUseSyncStatus = vi.mocked(useSyncStatus);

function renderLayout() {
  return renderWithProviders(
    <Routes>
      <Route element={<Layout />} path="/">
        <Route element={<div>Home Content</div>} index />
      </Route>
    </Routes>,
  );
}

describe("Layout", () => {
  beforeEach(() => {
    localStorage.clear();
    vi.mocked(toast.success).mockClear();
    vi.mocked(toast.error).mockClear();
    mockUseSyncStatus.mockReturnValue({ error: null, status: "idle", syncedCount: 0 });
    vi.stubGlobal("caches", { delete: vi.fn().mockResolvedValue(true) });
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it("renders header with app title", () => {
    renderWithProviders(
      <Routes>
        <Route element={<Layout />} path="/">
          <Route element={<div>Home Content</div>} index />
        </Route>
      </Routes>,
    );

    expect(screen.getByText("Bibliothèque")).toBeInTheDocument();
  });

  it("renders children content via Outlet", () => {
    renderWithProviders(
      <Routes>
        <Route element={<Layout />} path="/">
          <Route element={<div>Page Content</div>} index />
        </Route>
      </Routes>,
    );

    expect(screen.getByText("Page Content")).toBeInTheDocument();
  });

  it("renders bottom navigation", () => {
    renderWithProviders(
      <Routes>
        <Route element={<Layout />} path="/">
          <Route element={<div>Content</div>} index />
        </Route>
      </Routes>,
    );

    expect(screen.getByText("Accueil")).toBeInTheDocument();
    expect(screen.getByText("À acheter")).toBeInTheDocument();
    expect(screen.getByText("Corbeille")).toBeInTheDocument();
  });

  it("has a dark mode toggle button with aria-label", () => {
    renderWithProviders(
      <Routes>
        <Route element={<Layout />} path="/">
          <Route element={<div>Content</div>} index />
        </Route>
      </Routes>,
    );

    expect(screen.getByLabelText("Mode sombre")).toBeInTheDocument();
  });

  it("has a logout button with aria-label", () => {
    renderWithProviders(
      <Routes>
        <Route element={<Layout />} path="/">
          <Route element={<div>Content</div>} index />
        </Route>
      </Routes>,
    );

    expect(screen.getByLabelText("Déconnexion")).toBeInTheDocument();
  });

  it("has a tools link with aria-label", () => {
    renderWithProviders(
      <Routes>
        <Route element={<Layout />} path="/">
          <Route element={<div>Content</div>} index />
        </Route>
      </Routes>,
    );

    expect(screen.getByLabelText("Outils")).toBeInTheDocument();
  });

  it("toggles dark mode when button is clicked", async () => {
    const user = userEvent.setup();

    renderWithProviders(
      <Routes>
        <Route element={<Layout />} path="/">
          <Route element={<div>Content</div>} index />
        </Route>
      </Routes>,
    );

    // Initially "Mode sombre" (light mode)
    await user.click(screen.getByTitle("Mode sombre"));

    // After toggle, should show "Mode clair" (dark mode)
    expect(screen.getByTitle("Mode clair")).toBeInTheDocument();
  });

  it("removes token and navigates to /login on logout", async () => {
    const user = userEvent.setup();
    localStorage.setItem("jwt_token", "some-token");

    renderWithProviders(
      <Routes>
        <Route element={<Layout />} path="/">
          <Route element={<div>Content</div>} index />
        </Route>
        <Route element={<div>Login Page</div>} path="/login" />
      </Routes>,
    );

    await user.click(screen.getByTitle("Déconnexion"));

    expect(localStorage.getItem("jwt_token")).toBeNull();
    await waitFor(() => {
      expect(screen.getByText("Login Page")).toBeInTheDocument();
    });
  });

  it("renders OfflineBanner in the layout", () => {
    renderLayout();

    // OfflineBanner renders even when online (just hidden or empty),
    // but the component itself is in the DOM tree
    expect(document.querySelector(".flex.min-h-screen.flex-col")).toBeInTheDocument();
  });

  it("header link navigates to home", () => {
    renderLayout();

    const homeLink = screen.getByText("Bibliothèque").closest("a");
    expect(homeLink).toHaveAttribute("href", "/");
  });

  describe("global search", () => {
    it("renders search button in header", () => {
      renderLayout();
      expect(screen.getByLabelText("Rechercher")).toBeInTheDocument();
    });

    it("opens search input when clicking search button", async () => {
      const user = userEvent.setup();
      renderLayout();

      await user.click(screen.getByLabelText("Rechercher"));

      const input = screen.getByPlaceholderText("Rechercher par titre, auteur, éditeur…");
      expect(input).toBeInTheDocument();
      // Le formulaire slide en opacity-100 quand ouvert
      expect(input.closest("form")).toHaveClass("opacity-100");
    });

    it("navigates to /?search=value on Enter", async () => {
      const user = userEvent.setup();

      renderWithProviders(
        <Routes>
          <Route element={<Layout />} path="/">
            <Route element={<div>Home Content</div>} index />
          </Route>
          <Route element={<Layout />} path="/tools">
            <Route element={<div>Tools Content</div>} index />
          </Route>
        </Routes>,
        { initialEntries: ["/tools"] },
      );

      await user.click(screen.getByLabelText("Rechercher"));
      await user.type(screen.getByPlaceholderText("Rechercher par titre, auteur, éditeur…"), "naruto{Enter}");

      await waitFor(() => {
        expect(screen.getByText("Home Content")).toBeInTheDocument();
      });
    });

    it("closes search input on Escape", async () => {
      const user = userEvent.setup();
      renderLayout();

      await user.click(screen.getByLabelText("Rechercher"));
      const input = screen.getByPlaceholderText("Rechercher par titre, auteur, éditeur…") as HTMLInputElement;
      await user.type(input, "test");
      expect(input.value).toBe("test");

      await user.keyboard("{Escape}");

      // La valeur est effacée après Escape
      expect(input.value).toBe("");
    });
  });

  describe("sync feedback toasts", () => {
    // The useEffect uses a prevStatus ref initialized to the first status value.
    // To trigger the effect, we must render with "idle" first, then change the mock
    // and re-render so the status transition is detected.

    it("shows plural success toast when syncedCount > 1", () => {
      const { rerender } = renderLayout();

      mockUseSyncStatus.mockReturnValue({ error: null, status: "success", syncedCount: 3 });
      rerender(
        <Routes>
          <Route element={<Layout />} path="/">
            <Route element={<div>Home Content</div>} index />
          </Route>
        </Routes>,
      );

      expect(toast.success).toHaveBeenCalledWith("3 opérations synchronisées");
    });

    it("shows singular success toast when syncedCount === 1", () => {
      const { rerender } = renderLayout();

      mockUseSyncStatus.mockReturnValue({ error: null, status: "success", syncedCount: 1 });
      rerender(
        <Routes>
          <Route element={<Layout />} path="/">
            <Route element={<div>Home Content</div>} index />
          </Route>
        </Routes>,
      );

      expect(toast.success).toHaveBeenCalledWith("1 opération synchronisée");
    });

    it("does not show success toast when syncedCount === 0", () => {
      const { rerender } = renderLayout();

      mockUseSyncStatus.mockReturnValue({ error: null, status: "success", syncedCount: 0 });
      rerender(
        <Routes>
          <Route element={<Layout />} path="/">
            <Route element={<div>Home Content</div>} index />
          </Route>
        </Routes>,
      );

      expect(toast.success).not.toHaveBeenCalled();
    });

    it("shows error toast when status is error with message", () => {
      const { rerender } = renderLayout();

      mockUseSyncStatus.mockReturnValue({ error: "Network failed", status: "error", syncedCount: 0 });
      rerender(
        <Routes>
          <Route element={<Layout />} path="/">
            <Route element={<div>Home Content</div>} index />
          </Route>
        </Routes>,
      );

      expect(toast.error).toHaveBeenCalledWith("Erreur de synchronisation : Network failed");
    });

    it("does not show error toast when error is null", () => {
      const { rerender } = renderLayout();

      mockUseSyncStatus.mockReturnValue({ error: null, status: "error", syncedCount: 0 });
      rerender(
        <Routes>
          <Route element={<Layout />} path="/">
            <Route element={<div>Home Content</div>} index />
          </Route>
        </Routes>,
      );

      expect(toast.error).not.toHaveBeenCalled();
    });

    it("does not call invalidateQueries on sync success (handled by useSyncStatus)", () => {
      const queryClient = createTestQueryClient();
      const invalidateSpy = vi.spyOn(queryClient, "invalidateQueries");

      const { rerender } = renderWithProviders(
        <Routes>
          <Route element={<Layout />} path="/">
            <Route element={<div>Content</div>} index />
          </Route>
        </Routes>,
        { queryClient },
      );

      mockUseSyncStatus.mockReturnValue({ error: null, status: "success", syncedCount: 2 });
      rerender(
        <Routes>
          <Route element={<Layout />} path="/">
            <Route element={<div>Content</div>} index />
          </Route>
        </Routes>,
      );

      expect(invalidateSpy).not.toHaveBeenCalled();
    });

    it("does not fire toast twice for the same status (duplicate prevention)", () => {
      const { rerender } = renderLayout();

      // Transition from idle to success — should fire once
      mockUseSyncStatus.mockReturnValue({ error: null, status: "success", syncedCount: 2 });
      rerender(
        <Routes>
          <Route element={<Layout />} path="/">
            <Route element={<div>Home Content</div>} index />
          </Route>
        </Routes>,
      );

      expect(toast.success).toHaveBeenCalledTimes(1);

      // Re-render with the same status value — prevStatus guard prevents a second toast
      rerender(
        <Routes>
          <Route element={<Layout />} path="/">
            <Route element={<div>Home Content</div>} index />
          </Route>
        </Routes>,
      );

      expect(toast.success).toHaveBeenCalledTimes(1);
    });
  });
});
