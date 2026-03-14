import { Html5Qrcode } from "html5-qrcode";
import { Camera, X } from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { toast } from "sonner";

interface BarcodeScannerProps {
  onScan: (isbn: string) => void;
}

export default function BarcodeScanner({ onScan }: BarcodeScannerProps) {
  const [scanning, setScanning] = useState(false);
  const scannerRef = useRef<Html5Qrcode | null>(null);
  const containerRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    return () => {
      scannerRef.current?.stop().catch(() => {});
    };
  }, []);

  const startScanner = async () => {
    if (!containerRef.current) return;

    setScanning(true);
    const scanner = new Html5Qrcode(containerRef.current.id);
    scannerRef.current = scanner;

    try {
      await scanner.start(
        { facingMode: "environment" },
        { fps: 10, qrbox: { width: 250, height: 100 } },
        (decodedText) => {
          const isbn = decodedText.replace(/[^0-9X]/gi, "");
          if (isbn.length === 10 || isbn.length === 13) {
            scanner.stop().catch(() => {});
            setScanning(false);
            onScan(isbn);
          }
        },
        () => {},
      );
    } catch {
      setScanning(false);
      toast.error("Impossible d'accéder à la caméra");
    }
  };

  const stopScanner = () => {
    scannerRef.current?.stop().catch(() => {});
    setScanning(false);
  };

  return (
    <div>
      {!scanning ? (
        <button
          className="flex items-center gap-2 rounded-lg bg-surface-tertiary px-3 py-2 text-sm font-medium text-text-secondary hover:bg-surface-border"
          onClick={startScanner}
          type="button"
        >
          <Camera className="h-4 w-4" />
          Scanner
        </button>
      ) : (
        <div className="space-y-2">
          <div className="flex items-center justify-between">
            <span className="text-sm font-medium text-text-secondary">Scanner actif</span>
            <button
              aria-label="Fermer le scanner"
              className="rounded p-1 text-text-muted hover:text-text-secondary"
              onClick={stopScanner}
              type="button"
            >
              <X className="h-4 w-4" />
            </button>
          </div>
          <div
            className="overflow-hidden rounded-lg"
            id="barcode-scanner"
            ref={containerRef}
          />
        </div>
      )}
    </div>
  );
}
