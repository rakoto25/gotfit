"use client";

import Link from "next/link";
import { FormEvent, useEffect, useMemo, useState } from "react";
import {
  AlertTriangle,
  ArrowRight,
  BadgeCheck,
  CalendarCheck,
  CheckCircle2,
  Clock3,
  CreditCard,
  Loader2,
  MessageSquareWarning,
  RefreshCw,
  ShieldCheck,
  Sparkles,
  X,
} from "lucide-react";

import Header from "@/components/layout/Header";
import Footer from "@/components/layout/Footer";
import { getCurrentUser, getToken, hasRole } from "@/lib/auth";
import {
  Reservation,
  confirmPrestation,
  disputePrestation,
  fetchIntervenantReservations,
  fetchClientReservations,
  finishReservation,
  formatDate,
  formatMoney,
  getAnnonceTitle,
  canAddReservationToCalendar,
  downloadReservationCalendar,
  getReservationVisioHref,
  canAccessReservationVisio,
  isReservationOnline,
  isReservationPaid,
} from "@/lib/marketplace";

const statusLabels: Record<string, string> = {
  attente: "En attente",
  attente_paiement: "Paiement en attente",
  payee: "Payée",
  validee: "Validée",
  terminee: "Terminée",
  litige: "Litige",
  annulee: "Annulée",
  remboursee: "Remboursée",
  pending_payment: "Paiement en attente",
  paid: "Payée",
  confirme: "Confirmée",
  realise: "Réalisée",
  validated: "Prestation validée",
  transferred: "Coach payé",
  disputed: "Litige ouvert",
  refunded: "Remboursée",
  cancelled: "Annulée",
  blocked: "Reversement bloqué",
  pending: "En attente",
  pending_validation: "Validation client attendue",
  payment_failed: "Paiement échoué",
};

function getStatusLabel(status?: string | null) {
  if (!status) return "Non défini";
  return statusLabels[status] || status;
}

function getStatusStyle(status?: string | null) {
  if (["transferred", "terminee"].includes(status || "")) {
    return "bg-emerald-50 text-emerald-700 border-emerald-100";
  }

  if (["disputed", "litige", "blocked"].includes(status || "")) {
    return "bg-amber-50 text-amber-700 border-amber-100";
  }

  if (["refunded", "cancelled", "annulee", "remboursee"].includes(status || "")) {
    return "bg-red-50 text-red-700 border-red-100";
  }

  return "bg-orange-50 text-orange-700 border-orange-100";
}

function canConfirm(reservation: Reservation) {
  return (
    reservation.payment_status === "paid" &&
    ["pending_validation", "realise"].includes(
      reservation.prestation_status || reservation.status || ""
    )
  );
}

function canDispute(reservation: Reservation) {
  return (
    reservation.payment_status === "paid" &&
    !reservation.stripe_transfer_id &&
    !["validated", "transferred", "refunded", "cancelled", "disputed"].includes(
      reservation.prestation_status || ""
    )
  );
}

function getErrorMessage(error: unknown, fallback: string) {
  return error instanceof Error ? error.message : fallback;
}

