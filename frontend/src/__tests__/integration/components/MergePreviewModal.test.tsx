import { render, screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import MergePreviewModal from "../../../components/MergePreviewModal";
import type { MergePreview, MergePreviewTome } from "../../../types/api";

function createMockTome(overrides: Partial<MergePreviewTome> = {}): MergePreviewTome {
  return {
    bought: false,
    downloaded: false,
    isbn: null,
    number: 1,
    onNas: false,
    read: false,
    title: null,
    tomeEnd: null,
    ...overrides,
  };
}

function createMockPreview(overrides: Partial<MergePreview> = {}): MergePreview {
  return {
    authors: [],
    coverUrl: null,
    description: null,
    isOneShot: false,
    latestPublishedIssue: null,
    latestPublishedIssueComplete: false,
    publisher: null,
    sourceSeriesIds: [1, 2],
    title: "Test Series",
    tomes: [createMockTome({ number: 1 }), createMockTome({ number: 2 })],
    type: "manga",
    ...overrides,
  };
}

const defaultProps = {
  isExecuting: false,
  onClose: vi.fn(),
  onConfirm: vi.fn(),
  open: true,
};

describe("MergePreviewModal — accessibility", () => {
  it("has aria-labels on tome checkboxes", () => {
    render(
      <MergePreviewModal
        {...defaultProps}
        preview={createMockPreview({
          tomes: [createMockTome({ number: 1 }), createMockTome({ number: 2 })],
        })}
      />,
    );

    expect(screen.getByLabelText("Tome 1 acheté")).toBeInTheDocument();
    expect(screen.getByLabelText("Tome 1 téléchargé")).toBeInTheDocument();
    expect(screen.getByLabelText("Tome 1 lu")).toBeInTheDocument();
    expect(screen.getByLabelText("Tome 1 sur NAS")).toBeInTheDocument();
    expect(screen.getByLabelText("Tome 2 acheté")).toBeInTheDocument();
  });
});

describe("MergePreviewModal — add tome", () => {
  it("renders an 'Ajouter un tome' button", () => {
    render(
      <MergePreviewModal {...defaultProps} preview={createMockPreview()} />,
    );

    expect(
      screen.getByRole("button", { name: /ajouter un tome/i }),
    ).toBeInTheDocument();
  });

  it("adds a new tome row when clicking the add button", async () => {
    const user = userEvent.setup();
    render(
      <MergePreviewModal
        {...defaultProps}
        preview={createMockPreview({ tomes: [createMockTome({ number: 1 })] })}
      />,
    );

    const rows = () => within(screen.getByRole("table")).getAllByRole("row");
    // Header + 1 tome = 2 rows
    expect(rows()).toHaveLength(2);

    await user.click(screen.getByRole("button", { name: /ajouter un tome/i }));

    // Header + 2 tomes = 3 rows
    expect(rows()).toHaveLength(3);
  });

  it("assigns next number to the new tome", async () => {
    const user = userEvent.setup();
    render(
      <MergePreviewModal
        {...defaultProps}
        preview={createMockPreview({
          tomes: [createMockTome({ number: 3 }), createMockTome({ number: 5 })],
        })}
      />,
    );

    await user.click(screen.getByRole("button", { name: /ajouter un tome/i }));

    // New tome should have number = max + 1 = 6
    const numberInputs = screen.getAllByRole("spinbutton");
    const lastNumberInput = numberInputs[numberInputs.length - 2]; // last # input (before tomeEnd inputs)
    expect(lastNumberInput).toHaveValue(6);
  });

  it("includes added tome in confirm payload", async () => {
    const user = userEvent.setup();
    const onConfirm = vi.fn();
    render(
      <MergePreviewModal
        {...defaultProps}
        onConfirm={onConfirm}
        preview={createMockPreview({
          tomes: [createMockTome({ number: 1 })],
        })}
      />,
    );

    await user.click(screen.getByRole("button", { name: /ajouter un tome/i }));
    await user.click(screen.getByRole("button", { name: /confirmer la fusion/i }));

    expect(onConfirm).toHaveBeenCalledWith(
      expect.objectContaining({
        tomes: expect.arrayContaining([
          expect.objectContaining({ number: 1 }),
          expect.objectContaining({ number: 2 }),
        ]),
      }),
    );
    expect(onConfirm.mock.calls[0][0].tomes).toHaveLength(2);
  });
});
