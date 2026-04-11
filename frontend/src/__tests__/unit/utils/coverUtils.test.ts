import { getCoverSrc, getCoverThumbnailSrc } from "../../../utils/coverUtils";

describe("getCoverSrc", () => {
  it("returns local path when coverImage is set", () => {
    expect(
      getCoverSrc({
        coverImage: "abc.jpg",
        coverUrl: "https://example.com/img.jpg",
      }),
    ).toBe("/uploads/covers/abc.jpg");
  });

  it("appends cache-busting param when updatedAt is provided", () => {
    const updatedAt = "2025-01-15T10:30:00+00:00";
    const timestamp = new Date(updatedAt).getTime();
    expect(
      getCoverSrc({ coverImage: "abc.jpg", coverUrl: null, updatedAt }),
    ).toBe(`/uploads/covers/abc.jpg?v=${timestamp}`);
  });

  it("returns coverUrl without cache-busting param", () => {
    expect(
      getCoverSrc({
        coverImage: null,
        coverUrl: "https://example.com/img.jpg",
        updatedAt: "2025-01-15T10:30:00+00:00",
      }),
    ).toBe("https://example.com/img.jpg");
  });

  it("returns coverUrl when coverImage is null", () => {
    expect(
      getCoverSrc({
        coverImage: null,
        coverUrl: "https://example.com/img.jpg",
      }),
    ).toBe("https://example.com/img.jpg");
  });

  it("returns null when both are null", () => {
    expect(getCoverSrc({ coverImage: null, coverUrl: null })).toBeNull();
  });

  it("returns null when coverImage is null and coverUrl is undefined", () => {
    expect(getCoverSrc({ coverImage: null, coverUrl: undefined })).toBeNull();
  });

  it("prefers coverImage over coverUrl", () => {
    expect(
      getCoverSrc({
        coverImage: "local.jpg",
        coverUrl: "https://remote.com/img.jpg",
      }),
    ).toBe("/uploads/covers/local.jpg");
  });

  it("rejects coverUrl with javascript: scheme", () => {
    // eslint-disable-next-line no-script-url
    expect(
      getCoverSrc({ coverImage: null, coverUrl: "javascript:alert(1)" }),
    ).toBeNull();
  });

  it("rejects coverUrl with data: scheme", () => {
    expect(
      getCoverSrc({
        coverImage: null,
        coverUrl: "data:text/html,<script>alert(1)</script>",
      }),
    ).toBeNull();
  });

  it("accepts http:// coverUrl", () => {
    expect(
      getCoverSrc({ coverImage: null, coverUrl: "http://example.com/img.jpg" }),
    ).toBe("http://example.com/img.jpg");
  });

  it("accepts https:// coverUrl", () => {
    expect(
      getCoverSrc({
        coverImage: null,
        coverUrl: "https://example.com/img.jpg",
      }),
    ).toBe("https://example.com/img.jpg");
  });
});

describe("getCoverThumbnailSrc", () => {
  it("returns LiipImagine thumbnail path for local cover", () => {
    expect(
      getCoverThumbnailSrc({ coverImage: "abc.webp", coverUrl: null }),
    ).toBe("/media/cache/cover_thumbnail/uploads/covers/abc.webp");
  });

  it("appends cache-busting param when updatedAt is provided", () => {
    const updatedAt = "2025-01-15T10:30:00+00:00";
    const timestamp = new Date(updatedAt).getTime();
    expect(
      getCoverThumbnailSrc({
        coverImage: "abc.webp",
        coverUrl: null,
        updatedAt,
      }),
    ).toBe(
      `/media/cache/cover_thumbnail/uploads/covers/abc.webp?v=${timestamp}`,
    );
  });

  it("returns null when no local cover", () => {
    expect(
      getCoverThumbnailSrc({
        coverImage: null,
        coverUrl: "https://example.com/img.jpg",
      }),
    ).toBeNull();
  });

  it("returns null when coverImage is null", () => {
    expect(getCoverThumbnailSrc({ coverImage: null })).toBeNull();
  });

  it("prefers thumbnail over external URL", () => {
    expect(
      getCoverThumbnailSrc({
        coverImage: "local.webp",
        coverUrl: "https://remote.com/img.jpg",
      }),
    ).toBe("/media/cache/cover_thumbnail/uploads/covers/local.webp");
  });
});
