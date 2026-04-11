import { render, screen, fireEvent } from "@testing-library/react";
import CoverImage from "../../../components/CoverImage";

describe("CoverImage", () => {
  it("renders image with correct src and alt", () => {
    render(<CoverImage alt="Test Cover" src="/uploads/covers/test.webp" />);

    const img = screen.getByAltText("Test Cover");
    expect(img).toHaveAttribute("src", "/uploads/covers/test.webp");
  });

  it("shows skeleton before image loads", () => {
    render(<CoverImage alt="Test" src="/uploads/covers/test.webp" />);

    expect(screen.getByTestId("cover-skeleton")).toBeInTheDocument();
  });

  it("hides skeleton after image loads", () => {
    render(<CoverImage alt="Test" src="/uploads/covers/test.webp" />);

    const img = screen.getByAltText("Test");
    fireEvent.load(img);

    expect(screen.queryByTestId("cover-skeleton")).not.toBeInTheDocument();
  });

  it("shows fallback image on error", () => {
    render(
      <CoverImage
        alt="Test"
        fallbackSrc="/placeholder-bd.jpg"
        src="/uploads/covers/broken.webp"
      />,
    );

    const img = screen.getByAltText("Test");
    fireEvent.error(img);

    expect(img).toHaveAttribute("src", "/placeholder-bd.jpg");
  });

  it("applies loading=lazy by default", () => {
    render(<CoverImage alt="Test" src="/test.webp" />);

    expect(screen.getByAltText("Test")).toHaveAttribute("loading", "lazy");
  });

  it("supports loading=eager", () => {
    render(<CoverImage alt="Test" loading="eager" src="/test.webp" />);

    expect(screen.getByAltText("Test")).toHaveAttribute("loading", "eager");
  });

  it("passes className to wrapper", () => {
    const { container } = render(
      <CoverImage alt="Test" className="custom-class" src="/test.webp" />,
    );

    expect(container.firstChild).toHaveClass("custom-class");
  });

  it("uses object-cover by default", () => {
    render(<CoverImage alt="Test" src="/test.webp" />);

    expect(screen.getByAltText("Test").className).toContain("object-cover");
  });

  it("supports objectFit=contain", () => {
    render(<CoverImage alt="Test" objectFit="contain" src="/test.webp" />);

    const img = screen.getByAltText("Test");
    expect(img.className).toContain("object-contain");
    expect(img.className).not.toContain("object-cover");
  });
});
