"use client";

import Link from "next/link";
import { FormEvent, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import {
  ArrowRight,
  ChevronDown,
  Eye,
  EyeOff,
  LockKeyhole,
  Mail,
} from "lucide-react";

import AuthShell from "@/components/auth/AuthShell";
import GoogleSignInButton from "@/components/auth/GoogleSignInButton";
import {
  getCurrentUser,
  getPostAuthRoute,
  saveAuth,
} from "@/lib/auth";
import { API_BASE_URL } from "@/lib/api-config";
import type { User } from "@/types/auth";

type LoginResponse = {
  token?: string;
  user?: User;
  message?: string;
};

export default function LoginPage() {
  const router = useRouter();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  useEffect(() => {
    const currentUser = getCurrentUser();

    if (currentUser) {
      router.replace(getPostAuthRoute(currentUser));
    }
  }, [router]);

  async function handleLegacyLogin(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (loading) return;

    setError("");
    setLoading(true);

    try {
      const response = await fetch(`${API_BASE_URL}/login`, {
        method: "POST",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ email: email.trim(), password }),
      });

      const result = (await response.json().catch(() => null)) as
        | LoginResponse
        | null;

      if (!response.ok || !result?.token || !result.user) {
        throw new Error(
          result?.message || "Email ou mot de passe incorrect."
        );
      }

      saveAuth(result.token, result.user);
      window.dispatchEvent(new Event("gotfit:auth"));
      router.replace(getPostAuthRoute(result.user));
    } catch (loginError) {
      setError(
        loginError instanceof Error
          ? loginError.message
          : "La connexion n’a pas pu aboutir."
      );
    } finally {
      setLoading(false);
    }
  }

  return (
    <AuthShell
      eyebrow="Ravi de vous revoir"
      title="Connectez-vous en un clic."
      description="Utilisez votre compte Google : aucun nouveau mot de passe à retenir et vos informations essentielles sont déjà prêtes."
    >
      {error && (
        <div
          role="alert"
          className="mb-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold leading-6 text-red-700"
        >
          {error}
        </div>
      )}

      <div className="rounded-[1.75rem] border border-slate-200 bg-slate-50/70 p-5 sm:p-6">
        <GoogleSignInButton flow="login" onError={setError} />
      </div>

      <div className="my-7 flex items-center gap-4">
        <span className="h-px flex-1 bg-slate-200" />
        <span className="text-[11px] font-black uppercase tracking-[0.16em] text-slate-400">
          Accès historique
        </span>
        <span className="h-px flex-1 bg-slate-200" />
      </div>

      <details className="group rounded-2xl border border-slate-200 bg-white">
        <summary className="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 text-sm font-black text-slate-700">
          Se connecter avec email et mot de passe
          <ChevronDown
            size={18}
            className="transition group-open:rotate-180"
          />
        </summary>

        <form
          onSubmit={handleLegacyLogin}
          className="grid gap-4 border-t border-slate-100 px-5 pb-5 pt-4"
        >
          <label className="grid gap-2 text-sm font-bold text-slate-700">
            Adresse email
            <span className="gotfit-field">
              <Mail size={18} />
              <input
                type="email"
                value={email}
                onChange={(event) => setEmail(event.target.value)}
                autoComplete="email"
                placeholder="vous@exemple.com"
                required
              />
            </span>
          </label>

          <label className="grid gap-2 text-sm font-bold text-slate-700">
            Mot de passe
            <span className="gotfit-field">
              <LockKeyhole size={18} />
              <input
                type={showPassword ? "text" : "password"}
                value={password}
                onChange={(event) => setPassword(event.target.value)}
                autoComplete="current-password"
                placeholder="Votre mot de passe"
                required
              />
              <button
                type="button"
                onClick={() => setShowPassword((visible) => !visible)}
                aria-label={
                  showPassword
                    ? "Masquer le mot de passe"
                    : "Afficher le mot de passe"
                }
              >
                {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
              </button>
            </span>
          </label>

          <div className="flex justify-end">
            <Link
              href="/auth/forgot-password"
              className="text-xs font-black text-[var(--brand-strong)] hover:underline"
            >
              Mot de passe oublié ?
            </Link>
          </div>

          <button
            type="submit"
            disabled={loading}
            className="gotfit-button gotfit-button-dark"
          >
            {loading ? "Connexion…" : "Se connecter"}
            {!loading && <ArrowRight size={17} />}
          </button>
        </form>
      </details>

      <p className="mt-7 text-center text-sm text-slate-600">
        Première visite ?{" "}
        <Link
          href="/auth/register"
          className="font-black text-[var(--ink)] underline decoration-[var(--brand)] decoration-2 underline-offset-4"
        >
          Créer mon compte
        </Link>
      </p>
    </AuthShell>
  );
}
