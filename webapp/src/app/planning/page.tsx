"use client";

import Link from "next/link";
import type React from "react";
import { useEffect, useMemo, useState } from "react";
import {
  ArrowRight,
  CalendarCheck,
  CheckCircle2,
  Clock3,
  CreditCard,
  Loader2,
  RefreshCw,
  ShieldCheck,
  Users,
  X,
} from "lucide-react";

import Footer from "@/components/layout/Footer";
import Header from "@/components/layout/Header";
import { getToken } from "@/lib/auth";
import {
  PlanningEvent,
  fetchPlanning,
  formatMoney,
  getAnnonceTitle,
  canAddReservationToCalendar,
  downloadReservationCalendar,
  getReservationVisioHref,
} from "@/lib/marketplace";

const statusLabels: Record<string, string> = {
  attente: "En attente",
  confirme: "Confirmée",
  realise: "Réalisée",
  pending_payment: "Paiement en attente",
  paid: "Payée",
  pending_validation: "Validation client attendue",
  validated: "Validée",
  transferred: "Coach payé",
  disputed: "Litige ouvert",
  refunded: "Remboursée",
  cancelled: "Annulée",
  payment_failed: "Paiement échoué",
};

function getErrorMessage(error: unknown, fallback: string) {
  return error instanceof Error ? error.message : fallback;
}

function getStatusLabel(status?: string | null) {
  if (!status) return "Non défini";
  return statusLabels[status] || status;
}

function getStatusStyle(status?: string | null) {
  if (["validated", "transferred", "realise"].includes(status || "")) {
    return "border-emerald-100 bg-emerald-50 text-emerald-700";
  }

  if (["disputed", "pending_validation"].includes(status || "")) {
    return "border-amber-100 bg-amber-50 text-amber-700";
  }

  if (["refunded", "cancelled", "payment_failed"].includes(status || "")) {
    return "border-red-100 bg-red-50 text-red-700";
  }

  return "border-orange-100 bg-orange-50 text-orange-700";
}

function getCurrentMonthRange() {
  const now = new Date();
  const first = new Date(now.getFullYear(), now.getMonth(), 1);
  const last = new Date(now.getFullYear(), now.getMonth() + 1, 0);

  return {
    from: first.toISOString().slice(0, 10),
    to: last.toISOString().slice(0, 10),
  };
}

