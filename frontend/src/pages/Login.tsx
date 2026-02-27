import { BookOpen } from "lucide-react";
import { type FormEvent, useState } from "react";
import { useAuth } from "../hooks/useAuth";

export default function Login() {
  const { login, loginError, loginPending } = useAuth();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");

  function handleSubmit(e: FormEvent) {
    e.preventDefault();
    login({ email, password });
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-slate-900 px-4">
      <div className="w-full max-w-sm space-y-6">
        <div className="text-center">
          <BookOpen className="mx-auto h-12 w-12 text-primary-400" />
          <h1 className="mt-4 text-2xl font-bold text-white">Bibliothèque</h1>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4">
          {loginError && (
            <p className="rounded bg-red-900/50 p-3 text-sm text-red-300">
              {loginError.message}
            </p>
          )}

          <div>
            <label htmlFor="email" className="block text-sm font-medium text-slate-300">
              Email
            </label>
            <input
              autoComplete="email"
              className="mt-1 block w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-white placeholder-slate-400 focus:border-primary-500 focus:ring-primary-500 focus:outline-none"
              id="email"
              onChange={(e) => setEmail(e.target.value)}
              required
              type="email"
              value={email}
            />
          </div>

          <div>
            <label htmlFor="password" className="block text-sm font-medium text-slate-300">
              Mot de passe
            </label>
            <input
              autoComplete="current-password"
              className="mt-1 block w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-white placeholder-slate-400 focus:border-primary-500 focus:ring-primary-500 focus:outline-none"
              id="password"
              onChange={(e) => setPassword(e.target.value)}
              required
              type="password"
              value={password}
            />
          </div>

          <button
            className="w-full rounded-lg bg-primary-600 py-2.5 font-medium text-white hover:bg-primary-700 disabled:opacity-50"
            disabled={loginPending}
            type="submit"
          >
            {loginPending ? "Connexion…" : "Se connecter"}
          </button>
        </form>
      </div>
    </div>
  );
}
