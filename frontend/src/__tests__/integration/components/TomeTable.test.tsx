import { screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
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

  it("adds aria-labels on mobile card inputs when expanded", async () => {
    const user = userEvent.setup();
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

    const cards = screen.getByTestId("tomes-cards");

    // Expand the card first (saved tomes are collapsed by default)
    const header = cards.querySelector("[data-testid='tome-header-0']")!;
    await user.click(header);

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

  it("renders mobile cards collapsed by default with tome summary", () => {
    const tomeManager = createMockTomeManager();
    const form = createMockForm({
      tomes: [
        {
          bought: true,
          downloaded: false,
          id: 1,
          isbn: "978-2-1234-5678-0",
          isHorsSerie: false,
          number: 3,
          onNas: false,
          read: false,
          title: "Le Grand Voyage",
          tomeEnd: "",
        },
        {
          bought: false,
          downloaded: false,
          id: 2,
          isbn: "",
          isHorsSerie: false,
          number: 4,
          onNas: false,
          read: false,
          title: "",
          tomeEnd: "",
        },
      ],
    });

    renderWithProviders(<TomeTable form={form} tomeManager={tomeManager} />);

    const cards = screen.getByTestId("tomes-cards");

    // Collapsed cards show summary text
    expect(cards).toHaveTextContent("#3 - Le Grand Voyage");
    expect(cards).toHaveTextContent("#4");

    // Edit fields should be hidden when collapsed
    const isbnInputs = cards.querySelectorAll("input[aria-label='ISBN']");
    expect(isbnInputs).toHaveLength(0);

    // Only the quick-access "Acheté" checkbox should be visible when collapsed
    const checkboxes = cards.querySelectorAll("input[type='checkbox']");
    expect(checkboxes).toHaveLength(2); // one per collapsed tome
  });

  it("expands a mobile card when clicking the header", async () => {
    const user = userEvent.setup();
    const tomeManager = createMockTomeManager();
    const form = createMockForm({
      tomes: [
        {
          bought: true,
          downloaded: false,
          id: 1,
          isbn: "978-2-1234-5678-0",
          isHorsSerie: false,
          number: 3,
          onNas: false,
          read: false,
          title: "Le Grand Voyage",
          tomeEnd: "",
        },
      ],
    });

    renderWithProviders(<TomeTable form={form} tomeManager={tomeManager} />);

    const cards = screen.getByTestId("tomes-cards");

    // Click the collapsed card header to expand
    const header = cards.querySelector("[data-testid='tome-header-0']")!;
    await user.click(header);

    // Now edit fields should be visible
    const isbnInput = cards.querySelector("input[aria-label='ISBN']");
    expect(isbnInput).toBeInTheDocument();

    // Checkboxes should be visible
    const boughtCheckbox = cards.querySelector("input[type='checkbox']");
    expect(boughtCheckbox).toBeInTheDocument();
  });

  it("collapses an expanded mobile card when clicking the header again", async () => {
    const user = userEvent.setup();
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
          title: "Test",
          tomeEnd: "",
        },
      ],
    });

    renderWithProviders(<TomeTable form={form} tomeManager={tomeManager} />);

    const cards = screen.getByTestId("tomes-cards");
    const header = cards.querySelector("[data-testid='tome-header-0']")!;

    // Expand
    await user.click(header);
    expect(cards.querySelector("input[aria-label='ISBN']")).toBeInTheDocument();

    // Collapse
    await user.click(header);
    expect(cards.querySelector("input[aria-label='ISBN']")).not.toBeInTheDocument();
  });

  it("auto-expands new (unsaved) tome cards on mobile", () => {
    const tomeManager = createMockTomeManager();
    const form = createMockForm({
      tomes: [
        {
          bought: false,
          downloaded: false,
          id: undefined,
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

    const cards = screen.getByTestId("tomes-cards");

    // New tome should be expanded by default — edit fields visible
    const isbnInput = cards.querySelector("input[aria-label='ISBN']");
    expect(isbnInput).toBeInTheDocument();
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
