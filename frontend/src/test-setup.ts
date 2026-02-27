import "@testing-library/jest-dom/vitest";

// jsdom does not implement ResizeObserver — required by @headlessui/react
globalThis.ResizeObserver = class {
  observe() {}
  unobserve() {}
  disconnect() {}
};

// jsdom does not implement matchMedia — provide a minimal stub
Object.defineProperty(window, "matchMedia", {
  value: (query: string) => ({
    addEventListener: () => {},
    addListener: () => {},
    dispatchEvent: () => false,
    matches: false,
    media: query,
    onchange: null,
    removeEventListener: () => {},
    removeListener: () => {},
  }),
  writable: true,
});
