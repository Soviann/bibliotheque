import { GoogleLogin } from "@react-oauth/google";
import { useState } from "react";
import { useAuth } from "../hooks/useAuth";

const showDevLogin =
  import.meta.env.DEV && import.meta.env.VITE_DEBUG_LOGIN === "true";

export default function Login() {
  const { devLogin, devLoginError, devLoginPending, login, loginError, loginPending } =
    useAuth();
  const [googleError, setGoogleError] = useState(false);

  const handleDevLogin = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    const form = new FormData(e.currentTarget);
    devLogin({
      username: form.get("username") as string,
      password: form.get("password") as string,
    });
  };

  return (
    <div className="flex min-h-screen items-center justify-center bg-surface-secondary px-4">
      <div className="w-full max-w-sm space-y-6">
        <div className="text-center">
          <img alt="" className="mx-auto h-20 w-20 rounded-2xl shadow-lg" src="/app-icon.png" />
          <h1 className="mt-4 font-display text-3xl font-bold text-text-primary">
            Bibliothèque
          </h1>
        </div>

        <div className="space-y-4">
          {(loginError ?? devLoginError ?? googleError) && (
            <p className="rounded-xl bg-red-100 p-3 text-sm text-red-700 dark:bg-red-950/30 dark:text-red-400">
              {loginError?.message ??
                devLoginError?.message ??
                "Erreur lors de la connexion Google. Veuillez réessayer."}
            </p>
          )}

          <div className="flex justify-center">
            <GoogleLogin
              onError={() => setGoogleError(true)}
              onSuccess={(response) => {
                if (response.credential) {
                  login(response.credential);
                }
              }}
              shape="rectangular"
              size="large"
              text="signin_with"
              theme="filled_blue"
              width="300"
            />
          </div>

          {(loginPending || devLoginPending) && (
            <p className="text-center text-sm text-text-muted">Connexion…</p>
          )}
        </div>

        {showDevLogin && (
          <form
            className="space-y-3 rounded-xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-950/30"
            onSubmit={handleDevLogin}
          >
            <p className="text-center text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-400">
              Dev Login
            </p>
            <input
              autoComplete="username"
              className="w-full rounded-lg border border-border-primary bg-surface-primary px-3 py-2 text-sm text-text-primary"
              name="username"
              placeholder="Identifiant"
              required
              type="text"
            />
            <input
              autoComplete="current-password"
              className="w-full rounded-lg border border-border-primary bg-surface-primary px-3 py-2 text-sm text-text-primary"
              name="password"
              placeholder="Mot de passe"
              required
              type="password"
            />
            <button
              className="w-full rounded-lg bg-amber-600 py-2 text-sm font-medium text-white hover:bg-amber-700 disabled:opacity-50"
              disabled={devLoginPending}
              type="submit"
            >
              Connexion dev
            </button>
          </form>
        )}
      </div>
    </div>
  );
}
