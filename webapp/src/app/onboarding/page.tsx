"use client";

import Link from "next/link";
import { FormEvent, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import {
  ArrowLeft,
  BadgeCheck,
  CheckCircle2,
  Loader2,
  Save,
  ShieldCheck,
  UserRound,
  X,
} from "lucide-react";

import Header from "@/components/layout/Header";
import Footer from "@/components/layout/Footer";
import { getCurrentUser, getToken, hasRole } from "@/lib/auth";
import {
  fetchMyOnboarding,
  saveMyOnboarding,
  type ClientOnboarding,
} from "@/lib/client-journey";

function csvToArray(value: string) {
  return value
    .split(",")
    .map((item) => item.trim())
    .filter(Boolean);
}

function arrayToCsv(value?: string[] | null) {
  return Array.isArray(value) ? value.join(", ") : "";
}

function objectText(value?: Record<string, unknown> | null, key = "text") {
  if (!value) return "";
  const rawValue = value[key];
  return typeof rawValue === "string" ? rawValue : "";
}

export default function OnboardingPage() {
  const router = useRouter();

  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");
  const [onboarding, setOnboarding] = useState<ClientOnboarding | null>(null);

  const [goals, setGoals] = useState("");
  const [level, setLevel] = useState("");
  const [preferences, setPreferences] = useState("");
  const [availability, setAvailability] = useState("");
  const [health, setHealth] = useState("");
  const [height, setHeight] = useState("");
  const [weight, setWeight] = useState("");
  const [birthYear, setBirthYear] = useState("");
  const [lifestyle, setLifestyle] = useState("");
  const [emergencyName, setEmergencyName] = useState("");
  const [emergencyPhone, setEmergencyPhone] = useState("");
  const [extraAnswers, setExtraAnswers] = useState("");

  useEffect(() => {
    if (!getToken()) {
      router.replace("/auth/login");
      return;
    }

    const user = getCurrentUser();
    if (user && !hasRole(user, "client")) {
      setError("Le questionnaire onboarding est réservé aux comptes client.");
      setLoading(false);
      return;
    }

    loadOnboarding();
  }, [router]);

  async function loadOnboarding() {
    try {
      setLoading(true);
      setError("");

      const data = await fetchMyOnboarding();
      setOnboarding(data);

      setGoals(arrayToCsv(data?.goals || null));
      setLevel(data?.level || "");
      setPreferences(objectText(data?.training_preferences, "text"));
      setAvailability(objectText(data?.availability, "text"));
      setHealth(objectText(data?.health_constraints, "text"));
      setHeight(String(data?.measurements?.height || ""));
      setWeight(String(data?.measurements?.weight || ""));
      setBirthYear(String(data?.measurements?.birth_year || ""));
      setLifestyle(objectText(data?.lifestyle, "text"));
      setEmergencyName(String(data?.emergency_contact?.name || ""));
      setEmergencyPhone(String(data?.emergency_contact?.phone || ""));
      setExtraAnswers(objectText(data?.answers, "text"));
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Impossible de charger le questionnaire onboarding."
      );
    } finally {
      setLoading(false);
    }
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    try {
      setSaving(true);
      setError("");
      setSuccess("");

      const saved = await saveMyOnboarding({
        goals: csvToArray(goals),
        level,
        training_preferences: { text: preferences },
        availability: { text: availability },
        health_constraints: { text: health },
        measurements: {
          height,
          weight,
          birth_year: birthYear,
        },
        lifestyle: { text: lifestyle },
        emergency_contact: {
          name: emergencyName,
          phone: emergencyPhone,
        },
        answers: { text: extraAnswers },
        is_completed: true,
      });

      setOnboarding(saved);
      setSuccess("Questionnaire onboarding enregistré avec succès.");
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Impossible d’enregistrer le questionnaire onboarding."
      );
    } finally {
      setSaving(false);
    }
  }

  return (
    <>
      <Header />

      <main className="min-h-screen bg-[#FFF7ED] px-4 pb-16 pt-36 text-slate-950">
        <div className="mx-auto max-w-6xl">
          <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
            <Link
              href="/profile"
              className="inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-black text-orange-700 shadow-sm transition hover:bg-orange-50"
            >
              <ArrowLeft size={17} />
              Retour profil
            </Link>

            <Link
              href="/parcours-client"
              className="inline-flex items-center gap-2 rounded-full bg-slate-950 px-4 py-2 text-sm font-black text-white shadow-sm transition hover:bg-slate-800"
            >
              <UserRound size={17} />
              Mon parcours
            </Link>
          </div>

          <section className="mb-8 rounded-[2.5rem] bg-white p-6 shadow-[0_24px_80px_rgba(249,115,22,0.13)] sm:p-8">
            <span className="mb-5 inline-flex items-center gap-2 rounded-full border border-orange-200 bg-orange-50 px-4 py-2 text-xs font-black uppercase tracking-[0.16em] text-orange-700">
              <ShieldCheck size={16} />
              Priorité 2
            </span>
            <h1 className="max-w-3xl text-4xl font-black tracking-tight sm:text-5xl">
              Questionnaire d’onboarding client.
            </h1>
            <p className="mt-4 max-w-3xl text-sm font-semibold leading-7 text-slate-600">
              Ces informations aident les coachs à comprendre vos objectifs,
              votre niveau, vos contraintes et vos préférences avant la séance.
            </p>
          </section>

          {error && (
            <div className="mb-5 flex items-start gap-3 rounded-[2rem] border border-red-100 bg-red-50 px-5 py-4 text-sm font-bold text-red-700">
              <X className="mt-0.5 shrink-0" size={18} />
              {error}
            </div>
          )}

          {success && (
            <div className="mb-5 flex items-start gap-3 rounded-[2rem] border border-emerald-100 bg-emerald-50 px-5 py-4 text-sm font-bold text-emerald-700">
              <CheckCircle2 className="mt-0.5 shrink-0" size={18} />
              {success}
            </div>
          )}

          {loading ? (
            <div className="flex items-center justify-center rounded-[2rem] bg-white py-16 text-sm font-black text-orange-700 shadow-sm">
              <Loader2 className="mr-3 animate-spin" size={20} />
              Chargement du questionnaire...
            </div>
          ) : (
            <form onSubmit={handleSubmit} className="grid gap-5">
              <div className="grid gap-5 lg:grid-cols-2">
                <Field label="Objectifs principaux" hint="Séparez par des virgules">
                  <input
                    value={goals}
                    onChange={(event) => setGoals(event.target.value)}
                    placeholder="Perte de poids, mobilité, renforcement..."
                    className="gotfit-input"
                  />
                </Field>

                <Field label="Niveau actuel">
                  <select
                    value={level}
                    onChange={(event) => setLevel(event.target.value)}
                    className="gotfit-input"
                  >
                    <option value="">Choisir un niveau</option>
                    <option value="debutant">Débutant</option>
                    <option value="intermediaire">Intermédiaire</option>
                    <option value="avance">Avancé</option>
                    <option value="reprise">Reprise après pause/blessure</option>
                  </select>
                </Field>
              </div>

              <TextField
                label="Préférences d’entraînement"
                value={preferences}
                onChange={setPreferences}
                placeholder="À domicile, en salle, douceur, intensité, matériel disponible..."
              />

              <TextField
                label="Disponibilités"
                value={availability}
                onChange={setAvailability}
                placeholder="Exemple : lundi soir, mercredi matin, week-end..."
              />

              <TextField
                label="Contraintes santé / blessures"
                value={health}
                onChange={setHealth}
                placeholder="Blessures, douleurs, contre-indications, traitement, points de vigilance..."
              />

              <div className="grid gap-5 md:grid-cols-3">
                <Field label="Taille">
                  <input
                    value={height}
                    onChange={(event) => setHeight(event.target.value)}
                    placeholder="Ex : 175 cm"
                    className="gotfit-input"
                  />
                </Field>
                <Field label="Poids">
                  <input
                    value={weight}
                    onChange={(event) => setWeight(event.target.value)}
                    placeholder="Ex : 72 kg"
                    className="gotfit-input"
                  />
                </Field>
                <Field label="Année de naissance">
                  <input
                    value={birthYear}
                    onChange={(event) => setBirthYear(event.target.value)}
                    placeholder="Ex : 1992"
                    className="gotfit-input"
                  />
                </Field>
              </div>

              <TextField
                label="Mode de vie"
                value={lifestyle}
                onChange={setLifestyle}
                placeholder="Sommeil, stress, activité quotidienne, alimentation, rythme de travail..."
              />

              <div className="grid gap-5 md:grid-cols-2">
                <Field label="Contact d’urgence">
                  <input
                    value={emergencyName}
                    onChange={(event) => setEmergencyName(event.target.value)}
                    placeholder="Nom du contact"
                    className="gotfit-input"
                  />
                </Field>
                <Field label="Téléphone d’urgence">
                  <input
                    value={emergencyPhone}
                    onChange={(event) => setEmergencyPhone(event.target.value)}
                    placeholder="+33..."
                    className="gotfit-input"
                  />
                </Field>
              </div>

              <TextField
                label="Autres informations utiles"
                value={extraAnswers}
                onChange={setExtraAnswers}
                placeholder="Tout ce que le coach doit savoir pour mieux vous accompagner."
              />

              <div className="flex flex-col gap-3 rounded-[2rem] bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-3 text-sm font-bold text-slate-600">
                  <BadgeCheck className="text-orange-600" size={20} />
                  Statut : {onboarding?.is_completed ? "complété" : "brouillon"}
                </div>
                <button
                  type="submit"
                  disabled={saving}
                  className="inline-flex items-center justify-center gap-2 rounded-full bg-orange-600 px-6 py-4 text-sm font-black text-white shadow-lg shadow-orange-600/20 transition hover:-translate-y-0.5 hover:bg-orange-700 disabled:opacity-60"
                >
                  {saving ? <Loader2 className="animate-spin" size={18} /> : <Save size={18} />}
                  Enregistrer l’onboarding
                </button>
              </div>
            </form>
          )}
        </div>
      </main>

      <Footer />
    </>
  );
}

function Field({
  label,
  hint,
  children,
}: {
  label: string;
  hint?: string;
  children: React.ReactNode;
}) {
  return (
    <label className="block rounded-[2rem] bg-white p-5 shadow-sm">
      <span className="mb-2 block text-sm font-black text-slate-800">{label}</span>
      {hint && <span className="mb-3 block text-xs font-bold text-slate-400">{hint}</span>}
      {children}
    </label>
  );
}

function TextField({
  label,
  value,
  onChange,
  placeholder,
}: {
  label: string;
  value: string;
  onChange: (value: string) => void;
  placeholder: string;
}) {
  return (
    <Field label={label}>
      <textarea
        value={value}
        onChange={(event) => onChange(event.target.value)}
        rows={4}
        placeholder={placeholder}
        className="gotfit-input resize-none leading-7"
      />
    </Field>
  );
}
