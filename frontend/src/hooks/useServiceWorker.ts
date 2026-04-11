import { useEffect } from "react";
import { toast } from "sonner";
import { getToken } from "../services/api";

export function useServiceWorker() {
  useEffect(() => {
    async function register() {
      const { registerSW } = await import("virtual:pwa-register");
      registerSW({
        onNeedRefresh() {
          toast("Nouvelle version disponible", {
            action: {
              label: "Recharger",
              onClick: () => window.location.reload(),
            },
            duration: Infinity,
          });
        },
      });
    }
    register();

    // Respond to SW token requests for Background Sync
    const handleMessage = (event: MessageEvent) => {
      if (event.data?.type === "get-token" && event.ports[0]) {
        event.ports[0].postMessage({ token: getToken() });
      }
    };
    navigator.serviceWorker?.addEventListener("message", handleMessage);
    return () =>
      navigator.serviceWorker?.removeEventListener("message", handleMessage);
  }, []);
}