function formatDateTime(value?: string | null) {
  if (!value) return "Date non définie";

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

export default function PlanningPage() {
  const initialRange = useMemo(() => getCurrentMonthRange(), []);
  const [events, setEvents] = useState<PlanningEvent[]>([]);
  const [from, setFrom] = useState(initialRange.from);
  const [to, setTo] = useState(initialRange.to);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");
  const [calendarLoading, setCalendarLoading] = useState<number | null>(null);

  const stats = useMemo(() => {
    return {
      total: events.length,
      paid: events.filter((item) => item.payment_status === "paid").length,
      validation: events.filter(
        (item) => item.prestation_status === "pending_validation"
      ).length,
      disputes: events.filter((item) => item.prestation_status === "disputed").length,
    };
  }, [events]);

  async function loadPlanning() {
    try {
      setLoading(true);
      setError("");

      if (!getToken()) {
        setError("Veuillez vous connecter pour consulter votre planning.");
        return;
      }

      setEvents(await fetchPlanning({ from, to }));
    } catch (err) {
      setError(getErrorMessage(err, "Impossible de charger le planning."));
    } finally {
      setLoading(false);
    }
  }


  async function handleCalendar(event: PlanningEvent) {
    try {
      setCalendarLoading(event.id);
      setError("");
      setSuccess("");
      await downloadReservationCalendar(event);
      setSuccess("Le rendez-vous a été téléchargé au format calendrier (.ics).");
    } catch (err) {
      setError(getErrorMessage(err, "Impossible de préparer le calendrier."));
    } finally {
      setCalendarLoading(null);
    }
  }

  useEffect(() => {
    const timer = window.setTimeout(() => {
      loadPlanning();
    }, 0);

    return () => window.clearTimeout(timer);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return (
    <>
      <Header />

      <main className="min-h-screen bg-[#FFF7ED] px-4 pb-16 pt-36 text-slate-950">
        <div className="mx-auto max-w-7xl">
          <section className="mb-8 grid gap-6 lg:grid-cols-[1fr_0.9fr] lg:items-end">
            <div>
              <span className="mb-5 inline-flex items-center gap-2 rounded-full border border-orange-200 bg-white px-4 py-2 text-xs font-black uppercase tracking-[0.18em] text-orange-700 shadow-sm">
                <CalendarCheck size={16} />
                Planning synchronisé
              </span>

              <h1 className="max-w-3xl text-4xl font-black leading-tight tracking-tight sm:text-6xl">
                Planning des réservations.
              </h1>

              <p className="mt-5 max-w-2xl text-base font-medium leading-8 text-slate-600">
                Retrouvez les créneaux client/coach, les statuts de paiement et
                les prestations en attente de validation après séance.
              </p>
            </div>

            <div className="grid gap-3 sm:grid-cols-4 lg:grid-cols-2">
              <StatCard icon={<CalendarCheck size={22} />} label="Créneaux" value={stats.total} />
              <StatCard icon={<CreditCard size={22} />} label="Payées" value={stats.paid} />
              <StatCard icon={<CheckCircle2 size={22} />} label="À valider" value={stats.validation} />
              <StatCard icon={<ShieldCheck size={22} />} label="Litiges" value={stats.disputes} />
            </div>
          </section>

          <section className="mb-6 rounded-[2rem] bg-white p-5 shadow-sm">
            <div className="grid gap-4 md:grid-cols-[1fr_1fr_auto_auto] md:items-end">
              <label className="grid gap-2 text-sm font-black text-slate-700">
                Date début
                <input
                  type="date"
                  value={from}
                  onChange={(event) => setFrom(event.target.value)}
                  className="rounded-2xl border border-orange-100 bg-orange-50 px-4 py-3 text-sm font-bold outline-none"
                />
              </label>

              <label className="grid gap-2 text-sm font-black text-slate-700">
                Date fin
                <input
                  type="date"
                  value={to}
                  onChange={(event) => setTo(event.target.value)}
                  className="rounded-2xl border border-orange-100 bg-orange-50 px-4 py-3 text-sm font-bold outline-none"
                />
              </label>

              <button
                type="button"
                onClick={loadPlanning}
                className="inline-flex items-center justify-center gap-2 rounded-full bg-orange-600 px-5 py-3 text-sm font-black text-white shadow-lg shadow-orange-600/20 transition hover:-translate-y-0.5 hover:bg-orange-700"
              >
                <RefreshCw size={17} />
                Actualiser
              </button>

              <Link
                href="/reservations"
                className="inline-flex items-center justify-center gap-2 rounded-full bg-slate-950 px-5 py-3 text-sm font-black text-white transition hover:bg-slate-800"
              >
                Réservations
                <ArrowRight size={17} />
              </Link>
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
              Chargement du planning...
            </div>
          ) : events.length === 0 ? (
            <div className="rounded-[2rem] bg-white p-10 text-center shadow-sm">
              <h2 className="text-2xl font-black">Aucun créneau</h2>
              <p className="mx-auto mt-3 max-w-md text-sm font-semibold leading-6 text-slate-500">
                Aucune réservation trouvée sur cette période.
              </p>
            </div>
          ) : (
            <div className="grid gap-4">
              {events.map((event) => {
                const primaryStatus = event.prestation_status || event.status;

                return (
                  <article
                    key={event.id}
                    className="rounded-[2rem] border border-orange-100 bg-white p-5 shadow-sm"
                  >
                    <div className="grid gap-5 lg:grid-cols-[1fr_260px] lg:items-center">
                      <div>
                        <div className="mb-3 flex flex-wrap gap-2">
                          <span
                            className={`rounded-full border px-3 py-1 text-xs font-black ${getStatusStyle(primaryStatus)}`}
                          >
                            {getStatusLabel(primaryStatus)}
                          </span>
                          <span
                            className={`rounded-full border px-3 py-1 text-xs font-black ${getStatusStyle(event.payment_status)}`}
                          >
                            Paiement : {getStatusLabel(event.payment_status)}
                          </span>
                        </div>

                        <h2 className="text-2xl font-black">
                          {event.title || getAnnonceTitle(event.annonce)}
                        </h2>

                        <div className="mt-4 grid gap-3 text-sm font-bold text-slate-500 sm:grid-cols-3">
                          <span className="inline-flex items-center gap-2">
                            <Clock3 size={17} />
                            {formatDateTime(event.start || event.reservation_date)}
                          </span>
                          <span className="inline-flex items-center gap-2">
                            <Users size={17} />
                            {event.client?.name || "Client"} /{" "}
                            {event.intervenant?.name || "Coach"}
                          </span>
                          <span className="inline-flex items-center gap-2">
                            <CreditCard size={17} />
                            {formatMoney(
                              event.total_client_amount || event.price,
                              event.currency || "EUR"
                            )}
                          </span>
                        </div>
                      </div>

                      <div className="grid gap-3">
                        <button
                          type="button"
                          onClick={() => handleCalendar(event)}
                          disabled={
                            !canAddReservationToCalendar(event) ||
                            calendarLoading === event.id
                          }
                          className="inline-flex items-center justify-center gap-2 rounded-full border border-orange-200 bg-orange-50 px-5 py-3 text-sm font-black text-orange-700 transition hover:bg-orange-100 disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-100 disabled:text-slate-400"
                        >
                          {calendarLoading === event.id ? (
                            <Loader2 className="animate-spin" size={17} />
                          ) : (
                            <CalendarCheck size={17} />
                          )}
                          {canAddReservationToCalendar(event)
                            ? "Ajouter au calendrier"
                            : "Disponible après paiement"}
                        </button>

                        {event.payment_status === "paid" &&
                          Boolean(event.annonce?.is_online) && (
                            <Link
                              href={getReservationVisioHref(event)}
                              className="inline-flex items-center justify-center gap-2 rounded-full bg-orange-600 px-5 py-3 text-sm font-black text-white transition hover:bg-orange-700"
                            >
                              Ouvrir la visio
                              <ArrowRight size={17} />
                            </Link>
                          )}

                        <Link
                          href="/reservations"
                          className="inline-flex items-center justify-center gap-2 rounded-full bg-slate-950 px-5 py-3 text-sm font-black text-white transition hover:bg-slate-800"
                        >
                          Gérer
                          <ArrowRight size={17} />
                        </Link>
                      </div>
                    </div>
                  </article>
                );
              })}
            </div>
          )}
        </div>
      </main>

      <Footer />
    </>
  );
}

function StatCard({
  icon,
  label,
  value,
}: {
  icon: React.ReactNode;
  label: string;
  value: number;
}) {
  return (
    <div className="rounded-[1.5rem] bg-white p-5 shadow-sm">
      <div className="mb-3 text-orange-700">{icon}</div>
      <strong className="block text-2xl font-black">{value}</strong>
      <span className="text-xs font-bold text-slate-500">{label}</span>
    </div>
  );
}
