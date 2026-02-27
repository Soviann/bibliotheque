import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import type { LookupResult } from "../../types/api";

// Mock react-router hooks
const mockNavigate = vi.fn();
vi.mock("react-router-dom", async () => {
  const actual = await vi.importActual<typeof import("react-router-dom")>("react-router-dom");
  return {
    ...actual,
    useNavigate: () => mockNavigate,
    useParams: () => ({}),
  };
});

// Mock hooks
const mockUseLookupIsbn = vi.fn().mockReturnValue({ data: null, isFetching: false });
const mockUseLookupTitle = vi.fn().mockReturnValue({ data: null, isFetching: false });
vi.mock("../../hooks/useLookup", () => ({
  useLookupIsbn: (...args: unknown[]) => mockUseLookupIsbn(...args),
  useLookupTitle: (...args: unknown[]) => mockUseLookupTitle(...args),
}));

vi.mock("../../hooks/useComic", () => ({
  useComic: () => ({ data: undefined }),
}));

vi.mock("../../hooks/useCreateComic", () => ({
  useCreateComic: () => ({ isPending: false, mutate: vi.fn() }),
}));

vi.mock("../../hooks/useUpdateComic", () => ({
  useUpdateComic: () => ({ isPending: false, mutate: vi.fn() }),
}));

vi.mock("../../hooks/useAuthors", () => ({
  useAuthors: () => ({ data: { member: [] } }),
}));

vi.mock("../../components/BarcodeScanner", () => ({
  default: () => <button type="button">Scanner</button>,
}));

const fullLookup: LookupResult = {
  apiMessages: [],
  authors: "Thierry Cailleteau",
  description: "Une saga spatiale",
  isbn: "9782756001340",
  isOneShot: false,
  latestPublishedIssue: 20,
  publishedDate: "2006",
  publisher: "Delcourt",
  sources: ["google_books", "gemini"],
  thumbnail: "https://example.com/cover.jpg",
  title: "Aquablue",
};

describe("ComicForm — applyLookup", () => {
  beforeEach(() => {
    mockUseLookupIsbn.mockReturnValue({ data: null, isFetching: false });
    mockUseLookupTitle.mockReturnValue({ data: null, isFetching: false });
  });

  async function renderAndApplyLookup(lookupData: LookupResult) {
    mockUseLookupTitle.mockReturnValue({ data: lookupData, isFetching: false });

    // Import dynamically to ensure mocks are set up
    const { default: ComicForm } = await import("../../pages/ComicForm");
    const { QueryClient, QueryClientProvider } = await import("@tanstack/react-query");
    const { MemoryRouter } = await import("react-router-dom");

    const queryClient = new QueryClient({
      defaultOptions: { queries: { retry: false } },
    });

    render(
      <QueryClientProvider client={queryClient}>
        <MemoryRouter>
          <ComicForm />
        </MemoryRouter>
      </QueryClientProvider>,
    );

    // Switch to title lookup mode
    const titleButton = screen.getByRole("button", { name: "Titre" });
    await userEvent.click(titleButton);

    // Click "Appliquer"
    const applyButton = await screen.findByRole("button", { name: "Appliquer" });
    await userEvent.click(applyButton);
  }

  it("applies latestPublishedIssue from lookup result", async () => {
    await renderAndApplyLookup(fullLookup);

    const input = screen.getByLabelText("Dernier tome paru") as HTMLInputElement;
    expect(input.value).toBe("20");
  });

  it("applies coverUrl from lookup thumbnail", async () => {
    await renderAndApplyLookup(fullLookup);

    const input = screen.getByLabelText("URL de couverture") as HTMLInputElement;
    expect(input.value).toBe("https://example.com/cover.jpg");
  });

  it("applies title from lookup result", async () => {
    await renderAndApplyLookup(fullLookup);

    const input = screen.getByLabelText(/Titre \*/) as HTMLInputElement;
    expect(input.value).toBe("Aquablue");
  });

  it("applies publisher from lookup result", async () => {
    await renderAndApplyLookup(fullLookup);

    const input = screen.getByLabelText("Éditeur") as HTMLInputElement;
    expect(input.value).toBe("Delcourt");
  });

  it("applies description from lookup result", async () => {
    await renderAndApplyLookup(fullLookup);

    const textarea = screen.getByLabelText("Description") as HTMLTextAreaElement;
    expect(textarea.value).toBe("Une saga spatiale");
  });

  it("does not overwrite latestPublishedIssue when lookup value is null", async () => {
    const lookupWithoutIssue: LookupResult = { ...fullLookup, latestPublishedIssue: null };
    mockUseLookupTitle.mockReturnValue({ data: lookupWithoutIssue, isFetching: false });

    const { default: ComicForm } = await import("../../pages/ComicForm");
    const { QueryClient, QueryClientProvider } = await import("@tanstack/react-query");
    const { MemoryRouter } = await import("react-router-dom");

    const queryClient = new QueryClient({
      defaultOptions: { queries: { retry: false } },
    });

    render(
      <QueryClientProvider client={queryClient}>
        <MemoryRouter>
          <ComicForm />
        </MemoryRouter>
      </QueryClientProvider>,
    );

    // Manually set a value first
    const input = screen.getByLabelText("Dernier tome paru") as HTMLInputElement;
    await userEvent.type(input, "15");
    expect(input.value).toBe("15");

    // Switch to title lookup and apply
    await userEvent.click(screen.getByRole("button", { name: "Titre" }));
    const applyButton = await screen.findByRole("button", { name: "Appliquer" });
    await userEvent.click(applyButton);

    // Value should be preserved
    expect(input.value).toBe("15");
  });
});
