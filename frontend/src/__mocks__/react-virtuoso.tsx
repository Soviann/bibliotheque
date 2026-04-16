import { forwardRef, useImperativeHandle, type ReactNode } from "react";

interface VirtuosoProps {
  itemContent: (index: number) => ReactNode;
  totalCount?: number;
  data?: unknown[];
}

export const Virtuoso = forwardRef<unknown, VirtuosoProps>(function Virtuoso(
  { itemContent, totalCount, data },
  ref,
) {
  useImperativeHandle(ref, () => ({
    getState: (cb: (s: unknown) => void) => cb(undefined),
    scrollToIndex: () => {},
  }));

  const count = totalCount ?? data?.length ?? 0;
  const rows = [] as ReactNode[];
  for (let i = 0; i < count; i++) {
    rows.push(<div key={i}>{itemContent(i)}</div>);
  }
  return <div data-testid="mock-virtuoso">{rows}</div>;
});

export type VirtuosoHandle = unknown;
export type StateSnapshot = unknown;
