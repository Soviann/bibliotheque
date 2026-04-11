import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import CoverLightbox from "../../../components/CoverLightbox";

describe("CoverLightbox", () => {
  it("renders nothing when not open", () => {
    render(
      <CoverLightbox
        onClose={vi.fn()}
        open={false}
        src="/cover.jpg"
        title="Test"
      />,
    );

    expect(screen.queryByRole("dialog")).not.toBeInTheDocument();
  });

  it("renders image in dialog when open", () => {
    render(
      <CoverLightbox
        onClose={vi.fn()}
        open={true}
        src="/cover.jpg"
        title="Test"
      />,
    );

    expect(screen.getByRole("dialog")).toBeInTheDocument();
    const img = screen.getByAltText("Test");
    expect(img).toHaveAttribute("src", "/cover.jpg");
  });

  it("calls onClose when clicking the backdrop", async () => {
    const user = userEvent.setup();
    const onClose = vi.fn();

    render(
      <CoverLightbox
        onClose={onClose}
        open={true}
        src="/cover.jpg"
        title="Test"
      />,
    );

    // Click outside the image (on the backdrop)
    await user.click(screen.getByRole("dialog"));

    expect(onClose).toHaveBeenCalled();
  });

  it("calls onClose when pressing Escape", async () => {
    const user = userEvent.setup();
    const onClose = vi.fn();

    render(
      <CoverLightbox
        onClose={onClose}
        open={true}
        src="/cover.jpg"
        title="Test"
      />,
    );

    await user.keyboard("{Escape}");

    expect(onClose).toHaveBeenCalled();
  });
});
