"use client";

import Link from "next/link";
import { FormEvent, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import {
  ArrowRight,
  BadgeCheck,
  BriefcaseBusiness,
  Check,
  Eye,
  EyeOff,
  LockKeyhole,
  Mail,
  Phone,
  UserRound,
  UserRoundPlus,
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

type RegisterRole = "client" | "intervenant";
type RegisterMethod = "google" | "form";

type RegisterResponse = {
  token?: string;
  user?: User;
  message?: string;
  errors?: Record<string, string[]>;
};

const accountTypes = [
  {
    value: "client" as const,
    icon: UserRound,
    title: "Je cherche un coach",
    description: "Réserver, échanger et suivre mes séances.",
  },
  {
    value: "intervenant" as const,
    icon: BriefcaseBusiness,
    title: "Je suis coach",
    description: "Présenter mes services et gérer mes clients.",
  },
];

function getRegisterError(result: RegisterResponse | null) {
  const validationMessage = result?.errors
    ? Object.values(result.errors)[0]?.[0]
    : null;

  return (
    validationMessage ||
    result?.message ||
    "L’inscription n’a pas pu aboutir. Veuillez réessayer."
  );
}

export default function RegisterPage() {
  const router = useRouter();
  const [method, setMethod] = useState<RegisterMethod>("google");
  const [role, setRole] = useState<RegisterRole>("client");
  const [acceptTerms, setAcceptTerms] = useState(false);
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [phone, setPhone] = useState("");
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const [showPasswords, setShowPasswords] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  useEffect(() => {
    const currentUser = getCurrentUser();

    if (currentUser) {
      router.replace(getPostAuthRoute(currentUser));
    }
  }, [router]);

  function selectMethod(nextMethod: RegisterMethod) {
    setMethod(nextMethod);
    setError("");
  }

  async function handleManualRegister(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (loading) return;

    setError("");

    if (!acceptTerms) {
      setError("Acceptez les conditions générales pour créer votre compte.");
      return;
    }

    if (password.length < 8) {
      setError("Le mot de passe doit contenir au moins 8 caractères.");
      return;
    }

    if (password !== passwordConfirmation) {
      setError("Les deux mots de passe ne correspondent pas.");
      return;
    }

    setLoading(true);

    try {
      const response = await fetch(`${API_BASE_URL}/register`, {
        method: "POST",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          name: name.trim(),
          email: email.trim(),
          phone: phone.trim() || null,
          password,
          password_confirmation: passwordConfirmation,
          role,
          device_name: "gotfit-webapp",
        }),
      });

      const result = (await response.json().catch(() => null)) as
        | RegisterResponse
        | null;

      if (!response.ok || !result?.token || !result.user) {
        throw new Error(getRegisterError(result));
      }

      saveAuth(result.token, result.user);
      window.dispatchEvent(new Event("gotfit:auth"));
      router.replace(getPostAuthRoute(result.user));
    } catch (registerError) {
      setError(
        registerError instanceof Error
          ? registerError.message
          : "Une erreur est survenue pendant l’inscription."
      );
    } finally {
      setLoading(false);
    }
  }

  const termsControl = (
    <label className="flex cursor-pointer items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-xs font-semibold leading-5 text-slate-600">
      <input
        type="checkbox"
        checked={acceptTerms}
        onChange={(event) => {
          setAcceptTerms(event.target.checked);
          setError("");
        }}
        className="mt-0.5 h-4 w-4 shrink-0 accent-[var(--ink)]"
      />
      <span>
        J’accepte les{" "}
        <Link href="/cgu" className="font-black text-[var(--ink)] underline">
          conditions générales
        </Link>{" "}
        et la{" "}
        <Link
          href="/confidentialite"
          className="font-black text-[var(--ink)] underline"
        >
          politique de confidentialité
        </Link>
        .
      </span>
    </label>
  );

  const errorMessage = error ? (
    <div
      role="alert"
      className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold leading-6 text-red-700"
    >
      {error}
    </div>
  ) : null;

  return (
    <AuthShell
      eyebrow="Bienvenue chez Gotfit"
      title="Inscrivez-vous à votre façon."
      description="Créez votre compte avec Google en un clic ou remplissez le formulaire classique. Vous gardez le choix, sans perdre les fonctionnalités Gotfit."
    >
      <fieldset>
        <legend className="mb-3 text-sm font-black text-slate-700">
          Choisissez votre méthode d’inscription
        </legend>

        <div className="grid grid-cols-2 gap-2 rounded-2xl bg-slate-100 p-2">
          <button
            type="button"
            onClick={() => selectMethod("google")}
            aria-pressed={method === "google"}
            className={`rounded-xl px-3 py-3 text-sm font-black transition ${
              method === "google"
                ? "bg-white text-[var(--ink)] shadow-sm"
                : "text-slate-500 hover:text-[var(--ink)]"
            }`}
          >
            <span className="mr-2 inline-grid h-5 w-5 place-items-center rounded-full border border-slate-200 bg-white text-xs">
              G
            </span>
            Avec Google
          </button>

          <button
            type="button"
            onClick={() => selectMethod("form")}
            aria-pressed={method === "form"}
            className={`rounded-xl px-3 py-3 text-sm font-black transition ${
              method === "form"
                ? "bg-white text-[var(--ink)] shadow-sm"
                : "text-slate-500 hover:text-[var(--ink)]"
            }`}
          >
            <UserRoundPlus size={17} className="mr-2 inline" />
            Formulaire
          </button>
        </div>
      </fieldset>

      <fieldset className="mt-6">
        <legend className="mb-3 text-sm font-black text-slate-700">
          Comment souhaitez-vous utiliser Gotfit ?
        </legend>

        <div className="grid gap-3 sm:grid-cols-2">
          {accountTypes.map(
            ({ value, icon: Icon, title, description: itemDescription }) => {
              const selected = role === value;

              return (
                <button
                  key={value}
                  type="button"
                  onClick={() => {
                    setRole(value);
                    setError("");
                  }}
                  aria-pressed={selected}
                  className={`relative rounded-2xl border p-4 text-left transition ${
                    selected
                      ? "border-[var(--ink)] bg-[var(--ink)] text-white shadow-lg"
                      : "border-slate-200 bg-white text-[var(--ink)] hover:-translate-y-0.5 hover:border-[var(--brand)]"
                  }`}
                >
                  {selected && (
                    <span className="absolute right-3 top-3 grid h-6 w-6 place-items-center rounded-full bg-[var(--brand)] text-[var(--ink)]">
                      <Check size={14} strokeWidth={3} />
                    </span>
                  )}
                  <Icon
                    size={22}
                    className={selected ? "text-[var(--brand)]" : ""}
                  />
                  <strong className="mt-4 block text-sm font-black">
                    {title}
                  </strong>
                  <span
                    className={`mt-1 block text-xs leading-5 ${
                      selected ? "text-white/60" : "text-slate-500"
                    }`}
                  >
                    {itemDescription}
                  </span>
                </button>
              );
            }
          )}
        </div>
      </fieldset>

      {role === "intervenant" && (
        <div className="mt-4 flex items-start gap-3 rounded-2xl bg-amber-50 p-4 text-xs font-semibold leading-5 text-amber-900">
          <BadgeCheck size={18} className="mt-0.5 shrink-0" />
          Les profils coach sont vérifiés par l’équipe Gotfit avant publication.
        </div>
      )}

      {method === "google" ? (
        <div className="mt-5 space-y-4">
          {termsControl}
          {errorMessage}

          <div className="rounded-[1.75rem] border border-slate-200 bg-slate-50/70 p-5 sm:p-6">
            {acceptTerms ? (
              <GoogleSignInButton
                flow="register"
                role={role}
                onError={setError}
              />
            ) : (
              <button
                type="button"
                onClick={() =>
                  setError(
                    "Acceptez les conditions générales pour créer votre compte."
                  )
                }
                className="flex w-full items-center justify-center gap-3 rounded-full border border-slate-200 bg-white px-5 py-3 text-sm font-black text-slate-400"
              >
                <span className="grid h-5 w-5 place-items-center rounded-full bg-slate-100 font-black">
                  G
                </span>
                Continuer avec Google
              </button>
            )}
          </div>
        </div>
      ) : (
        <form onSubmit={handleManualRegister} className="mt-5 grid gap-4">
          <label className="grid gap-2 text-sm font-bold text-slate-700">
            Nom complet
            <span className="gotfit-field">
              <UserRound size={18} />
              <input
                type="text"
                value={name}
                onChange={(event) => setName(event.target.value)}
                autoComplete="name"
                placeholder="Votre nom complet"
                maxLength={255}
                required
              />
            </span>
          </label>

          <div className="grid gap-4 sm:grid-cols-2">
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
              <span>
                Téléphone{" "}
                <span className="font-semibold text-slate-400">
                  (optionnel)
                </span>
              </span>
              <span className="gotfit-field">
                <Phone size={18} />
                <input
                  type="tel"
                  value={phone}
                  onChange={(event) => setPhone(event.target.value)}
                  autoComplete="tel"
                  placeholder="+33 6 00 00 00 00"
                  maxLength={50}
                />
              </span>
            </label>
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <label className="grid gap-2 text-sm font-bold text-slate-700">
              Mot de passe
              <span className="gotfit-field">
                <LockKeyhole size={18} />
                <input
                  type={showPasswords ? "text" : "password"}
                  value={password}
                  onChange={(event) => setPassword(event.target.value)}
                  autoComplete="new-password"
                  placeholder="8 caractères minimum"
                  minLength={8}
                  required
                />
                <button
                  type="button"
                  onClick={() => setShowPasswords((visible) => !visible)}
                  aria-label={
                    showPasswords
                      ? "Masquer les mots de passe"
                      : "Afficher les mots de passe"
                  }
                >
                  {showPasswords ? <EyeOff size={18} /> : <Eye size={18} />}
                </button>
              </span>
            </label>

            <label className="grid gap-2 text-sm font-bold text-slate-700">
              Confirmation
              <span className="gotfit-field">
                <LockKeyhole size={18} />
                <input
                  type={showPasswords ? "text" : "password"}
                  value={passwordConfirmation}
                  onChange={(event) =>
                    setPasswordConfirmation(event.target.value)
                  }
                  autoComplete="new-password"
                  placeholder="Répétez le mot de passe"
                  minLength={8}
                  required
                />
              </span>
            </label>
          </div>

          {termsControl}
          {errorMessage}

          <button
            type="submit"
            disabled={loading}
            className="gotfit-button gotfit-button-dark w-full"
          >
            {loading ? "Création du compte…" : "Créer mon compte"}
            {!loading && <ArrowRight size={18} />}
          </button>
        </form>
      )}

      <p className="mt-7 text-center text-sm text-slate-600">
        Vous avez déjà un compte ?{" "}
        <Link
          href="/auth/login"
          className="font-black text-[var(--ink)] underline decoration-[var(--brand)] decoration-2 underline-offset-4"
        >
          Se connecter
        </Link>
      </p>
    </AuthShell>
  );
}
