import { screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";
import TomeDrawer from "../../../components/TomeDrawer";
import { createMockTome } from "../../helpers/factories";
import { renderWithProviders } from "../../helpers/test-utils";

describe("TomeDrawer", () => {
  it("renders tome label, series title and status buttons when open", () => {
    const tome = createMockTome({
      bought: true,
      number: 3,
      onNas: false,
      read: true,
    });

    renderWithProviders(
      <TomeDrawer
        isOpen={true}
        onClose={vi.fn()}
        onToggleField={vi.fn()}
        seriesTitle="Blacksad"
        tome={tome}
        tomeNumber={3}
      />,
    );

    expect(screen.getByText(/Tome 3/)).toBeInTheDocument();
    expect(screen.getByText(/Blacksad/)).toBeInTheDocument();
    expect(screen.getByText("Sauvegarde automatique au tap")).toBeInTheDocument();
    expect(screen.getByText("Acheté")).toBeInTheDocument();
    expect(screen.getByText("Sur NAS")).toBeInTheDocument();
    expect(screen.getByText("Lu")).toBeInTheDocument();
  });

  it("renders HS prefix for hors-série", () => {
    renderWithProviders(
      <TomeDrawer
        isHorsSerie={true}
        isOpen={true}
        onClose={vi.fn()}
        onToggleField={vi.fn()}
        tomeNumber={1}
      />,
    );

    expect(screen.getByText(/HS 1/)).toBeInTheDocument();
  });

  it("calls onToggleField with 'bought' when clicking Acheté button", async () => {
    const user = userEvent.setup();
    const onToggle = vi.fn();
    const tome = createMockTome({ bought: false, number: 2 });

    renderWithProviders(
      <TomeDrawer
        isOpen={true}
        onClose={vi.fn()}
        onToggleField={onToggle}
        tome={tome}
        tomeNumber={2}
      />,
    );

    await user.click(screen.getByRole("button", { name: /Acheté/ }));
    expect(onToggle).toHaveBeenCalledWith("bought");
  });

  it("calls onToggleField with 'onNas' when clicking Sur NAS button", async () => {
    const user = userEvent.setup();
    const onToggle = vi.fn();

    renderWithProviders(
      <TomeDrawer
        isOpen={true}
        onClose={vi.fn()}
        onToggleField={onToggle}
        tomeNumber={4}
      />,
    );

    await user.click(screen.getByRole("button", { name: /Sur NAS/ }));
    expect(onToggle).toHaveBeenCalledWith("onNas");
  });

  it("calls onToggleField with 'read' when clicking Lu button", async () => {
    const user = userEvent.setup();
    const onToggle = vi.fn();

    renderWithProviders(
      <TomeDrawer
        isOpen={true}
        onClose={vi.fn()}
        onToggleField={onToggle}
        tomeNumber={5}
      />,
    );

    await user.click(screen.getByRole("button", { name: /Lu/ }));
    expect(onToggle).toHaveBeenCalledWith("read");
  });

  it("calls onClose when clicking close button", async () => {
    const user = userEvent.setup();
    const onClose = vi.fn();

    renderWithProviders(
      <TomeDrawer
        isOpen={true}
        onClose={onClose}
        onToggleField={vi.fn()}
        tomeNumber={1}
      />,
    );

    await user.click(screen.getByRole("button", { name: "Fermer" }));
    expect(onClose).toHaveBeenCalledTimes(1);
  });
});
