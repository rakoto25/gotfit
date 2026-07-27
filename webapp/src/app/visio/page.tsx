"use client";

import Link from "next/link";
import { FormEvent, useEffect, useMemo, useState } from "react";
import {
  AlertTriangle,
  ArrowRight,
  Calendar,
  CheckCircle2,
  Clock,
  Loader2,
  Plus,
  RefreshCw,
  ShieldCheck,
  Users,
  Video,
  X,
} from "lucide-react";

import Footer from "@/components/layout/Footer";
import Header from "@/components/layout/Header";
import { getCurrentUser, getToken, hasRole } from "@/lib/auth";
import { formatDate, formatMoney } from "@/lib/marketplace";
import {
  createVisioSession,
  fetchVisioSessions,
  getVisioStatusLabel,
  type CreateVisioSessionPayload,
  type VisioSession,
} from "@/lib/visio";

function getErrorMessage(error: unknown, fallback: string) {
  return error instanceof Error ? error.message : fallback;
}

function toDateTimeLocal(value: Date) {
  const offset = value.getTimezoneOffset();
  const local = new Date(value.getTime() - offset * 60 * 1000);
  return local.toISOString().slice(0, 16);
}

function getStatusClass(status: VisioSession["status"]) {
  if (status === "live") return "border-emerald-100 bg-emerald-50 text-emerald-700";
  if (status === "confirmed") return "border-orange-100 bg-orange-50 text-orange-700";
  if (status === "ended") return "border-slate-200 bg-slate-100 text-slate-600";
  if (status === "cancelled") return "border-red-100 bg-red-50 text-red-700";
  return "border-amber-100 bg-amber-50 text-amber-700";
}

const initialForm: CreateVisioSessionPayload = {
  title: "",
  description: "",
  start_at: toDateTimeLocal(new Date(Date.now() + 24 * 60 * 60 * 1000)),
  duration_minutes: 60,
  min_participants: 2,
  max_participants: 8,
  price: 25,
  currency: "EUR",
};

