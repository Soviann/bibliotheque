import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen } from "@testing-library/react";
import type { ReactNode } from "react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import OfflineBanner from "../../components/OfflineBanner";

const mockUseOnlineStatus = vi.fn().mockReturnValue(true);
vi.mock("../../hooks/useOnlineStatus", () => ({
  useOnlineStatus: () => mockUseOnlineStatus(),
}));

const mockUsePendingQueueCount = vi.fn().mockReturnValue(0);
vi.mock("../../hooks/usePendingQueueCount", () => ({
  usePendingQueueCount: () => mockUsePendingQueueCount(),
}));

function Wrapper({ children }: { children: ReactNode }) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>;
}

describe("OfflineBanner", () => {
  beforeEach(() => {
    mockUseOnlineStatus.mockReturnValue(true);
    mockUsePendingQueueCount.mockReturnValue(0);
  });

  it("is hidden when online with no pending operations", () => {
    const { container } = render(<OfflineBanner />, { wrapper: Wrapper });
    expect(container.firstChild).toBeNull();
  });

  it("shows 'Mode hors ligne' when offline with no pending", () => {
    mockUseOnlineStatus.mockReturnValue(false);
    mockUsePendingQueueCount.mockReturnValue(0);

    render(<OfflineBanner />, { wrapper: Wrapper });
    expect(screen.getByText("Mode hors ligne")).toBeInTheDocument();
  });

  it("shows offline message with pending count when offline", () => {
    mockUseOnlineStatus.mockReturnValue(false);
    mockUsePendingQueueCount.mockReturnValue(3);

    render(<OfflineBanner />, { wrapper: Wrapper });
    expect(screen.getByText("Mode hors ligne — 3 opérations en attente")).toBeInTheDocument();
  });

  it("shows singular form for single pending operation when offline", () => {
    mockUseOnlineStatus.mockReturnValue(false);
    mockUsePendingQueueCount.mockReturnValue(1);

    render(<OfflineBanner />, { wrapper: Wrapper });
    expect(screen.getByText("Mode hors ligne — 1 opération en attente")).toBeInTheDocument();
  });

  it("shows sync pending message when online with pending operations", () => {
    mockUseOnlineStatus.mockReturnValue(true);
    mockUsePendingQueueCount.mockReturnValue(2);

    render(<OfflineBanner />, { wrapper: Wrapper });
    expect(screen.getByText("2 opérations en attente de synchronisation")).toBeInTheDocument();
  });

  it("shows singular sync pending message for single operation", () => {
    mockUseOnlineStatus.mockReturnValue(true);
    mockUsePendingQueueCount.mockReturnValue(1);

    render(<OfflineBanner />, { wrapper: Wrapper });
    expect(screen.getByText("1 opération en attente de synchronisation")).toBeInTheDocument();
  });
});
