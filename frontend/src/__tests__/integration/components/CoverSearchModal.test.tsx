import { act, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import CoverSearchModal from "../../../components/CoverSearchModal";
import type { CoverSearchResult } from "../../../types/api";
import { server } from "../../helpers/server";
import { renderWithProviders } from "../../helpers/test-utils";

const mockResults: CoverSearchResult[] = [
  {
    height: 800,
    thumbnail: "https://example.com/thumb1.jpg",
    title: "Naruto Vol. 1",
    url: "https://example.com/cover1.jpg",
    width: 600,
  },
  {
    height: 900,
    thumbnail: "https://example.com/thumb2.jpg",
    title: "Naruto Vol. 2",
    url: "https://example.com/cover2.jpg",
    width: 650,
  },
];

describe("CoverSearchModal", () => {
  const defaultProps = {
    defaultQuery: "Naruto",
    onClose: vi.fn(),
    onSelect: vi.fn(),
    open: true,
    type: "manga",
  };

  beforeEach(() => {
    defaultProps.onClose = vi.fn();
    defaultProps.onSelect = vi.fn();
  });

  it("renders with title and search input when open", () => {
    renderWithProviders(<CoverSearchModal {...defaultProps} />);

    expect(screen.getByText("Rechercher une couverture")).toBeInTheDocument();
    expect(screen.getByPlaceholderText("Rechercher...")).toHaveValue("Naruto");
  });

  it("is not visible when open is false", () => {
    renderWithProviders(<CoverSearchModal {...defaultProps} open={false} />);

    expect(
      screen.queryByText("Rechercher une couverture"),
    ).not.toBeInTheDocument();
  });

  it("displays results as images", async () => {
    server.use(
      http.get("/api/lookup/covers", () => HttpResponse.json(mockResults)),
    );

    renderWithProviders(<CoverSearchModal {...defaultProps} />);

    await waitFor(() => {
      expect(screen.getByAltText("Naruto Vol. 1")).toBeInTheDocument();
      expect(screen.getByAltText("Naruto Vol. 2")).toBeInTheDocument();
    });
  });

  it("calls onSelect with url when an image is clicked", async () => {
    const user = userEvent.setup();
    server.use(
      http.get("/api/lookup/covers", () => HttpResponse.json(mockResults)),
    );

    renderWithProviders(<CoverSearchModal {...defaultProps} />);

    await waitFor(() => {
      expect(screen.getByAltText("Naruto Vol. 1")).toBeInTheDocument();
    });

    await user.click(screen.getByAltText("Naruto Vol. 1"));

    expect(defaultProps.onSelect).toHaveBeenCalledWith(
      "https://example.com/cover1.jpg",
    );
  });

  it("displays empty state when no results", async () => {
    server.use(http.get("/api/lookup/covers", () => HttpResponse.json([])));

    renderWithProviders(<CoverSearchModal {...defaultProps} />);

    await waitFor(() => {
      expect(screen.getByText("Aucune image trouvée")).toBeInTheDocument();
    });
  });

  it("shows skeleton loading state", () => {
    server.use(http.get("/api/lookup/covers", () => new Promise(() => {})));

    renderWithProviders(<CoverSearchModal {...defaultProps} />);

    expect(screen.getAllByTestId("skeleton-box")).toHaveLength(8);
  });

  it("shows minimum characters message when query has 1 char", async () => {
    const user = userEvent.setup();
    renderWithProviders(<CoverSearchModal {...defaultProps} defaultQuery="" />);

    await user.type(screen.getByPlaceholderText("Rechercher..."), "N");

    await waitFor(() => {
      expect(
        screen.getByText("Saisissez au moins 2 caractères"),
      ).toBeInTheDocument();
    });
  });

  it("does not show minimum characters message when query is empty", () => {
    renderWithProviders(<CoverSearchModal {...defaultProps} defaultQuery="" />);

    expect(
      screen.queryByText("Saisissez au moins 2 caractères"),
    ).not.toBeInTheDocument();
  });

  it("calls onClose when close button is clicked", async () => {
    const user = userEvent.setup();
    renderWithProviders(<CoverSearchModal {...defaultProps} />);

    const closeButton = screen.getByRole("button", { name: "Fermer" });
    await user.click(closeButton);

    expect(defaultProps.onClose).toHaveBeenCalledOnce();
  });

  it("shows scroll indicator when content overflows", async () => {
    server.use(
      http.get("/api/lookup/covers", () => HttpResponse.json(mockResults)),
    );

    renderWithProviders(<CoverSearchModal {...defaultProps} />);

    await waitFor(() => {
      expect(screen.getByAltText("Naruto Vol. 1")).toBeInTheDocument();
    });

    // Simulate overflow: scrollHeight > clientHeight
    const scrollContainer = screen
      .getByAltText("Naruto Vol. 1")
      .closest(".overflow-y-auto")!;
    Object.defineProperty(scrollContainer, "scrollHeight", {
      value: 800,
      configurable: true,
    });
    Object.defineProperty(scrollContainer, "clientHeight", {
      value: 400,
      configurable: true,
    });
    Object.defineProperty(scrollContainer, "scrollTop", {
      value: 0,
      configurable: true,
    });

    // Trigger scroll event to update state
    act(() => {
      scrollContainer.dispatchEvent(new Event("scroll"));
    });

    await waitFor(() => {
      expect(screen.getByTestId("scroll-indicator")).toBeInTheDocument();
    });
  });
});
