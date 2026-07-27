"use client";

import Link from "next/link";
import { FormEvent, useEffect, useMemo, useState } from "react";
import { useParams, useRouter } from "next/navigation";
import {
  ArrowLeft,
  BadgeCheck,
  CalendarCheck,
  CheckCircle2,
  Loader2,
  Plus,
  Save,
  ShieldCheck,
  Trash2,
  UserRound,
  X,
} from "lucide-react";

import Header from "@/components/layout/Header";
import Footer from "@/components/layout/Footer";
import { getCurrentUser, getToken } from "@/lib/auth";
import {
  createClientNote,
  deleteClientNote,
  fetchClientHistory,
  fetchClientNotes,
  fetchClientOnboarding,
  type ClientNote,
  type ClientOnboarding,
  type ClientHistory,
} from "@/lib/client-journey";
import { formatDate, formatMoney, getAnnonceTitle } from "@/lib/marketplace";

function formatDateTime(value?: string | null) {
  if (!value) return "Non défini";

  try {
    return new Intl.DateTimeFormat("fr-FR", {
      day: "2-digit",
      month: "short",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    }).format(new Date(value));
  } catch {
    return value;
  }
}

function asList(value?: string[] | null) {
  return Array.isArray(value) && value.length ? value.join(", ") : "Non renseigné";
}

function textFromObject(value?: Record<string, unknown> | null, key = "text") {
  if (!value) return "Non renseigné";
  const rawValue = value[key];
  return typeof rawValue === "string" && rawValue.trim()
    ? rawValue
    : "Non renseigné";
}

