import { render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import { MemoryRouter } from "react-router-dom";
import VirtualGrid from "../../../components/VirtualGrid";

// Mock useColumnCount to return a fixed column count
vi.mock("../../../hooks/useColumnCount", () => ({
  useColumnCount: () => ({ columnCount: 3, containerRef: vi.fn() }),
}));

// Mock useScrollRestoration exports
vi.mock("../../../hooks/useScrollRestoration", () => ({
  getSavedVirtuosoState: vi.fn(() => undefined),
  saveVirtuosoState: vi.fn(),
}));

// Mock react-virtuoso: render all rows via itemContent
vi.mock("react-virtuoso", () => ({
  Virtuoso: ({
    itemContent,
    totalCount,
  }: {
    itemContent: (index: number) => React.ReactNode;
    totalCount: number;
  }) => (
    <div data-testid="mock-virtuoso">
      {Array.from({ length: totalCount }, (_, i) => (
        <div key={i}>{itemContent(i)}</div>
      ))}
    </div>
  ),
}));

function renderWithRouter(ui: React.ReactNode) {
  return render(<MemoryRouter>{ui}</MemoryRouter>);
}

describe("VirtualGrid", () => {
  it("renders virtual rows with items", () => {
    const items = Array.from({ length: 9 }, (_, i) => ({
      id: i,
      name: `Item ${i}`,
    }));

    renderWithRouter(
      <VirtualGrid
        items={items}
        renderItem={(item) => (
          <div data-testid={`item-${item.id}`}>{item.name}</div>
        )}
      />,
    );

    // With 3 columns and 9 items → 3 rows, all items rendered
    expect(screen.getByTestId("item-0")).toBeInTheDocument();
    expect(screen.getByTestId("item-4")).toBeInTheDocument();
    expect(screen.getByTestId("item-8")).toBeInTheDocument();
  });

  it("renders nothing when items is empty", () => {
    renderWithRouter(
      <VirtualGrid
        items={[]}
        renderItem={(item: { id: number }) => <div>{item.id}</div>}
      />,
    );

    expect(screen.getByTestId("virtual-grid")).toBeInTheDocument();
  });

  it("applies custom testId", () => {
    renderWithRouter(
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

    const { container } = renderWithRouter(
      <VirtualGrid items={items} renderItem={(item) => <div>{item.id}</div>} />,
    );

    // Virtual rows should have grid classes
    const gridRows = container.querySelectorAll(".grid");
    expect(gridRows.length).toBeGreaterThan(0);
  });
});
