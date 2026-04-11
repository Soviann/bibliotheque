import { screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import OfflineBanner from "../../../components/OfflineBanner";
import { renderWithProviders } from "../../helpers/test-utils";
import type { QueueItem } from "../../../services/offlineQueue";

// Mock the hooks used by OfflineBanner
const mockUseOnlineStatus = vi.fn(() => true);
const mockUsePendingQueueCount = vi.fn(() => 0);
const mockGetAll = vi.fn<() => Promise<QueueItem[]>>(() => Promise.resolve([]));

vi.mock("../../../hooks/useOnlineStatus", () => ({
  useOnlineStatus: () => mockUseOnlineStatus(),
}));

vi.mock("../../../hooks/usePendingQueueCount", () => ({
  usePendingQueueCount: () => mockUsePendingQueueCount(),
}));

vi.mock("../../../services/offlineQueue", () => ({
  getAll: () => mockGetAll(),
}));

describe("OfflineBanner", () => {
  beforeEach(() => {
    mockUseOnlineStatus.mockReturnValue(true);
    mockUsePendingQueueCount.mockReturnValue(0);
  });

  it("is not visible when online and no pending operations", () => {
    renderWithProviders(<OfflineBanner />);

    expect(screen.queryByText(/hors ligne/i)).not.toBeInTheDocument();
    expect(screen.queryByText(/en attente/i)).not.toBeInTheDocument();
  });

  it("shows offline message when offline", () => {
    mockUseOnlineStatus.mockReturnValue(false);

    renderWithProviders(<OfflineBanner />);

    expect(screen.getByText("Mode hors ligne")).toBeInTheDocument();
  });

  it("shows offline message with pending count when offline with pending ops", () => {
    mockUseOnlineStatus.mockReturnValue(false);
    mockUsePendingQueueCount.mockReturnValue(3);

    renderWithProviders(<OfflineBanner />);

    expect(
      screen.getByText("Mode hors ligne — 3 opérations en attente"),
    ).toBeInTheDocument();
  });

  it("shows pending sync message when online with pending ops", () => {
    mockUseOnlineStatus.mockReturnValue(true);
    mockUsePendingQueueCount.mockReturnValue(2);

    renderWithProviders(<OfflineBanner />);

    expect(
      screen.getByText("2 opérations en attente de synchronisation"),
    ).toBeInTheDocument();
  });

  it("uses singular form for single pending operation when offline", () => {
    mockUseOnlineStatus.mockReturnValue(false);
    mockUsePendingQueueCount.mockReturnValue(1);

    renderWithProviders(<OfflineBanner />);

    expect(
      screen.getByText("Mode hors ligne — 1 opération en attente"),
    ).toBeInTheDocument();
  });

  it("uses singular form for single pending operation when online", () => {
    mockUseOnlineStatus.mockReturnValue(true);
    mockUsePendingQueueCount.mockReturnValue(1);

    renderWithProviders(<OfflineBanner />);

    expect(
      screen.getByText("1 opération en attente de synchronisation"),
    ).toBeInTheDocument();
  });

  it("shows expand button when there are pending operations", () => {
    mockUseOnlineStatus.mockReturnValue(false);
    mockUsePendingQueueCount.mockReturnValue(2);

    renderWithProviders(<OfflineBanner />);

    expect(
      screen.getByLabelText("Voir les opérations en attente"),
    ).toBeInTheDocument();
  });

  it("does not show expand button when offline with no pending ops", () => {
    mockUseOnlineStatus.mockReturnValue(false);
    mockUsePendingQueueCount.mockReturnValue(0);

    renderWithProviders(<OfflineBanner />);

    expect(
      screen.queryByLabelText("Voir les opérations en attente"),
    ).not.toBeInTheDocument();
  });

  it("expands to show queue items when clicked", async () => {
    const user = userEvent.setup();
    mockUseOnlineStatus.mockReturnValue(false);
    mockUsePendingQueueCount.mockReturnValue(2);
    mockGetAll.mockResolvedValue([
      {
        id: 1,
        operation: "create",
        payload: { title: "Naruto" },
        resourceType: "comic_series",
        retryCount: 0,
        status: "pending",
        timestamp: Date.now(),
        url: "/api/comic_series",
      },
      {
        id: 2,
        operation: "update",
        payload: { bought: true },
        resourceType: "tome",
        retryCount: 0,
        status: "pending",
        timestamp: Date.now(),
        url: "/api/tomes/5",
      },
    ] as QueueItem[]);

    renderWithProviders(<OfflineBanner />);

    await user.click(screen.getByLabelText("Voir les opérations en attente"));

    await waitFor(() => {
      expect(screen.getByText("Création série")).toBeInTheDocument();
      expect(screen.getByText("Mise à jour tome")).toBeInTheDocument();
    });
  });
});
