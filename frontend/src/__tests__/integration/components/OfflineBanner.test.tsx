import { screen } from "@testing-library/react";
import OfflineBanner from "../../../components/OfflineBanner";
import { renderWithProviders } from "../../helpers/test-utils";

// Mock the hooks used by OfflineBanner
const mockUseOnlineStatus = vi.fn(() => true);
const mockUsePendingQueueCount = vi.fn(() => 0);

vi.mock("../../../hooks/useOnlineStatus", () => ({
  useOnlineStatus: () => mockUseOnlineStatus(),
}));

vi.mock("../../../hooks/usePendingQueueCount", () => ({
  usePendingQueueCount: () => mockUsePendingQueueCount(),
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

    expect(screen.getByText("Mode hors ligne — 3 opérations en attente")).toBeInTheDocument();
  });

  it("shows pending sync message when online with pending ops", () => {
    mockUseOnlineStatus.mockReturnValue(true);
    mockUsePendingQueueCount.mockReturnValue(2);

    renderWithProviders(<OfflineBanner />);

    expect(screen.getByText("2 opérations en attente de synchronisation")).toBeInTheDocument();
  });

  it("uses singular form for single pending operation when offline", () => {
    mockUseOnlineStatus.mockReturnValue(false);
    mockUsePendingQueueCount.mockReturnValue(1);

    renderWithProviders(<OfflineBanner />);

    expect(screen.getByText("Mode hors ligne — 1 opération en attente")).toBeInTheDocument();
  });

  it("uses singular form for single pending operation when online", () => {
    mockUseOnlineStatus.mockReturnValue(true);
    mockUsePendingQueueCount.mockReturnValue(1);

    renderWithProviders(<OfflineBanner />);

    expect(screen.getByText("1 opération en attente de synchronisation")).toBeInTheDocument();
  });
});
