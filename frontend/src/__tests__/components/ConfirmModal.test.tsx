import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";
import ConfirmModal from "../../components/ConfirmModal";

describe("ConfirmModal", () => {
  it("renders title and description when open", () => {
    render(
      <ConfirmModal
        description="Cette action est irréversible."
        onClose={vi.fn()}
        onConfirm={vi.fn()}
        open={true}
        title="Supprimer ?"
      />,
    );

    expect(screen.getByText("Supprimer ?")).toBeInTheDocument();
    expect(
      screen.getByText("Cette action est irréversible."),
    ).toBeInTheDocument();
  });

  it("renders nothing when closed", () => {
    render(
      <ConfirmModal
        description="Description"
        onClose={vi.fn()}
        onConfirm={vi.fn()}
        open={false}
        title="Title"
      />,
    );

    expect(screen.queryByText("Title")).not.toBeInTheDocument();
  });

  it("uses custom confirm label", () => {
    render(
      <ConfirmModal
        confirmLabel="Oui, supprimer"
        description="Description"
        onClose={vi.fn()}
        onConfirm={vi.fn()}
        open={true}
        title="Title"
      />,
    );

    expect(
      screen.getByRole("button", { name: "Oui, supprimer" }),
    ).toBeInTheDocument();
  });

  it("calls onConfirm and onClose when confirm button clicked", async () => {
    const onConfirm = vi.fn();
    const onClose = vi.fn();
    const user = userEvent.setup();

    render(
      <ConfirmModal
        description="Description"
        onClose={onClose}
        onConfirm={onConfirm}
        open={true}
        title="Title"
      />,
    );

    await user.click(screen.getByRole("button", { name: "Confirmer" }));

    expect(onConfirm).toHaveBeenCalledOnce();
    expect(onClose).toHaveBeenCalledOnce();
  });

  it("calls onClose when cancel button clicked", async () => {
    const onClose = vi.fn();
    const user = userEvent.setup();

    render(
      <ConfirmModal
        description="Description"
        onClose={onClose}
        onConfirm={vi.fn()}
        open={true}
        title="Title"
      />,
    );

    await user.click(screen.getByRole("button", { name: "Annuler" }));

    expect(onClose).toHaveBeenCalledOnce();
  });
});
