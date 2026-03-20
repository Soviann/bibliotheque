import { Html5Qrcode } from "html5-qrcode";
import { Camera, X } from "lucide-react";
import { useCallback, useEffect, useRef, useState } from "react";
import { toast } from "sonner";

interface BarcodeScannerProps {
  onScan: (isbn: string) => void;
}

export default function BarcodeScanner({ onScan }: BarcodeScannerProps) {
  const [scanning, setScanning] = useState(false);
  const scannerRef = useRef<Html5Qrcode | null>(null);
  const onScanRef = useRef(onScan);
  onScanRef.current = onScan;

  const stopScanner = useCallback(() => {
    scannerRef.current?.stop().catch(() => {});
    scannerRef.current = null;
    setScanning(false);
  }, []);

  useEffect(() => {
    if (!scanning) return;

    const container = document.getElementById("barcode-scanner");
    if (!container) return;

    const scanner = new Html5Qrcode("barcode-scanner");
    scannerRef.current = scanner;

    scanner
      .start(
        { facingMode: "environment" },
        { fps: 10, qrbox: { width: 250, height: 100 } },
        (decodedText) => {
          const isbn = decodedText.replace(/[^0-9X]/gi, "");
          if (isbn.length === 10 || isbn.length === 13) {
            scanner.stop().catch(() => {});
            scannerRef.current = null;
            setScanning(false);
            onScanRef.current(isbn);
          }
        },
        () => {},
      )
      .catch(() => {
        scannerRef.current = null;
        setScanning(false);
        toast.error("Impossible d'accéder à la caméra");
      });

    return () => {
      if (scannerRef.current === scanner) {
        scanner.stop().catch(() => {});
        scannerRef.current = null;
      }
    };
  }, [scanning]);

  return (
    <div>
      {!scanning ? (
        <button
          className="flex items-center gap-2 rounded-lg bg-surface-tertiary px-3 py-2 text-sm font-medium text-text-secondary hover:bg-surface-border"
          onClick={() => setScanning(true)}
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
          />
        </div>
      )}
    </div>
  );
}
