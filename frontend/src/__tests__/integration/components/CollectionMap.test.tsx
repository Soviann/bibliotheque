import { render, screen, within } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import CollectionMap from "../../../components/CollectionMap";
import { createMockTome } from "../../helpers/factories";

describe("CollectionMap", () => {
  it("renders correct number of cells for latestPublishedIssue", () => {
    const tomes = [
      createMockTome({ id: 1, number: 1, bought: true }),
      createMockTome({ id: 2, number: 3, bought: true }),
    ];
    const { container } = render(
      <CollectionMap latestPublishedIssue={5} tomes={tomes} />,
    );
    const grid = container.querySelector("[role='img']")!;
    const cells = within(grid).getAllByTitle(/^Tome \d/);
    expect(cells).toHaveLength(5);
  });

  it("applies filled style to bought tome", () => {
    const tomes = [createMockTome({ id: 1, number: 1, bought: true })];
    render(<CollectionMap latestPublishedIssue={1} tomes={tomes} />);
    const cell = screen.getByTitle(/Tome 1/);
    expect(cell.className).toMatch(/bg-\[rgb\(var\(--series-color\)\)\]/);
  });

  it("applies outline style to onNas (not bought) tome", () => {
    const tomes = [createMockTome({ id: 1, number: 1, onNas: true })];
    render(<CollectionMap latestPublishedIssue={1} tomes={tomes} />);
    const cell = screen.getByTitle(/Tome 1/);
    expect(cell.className).toMatch(/border-\[rgb\(var\(--series-color\)\)\]/);
    expect(cell.className).not.toMatch(/bg-\[rgb\(var\(--series-color\)\)\]/);
  });

  it("shows checkmark icon for read tome", () => {
    const tomes = [
      createMockTome({ id: 1, number: 1, bought: true, read: true }),
    ];
    render(<CollectionMap latestPublishedIssue={1} tomes={tomes} />);
    const cell = screen.getByTitle(/Tome 1/);
    const svg = cell.querySelector("svg");
    expect(svg).toBeInTheDocument();
  });

  it("applies dashed border to missing tome", () => {
    render(<CollectionMap latestPublishedIssue={3} tomes={[]} />);
    const cell = screen.getByTitle(/Tome 1/);
    expect(cell.className).toMatch(/border-dashed/);
  });

  it("renders hors-série tomes in separate section", () => {
    const tomes = [
      createMockTome({ id: 1, number: 1, bought: true }),
      createMockTome({ id: 2, number: 1, isHorsSerie: true, bought: true }),
    ];
    render(<CollectionMap latestPublishedIssue={1} tomes={tomes} />);
    expect(screen.getByText("Hors-série")).toBeInTheDocument();
    expect(screen.getByTitle(/HS 1/)).toBeInTheDocument();
  });

  it("returns null when latestPublishedIssue is null", () => {
    const { container } = render(
      <CollectionMap latestPublishedIssue={null} tomes={[]} />,
    );
    expect(container.innerHTML).toBe("");
  });

  it("handles tomeEnd ranges correctly", () => {
    const tomes = [
      createMockTome({ id: 1, number: 3, tomeEnd: 5, bought: true }),
    ];
    const { container } = render(
      <CollectionMap latestPublishedIssue={5} tomes={tomes} />,
    );
    const grid = container.querySelector("[role='img']")!;
    // Cells 3, 4, 5 should be bought (filled)
    for (const n of [3, 4, 5]) {
      const cell = within(grid).getByTitle(new RegExp(`Tome ${n}`));
      expect(cell.className).toMatch(/bg-\[rgb\(var\(--series-color\)\)\]/);
    }
    // Cells 1, 2 should be missing (dashed)
    for (const n of [1, 2]) {
      const cell = within(grid).getByTitle(new RegExp(`Tome ${n}`));
      expect(cell.className).toMatch(/border-dashed/);
    }
  });

  it("shows both filled background and checkmark for bought + read", () => {
    const tomes = [
      createMockTome({ id: 1, number: 1, bought: true, read: true }),
    ];
    render(<CollectionMap latestPublishedIssue={1} tomes={tomes} />);
    const cell = screen.getByTitle(/Tome 1/);
    expect(cell.className).toMatch(/bg-\[rgb\(var\(--series-color\)\)\]/);
    expect(cell.querySelector("svg")).toBeInTheDocument();
  });

  it("renders all cells as dashed when tomes array is empty", () => {
    render(<CollectionMap latestPublishedIssue={4} tomes={[]} />);
    for (const n of [1, 2, 3, 4]) {
      const cell = screen.getByTitle(new RegExp(`Tome ${n}`));
      expect(cell.className).toMatch(/border-dashed/);
    }
  });

  it("calls onSelectTome when clicking a regular tome cell", async () => {
    const onSelectTome = vi.fn();
    const tome = createMockTome({ id: 10, number: 2, bought: true });
    render(
      <CollectionMap
        latestPublishedIssue={3}
        onSelectTome={onSelectTome}
        tomes={[tome]}
      />,
    );

    const cell = screen.getByTitle(/Tome 2/);
    cell.click();

    expect(onSelectTome).toHaveBeenCalledWith(2, tome, false);
  });

  it("calls onSelectTome when clicking a missing tome cell", async () => {
    const onSelectTome = vi.fn();
    render(
      <CollectionMap
        latestPublishedIssue={3}
        onSelectTome={onSelectTome}
        tomes={[]}
      />,
    );

    const cell = screen.getByTitle(/Tome 1/);
    cell.click();

    expect(onSelectTome).toHaveBeenCalledWith(1, undefined, false);
  });

  it("calls onSelectTome when clicking a hors-série cell", async () => {
    const onSelectTome = vi.fn();
    const hsTome = createMockTome({ id: 15, isHorsSerie: true, number: 1 });
    render(
      <CollectionMap
        latestPublishedIssue={1}
        onSelectTome={onSelectTome}
        tomes={[hsTome]}
      />,
    );

    const cell = screen.getByTitle(/HS 1/);
    cell.click();

    expect(onSelectTome).toHaveBeenCalledWith(1, hsTome, true);
  });
});
