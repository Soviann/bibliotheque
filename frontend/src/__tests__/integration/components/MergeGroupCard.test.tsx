import { screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";
import MergeGroupCard from "../../../components/MergeGroupCard";
import type { MergeGroup } from "../../../types/api";
import { renderWithProviders } from "../../helpers/test-utils";

const group: MergeGroup = {
  entries: [
    { originalTitle: "Naruto - T1", seriesId: 1, suggestedTomeNumber: 1 },
    { originalTitle: "Naruto - T2", seriesId: 2, suggestedTomeNumber: 2 },
    {
      originalTitle: "Naruto Intégrale",
      seriesId: 3,
      suggestedTomeNumber: null,
    },
  ],
  suggestedTitle: "Naruto",
};

describe("MergeGroupCard", () => {
  it("renders the suggested title", () => {
    renderWithProviders(
      <MergeGroupCard group={group} onPreview={vi.fn()} onSkip={vi.fn()} />,
    );

    expect(screen.getByText("Naruto")).toBeInTheDocument();
  });

  it("renders entry count badge", () => {
    renderWithProviders(
      <MergeGroupCard group={group} onPreview={vi.fn()} onSkip={vi.fn()} />,
    );

    expect(screen.getByText("3 séries")).toBeInTheDocument();
  });

  it("renders all entries with their titles", () => {
    renderWithProviders(
      <MergeGroupCard group={group} onPreview={vi.fn()} onSkip={vi.fn()} />,
    );

    expect(screen.getByText("Naruto - T1")).toBeInTheDocument();
    expect(screen.getByText("Naruto - T2")).toBeInTheDocument();
    expect(screen.getByText("Naruto Intégrale")).toBeInTheDocument();
  });

  it("renders tome number for entries that have one", () => {
    renderWithProviders(
      <MergeGroupCard group={group} onPreview={vi.fn()} onSkip={vi.fn()} />,
    );

    expect(screen.getByText("Tome 1")).toBeInTheDocument();
    expect(screen.getByText("Tome 2")).toBeInTheDocument();
  });

  it("calls onPreview with group when clicking preview button", async () => {
    const user = userEvent.setup();
    const onPreview = vi.fn();

    renderWithProviders(
      <MergeGroupCard group={group} onPreview={onPreview} onSkip={vi.fn()} />,
    );

    await user.click(screen.getByText("Aperçu et fusion"));

    expect(onPreview).toHaveBeenCalledWith(group);
  });

  it("calls onSkip with group when clicking skip button", async () => {
    const user = userEvent.setup();
    const onSkip = vi.fn();

    renderWithProviders(
      <MergeGroupCard group={group} onPreview={vi.fn()} onSkip={onSkip} />,
    );

    await user.click(screen.getByText("Ignorer"));

    expect(onSkip).toHaveBeenCalledWith(group);
  });
});
