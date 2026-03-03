import { screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import CardActionBar from "../../../components/CardActionBar";
import { createMockComicSeries } from "../../helpers/factories";
import { renderWithProviders } from "../../helpers/test-utils";

describe("CardActionBar", () => {
  const comic = createMockComicSeries({ id: 1, title: "Naruto" });
  const defaultProps = {
    comic,
    onClose: vi.fn(),
    onDelete: vi.fn(),
    onEdit: vi.fn(),
  };

  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("renders nothing when comic is null", () => {
    const { container } = renderWithProviders(
      <CardActionBar {...defaultProps} comic={null} />,
    );

    expect(container.firstChild).toBeNull();
  });

  it("renders comic title", () => {
    renderWithProviders(<CardActionBar {...defaultProps} />);

    expect(screen.getByText("Naruto")).toBeInTheDocument();
  });

  it("renders edit and delete buttons", () => {
    renderWithProviders(<CardActionBar {...defaultProps} />);

    expect(screen.getByRole("button", { name: /modifier/i })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: /supprimer/i })).toBeInTheDocument();
  });

  it("calls onEdit with comic when edit button is clicked", async () => {
    const user = userEvent.setup();

    renderWithProviders(<CardActionBar {...defaultProps} />);

    await user.click(screen.getByRole("button", { name: /modifier/i }));

    expect(defaultProps.onEdit).toHaveBeenCalledWith(comic);
  });

  it("calls onDelete with comic when delete button is clicked", async () => {
    const user = userEvent.setup();

    renderWithProviders(<CardActionBar {...defaultProps} />);

    await user.click(screen.getByRole("button", { name: /supprimer/i }));

    expect(defaultProps.onDelete).toHaveBeenCalledWith(comic);
  });

  it("calls onClose when overlay is clicked", async () => {
    const user = userEvent.setup();

    renderWithProviders(<CardActionBar {...defaultProps} />);

    const overlay = screen.getByTestId("card-action-overlay");
    await user.click(overlay);

    expect(defaultProps.onClose).toHaveBeenCalled();
  });
});
