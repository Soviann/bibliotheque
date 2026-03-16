import { render, screen } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import VirtualGrid from "../../../components/VirtualGrid";

// Mock useColumnCount to return a fixed column count
vi.mock("../../../hooks/useColumnCount", () => ({
  useColumnCount: () => ({ columnCount: 3, containerRef: vi.fn() }),
}));

// Mock useWindowVirtualizer
const mockGetVirtualItems = vi.fn();
const mockGetTotalSize = vi.fn(() => 720);

vi.mock("@tanstack/react-virtual", () => ({
  useWindowVirtualizer: vi.fn(() => ({
    getVirtualItems: mockGetVirtualItems,
    getTotalSize: mockGetTotalSize,
  })),
}));

beforeEach(() => {
  mockGetVirtualItems.mockReturnValue([
    { index: 0, key: "0", size: 240, start: 0 },
    { index: 1, key: "1", size: 240, start: 240 },
    { index: 2, key: "2", size: 240, start: 480 },
  ]);
});

describe("VirtualGrid", () => {
  it("renders virtual rows with items", () => {
    const items = Array.from({ length: 9 }, (_, i) => ({ id: i, name: `Item ${i}` }));

    render(
      <VirtualGrid
        items={items}
        renderItem={(item) => <div data-testid={`item-${item.id}`}>{item.name}</div>}
      />,
    );

    // With 3 columns and 3 virtual rows, first row has items 0-2
    expect(screen.getByTestId("item-0")).toBeInTheDocument();
    expect(screen.getByTestId("item-1")).toBeInTheDocument();
    expect(screen.getByTestId("item-2")).toBeInTheDocument();
  });

  it("renders nothing when items is empty", () => {
    const { container } = render(
      <VirtualGrid
        items={[]}
        renderItem={(item: { id: number }) => <div>{item.id}</div>}
      />,
    );

    // Should render the container but with 0 height
    mockGetVirtualItems.mockReturnValue([]);
    mockGetTotalSize.mockReturnValue(0);

    const { container: emptyContainer } = render(
      <VirtualGrid
        items={[]}
        renderItem={(item: { id: number }) => <div>{item.id}</div>}
      />,
    );

    expect(emptyContainer.querySelector("[data-testid='virtual-grid']")).toBeInTheDocument();
  });

  it("applies custom testId", () => {
    render(
      <VirtualGrid
        items={[{ id: 1 }]}
        renderItem={(item) => <div>{item.id}</div>}
        testId="my-grid"
      />,
    );

    expect(screen.getByTestId("my-grid")).toBeInTheDocument();
  });

  it("uses grid layout classes on rows", () => {
    const items = Array.from({ length: 6 }, (_, i) => ({ id: i }));

    const { container } = render(
      <VirtualGrid
        items={items}
        renderItem={(item) => <div>{item.id}</div>}
      />,
    );

    // Virtual rows should have grid classes
    const gridRows = container.querySelectorAll(".grid");
    expect(gridRows.length).toBeGreaterThan(0);
  });
});