export default function ReservationsPage() {
  const [reservations, setReservations] = useState<Reservation[]>([]);
  const [roleLabel, setRoleLabel] = useState("client");
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState<number | null>(null);
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");
  const [disputeId, setDisputeId] = useState<number | null>(null);
  const [reason, setReason] = useState("");
  const [calendarLoading, setCalendarLoading] = useState<number | null>(null);

  const counters = useMemo(() => {
    return {
      total: reservations.length,
      paid: reservations.filter((item) => item.payment_status === "paid").length,
      pending: reservations.filter((item) => item.payment_status === "pending")
        .length,
      disputes: reservations.filter(
        (item) => item.prestation_status === "disputed"
      ).length,
    };
  }, [reservations]);

  async function loadReservations() {
    try {
      setLoading(true);
      setError("");

      if (!getToken()) {
        setError("Veuillez vous connecter pour consulter vos réservations.");
        return;
      }

      const currentUser = getCurrentUser();
      const isCoach = hasRole(currentUser, "intervenant");

      setRoleLabel(isCoach ? "coach" : "client");
      setReservations(
        isCoach ? await fetchIntervenantReservations() : await fetchClientReservations()
      );
    } catch (err) {
      setError(getErrorMessage(err, "Impossible de charger les réservations."));
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    const timer = window.setTimeout(() => {
      loadReservations();
    }, 0);

    return () => window.clearTimeout(timer);
  }, []);


  async function handleCalendar(reservation: Reservation) {
    try {
      setCalendarLoading(reservation.id);
      setError("");
      await downloadReservationCalendar(reservation);
      setSuccess("Le fichier calendrier a été téléchargé. Ouvrez-le pour ajouter la séance à Google Calendar, Outlook ou Apple Calendar.");
    } catch (err) {
      setError(getErrorMessage(err, "Impossible de préparer le calendrier."));
    } finally {
      setCalendarLoading(null);
    }
  }

  async function handleConfirm(reservationId: number) {
    try {
      setActionLoading(reservationId);
      setError("");
      setSuccess("");
      await confirmPrestation(reservationId);
      setSuccess("Prestation confirmée. Le reversement coach peut être lancé.");
      await loadReservations();
    } catch (err) {
      setError(getErrorMessage(err, "Impossible de confirmer la prestation."));
    } finally {
      setActionLoading(null);
    }
  }

  async function handleFinish(reservationId: number) {
    try {
      setActionLoading(reservationId);
      setError("");
      setSuccess("");
      await finishReservation(reservationId);
      setSuccess(
        "Séance marquée comme réalisée. Le client peut maintenant confirmer ou ouvrir un litige."
      );
      await loadReservations();
    } catch (err) {
      setError(getErrorMessage(err, "Impossible de terminer cette réservation."));
    } finally {
      setActionLoading(null);
    }
  }

  async function handleDispute(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (!disputeId) return;

    if (reason.trim().length < 10) {
      setError("Merci d’expliquer le litige en au moins 10 caractères.");
      return;
    }

    try {
      setActionLoading(disputeId);
      setError("");
      setSuccess("");
      await disputePrestation(disputeId, reason.trim());
      setSuccess("Litige ouvert. Le reversement coach est bloqué.");
      setDisputeId(null);
      setReason("");
      await loadReservations();
    } catch (err) {
      setError(getErrorMessage(err, "Impossible d’ouvrir le litige."));
    } finally {
      setActionLoading(null);
    }
  }

  return (
    <>
      <Header />

      <main className="min-h-screen bg-[#FFF7ED] px-4 pb-16 pt-36 text-slate-950">
        <div className="mx-auto max-w-7xl">
          <section className="mb-8 grid gap-6 lg:grid-cols-[1fr_0.9fr] lg:items-end">
            <div>
              <span className="mb-5 inline-flex items-center gap-2 rounded-full border border-orange-200 bg-white px-4 py-2 text-xs font-black uppercase tracking-[0.18em] text-orange-700 shadow-sm">
                <ShieldCheck size={16} />
                Suivi marketplace
              </span>

              <h1 className="max-w-3xl text-4xl font-black leading-tight tracking-tight sm:text-6xl">
                {roleLabel === "coach"
                  ? "Réservations coach et validation."
                  : "Mes réservations et paiements."}
              </h1>

              <p className="mt-5 max-w-2xl text-base font-medium leading-8 text-slate-600">
                {roleLabel === "coach"
                  ? "Suivez vos séances, terminez les prestations réalisées et laissez le client confirmer avant reversement."
                  : "Suivez le paiement, confirmez la prestation après réalisation ou ouvrez un litige avant le reversement au coach."}
              </p>
            </div>

            <div className="grid gap-3 sm:grid-cols-4 lg:grid-cols-2">
              <div className="rounded-[1.5rem] bg-white p-5 shadow-sm">
                <Sparkles className="mb-3 text-orange-700" size={22} />
                <strong className="block text-2xl font-black">
                  {counters.total}
                </strong>
                <span className="text-xs font-bold text-slate-500">
                  Réservations
                </span>
              </div>
              <div className="rounded-[1.5rem] bg-white p-5 shadow-sm">
                <CreditCard className="mb-3 text-orange-700" size={22} />
                <strong className="block text-2xl font-black">
                  {counters.paid}
                </strong>
                <span className="text-xs font-bold text-slate-500">Payées</span>
              </div>
              <div className="rounded-[1.5rem] bg-white p-5 shadow-sm">
                <Clock3 className="mb-3 text-orange-700" size={22} />
                <strong className="block text-2xl font-black">
                  {counters.pending}
                </strong>
                <span className="text-xs font-bold text-slate-500">
                  En attente
                </span>
              </div>
              <div className="rounded-[1.5rem] bg-white p-5 shadow-sm">
                <AlertTriangle className="mb-3 text-orange-700" size={22} />
                <strong className="block text-2xl font-black">
                  {counters.disputes}
                </strong>
                <span className="text-xs font-bold text-slate-500">Litiges</span>
              </div>
            </div>
          </section>

          <div className="mb-6 flex flex-wrap gap-3">
            <Link
              href="/annonces"
              className="inline-flex items-center gap-2 rounded-full bg-orange-600 px-5 py-3 text-sm font-black text-white shadow-lg shadow-orange-600/20 transition hover:-translate-y-0.5 hover:bg-orange-700"
            >
              Trouver une annonce
              <ArrowRight size={17} />
            </Link>
            <Link
              href="/planning"
              className="inline-flex items-center gap-2 rounded-full bg-slate-950 px-5 py-3 text-sm font-black text-white shadow-lg transition hover:-translate-y-0.5 hover:bg-slate-800"
            >
              Voir le planning
              <ArrowRight size={17} />
            </Link>
            <button
              type="button"
              onClick={loadReservations}
              className="inline-flex items-center gap-2 rounded-full bg-white px-5 py-3 text-sm font-black text-orange-700 shadow-sm transition hover:bg-orange-50"
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

          {loading && (
            <div className="flex items-center justify-center rounded-[2rem] bg-white py-16 text-sm font-black text-orange-700 shadow-sm">
              <Loader2 className="mr-3 animate-spin" size={20} />
              Chargement des réservations...
            </div>
          )}

          {!loading && reservations.length === 0 && (
            <div className="rounded-[2rem] bg-white p-10 text-center shadow-sm">
              <h2 className="text-2xl font-black">Aucune réservation</h2>
              <p className="mx-auto mt-3 max-w-md text-sm font-semibold leading-6 text-slate-500">
                Réservez une prestation pour suivre son paiement, sa validation
                et son statut de reversement.
              </p>
            </div>
          )}

          <div className="grid gap-5">
            {reservations.map((reservation) => {
              const primaryStatus =
                reservation.prestation_status || reservation.status;
              const isBusy = actionLoading === reservation.id;

              return (
                <article
                  key={reservation.id}
                  className="rounded-[2rem] border border-orange-100 bg-white p-5 shadow-sm sm:p-6"
                >
                  <div className="grid gap-5 lg:grid-cols-[1fr_320px] lg:items-start">
                    <div>
                      <div className="mb-4 flex flex-wrap items-center gap-2">
                        <span
                          className={`inline-flex rounded-full border px-3 py-1 text-xs font-black ${getStatusStyle(
                            primaryStatus
                          )}`}
                        >
                          {getStatusLabel(primaryStatus)}
                        </span>
                        <span
                          className={`inline-flex rounded-full border px-3 py-1 text-xs font-black ${getStatusStyle(
                            reservation.payment_status
                          )}`}
                        >
                          Paiement : {getStatusLabel(reservation.payment_status)}
                        </span>
                        <span
                          className={`inline-flex rounded-full border px-3 py-1 text-xs font-black ${getStatusStyle(
                            reservation.payout_status
                          )}`}
                        >
                          Reversement : {getStatusLabel(reservation.payout_status)}
                        </span>
                      </div>

                      <h2 className="text-2xl font-black tracking-tight">
                        {getAnnonceTitle(reservation.annonce)}
                      </h2>

                      <div className="mt-4 grid gap-3 text-sm font-bold text-slate-500 sm:grid-cols-3">
                        <span className="inline-flex items-center gap-2">
                          <CalendarCheck size={17} />
                          {formatDate(reservation.reservation_date)}
                        </span>
                        <span className="inline-flex items-center gap-2">
                          <Clock3 size={17} />
                          {reservation.reservation_time || "Heure à confirmer"}
                        </span>
                        <span className="inline-flex items-center gap-2">
                          <CreditCard size={17} />
                          {formatMoney(
                            reservation.total_client_amount || reservation.price,
                            reservation.currency || "EUR"
                          )}
                        </span>
                      </div>

                      {reservation.dispute_reason && (
                        <div className="mt-4 rounded-2xl bg-amber-50 px-4 py-3 text-sm font-bold leading-6 text-amber-700">
                          Motif litige : {reservation.dispute_reason}
                        </div>
                      )}

                      {reservation.validation_deadline && (
                        <div className="mt-4 rounded-2xl bg-emerald-50 px-4 py-3 text-sm font-bold leading-6 text-emerald-700">
                          Validation automatique prévue après le{" "}
                          {formatDate(reservation.validation_deadline)} si aucun
                          litige n’est ouvert.
                        </div>
                      )}
                    </div>

                    <div className="grid gap-3">
                      <button
                        type="button"
                        onClick={() => handleCalendar(reservation)}
                        disabled={
                          !canAddReservationToCalendar(reservation) ||
                          calendarLoading === reservation.id
                        }
                        title={
                          canAddReservationToCalendar(reservation)
                            ? "Télécharger le rendez-vous au format .ics"
                            : "Disponible après paiement"
                        }
                        className="inline-flex items-center justify-center gap-2 rounded-full border border-orange-200 bg-white px-5 py-3 text-sm font-black text-orange-700 transition hover:bg-orange-50 disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-100 disabled:text-slate-400"
                      >
                        {calendarLoading === reservation.id ? (
                          <Loader2 className="animate-spin" size={17} />
                        ) : (
                          <CalendarCheck size={17} />
                        )}
                        {canAddReservationToCalendar(reservation)
                          ? "Ajouter au calendrier"
                          : "Calendrier après paiement"}
                      </button>

                      {canAccessReservationVisio(reservation) && (
                        <Link
                          href={getReservationVisioHref(reservation)}
                          className="inline-flex items-center justify-center gap-2 rounded-full bg-orange-600 px-5 py-3 text-sm font-black text-white transition hover:bg-orange-700"
                        >
                          Accéder à la visio
                          <ArrowRight size={17} />
                        </Link>
                      )}

                      {isReservationPaid(reservation) &&
                        isReservationOnline(reservation) &&
                        !reservation.visio_session_id &&
                        !reservation.visio_session?.id && (
                          <div className="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-center text-xs font-bold text-amber-700">
                            Salle visio en préparation. Actualisez dans quelques instants.
                          </div>
                        )}

                      {reservation.payment_status === "pending" && (
                        <Link
                          href={`/annonces/${reservation.annonce_id}`}
                          className="inline-flex items-center justify-center gap-2 rounded-full bg-orange-600 px-5 py-3 text-sm font-black text-white transition hover:bg-orange-700"
                        >
                          Finaliser le paiement
                          <ArrowRight size={17} />
                        </Link>
                      )}

                      {roleLabel === "coach" &&
                        reservation.payment_status === "paid" &&
                        !["realise", "validated", "transferred", "disputed", "refunded", "cancelled"].includes(
                          reservation.prestation_status || reservation.status || ""
                        ) && (
                          <button
                            type="button"
                            onClick={() => handleFinish(reservation.id)}
                            disabled={isBusy}
                            className="inline-flex items-center justify-center gap-2 rounded-full bg-slate-950 px-5 py-3 text-sm font-black text-white transition hover:bg-slate-800 disabled:opacity-60"
                          >
                            {isBusy ? (
                              <Loader2 className="animate-spin" size={17} />
                            ) : (
                              <CheckCircle2 size={17} />
                            )}
                            Terminer la séance
                          </button>
                        )}

                      {roleLabel === "client" && canConfirm(reservation) && (
                        <button
                          type="button"
                          onClick={() => handleConfirm(reservation.id)}
                          disabled={isBusy}
                          className="inline-flex items-center justify-center gap-2 rounded-full bg-slate-950 px-5 py-3 text-sm font-black text-white transition hover:bg-slate-800 disabled:opacity-60"
                        >
                          {isBusy ? (
                            <Loader2 className="animate-spin" size={17} />
                          ) : (
                            <BadgeCheck size={17} />
                          )}
                          Confirmer la prestation
                        </button>
                      )}

                      {roleLabel === "client" && canDispute(reservation) && (
                        <button
                          type="button"
                          onClick={() => {
                            setDisputeId(reservation.id);
                            setReason("");
                          }}
                          className="inline-flex items-center justify-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-5 py-3 text-sm font-black text-amber-700 transition hover:bg-amber-100"
                        >
                          <MessageSquareWarning size={17} />
                          Ouvrir un litige
                        </button>
                      )}

                      {reservation.stripe_transfer_id && (
                        <div className="rounded-2xl bg-emerald-50 px-4 py-3 text-xs font-bold leading-5 text-emerald-700">
                          Coach payé : {reservation.stripe_transfer_id}
                        </div>
                      )}
                    </div>
                  </div>
                </article>
              );
            })}
          </div>
        </div>
      </main>

      {disputeId && (
        <div className="fixed inset-0 z-[80] flex items-center justify-center bg-slate-950/45 px-4 backdrop-blur-sm">
          <form
            onSubmit={handleDispute}
            className="w-full max-w-lg rounded-[2rem] bg-white p-6 shadow-2xl"
          >
            <div className="mb-5 flex items-start justify-between gap-4">
              <div>
                <h2 className="text-2xl font-black">Ouvrir un litige</h2>
                <p className="mt-2 text-sm font-semibold leading-6 text-slate-500">
                  Expliquez le problème. Le reversement coach sera bloqué en
                  attendant la décision admin.
                </p>
              </div>
              <button
                type="button"
                onClick={() => setDisputeId(null)}
                className="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-600"
              >
                <X size={18} />
              </button>
            </div>

            <textarea
              value={reason}
              onChange={(event) => setReason(event.target.value)}
              rows={5}
              placeholder="Exemple : le coach ne s’est pas présenté..."
              className="w-full resize-none rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold leading-6 outline-none placeholder:text-slate-400"
            />

            <div className="mt-5 flex flex-col gap-3 sm:flex-row">
              <button
                type="button"
                onClick={() => setDisputeId(null)}
                className="inline-flex flex-1 items-center justify-center rounded-full bg-slate-100 px-5 py-3 text-sm font-black text-slate-700"
              >
                Annuler
              </button>
              <button
                type="submit"
                disabled={actionLoading === disputeId}
                className="inline-flex flex-1 items-center justify-center gap-2 rounded-full bg-slate-950 px-5 py-3 text-sm font-black text-white disabled:opacity-60"
              >
                {actionLoading === disputeId ? (
                  <Loader2 className="animate-spin" size={17} />
                ) : null}
                Envoyer le litige
              </button>
            </div>
          </form>
        </div>
      )}

      <Footer />
    </>
  );
}