export default function VisioPage() {
  const [sessions, setSessions] = useState<VisioSession[]>([]);
  const [loading, setLoading] = useState(true);
  const [creating, setCreating] = useState(false);
  const [showCreate, setShowCreate] = useState(false);
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");
  const [form, setForm] = useState<CreateVisioSessionPayload>(initialForm);

  const user = useMemo(() => getCurrentUser(), []);
  const isCoach = hasRole(user, "intervenant");

  const stats = useMemo(() => {
    return {
      total: sessions.length,
      open: sessions.filter((item) => item.status === "open").length,
      confirmed: sessions.filter((item) => item.status === "confirmed").length,
      live: sessions.filter((item) => item.status === "live").length,
    };
  }, [sessions]);

  async function loadSessions() {
    try {
      setLoading(true);
      setError("");
      setSessions(await fetchVisioSessions(Boolean(getToken())));
    } catch (err) {
      setError(getErrorMessage(err, "Impossible de charger les séances visio."));
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    const timer = window.setTimeout(() => {
      loadSessions();
    }, 0);

    return () => window.clearTimeout(timer);
  }, []);

  async function handleCreate(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (!getToken()) {
      setError("Veuillez vous connecter avec un compte coach pour créer une visio.");
      return;
    }

    if (!isCoach) {
      setError("La création de visio est réservée aux coachs/intervenants.");
      return;
    }

    try {
      setCreating(true);
      setError("");
      setSuccess("");

      const session = await createVisioSession({
        ...form,
        min_participants: Math.max(2, Number(form.min_participants) || 2),
        max_participants: form.max_participants
          ? Number(form.max_participants)
          : null,
        duration_minutes: Number(form.duration_minutes) || 60,
        price: Number(form.price) || 0,
        currency: form.currency || "EUR",
      });

      setSuccess("Séance visio créée avec succès.");
      setShowCreate(false);
      setForm(initialForm);
      setSessions((current) => [session, ...current]);
    } catch (err) {
      setError(getErrorMessage(err, "Impossible de créer la séance visio."));
    } finally {
      setCreating(false);
    }
  }

  return (
    <>
      <Header />

      <main className="min-h-screen bg-[#FFF7ED] px-4 pb-16 pt-36 text-slate-950">
        <div className="mx-auto max-w-7xl">
          <section className="mb-8 grid gap-6 lg:grid-cols-[1fr_0.8fr] lg:items-end">
            <div>
              <span className="mb-5 inline-flex items-center gap-2 rounded-full border border-orange-200 bg-white px-4 py-2 text-xs font-black uppercase tracking-[0.18em] text-orange-700 shadow-sm">
                <Video size={16} />
                Priorité 3 - Visio
              </span>

              <h1 className="max-w-3xl text-4xl font-black leading-tight tracking-tight sm:text-6xl">
                Séances visio coach et groupe.
              </h1>

              <p className="mt-5 max-w-2xl text-base font-medium leading-8 text-slate-600">
                Créez, réservez et rejoignez des séances en ligne. Une visio
                démarre uniquement avec 1 coach et au minimum 2 participants
                clients validés.
              </p>
            </div>

            <div className="grid gap-3 sm:grid-cols-4 lg:grid-cols-2">
              <div className="rounded-[1.5rem] bg-white p-5 shadow-sm">
                <Video className="mb-3 text-orange-700" size={22} />
                <strong className="block text-2xl font-black">{stats.total}</strong>
                <span className="text-xs font-bold text-slate-500">Séances</span>
              </div>
              <div className="rounded-[1.5rem] bg-white p-5 shadow-sm">
                <ShieldCheck className="mb-3 text-orange-700" size={22} />
                <strong className="block text-2xl font-black">
                  {stats.confirmed}
                </strong>
                <span className="text-xs font-bold text-slate-500">
                  Confirmées
                </span>
              </div>
              <div className="rounded-[1.5rem] bg-white p-5 shadow-sm">
                <Users className="mb-3 text-orange-700" size={22} />
                <strong className="block text-2xl font-black">{stats.open}</strong>
                <span className="text-xs font-bold text-slate-500">Ouvertes</span>
              </div>
              <div className="rounded-[1.5rem] bg-white p-5 shadow-sm">
                <CheckCircle2 className="mb-3 text-orange-700" size={22} />
                <strong className="block text-2xl font-black">{stats.live}</strong>
                <span className="text-xs font-bold text-slate-500">En direct</span>
              </div>
            </div>
          </section>

          <div className="mb-6 flex flex-wrap gap-3">
            {isCoach && (
              <button
                type="button"
                onClick={() => setShowCreate((value) => !value)}
                className="inline-flex items-center gap-2 rounded-full bg-orange-600 px-5 py-3 text-sm font-black text-white shadow-lg shadow-orange-600/20 transition hover:-translate-y-0.5 hover:bg-orange-700"
              >
                <Plus size={17} />
                Créer une visio
              </button>
            )}

            <button
              type="button"
              onClick={loadSessions}
              className="inline-flex items-center gap-2 rounded-full border border-orange-200 bg-white px-5 py-3 text-sm font-black text-orange-700 transition hover:-translate-y-0.5 hover:bg-orange-50"
            >
              <RefreshCw size={17} />
              Actualiser
            </button>
          </div>

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

          {showCreate && (
            <form
              onSubmit={handleCreate}
              className="mb-8 rounded-[2rem] border border-orange-100 bg-white p-5 shadow-sm md:p-7"
            >
              <div className="mb-5 flex items-center justify-between gap-4">
                <div>
                  <h2 className="text-2xl font-black">Nouvelle séance visio</h2>
                  <p className="mt-1 text-sm font-semibold text-slate-500">
                    Minimum 2 participants clients pour démarrer.
                  </p>
                </div>
                <button
                  type="button"
                  onClick={() => setShowCreate(false)}
                  className="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-600"
                  aria-label="Fermer"
                >
                  <X size={18} />
                </button>
              </div>

              <div className="grid gap-4 md:grid-cols-2">
                <label className="grid gap-2 text-sm font-black text-slate-700 md:col-span-2">
                  Titre
                  <input
                    value={form.title}
                    onChange={(event) =>
                      setForm((current) => ({
                        ...current,
                        title: event.target.value,
                      }))
                    }
                    className="gotfit-input"
                    placeholder="Cours visio GotFit"
                    required
                  />
                </label>

                <label className="grid gap-2 text-sm font-black text-slate-700 md:col-span-2">
                  Description
                  <textarea
                    value={form.description}
                    onChange={(event) =>
                      setForm((current) => ({
                        ...current,
                        description: event.target.value,
                      }))
                    }
                    className="gotfit-input min-h-28 resize-none"
                    placeholder="Décrivez le contenu de la séance..."
                  />
                </label>

                <label className="grid gap-2 text-sm font-black text-slate-700">
                  Date et heure
                  <input
                    type="datetime-local"
                    value={form.start_at}
                    onChange={(event) =>
                      setForm((current) => ({
                        ...current,
                        start_at: event.target.value,
                      }))
                    }
                    className="gotfit-input"
                    required
                  />
                </label>

                <label className="grid gap-2 text-sm font-black text-slate-700">
                  Durée en minutes
                  <input
                    type="number"
                    min={15}
                    max={360}
                    value={form.duration_minutes}
                    onChange={(event) =>
                      setForm((current) => ({
                        ...current,
                        duration_minutes: Number(event.target.value),
                      }))
                    }
                    className="gotfit-input"
                  />
                </label>

                <label className="grid gap-2 text-sm font-black text-slate-700">
                  Minimum participants
                  <input
                    type="number"
                    min={2}
                    value={form.min_participants}
                    onChange={(event) =>
                      setForm((current) => ({
                        ...current,
                        min_participants: Number(event.target.value),
                      }))
                    }
                    className="gotfit-input"
                  />
                </label>

                <label className="grid gap-2 text-sm font-black text-slate-700">
                  Maximum participants
                  <input
                    type="number"
                    min={2}
                    value={form.max_participants || ""}
                    onChange={(event) =>
                      setForm((current) => ({
                        ...current,
                        max_participants: event.target.value
                          ? Number(event.target.value)
                          : null,
                      }))
                    }
                    className="gotfit-input"
                  />
                </label>

                <label className="grid gap-2 text-sm font-black text-slate-700">
                  Prix
                  <input
                    type="number"
                    min={0}
                    step="0.01"
                    value={form.price}
                    onChange={(event) =>
                      setForm((current) => ({
                        ...current,
                        price: Number(event.target.value),
                      }))
                    }
                    className="gotfit-input"
                  />
                </label>

                <label className="grid gap-2 text-sm font-black text-slate-700">
                  Devise
                  <input
                    value={form.currency}
                    maxLength={3}
                    onChange={(event) =>
                      setForm((current) => ({
                        ...current,
                        currency: event.target.value.toUpperCase(),
                      }))
                    }
                    className="gotfit-input"
                  />
                </label>
              </div>

              <button
                type="submit"
                disabled={creating}
                className="mt-6 inline-flex items-center gap-2 rounded-full bg-slate-950 px-6 py-3 text-sm font-black text-white shadow-lg transition hover:-translate-y-0.5 hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-70"
              >
                {creating ? <Loader2 className="animate-spin" size={17} /> : <Plus size={17} />}
                Créer la séance
              </button>
            </form>
          )}

          {loading ? (
            <div className="flex items-center justify-center rounded-[2rem] bg-white py-16 text-sm font-black text-orange-700 shadow-sm">
              <Loader2 className="mr-3 animate-spin" size={20} />
              Chargement des séances visio...
            </div>
          ) : sessions.length === 0 ? (
            <div className="rounded-[2rem] bg-white p-10 text-center shadow-sm">
              <AlertTriangle className="mx-auto mb-4 text-orange-600" size={34} />
              <h2 className="text-2xl font-black">Aucune séance visio</h2>
              <p className="mx-auto mt-3 max-w-md text-sm font-semibold leading-6 text-slate-500">
                Les cours en ligne apparaîtront ici dès qu’un coach aura créé
                une séance.
              </p>
            </div>
          ) : (
            <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
              {sessions.map((session) => (
                <Link
                  key={session.id}
                  href={`/visio/${session.id}`}
                  className="group rounded-[2rem] border border-orange-100 bg-white p-5 shadow-sm transition hover:-translate-y-1 hover:shadow-xl"
                >
                  <div className="mb-5 flex items-start justify-between gap-3">
                    <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-orange-100 text-orange-700">
                      <Video size={24} />
                    </div>
                    <span
                      className={`rounded-full border px-3 py-1 text-xs font-black ${getStatusClass(session.status)}`}
                    >
                      {getVisioStatusLabel(session.status)}
                    </span>
                  </div>

                  <h2 className="line-clamp-2 text-xl font-black">
                    {session.title}
                  </h2>
                  <p className="mt-2 line-clamp-2 text-sm font-semibold leading-6 text-slate-500">
                    {session.description || "Séance visio GotFit"}
                  </p>

                  <div className="mt-5 grid gap-3">
                    <div className="flex items-center gap-2 rounded-2xl bg-orange-50 p-4 text-sm font-bold text-slate-600">
                      <Calendar size={17} />
                      {formatDate(session.start_at)}
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                      <div className="rounded-2xl bg-slate-50 p-4">
                        <Clock className="mb-2 text-orange-700" size={17} />
                        <strong className="text-sm font-black">
                          {session.duration_minutes} min
                        </strong>
                      </div>
                      <div className="rounded-2xl bg-slate-50 p-4">
                        <Users className="mb-2 text-orange-700" size={17} />
                        <strong className="text-sm font-black">
                          {session.paid_participants_count || 0}/
                          {session.min_participants}
                        </strong>
                      </div>
                    </div>
                  </div>

                  <div className="mt-5 flex items-center justify-between gap-3">
                    <span className="text-lg font-black text-slate-950">
                      {formatMoney(session.price, session.currency || "EUR")}
                    </span>
                    <span className="inline-flex items-center gap-2 text-sm font-black text-orange-700">
                      Ouvrir
                      <ArrowRight
                        size={17}
                        className="transition group-hover:translate-x-1"
                      />
                    </span>
                  </div>
                </Link>
              ))}
            </div>
          )}
        </div>
      </main>

      <Footer />
    </>
  );
}
