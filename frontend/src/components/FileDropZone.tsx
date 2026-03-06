import { FileSpreadsheet, Upload } from "lucide-react";
import { useCallback, useRef, useState } from "react";
import type { DragEvent } from "react";

interface FileDropZoneProps {
  accept?: string;
  onFileSelect: (file: File) => void;
}

export default function FileDropZone({
  accept = ".xlsx",
  onFileSelect,
}: FileDropZoneProps) {
  const [isDragOver, setIsDragOver] = useState(false);
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  const handleFile = useCallback(
    (file: File) => {
      setSelectedFile(file);
      onFileSelect(file);
    },
    [onFileSelect],
  );

  const handleDrop = useCallback(
    (e: DragEvent<HTMLDivElement>) => {
      e.preventDefault();
      setIsDragOver(false);

      const file = e.dataTransfer.files[0];
      if (file) {
        handleFile(file);
      }
    },
    [handleFile],
  );

  const handleDragOver = useCallback((e: DragEvent<HTMLDivElement>) => {
    e.preventDefault();
    setIsDragOver(true);
  }, []);

  const handleDragLeave = useCallback(() => {
    setIsDragOver(false);
  }, []);

  return (
    <div
      className={`flex cursor-pointer flex-col items-center gap-3 rounded-xl border-2 border-dashed p-8 transition ${
        isDragOver
          ? "border-primary-500 bg-primary-50 dark:bg-primary-950/20"
          : "border-surface-border hover:border-primary-400"
      }`}
      onClick={() => inputRef.current?.click()}
      onDragLeave={handleDragLeave}
      onDragOver={handleDragOver}
      onDrop={handleDrop}
      role="button"
      tabIndex={0}
    >
      <input
        accept={accept}
        className="hidden"
        data-testid="file-input"
        onChange={(e) => {
          const file = e.target.files?.[0];
          if (file) {
            handleFile(file);
          }
        }}
        ref={inputRef}
        type="file"
      />
      {selectedFile ? (
        <>
          <FileSpreadsheet className="h-8 w-8 text-primary-600 dark:text-primary-400" />
          <span className="text-sm font-medium text-text-primary">
            {selectedFile.name}
          </span>
          <span className="text-xs text-text-secondary">
            Cliquer ou deposer pour changer de fichier
          </span>
        </>
      ) : (
        <>
          <Upload className="h-8 w-8 text-text-muted" />
          <span className="text-sm text-text-secondary">
            Glisser-deposer un fichier {accept} ou cliquer pour parcourir
          </span>
        </>
      )}
    </div>
  );
}
