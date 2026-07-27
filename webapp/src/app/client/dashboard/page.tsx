"use client";

import Link from "next/link";
import { useEffect, useMemo, useState } from "react";
import {
  ArrowRight,
  CalendarCheck,
  CheckCircle2,
  Clock3,
  CreditCard,
  Loader2,
  MessageCircle,
  RefreshCw,
  ShieldCheck,
  Sparkles,
  UserRound,
  Video,
  X,
} from "lucide-react";

import Header from "@/components/layout/Header";
import Footer from "@/components/layout/Footer";
import { getCurrentUser, getToken, hasRole } from "@/lib/auth";
import {
  fetchClientReservations,
  formatDate,
  formatMoney,
  getAnnonceTitle,
  canAddReservationToCalendar,
  downloadReservationCalendar,
  getReservationVisioHref,
  canAccessReservationVisio,
  isReservationOnline,
  isReservationPaid,
  type Reservation,
} from "@/lib/marketplace";
import { fetchVisioSessions, type VisioSession } from "@/lib/visio";

function getErrorMessage(error: unknown, fallback: string) {
  return error instanceof Error ? error.message : fallback;
}

function statusLabel(status?: string | null) {
  const labels: Record<string, string> = {
    pending: "En attente",
    attente: "En attente",
    pending_payment: "Paiement attendu",
    paid: "Payée",
    confirme: "Confirmée",
    confirmed: "Confirmée",
    realise: "Réalisée",
    pending_validation: "À confirmer",
    validated: "Validée",
    transferred: "Coach payé",
    disputed: "Litige",
    refunded: "Remboursée",
    cancelled: "Annulée",
  };
  return status ? labels[status] || status : "Non défini";
}

function dateTimeLabel(session: VisioSession) {
  try {
    return new Intl.DateTimeFormat("fr-FR", {
      day: "2-digit",
      month: "short",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    }).format(new Date(session.start_at));
  } catch {
    return session.start_at;
  }
}

