import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render } from "@testing-library/react";
import type { RenderOptions } from "@testing-library/react";
import type { ReactElement, ReactNode } from "react";
import { MemoryRouter } from "react-router-dom";

export function createTestQueryClient() {
  return new QueryClient({
    defaultOptions: {
      mutations: { retry: false },
      queries: { gcTime: Infinity, retry: false, staleTime: Infinity },
    },
  });
}

interface ProvidersOptions {
  initialEntries?: string[];
  queryClient?: QueryClient;
}

function createProviders({
  initialEntries = ["/"],
  queryClient,
}: ProvidersOptions = {}) {
  const client = queryClient ?? createTestQueryClient();

  return function AllProviders({ children }: { children: ReactNode }) {
    return (
      <QueryClientProvider client={client}>
        <MemoryRouter initialEntries={initialEntries}>{children}</MemoryRouter>
      </QueryClientProvider>
    );
  };
}

export function renderWithProviders(
  ui: ReactElement,
  options?: Omit<RenderOptions, "wrapper"> & ProvidersOptions,
) {
  const { initialEntries, queryClient, ...renderOptions } = options ?? {};
  const wrapper = createProviders({ initialEntries, queryClient });
  return {
    ...render(ui, { wrapper, ...renderOptions }),
    queryClient: queryClient ?? createTestQueryClient(),
  };
}

export { render };