export default function ClientJourneyDetailPage() {
  const router = useRouter();
  const params = useParams<{ id: string }>();
  const clientId = params?.id;
  const currentUser = useMemo(() => getCurrentUser(), []);

  const [history, setHistory] = useState<ClientHistory | null>(null);
  const [notes, setNotes] = useState<ClientNote[]>([]);
  const [onboarding, setOnboarding] = useState<ClientOnboarding | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [deletingId, setDeletingId] = useState<number | null>(null);
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");

  const [noteTitle, setNoteTitle] = useState("");
  const [noteContent, setNoteContent] = useState("");
  const [noteVisibility, setNoteVisibility] = useState<"private" | "shared">(
    "private"
  );

  useEffect(() => {
    if (!getToken()) {
      router.replace("/auth/login");
      return;
    }

    if (clientId) {
      loadData();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [clientId, router]);

  async function loadData() {
    if (!clientId) return;

    try {
      setLoading(true);
      setError("");

      const [historyPayload, notesPayload, onboardingPayload] = await Promise.all([
        fetchClientHistory(clientId),
        fetchClientNotes(clientId),
        fetchClientOnboarding(clientId),
      ]);

      setHistory(historyPayload);
      setNotes(notesPayload);
      setOnboarding(onboardingPayload);
    } catch (err) {
      setError(
        err instanceof Error ? err.message : "Impossible de charger le dossier client."
      );
    } finally {
      setLoading(false);
    }
  }

  async function handleCreateNote(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (!clientId) return;
    if (noteContent.trim().length < 2) {
      setError("Le contenu de la note est obligatoire.");
      return;
    }

    try {
      setSaving(true);
      setError("");
      setSuccess("");

      await createClientNote(clientId, {
        title: noteTitle.trim() || undefined,
        content: noteContent.trim(),
        visibility: noteVisibility,
      });

      setNoteTitle("");
      setNoteContent("");
      setNoteVisibility("private");
      setSuccess("Note enregistrée avec succès.");
      await loadData();
    } catch (err) {
      setError(err instanceof Error ? err.message : "Impossible d’ajouter la note.");
    } finally {
      setSaving(false);
    }
  }

  async function handleDeleteNote(noteId: number) {
    try {
      setDeletingId(noteId);
      setError("");
      setSuccess("");
      await deleteClientNote(noteId);
      setSuccess("Note supprimée.");
      await loadData();
    } catch (err) {
      setError(err instanceof Error ? err.message : "Impossible de supprimer la note.");
    } finally {
      setDeletingId(null);
    }
  }

  const client = history?.client;

  return (
    <>
      <Header />

      <main className="min-h-screen bg-[#FFF7ED] px-4 pb-16 pt-36 text-slate-950">
        <div className="mx-auto max-w-7xl">
          <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
            <Link
              href="/parcours-client"
              className="inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-black text-orange-700 shadow-sm transition hover:bg-orange-50"
            >
              <ArrowLeft size={17} />
              Tous les parcours
            </Link>

            {currentUser?.id === Number(clientId) && (
              <Link
                href="/onboarding"
                className="inline-flex items-center gap-2 rounded-full bg-slate-950 px-4 py-2 text-sm font-black text-white shadow-sm transition hover:bg-slate-800"
              >
                <ShieldCheck size={17} />
                Modifier mon onboarding
              </Link>
            )}
          </div>

          <section className="mb-8 rounded-[2.5rem] bg-white p-6 shadow-[0_24px_80px_rgba(249,115,22,0.13)] sm:p-8">
            <span className="mb-5 inline-flex items-center gap-2 rounded-full border border-orange-200 bg-orange-50 px-4 py-2 text-xs font-black uppercase tracking-[0.16em] text-orange-700">
              <UserRound size={16} />
              Dossier client
            </span>
            <h1 className="text-4xl font-black tracking-tight sm:text-5xl">
              {client?.name || "Parcours client"}
            </h1>
            <p className="mt-3 text-sm font-semibold text-slate-500">
              {client?.email || "Email non renseigné"}
            </p>

            <div className="mt-6 grid gap-3 sm:grid-cols-4">
              <Metric label="Réservations" value={history?.summary?.reservations_count || 0} />
              <Metric
                label="Réalisées"
                value={history?.summary?.completed_reservations_count || 0}
              />
              <Metric
                label="Payées"
                value={history?.summary?.paid_reservations_count || 0}
              />
              <Metric label="Notes" value={history?.summary?.notes_count || notes.length} />
            </div>
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
              Chargement du dossier...
            </div>
          ) : (
            <div className="grid gap-6 xl:grid-cols-[1fr_420px]">
              <div className="grid gap-6">
                <section className="rounded-[2rem] bg-white p-5 shadow-sm sm:p-7">
                  <h2 className="mb-5 text-2xl font-black tracking-tight">
                    Historique des réservations
                  </h2>

                  <div className="grid gap-4">
                    {(history?.reservations || []).length === 0 ? (
                      <p className="text-sm font-semibold text-slate-500">
                        Aucune réservation trouvée.
                      </p>
                    ) : (
                      history?.reservations?.map((reservation) => (
                        <article
                          key={reservation.id}
                          className="rounded-2xl border border-orange-100 bg-orange-50 p-4"
                        >
                          <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                              <h3 className="text-lg font-black">
                                {getAnnonceTitle(reservation.annonce)}
                              </h3>
                              <p className="mt-2 text-sm font-bold text-slate-500">
                                {formatDate(reservation.reservation_date)} à{" "}
                                {reservation.reservation_time || "heure à confirmer"}
                              </p>
                            </div>
                            <div className="text-left sm:text-right">
                              <strong className="block text-sm font-black text-orange-700">
                                {formatMoney(
                                  reservation.total_client_amount || reservation.price,
                                  reservation.currency || "EUR"
                                )}
                              </strong>
                              <span className="text-xs font-bold text-slate-500">
                                {reservation.status || reservation.prestation_status || "Statut inconnu"}
                              </span>
                            </div>
                          </div>
                        </article>
                      ))
                    )}
                  </div>
                </section>

                <section className="rounded-[2rem] bg-white p-5 shadow-sm sm:p-7">
                  <h2 className="mb-5 text-2xl font-black tracking-tight">
                    Questionnaire onboarding
                  </h2>

                  {onboarding ? (
                    <div className="grid gap-4">
                      <Info label="Objectifs" value={asList(onboarding.goals)} />
                      <Info label="Niveau" value={onboarding.level || "Non renseigné"} />
                      <Info
                        label="Préférences"
                        value={textFromObject(onboarding.training_preferences)}
                      />
                      <Info
                        label="Disponibilités"
                        value={textFromObject(onboarding.availability)}
                      />
                      <Info
                        label="Contraintes santé"
                        value={textFromObject(onboarding.health_constraints)}
                      />
                      <Info label="Mode de vie" value={textFromObject(onboarding.lifestyle)} />
                    </div>
                  ) : (
                    <p className="text-sm font-semibold leading-6 text-slate-500">
                      Aucun questionnaire onboarding n’a encore été complété par ce client.
                    </p>
                  )}
                </section>
              </div>

              <aside className="grid gap-6">
                <form
                  onSubmit={handleCreateNote}
                  className="rounded-[2rem] bg-white p-5 shadow-sm sm:p-7"
                >
                  <h2 className="mb-5 text-2xl font-black tracking-tight">
                    Ajouter une note
                  </h2>

                  <div className="grid gap-4">
                    <input
                      value={noteTitle}
                      onChange={(event) => setNoteTitle(event.target.value)}
                      placeholder="Titre optionnel"
                      className="gotfit-input"
                    />

                    <select
                      value={noteVisibility}
                      onChange={(event) =>
                        setNoteVisibility(event.target.value as "private" | "shared")
                      }
                      className="gotfit-input"
                    >
                      <option value="private">Note privée</option>
                      <option value="shared">Note partagée</option>
                    </select>

                    <textarea
                      value={noteContent}
                      onChange={(event) => setNoteContent(event.target.value)}
                      rows={5}
                      placeholder="Bilan de séance, objectifs, points d’attention..."
                      className="gotfit-input resize-none leading-7"
                    />

                    <button
                      type="submit"
                      disabled={saving}
                      className="inline-flex items-center justify-center gap-2 rounded-2xl bg-orange-600 px-5 py-4 text-sm font-black text-white shadow-lg shadow-orange-600/20 transition hover:-translate-y-0.5 hover:bg-orange-700 disabled:opacity-60"
                    >
                      {saving ? <Loader2 className="animate-spin" size={18} /> : <Plus size={18} />}
                      Enregistrer la note
                    </button>
                  </div>
                </form>

                <section className="rounded-[2rem] bg-white p-5 shadow-sm sm:p-7">
                  <h2 className="mb-5 text-2xl font-black tracking-tight">
                    Notes
                  </h2>

                  <div className="grid gap-4">
                    {notes.length === 0 ? (
                      <p className="text-sm font-semibold leading-6 text-slate-500">
                        Aucune note disponible pour ce dossier.
                      </p>
                    ) : (
                      notes.map((note) => (
                        <article
                          key={note.id}
                          className="rounded-2xl border border-orange-100 bg-orange-50 p-4"
                        >
                          <div className="mb-3 flex items-start justify-between gap-3">
                            <div>
                              <span
                                className={`mb-2 inline-flex rounded-full px-3 py-1 text-xs font-black ${
                                  note.visibility === "shared"
                                    ? "bg-emerald-100 text-emerald-700"
                                    : "bg-slate-200 text-slate-700"
                                }`}
                              >
                                {note.visibility === "shared"
                                  ? "Partagée"
                                  : "Privée"}
                              </span>
                              <h3 className="font-black">
                                {note.title || "Note sans titre"}
                              </h3>
                            </div>

                            {note.author_id === currentUser?.id && (
                              <button
                                type="button"
                                onClick={() => handleDeleteNote(note.id)}
                                disabled={deletingId === note.id}
                                className="flex h-9 w-9 items-center justify-center rounded-full bg-white text-red-600 shadow-sm disabled:opacity-50"
                              >
                                {deletingId === note.id ? (
                                  <Loader2 className="animate-spin" size={16} />
                                ) : (
                                  <Trash2 size={16} />
                                )}
                              </button>
                            )}
                          </div>

                          <p className="whitespace-pre-line text-sm font-semibold leading-7 text-slate-600">
                            {note.content}
                          </p>

                          <div className="mt-4 flex flex-wrap items-center gap-2 text-xs font-bold text-slate-400">
                            <BadgeCheck size={14} />
                            {note.author?.name || "Auteur inconnu"}
                            <span>•</span>
                            {formatDateTime(note.created_at)}
                          </div>
                        </article>
                      ))
                    )}
                  </div>
                </section>
              </aside>
            </div>
          )}
        </div>
      </main>

      <Footer />
    </>
  );
}

function Metric({ label, value }: { label: string; value: number }) {
  return (
    <div className="rounded-2xl bg-orange-50 p-4">
      <strong className="block text-2xl font-black">{value}</strong>
      <span className="text-xs font-bold text-slate-500">{label}</span>
    </div>
  );
}

function Info({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-2xl bg-orange-50 p-4">
      <strong className="mb-2 block text-sm font-black text-slate-800">
        {label}
      </strong>
      <p className="whitespace-pre-line text-sm font-semibold leading-6 text-slate-600">
        {value}
      </p>
    </div>
  );
}
