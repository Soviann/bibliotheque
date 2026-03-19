import { Disclosure, DisclosureButton, DisclosurePanel } from "@headlessui/react";
import { ChevronDown } from "lucide-react";

interface CollapsibleSectionProps {
  children: React.ReactNode;
  defaultOpen?: boolean;
  title: string;
}

export default function CollapsibleSection({
  children,
  defaultOpen = true,
  title,
}: CollapsibleSectionProps) {
  return (
    <Disclosure defaultOpen={defaultOpen}>
      {({ open }) => (
        <div>
          <DisclosureButton className="flex w-full items-center gap-2 rounded-lg py-2 text-left text-sm font-semibold text-text-primary hover:text-primary-600">
            <ChevronDown
              className={`h-4 w-4 transition-transform ${open ? "" : "-rotate-90"}`}
            />
            {title}
          </DisclosureButton>
          <DisclosurePanel static>
            <div className="space-y-5" hidden={!open}>
              {children}
            </div>
          </DisclosurePanel>
        </div>
      )}
    </Disclosure>
  );
}
