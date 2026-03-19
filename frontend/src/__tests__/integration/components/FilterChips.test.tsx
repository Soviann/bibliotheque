import { screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import FilterChips from "../../../components/FilterChips";
import { renderWithProviders } from "../../helpers/test-utils";

describe("FilterChips", () => {
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

  it("renders all type chips", () => {
    renderWithProviders(<FilterChips {...defaultProps} />);

    expect(screen.getByRole("button", { name: "BD" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Comics" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Livre" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Manga" })).toBeInTheDocument();
  });

  it("renders all status chips", () => {
    renderWithProviders(<FilterChips {...defaultProps} />);

    expect(screen.getByRole("button", { name: "En cours" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Terminé" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Arrêté" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Souhaits" })).toBeInTheDocument();
  });

  it("calls onTypeChange with type value when clicking an inactive type chip", async () => {
    const user = userEvent.setup();
    renderWithProviders(<FilterChips {...defaultProps} />);

    await user.click(screen.getByRole("button", { name: "Manga" }));

    expect(defaultProps.onTypeChange).toHaveBeenCalledWith("manga");
  });

  it("calls onTypeChange with empty string when clicking the active type chip", async () => {
    const user = userEvent.setup();
    renderWithProviders(<FilterChips {...defaultProps} type="manga" />);

    await user.click(screen.getByRole("button", { name: "Manga" }));

    expect(defaultProps.onTypeChange).toHaveBeenCalledWith("");
  });

  it("calls onStatusChange with status value when clicking an inactive status chip", async () => {
    const user = userEvent.setup();
    renderWithProviders(<FilterChips {...defaultProps} />);

    await user.click(screen.getByRole("button", { name: "Terminé" }));

    expect(defaultProps.onStatusChange).toHaveBeenCalledWith("finished");
  });

  it("calls onStatusChange with empty string when clicking the active status chip", async () => {
    const user = userEvent.setup();
    renderWithProviders(<FilterChips {...defaultProps} status="finished" />);

    await user.click(screen.getByRole("button", { name: "Terminé" }));

    expect(defaultProps.onStatusChange).toHaveBeenCalledWith("");
  });

  it("marks the active type chip with aria-pressed=true", () => {
    renderWithProviders(<FilterChips {...defaultProps} type="bd" />);

    expect(screen.getByRole("button", { name: "BD" })).toHaveAttribute("aria-pressed", "true");
    expect(screen.getByRole("button", { name: "Manga" })).toHaveAttribute("aria-pressed", "false");
  });

  it("marks the active status chip with aria-pressed=true", () => {
    renderWithProviders(<FilterChips {...defaultProps} status="buying" />);

    expect(screen.getByRole("button", { name: "En cours" })).toHaveAttribute("aria-pressed", "true");
    expect(screen.getByRole("button", { name: "Terminé" })).toHaveAttribute("aria-pressed", "false");
  });

  it("has a scrollable container", () => {
    renderWithProviders(<FilterChips {...defaultProps} />);

    const container = screen.getByTestId("filter-chips");
    expect(container).toBeInTheDocument();
  });

  it("switches type when clicking a different type chip while one is active", async () => {
    const user = userEvent.setup();
    renderWithProviders(<FilterChips {...defaultProps} type="manga" />);

    await user.click(screen.getByRole("button", { name: "BD" }));

    expect(defaultProps.onTypeChange).toHaveBeenCalledWith("bd");
  });

  it("allows type and status to be active simultaneously", () => {
    renderWithProviders(<FilterChips {...defaultProps} status="buying" type="manga" />);

    expect(screen.getByRole("button", { name: "Manga" })).toHaveAttribute("aria-pressed", "true");
    expect(screen.getByRole("button", { name: "En cours" })).toHaveAttribute("aria-pressed", "true");
  });
});
