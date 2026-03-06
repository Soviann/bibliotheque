import { screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import MergeSeriesConfirmModal from "../../../components/MergeSeriesConfirmModal";
import { renderWithProviders } from "../../helpers/test-utils";

const entries = [
  { id: 1, title: "Naruto Tome 1" },
  { id: 2, title: "Naruto Tome 2" },
  { id: 3, title: "Naruto Tome 3" },
];

describe("MergeSeriesConfirmModal", () => {
  const defaultProps = {
    entries,
    onClose: vi.fn(),
    onConfirm: vi.fn(),
    open: true,
  };

  it("renders all series entries with checkboxes checked by default", () => {
    renderWithProviders(<MergeSeriesConfirmModal {...defaultProps} />);

    expect(screen.getByText("Naruto Tome 1")).toBeInTheDocument();
    expect(screen.getByText("Naruto Tome 2")).toBeInTheDocument();
    expect(screen.getByText("Naruto Tome 3")).toBeInTheDocument();

    const checkboxes = screen.getAllByRole("checkbox");
    expect(checkboxes).toHaveLength(3);
    checkboxes.forEach((cb) => expect(cb).toBeChecked());
  });

  it("allows unchecking a series entry", async () => {
    const user = userEvent.setup();
    renderWithProviders(<MergeSeriesConfirmModal {...defaultProps} />);

    const checkboxes = screen.getAllByRole("checkbox");
    await user.click(checkboxes[1]);

    expect(checkboxes[1]).not.toBeChecked();
  });

  it("disables confirm button when fewer than 2 series are checked", async () => {
    const user = userEvent.setup();
    renderWithProviders(<MergeSeriesConfirmModal {...defaultProps} />);

    const checkboxes = screen.getAllByRole("checkbox");
    // Uncheck two of three → only 1 left
    await user.click(checkboxes[0]);
    await user.click(checkboxes[1]);

    expect(screen.getByRole("button", { name: /continuer/i })).toBeDisabled();
  });

  it("calls onConfirm with only checked series IDs", async () => {
    const onConfirm = vi.fn();
    const user = userEvent.setup();
    renderWithProviders(
      <MergeSeriesConfirmModal {...defaultProps} onConfirm={onConfirm} />,
    );

    // Uncheck the second entry
    const checkboxes = screen.getAllByRole("checkbox");
    await user.click(checkboxes[1]);

    await user.click(screen.getByRole("button", { name: /continuer/i }));

    expect(onConfirm).toHaveBeenCalledWith([1, 3]);
  });

  it("calls onClose when cancel button is clicked", async () => {
    const onClose = vi.fn();
    const user = userEvent.setup();
    renderWithProviders(
      <MergeSeriesConfirmModal {...defaultProps} onClose={onClose} />,
    );

    await user.click(screen.getByRole("button", { name: /annuler/i }));

    expect(onClose).toHaveBeenCalled();
  });

  it("does not render when open is false", () => {
    renderWithProviders(
      <MergeSeriesConfirmModal {...defaultProps} open={false} />,
    );

    expect(screen.queryByText("Naruto Tome 1")).not.toBeInTheDocument();
  });

  it("enables confirm button when exactly 2 series are checked", async () => {
    const user = userEvent.setup();
    renderWithProviders(<MergeSeriesConfirmModal {...defaultProps} />);

    const checkboxes = screen.getAllByRole("checkbox");
    // Uncheck one of three → exactly 2 left
    await user.click(checkboxes[0]);

    expect(screen.getByRole("button", { name: /continuer/i })).toBeEnabled();
  });

  it("allows re-checking a previously unchecked entry", async () => {
    const user = userEvent.setup();
    renderWithProviders(<MergeSeriesConfirmModal {...defaultProps} />);

    const checkboxes = screen.getAllByRole("checkbox");
    await user.click(checkboxes[1]);
    expect(checkboxes[1]).not.toBeChecked();

    await user.click(checkboxes[1]);
    expect(checkboxes[1]).toBeChecked();
  });

  it("renders without crash when entries is empty", () => {
    renderWithProviders(
      <MergeSeriesConfirmModal {...defaultProps} entries={[]} />,
    );

    expect(screen.queryAllByRole("checkbox")).toHaveLength(0);
    expect(screen.getByRole("button", { name: /continuer/i })).toBeDisabled();
  });

  it("resets checkboxes when entries change", async () => {
    const user = userEvent.setup();
    const { rerender } = renderWithProviders(
      <MergeSeriesConfirmModal {...defaultProps} />,
    );

    // Uncheck first entry
    const checkboxes = screen.getAllByRole("checkbox");
    await user.click(checkboxes[0]);
    expect(checkboxes[0]).not.toBeChecked();

    // Re-render with different entries
    rerender(
      <MergeSeriesConfirmModal
        {...defaultProps}
        entries={[
          { id: 10, title: "One Piece Vol 1" },
          { id: 11, title: "One Piece Vol 2" },
        ]}
      />,
    );

    const newCheckboxes = screen.getAllByRole("checkbox");
    expect(newCheckboxes).toHaveLength(2);
    newCheckboxes.forEach((cb) => expect(cb).toBeChecked());
  });
});
