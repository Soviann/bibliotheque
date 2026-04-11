import { screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import ConfirmModal from "../../../components/ConfirmModal";
import { renderWithProviders } from "../../helpers/test-utils";

describe("ConfirmModal", () => {
  const defaultProps = {
    description: "Cette action est irréversible.",
    onClose: vi.fn(),
    onConfirm: vi.fn(),
    open: true,
    title: "Confirmer la suppression",
  };

  beforeEach(() => {
    defaultProps.onClose = vi.fn();
    defaultProps.onConfirm = vi.fn();
  });

  it("renders with title and description when open", () => {
    renderWithProviders(<ConfirmModal {...defaultProps} />);

    expect(screen.getByText("Confirmer la suppression")).toBeInTheDocument();
    expect(
      screen.getByText("Cette action est irréversible."),
    ).toBeInTheDocument();
  });

  it("calls onConfirm and onClose when confirm button clicked", async () => {
    const user = userEvent.setup();
    renderWithProviders(<ConfirmModal {...defaultProps} />);

    await user.click(screen.getByText("Confirmer"));

    expect(defaultProps.onConfirm).toHaveBeenCalledOnce();
    expect(defaultProps.onClose).toHaveBeenCalledOnce();
  });

  it("calls onClose when cancel button clicked", async () => {
    const user = userEvent.setup();
    renderWithProviders(<ConfirmModal {...defaultProps} />);

    await user.click(screen.getByText("Annuler"));

    expect(defaultProps.onClose).toHaveBeenCalledOnce();
    expect(defaultProps.onConfirm).not.toHaveBeenCalled();
  });

  it("uses custom confirmLabel when provided", () => {
    renderWithProviders(
      <ConfirmModal
        {...defaultProps}
        confirmLabel="Supprimer définitivement"
      />,
    );

    expect(screen.getByText("Supprimer définitivement")).toBeInTheDocument();
  });

  it("is not visible when open is false", () => {
    renderWithProviders(<ConfirmModal {...defaultProps} open={false} />);

    expect(
      screen.queryByText("Confirmer la suppression"),
    ).not.toBeInTheDocument();
  });

  it("closes modal when Escape key is pressed", async () => {
    const user = userEvent.setup();
    renderWithProviders(<ConfirmModal {...defaultProps} />);

    expect(screen.getByText("Confirmer la suppression")).toBeInTheDocument();

    await user.keyboard("{Escape}");

    expect(defaultProps.onClose).toHaveBeenCalledOnce();
  });
});
