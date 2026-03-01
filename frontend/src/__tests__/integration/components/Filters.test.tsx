import { screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import Filters from "../../../components/Filters";
import { renderWithProviders } from "../../helpers/test-utils";

describe("Filters", () => {
  const defaultProps = {
    onStatusChange: vi.fn(),
    onTypeChange: vi.fn(),
    status: "",
    type: "",
  };

  beforeEach(() => {
    defaultProps.onStatusChange = vi.fn();
    defaultProps.onTypeChange = vi.fn();
  });

  it("renders type filter with default label", () => {
    renderWithProviders(<Filters {...defaultProps} />);

    expect(screen.getByText("Tous les types")).toBeInTheDocument();
  });

  it("renders status filter with default label", () => {
    renderWithProviders(<Filters {...defaultProps} />);

    expect(screen.getByText("Tous les statuts")).toBeInTheDocument();
  });

  it("hides status filter when hideStatus is true", () => {
    renderWithProviders(<Filters {...defaultProps} hideStatus />);

    expect(screen.queryByText("Tous les statuts")).not.toBeInTheDocument();
    expect(screen.getByText("Tous les types")).toBeInTheDocument();
  });

  it("calls onTypeChange when type filter is changed", async () => {
    const user = userEvent.setup();
    renderWithProviders(<Filters {...defaultProps} />);

    // Open the type listbox
    await user.click(screen.getByText("Tous les types"));
    // Select "Manga"
    await user.click(screen.getByText("Manga"));

    expect(defaultProps.onTypeChange).toHaveBeenCalledWith("manga");
  });

  it("calls onStatusChange when status filter is changed", async () => {
    const user = userEvent.setup();
    renderWithProviders(<Filters {...defaultProps} />);

    // Open the status listbox
    await user.click(screen.getByText("Tous les statuts"));
    // Select a status option
    await user.click(screen.getByText("Terminé"));

    expect(defaultProps.onStatusChange).toHaveBeenCalledWith("finished");
  });

  it("shows selected type label", () => {
    renderWithProviders(<Filters {...defaultProps} type="manga" />);

    expect(screen.getByText("Manga")).toBeInTheDocument();
  });

  it("shows selected status label", () => {
    renderWithProviders(<Filters {...defaultProps} status="buying" />);

    expect(screen.getByText("En cours d'achat")).toBeInTheDocument();
  });

  it("calls onTypeChange with empty string when selecting 'Tous les types'", async () => {
    const user = userEvent.setup();
    renderWithProviders(<Filters {...defaultProps} type="manga" />);

    // Open type filter (currently showing "Manga")
    await user.click(screen.getByText("Manga"));
    // Select "Tous les types"
    await user.click(screen.getByText("Tous les types"));

    expect(defaultProps.onTypeChange).toHaveBeenCalledWith("");
  });
});
