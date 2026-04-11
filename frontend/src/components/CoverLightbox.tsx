import { Dialog, DialogBackdrop, DialogPanel } from "@headlessui/react";

interface CoverLightboxProps {
  onClose: () => void;
  open: boolean;
  src: string;
  title: string;
}

export default function CoverLightbox({
  onClose,
  open,
  src,
  title,
}: CoverLightboxProps) {
  return (
    <Dialog className="relative z-50" onClose={onClose} open={open}>
      <DialogBackdrop className="fixed inset-0 bg-black/80 transition duration-200 ease-out data-closed:opacity-0" />
      <div className="fixed inset-0 flex items-center justify-center p-4">
        <DialogPanel className="transition duration-200 ease-out data-closed:scale-95 data-closed:opacity-0">
          <img
            alt={title}
            className="max-h-[90vh] max-w-[90vw] rounded-lg object-contain shadow-2xl"
            src={src}
          />
        </DialogPanel>
      </div>
    </Dialog>
  );
}