export default function ClientDashboardPage() {
  const [reservations, setReservations] = useState<Reservation[]>([]);
  const [sessions, setSessions] = useState<VisioSession[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");
  const [calendarLoading, setCalendarLoading] = useState<number | null>(null);

  const user = useMemo(() => getCurrentUser(), []);

  const upcomingReservations = useMemo(() => {
    const now = new Date();
    return reservations
      .filter((item) => {
        if (!item.reservation_date) return true;
        const value = new Date(`${item.reservation_date}T${item.reservation_time || "00:00"}`);
        return value >= now && !["cancelled", "refunded"].includes(item.prestation_status || item.status || "");
      })
      .slice(0, 5);
  }, [reservations]);

  const upcomingVisios = useMemo(() => {
    const now = Date.now();
    const linkedSessionIds = new Set(
      reservations
        .filter(isReservationPaid)
        .map((item) => item.visio_session_id || item.visio_session?.id)
        .filter((value): value is number => Boolean(value))
        .map(Number)
    );

    return sessions
      .filter((item) => {
        const belongsToClient = Boolean(
          user && item.participants?.some((participant) =>
            Number(participant.user_id) === Number(user.id) &&
            participant.role === "participant" &&
            !["cancelled", "no_show"].includes(participant.status)
          )
        );

        return (linkedSessionIds.has(Number(item.id)) || belongsToClient) &&
          new Date(item.start_at).getTime() >= now &&
          !["ended", "cancelled"].includes(item.status);
      })
      .sort((a, b) => new Date(a.start_at).getTime() - new Date(b.start_at).getTime())
      .slice(0, 4);
  }, [sessions, reservations, user]);

  const stats = useMemo(() => ({
    total: reservations.length,
    paid: reservations.filter(isReservationPaid).length,
    upcoming: upcomingReservations.length,
    toConfirm: reservations.filter((item) => item.prestation_status === "pending_validation").length,
  }), [reservations, upcomingReservations.length]);

  async function loadDashboard() {
    try {
      setLoading(true);
      setError("");
      if (!getToken()) throw new Error("Veuillez vous connecter pour accéder à votre espace client.");
      if (user && !hasRole(user, "client")) throw new Error("Cet espace est réservé aux comptes clients.");

      const [reservationItems, visioItems] = await Promise.all([
        fetchClientReservations(),
        fetchVisioSessions(true).catch(() => []),
      ]);
      setReservations(reservationItems);
      setSessions(visioItems);
    } catch (err) {
      setError(getErrorMessage(err, "Impossible de charger le tableau de bord."));
    } finally {
      setLoading(false);
    }
  }


  async function handleCalendar(reservation: Reservation) {
    try {
      setCalendarLoading(reservation.id);
      setError("");
      setSuccess("");
      await downloadReservationCalendar(reservation);
      setSuccess("Le rendez-vous a été téléchargé au format calendrier (.ics).");
    } catch (err) {
      setError(getErrorMessage(err, "Impossible de préparer le calendrier."));
    } finally {
      setCalendarLoading(null);
    }
  }

  useEffect(() => {
    const timer = window.setTimeout(loadDashboard, 0);
    return () => window.clearTimeout(timer);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return (
    <>
      <Header />
      <main className="min-h-screen bg-[#FFF7ED] px-4 pb-16 pt-36 text-slate-950">
        <div className="mx-auto max-w-7xl">
          <section className="overflow-hidden rounded-[2.5rem] bg-slate-950 p-6 text-white shadow-2xl sm:p-10">
            <div className="grid gap-8 lg:grid-cols-[1fr_0.8fr] lg:items-end">
              <div>
                <span className="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-2 text-xs font-black uppercase tracking-[0.18em] text-orange-200">
                  <ShieldCheck size={16} /> Espace client
                </span>
                <h1 className="mt-5 text-4xl font-black tracking-tight sm:text-6xl">
                  Bonjour {user?.name?.split(" ")[0] || "client"}.
                </h1>
                <p className="mt-4 max-w-2xl text-base font-semibold leading-8 text-slate-300">
                  Retrouvez vos réservations, vos paiements, vos rendez-vous et vos accès visio depuis un seul espace.
                </p>
              </div>
              <div className="grid grid-cols-2 gap-3">
                {[
                  [stats.total, "Réservations"],
                  [stats.paid, "Payées"],
                  [stats.upcoming, "À venir"],
                  [stats.toConfirm, "À confirmer"],
                ].map(([value, label]) => (
                  <div key={String(label)} className="rounded-3xl bg-white/10 p-5 backdrop-blur">
                    <strong className="block text-3xl font-black">{value}</strong>
                    <span className="text-xs font-bold text-slate-300">{label}</span>
                  </div>
                ))}
              </div>
            </div>
          </section>

          <div className="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            {[
              ["/annonces", Sparkles, "Réserver", "Trouver une prestation"],
              ["/reservations", CreditCard, "Paiements", "Suivre mes réservations"],
              ["/planning", CalendarCheck, "Planning", "Voir mes rendez-vous"],
              ["/visio", Video, "Visio", "Rejoindre une séance"],
              ["/messages", MessageCircle, "Messages", "Contacter mon coach"],
            ].map(([href, Icon, title, subtitle]) => (
              <Link key={String(href)} href={String(href)} className="rounded-[1.5rem] bg-white p-5 shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
                <Icon className="mb-4 text-orange-600" size={24} />
                <strong className="block text-base font-black">{String(title)}</strong>
                <span className="mt-1 block text-xs font-semibold text-slate-500">{String(subtitle)}</span>
              </Link>
            ))}
          </div>

          {error && <div className="mt-6 flex gap-3 rounded-3xl border border-red-100 bg-red-50 p-5 text-sm font-bold text-red-700"><X size={18} />{error}</div>}

          {success && <div className="mt-6 flex gap-3 rounded-3xl border border-emerald-100 bg-emerald-50 p-5 text-sm font-bold text-emerald-700"><CheckCircle2 size={18} />{success}</div>}

          {loading ? (
            <div className="mt-6 flex items-center justify-center rounded-[2rem] bg-white py-16 font-black text-orange-700"><Loader2 className="mr-3 animate-spin" />Chargement de votre espace...</div>
          ) : (
            <div className="mt-6 grid gap-6 lg:grid-cols-[1fr_0.72fr]">
              <section className="rounded-[2rem] bg-white p-5 shadow-sm sm:p-7">
                <div className="mb-6 flex items-center justify-between gap-4">
                  <div><h2 className="text-2xl font-black">Prochains rendez-vous</h2><p className="mt-1 text-sm font-semibold text-slate-500">Vos prestations réservées et payées.</p></div>
                  <button onClick={loadDashboard} className="flex h-11 w-11 items-center justify-center rounded-full bg-orange-50 text-orange-700" aria-label="Actualiser"><RefreshCw size={18} /></button>
                </div>
                <div className="grid gap-4">
                  {upcomingReservations.length ? upcomingReservations.map((reservation) => (
                    <article key={reservation.id} className="rounded-3xl border border-orange-100 bg-orange-50 p-5">
                      <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                        <div>
                          <span className="rounded-full bg-white px-3 py-1 text-xs font-black text-orange-700">{statusLabel(reservation.prestation_status || reservation.status)}</span>
                          <h3 className="mt-3 text-lg font-black">{getAnnonceTitle(reservation.annonce)}</h3>
                          <div className="mt-3 flex flex-wrap gap-4 text-xs font-bold text-slate-500">
                            <span className="inline-flex items-center gap-2"><CalendarCheck size={15}/>{formatDate(reservation.reservation_date)}</span>
                            <span className="inline-flex items-center gap-2"><Clock3 size={15}/>{reservation.reservation_time || "À confirmer"}</span>
                            <span className="inline-flex items-center gap-2"><CreditCard size={15}/>{formatMoney(reservation.total_client_amount || reservation.price, reservation.currency || "EUR")}</span>
                          </div>
                        </div>
                        <div className="flex flex-wrap gap-2">
                          <button
                            type="button"
                            onClick={() => handleCalendar(reservation)}
                            disabled={!canAddReservationToCalendar(reservation) || calendarLoading === reservation.id}
                            className="inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-xs font-black text-orange-700 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400"
                          >
                            {calendarLoading === reservation.id ? <Loader2 className="animate-spin" size={15}/> : <CalendarCheck size={15}/>}
                            {canAddReservationToCalendar(reservation) ? "Calendrier" : "Après paiement"}
                          </button>
                          {reservation.payment_status === "paid" && Boolean(reservation.annonce?.is_online) && (
                            reservation.visio_session_id || reservation.visio_session?.id ? (
                              <Link href={getReservationVisioHref(reservation)} className="inline-flex items-center gap-2 rounded-full bg-slate-950 px-4 py-2 text-xs font-black text-white"><Video size={15}/>Accéder à la visio</Link>
                            ) : (
                              <span className="inline-flex items-center gap-2 rounded-full bg-slate-100 px-4 py-2 text-xs font-black text-slate-500"><Video size={15}/>Visio en préparation</span>
                            )
                          )}
                        </div>
                      </div>
                    </article>
                  )) : <div className="rounded-3xl bg-orange-50 p-8 text-center"><CalendarCheck className="mx-auto text-orange-500"/><p className="mt-3 font-bold text-slate-600">Aucun rendez-vous à venir.</p><Link href="/annonces" className="mt-4 inline-flex items-center gap-2 font-black text-orange-700">Trouver une prestation <ArrowRight size={16}/></Link></div>}
                </div>
              </section>

              <aside className="grid gap-6">
                <section className="rounded-[2rem] bg-white p-5 shadow-sm sm:p-7">
                  <h2 className="text-2xl font-black">Prochaines visios</h2>
                  <p className="mt-1 text-sm font-semibold text-slate-500">Accès disponible lorsque la séance est confirmée.</p>
                  <div className="mt-5 grid gap-3">
                    {upcomingVisios.length ? upcomingVisios.map((session) => (
                      <Link key={session.id} href={`/visio/${session.id}`} className="rounded-2xl border border-orange-100 bg-orange-50 p-4 transition hover:bg-orange-100">
                        <div className="flex items-start justify-between gap-3"><strong className="text-sm font-black">{session.title}</strong><Video size={18} className="text-orange-600"/></div>
                        <span className="mt-2 block text-xs font-bold text-slate-500">{dateTimeLabel(session)} · {session.duration_minutes} min</span>
                      </Link>
                    )) : <div className="rounded-2xl bg-orange-50 p-5 text-sm font-semibold text-slate-500">Aucune séance visio programmée.</div>}
                  </div>
                </section>

                <section className="rounded-[2rem] bg-gradient-to-br from-orange-600 to-orange-500 p-6 text-white shadow-lg">
                  <UserRound size={28}/><h2 className="mt-4 text-2xl font-black">Mon profil</h2><p className="mt-2 text-sm font-semibold leading-6 text-orange-50">Gardez vos coordonnées et votre questionnaire d’onboarding à jour.</p>
                  <div className="mt-5 flex flex-wrap gap-2"><Link href="/profile" className="rounded-full bg-white px-4 py-2 text-xs font-black text-orange-700">Modifier mon profil</Link><Link href="/onboarding" className="rounded-full bg-slate-950 px-4 py-2 text-xs font-black text-white">Mon onboarding</Link></div>
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
