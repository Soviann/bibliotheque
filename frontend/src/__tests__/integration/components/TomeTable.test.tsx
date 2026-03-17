import { screen } from "@testing-library/react";
import TomeTable from "../../../components/TomeTable";
import type { FormData } from "../../../hooks/useComicForm";
import type { TomeManager } from "../../../hooks/useTomeManagement";
import { renderWithProviders } from "../../helpers/test-utils";

function createMockTomeManager(overrides: Partial<TomeManager> = {}): TomeManager {
  return {
    addBatchTomes: vi.fn(),
    addTome: vi.fn(),
    batchFrom: 1,
    batchSize: 1,
    batchTo: 1,
    lookupTomeIsbn: vi.fn(),
    maxBatchSize: 100,
    removeTome: vi.fn(),
    setBatchFrom: vi.fn(),
    setBatchTo: vi.fn(),
    tomeLookupLoading: null,
    updateTome: vi.fn(),
    ...overrides,
  };
}

function createMockForm(overrides: Partial<FormData> = {}): FormData {
  return {
    authors: [],
    coverUrl: "",
    defaultTomeBought: false,
    defaultTomeDownloaded: false,
    defaultTomeRead: false,
    description: "",
    isOneShot: false,
    latestPublishedIssue: "",
    latestPublishedIssueComplete: false,
    publishedDate: "",
    publisher: "",
    status: "buying",
    title: "Test",
    tomes: [],
    type: "manga",
    ...overrides,
  };
}

describe("TomeTable", () => {
  it("shows tooltip on disabled generate button when batch exceeds max", () => {
    const tomeManager = createMockTomeManager({
      batchFrom: 1,
      batchSize: 150,
      batchTo: 150,
      maxBatchSize: 100,
    });
    const form = createMockForm();

    renderWithProviders(<TomeTable form={form} tomeManager={tomeManager} />);

    const generateButton = screen.getByRole("button", { name: /Générer/ });
    expect(generateButton).toBeDisabled();
    expect(generateButton).toHaveAttribute("title", "Maximum 100 tomes à la fois");
  });

  it("does not show tooltip when batch is within limit", () => {
    const tomeManager = createMockTomeManager({
      batchFrom: 1,
      batchSize: 10,
      batchTo: 10,
      maxBatchSize: 100,
    });
    const form = createMockForm();

    renderWithProviders(<TomeTable form={form} tomeManager={tomeManager} />);

    const generateButton = screen.getByRole("button", { name: /Générer/ });
    expect(generateButton).not.toHaveAttribute("title");
  });
});
