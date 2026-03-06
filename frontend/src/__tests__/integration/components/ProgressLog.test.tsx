import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import ProgressLog from "../../../components/ProgressLog";
import type { BatchLookupProgress } from "../../../types/api";

function createProgress(overrides: Partial<BatchLookupProgress> = {}): BatchLookupProgress {
  return {
    current: 1,
    seriesTitle: "Test Series",
    status: "updated",
    total: 10,
    updatedFields: [],
    ...overrides,
  };
}

describe("ProgressLog", () => {
  it("renders a progress bar", () => {
    render(<ProgressLog progress={[]} total={10} />);

    expect(screen.getByRole("progressbar")).toBeInTheDocument();
  });

  it("displays progress entries with series titles", () => {
    const progress = [
      createProgress({ current: 1, seriesTitle: "Naruto", status: "updated" }),
      createProgress({ current: 2, seriesTitle: "One Piece", status: "skipped" }),
    ];

    render(<ProgressLog progress={progress} total={5} />);

    expect(screen.getByText("Naruto")).toBeInTheDocument();
    expect(screen.getByText("One Piece")).toBeInTheDocument();
  });

  it("shows updated fields when present", () => {
    const progress = [
      createProgress({
        seriesTitle: "Naruto",
        status: "updated",
        updatedFields: ["description", "publisher"],
      }),
    ];

    render(<ProgressLog progress={progress} total={5} />);

    expect(screen.getByText("(description, publisher)")).toBeInTheDocument();
  });

  it("shows progress count on progress bar", () => {
    const progress = [
      createProgress({ current: 1 }),
      createProgress({ current: 2 }),
    ];

    render(<ProgressLog progress={progress} total={5} />);

    expect(screen.getByText("2 / 5")).toBeInTheDocument();
  });
});
