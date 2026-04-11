import { Html5Qrcode } from "html5-qrcode";
import { Camera, Loader2 } from "lucide-react";
import { useCallback, useEffect, useRef, useState } from "react";
import { toast } from "sonner";
import { fetchLookupIsbn } from "../hooks/useLookup";
import type { LookupResult } from "../types/api";
import CoverImage from "./CoverImage";

interface QuickAddScanProps {
  batchMode: boolean;
  onAdd: (result: {
    coverUrl: string | null;
    title: string;
    tomeNumber: number;
  }) => void;
}

export default function QuickAddScan({ batchMode, onAdd }: QuickAddScanProps) {
  const [scanning, setScanning] = useState(false);
  const [loading, setLoading] = useState(false);
  const [preview, setPreview] = useState<LookupResult | null>(null);
  const scannerRef = useRef<Html5Qrcode | null>(null);
  const onAddRef = useRef(onAdd);
  onAddRef.current = onAdd;

  const startScanner = useCallback(() => {
    setPreview(null);
    setScanning(true);
  }, []);

  useEffect(() => {
    if (!scanning) return;

    const container = document.getElementById("quick-add-scanner");
    if (!container) return;

    const scanner = new Html5Qrcode("quick-add-scanner");
    scannerRef.current = scanner;

    scanner
      .start(
        { facingMode: "environment" },
        { fps: 10, qrbox: { width: 250, height: 100 } },
        async (decodedText) => {
          const isbn = decodedText.replace(/[^0-9X]/gi, "");
          if (isbn.length !== 10 && isbn.length !== 13) return;

          scanner.stop().catch(() => {});
          scannerRef.current = null;
          setScanning(false);
          setLoading(true);

          try {
            const result = await fetchLookupIsbn(isbn);
            if (result.title) {
              setPreview(result);
            } else {
              toast.error("ISBN non trouvé");
              startScanner();
            }
          } catch {
            toast.error("Erreur lors de la recherche");
            startScanner();
          } finally {
            setLoading(false);
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
  }, [scanning, startScanner]);

  const handleConfirm = useCallback(() => {
    if (!preview) return;
    onAddRef.current({
      coverUrl: preview.thumbnail,
      title: preview.title ?? "Sans titre",
      tomeNumber: preview.tomeNumber ?? 1,
    });
    setPreview(null);
    if (batchMode) {
      startScanner();
    }
  }, [preview, batchMode, startScanner]);

  if (loading) {
    return (
      <div className="flex flex-1 flex-col items-center justify-center gap-3">
        <Loader2 className="h-8 w-8 animate-spin text-primary-500" />
        <p className="text-sm text-text-muted">Recherche en cours…</p>
      </div>
    );
  }

  if (preview) {
    return (
      <div className="flex flex-1 flex-col items-center justify-center gap-4 px-4">
        {preview.thumbnail && (
          <CoverImage
            alt={preview.title ?? ""}
            className="h-48 w-36 rounded-xl shadow-lg"
            src={preview.thumbnail}
          />
        )}
        <div className="text-center">
          <h3 className="font-display text-lg font-semibold text-text-primary">
            {preview.title}
          </h3>
          {preview.tomeNumber && (
            <p className="text-sm text-text-muted">Tome {preview.tomeNumber}</p>
          )}
          {preview.publisher && (
            <p className="text-xs text-text-muted">{preview.publisher}</p>
          )}
        </div>
        <div className="flex gap-3">
          <button
            className="rounded-xl bg-primary-600 px-6 py-2.5 text-sm font-medium text-white transition-transform active:scale-95"
            onClick={handleConfirm}
            type="button"
          >
            Ajouter
          </button>
          <button
            className="rounded-xl border border-surface-border px-6 py-2.5 text-sm font-medium text-text-secondary"
            onClick={startScanner}
            type="button"
          >
            Rescanner
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="flex flex-1 flex-col items-center justify-center gap-4">
      {!scanning ? (
        <button
          className="flex flex-col items-center gap-3 rounded-2xl bg-surface-tertiary px-8 py-6 transition-transform active:scale-95"
          onClick={startScanner}
          type="button"
        >
          <Camera className="h-12 w-12 text-primary-500" />
          <span className="text-sm font-medium text-text-secondary">
            Appuyer pour scanner
          </span>
        </button>
      ) : (
        <div
          className="w-full max-w-sm overflow-hidden rounded-2xl"
          id="quick-add-scanner"
        />
      )}
    </div>
  );
}
