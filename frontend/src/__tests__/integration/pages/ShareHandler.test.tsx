import { screen, waitFor } from "@testing-library/react";
import { http, HttpResponse } from "msw";
import ShareHandler from "../../../pages/ShareHandler";
import { server } from "../../helpers/server";
import { renderWithProviders } from "../../helpers/test-utils";

const mockNavigate = vi.fn();

vi.mock("react-router-dom", async () => {
  const actual = await vi.importActual("react-router-dom");
  return {
    ...actual,
    useNavigate: () => mockNavigate,
  };
});

vi.mock("sonner", () => ({
  toast: {
    error: vi.fn(),
    success: vi.fn(),
  },
}));

const SHARE_URL = "/api/share";

const mockLookupResult = {
  amazonUrl: null,
  authors: "Goscinny",
  description: "Une bande dessinée",
  isbn: "2723492532",
  isOneShot: false,
  latestPublishedIssue: 38,
  publishedDate: "1961",
  publisher: "Dargaud",
  thumbnail: "https://example.com/cover.jpg",
  title: "Astérix",
  tomeEnd: null,
  tomeNumber: null,
};

describe("ShareHandler", () => {
  beforeEach(() => {
    mockNavigate.mockReset();
  });

  it("navigates to /comic/:id when backend returns matched=true", async () => {
    server.use(
      http.post(SHARE_URL, () =>
        HttpResponse.json({ matched: true, seriesId: 42 }),
      ),
    );

    renderWithProviders(<ShareHandler />, {
      initialEntries: ["/share?url=https%3A%2F%2Famazon.fr%2Fdp%2F2723492532"],
    });

    expect(screen.getByText("Analyse du lien partagé…")).toBeInTheDocument();

    await waitFor(() => {
      expect(mockNavigate).toHaveBeenCalledWith("/comic/42", { replace: true });
    });
  });

  it("navigates to /comic/new with lookupResult state when backend returns matched=false", async () => {
    server.use(
      http.post(SHARE_URL, () =>
        HttpResponse.json({ matched: false, lookupResult: mockLookupResult }),
      ),
    );

    renderWithProviders(<ShareHandler />, {
      initialEntries: ["/share?url=https%3A%2F%2Ffr.wikipedia.org%2Fwiki%2FAst%C3%A9rix"],
    });

    await waitFor(() => {
      expect(mockNavigate).toHaveBeenCalledWith("/comic/new", {
        replace: true,
        state: { lookupResult: mockLookupResult },
      });
    });
  });

  it("shows toast and navigates to / on HTTP error", async () => {
    const { toast } = await import("sonner");

    server.use(
      http.post(SHARE_URL, () =>
        HttpResponse.json({ error: "Internal Server Error" }, { status: 500 }),
      ),
    );

    renderWithProviders(<ShareHandler />, {
      initialEntries: ["/share?url=https%3A%2F%2Famazon.fr%2Fdp%2F2723492532"],
    });

    await waitFor(() => {
      expect(toast.error).toHaveBeenCalledWith(
        "Impossible d'analyser le lien partagé.",
      );
      expect(mockNavigate).toHaveBeenCalledWith("/", { replace: true });
    });
  });

  it("shows toast and navigates to / when no URL is provided", async () => {
    const { toast } = await import("sonner");

    renderWithProviders(<ShareHandler />, {
      initialEntries: ["/share"],
    });

    await waitFor(() => {
      expect(toast.error).toHaveBeenCalledWith("Aucun lien à analyser.");
      expect(mockNavigate).toHaveBeenCalledWith("/", { replace: true });
    });
  });
});
