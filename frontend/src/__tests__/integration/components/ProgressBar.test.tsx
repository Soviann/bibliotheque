import { render, screen } from "@testing-library/react";
import ProgressBar from "../../../components/ProgressBar";

describe("ProgressBar", () => {
  it("renders label, count, and percentage", () => {
    render(<ProgressBar current={12} label="Achetés" total={24} />);

    expect(screen.getByText("Achetés")).toBeInTheDocument();
    expect(screen.getByText("12 / 24")).toBeInTheDocument();
    expect(screen.getByText("(50%)")).toBeInTheDocument();
  });

  it("renders correct percentage width", () => {
    render(<ProgressBar current={12} label="Achetés" total={24} />);

    const bar = screen.getByRole("progressbar");
    expect(bar).toHaveAttribute("aria-valuenow", "12");
    expect(bar).toHaveAttribute("aria-valuemax", "24");
    expect(bar).toHaveAttribute("aria-valuemin", "0");
  });

  it("handles zero total gracefully", () => {
    render(<ProgressBar current={0} label="Achetés" total={0} />);

    expect(screen.getByText("0 / 0")).toBeInTheDocument();
    expect(screen.getByText("(0%)")).toBeInTheDocument();
    const bar = screen.getByRole("progressbar");
    expect(bar).toHaveAttribute("aria-valuenow", "0");
    expect(bar).toHaveAttribute("aria-valuemax", "0");
  });

  it("handles full progress", () => {
    render(<ProgressBar current={10} label="Lus" total={10} />);

    expect(screen.getByText("10 / 10")).toBeInTheDocument();
    expect(screen.getByText("(100%)")).toBeInTheDocument();
  });

  it("applies custom color class", () => {
    render(<ProgressBar color="bg-green-500" current={5} label="Lus" total={10} />);

    const bar = screen.getByRole("progressbar");
    const fill = bar.firstChild as HTMLElement;
    expect(fill.className).toContain("bg-green-500");
  });

  it("defaults to primary color", () => {
    render(<ProgressBar current={5} label="Test" total={10} />);

    const bar = screen.getByRole("progressbar");
    const fill = bar.firstChild as HTMLElement;
    expect(fill.className).toContain("bg-primary-600");
  });

  it("renders compact variant without label text", () => {
    render(<ProgressBar compact current={3} label="Progression d'achat" total={10} />);

    expect(screen.getByText("3 / 10")).toBeInTheDocument();
    expect(screen.queryByText("Progression d'achat")).not.toBeInTheDocument();
    const bar = screen.getByRole("progressbar");
    expect(bar).toHaveAttribute("aria-label", "Progression d'achat");
  });

  it("uses smaller height in compact mode", () => {
    render(<ProgressBar compact current={5} label="Test" total={10} />);

    const bar = screen.getByRole("progressbar");
    expect(bar.className).toContain("h-1.5");
  });
});
