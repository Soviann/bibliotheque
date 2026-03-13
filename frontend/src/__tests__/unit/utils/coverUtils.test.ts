import { getCoverSrc } from "../../../utils/coverUtils";

describe("getCoverSrc", () => {
  it("returns local path when coverImage is set", () => {
    expect(getCoverSrc({ coverImage: "abc.jpg", coverUrl: "https://example.com/img.jpg" }))
      .toBe("/uploads/covers/abc.jpg");
  });

  it("returns coverUrl when coverImage is null", () => {
    expect(getCoverSrc({ coverImage: null, coverUrl: "https://example.com/img.jpg" }))
      .toBe("https://example.com/img.jpg");
  });

  it("returns null when both are null", () => {
    expect(getCoverSrc({ coverImage: null, coverUrl: null })).toBeNull();
  });

  it("returns null when coverImage is null and coverUrl is undefined", () => {
    expect(getCoverSrc({ coverImage: null, coverUrl: undefined })).toBeNull();
  });

  it("prefers coverImage over coverUrl", () => {
    expect(getCoverSrc({ coverImage: "local.jpg", coverUrl: "https://remote.com/img.jpg" }))
      .toBe("/uploads/covers/local.jpg");
  });
});
