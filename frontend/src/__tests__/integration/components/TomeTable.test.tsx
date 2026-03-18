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

  it("adds aria-labels on mobile card inputs", () => {
    const tomeManager = createMockTomeManager();
    const form = createMockForm({
      tomes: [
        {
          bought: false,
          downloaded: false,
          id: 1,
          isbn: "",
          isHorsSerie: false,
          number: 1,
          onNas: false,
          read: false,
          title: "",
          tomeEnd: "",
        },
      ],
    });

    // Simulate mobile by checking the cards container
    renderWithProviders(<TomeTable form={form} tomeManager={tomeManager} />);

    const cards = screen.getByTestId("tomes-cards");
    const numberInput = cards.querySelector("input[type='number'][aria-label='Numéro']");
    expect(numberInput).toBeInTheDocument();

    const tomeEndInput = cards.querySelector("input[aria-label='Fin']");
    expect(tomeEndInput).toBeInTheDocument();

    const titleInput = cards.querySelector("input[aria-label='Titre']");
    expect(titleInput).toBeInTheDocument();

    const isbnInput = cards.querySelector("input[aria-label='ISBN']");
    expect(isbnInput).toBeInTheDocument();
  });

  it("adds aria-labels on desktop table inputs", () => {
    const tomeManager = createMockTomeManager();
    const form = createMockForm({
      tomes: [
        {
          bought: false,
          downloaded: false,
          id: 1,
          isbn: "",
          isHorsSerie: false,
          number: 1,
          onNas: false,
          read: false,
          title: "",
          tomeEnd: "",
        },
      ],
    });

    renderWithProviders(<TomeTable form={form} tomeManager={tomeManager} />);

    const table = screen.getByTestId("tomes-table");
    const numberInput = table.querySelector("input[type='number'][aria-label='Numéro']");
    expect(numberInput).toBeInTheDocument();

    const tomeEndInput = table.querySelector("input[aria-label='Fin']");
    expect(tomeEndInput).toBeInTheDocument();

    const titleInput = table.querySelector("input[aria-label='Titre']");
    expect(titleInput).toBeInTheDocument();

    const isbnInput = table.querySelector("input[aria-label='ISBN']");
    expect(isbnInput).toBeInTheDocument();

    // Desktop delete button should have aria-label
    const deleteButton = table.querySelector("button[aria-label]");
    expect(deleteButton).toHaveAttribute("aria-label", "Supprimer tome 1");
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
