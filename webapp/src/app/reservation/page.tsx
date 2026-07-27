"use client";

import Link from "next/link";
import { FormEvent, Suspense, useEffect, useMemo, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import {
  Elements,
  PaymentElement,
  useElements,
  useStripe,
} from "@stripe/react-stripe-js";
import { loadStripe } from "@stripe/stripe-js";
import type { StripeElementsOptions } from "@stripe/stripe-js";
import {
  ArrowLeft,
  CalendarDays,
  Clock3,
  CreditCard,
  Loader2,
  MapPin,
  ShieldCheck,
  Sparkles,
  UserRound,
  Users,
  X,
} from "lucide-react";

import Header from "@/components/layout/Header";
import Footer from "@/components/layout/Footer";
import { getToken } from "@/lib/auth";
import {
  Annonce,
  PaymentIntentPayload,
  Reservation,
  createPaymentIntent,
  fetchAnnonce,
  fetchAnnonces,
  formatMoney,
  getAnnonceDescription,
  getAnnonceTitle,
  reserveAnnonce,
} from "@/lib/marketplace";

const stripePromise = process.env.NEXT_PUBLIC_STRIPE_KEY
  ? loadStripe(process.env.NEXT_PUBLIC_STRIPE_KEY)
  : null;

function getToday() {
  const now = new Date();
  const year = now.getFullYear();
  const month = String(now.getMonth() + 1).padStart(2, "0");
  const day = String(now.getDate()).padStart(2, "0");

  return `${year}-${month}-${day}`;
}

function getErrorMessage(error: unknown, fallback: string) {
  return error instanceof Error ? error.message : fallback;
}

function getCoachName(annonce?: Annonce | null) {
  return annonce?.intervenant?.name || annonce?.user?.name || "Intervenant Gotfit";
}

function getLocation(annonce?: Annonce | null) {
  if (annonce?.is_online) return "Séance en ligne";

  return annonce?.city || annonce?.location || annonce?.address || "Lieu à confirmer";
}

function getAnnonceOwnerId(annonce: Annonce) {
  return annonce.intervenant?.id || annonce.user?.id || annonce.user_id || null;
}

function isVisibleAnnonce(annonce: Annonce) {
  const status = annonce.status?.toLowerCase();

  return !status || ["valide", "approved", "active", "published"].includes(status);
}

function getPaymentReturnUrl(reservationId: number) {
  const origin = window.location.origin;
  const basePath = window.location.pathname.startsWith("/webapp") ? "/webapp" : "";

  return `${origin}${basePath}/reservations?payment=success&reservation=${reservationId}`;
}

function CheckoutForm({
  reservation,
  payment,
}: {
  reservation: Reservation;
  payment: PaymentIntentPayload;
}) {
  const stripe = useStripe();
  const elements = useElements();

  const [processing, setProcessing] = useState(false);
  const [elementReady, setElementReady] = useState(false);
  const [paymentComplete, setPaymentComplete] = useState(false);
  const [error, setError] = useState("");

  async function handlePayment(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (processing) return;

    if (!stripe || !elements) {
      setError("Stripe n'est pas encore chargé. Veuillez patienter quelques secondes.");
      return;
    }

    if (!elementReady) {
      setError("Le formulaire de paiement est encore en chargement. Veuillez patienter.");
      return;
    }

    if (!paymentComplete) {
      setError("Veuillez compléter les informations de paiement avant de confirmer.");
      return;
    }

    try {
      setProcessing(true);
      setError("");

      const submitResult = await elements.submit();

      if (submitResult.error) {
        setError(
          submitResult.error.message ||
            "Veuillez vérifier les informations de paiement."
        );
        setProcessing(false);
        return;
      }

      const result = await stripe.confirmPayment({
        elements,
        confirmParams: {
          return_url: getPaymentReturnUrl(reservation.id),
        },
      });

      if (result.error) {
        setError(result.error.message || "Paiement refusé.");
        setProcessing(false);
        return;
      }

      setError("");
    } catch (err) {
      setError(getErrorMessage(err, "Impossible de finaliser le paiement."));
      setProcessing(false);
    }
  }

  const canPay = Boolean(
    stripe && elements && elementReady && paymentComplete && !processing
  );

  return (
    <form onSubmit={handlePayment} className="grid gap-5">
      <div className="rounded-[1.5rem] border border-orange-100 bg-orange-50 p-4">
        <div className="flex items-start justify-between gap-4">
          <div>
            <span className="text-xs font-black uppercase tracking-[0.16em] text-orange-700">
              Montant à payer
            </span>
            <strong className="mt-1 block text-3xl font-black text-slate-950">
              {formatMoney(payment.amount, payment.currency)}
            </strong>
          </div>
          <CreditCard className="text-orange-700" size={28} />
        </div>
      </div>

      <div className="rounded-[1.5rem] border border-slate-200 bg-white p-4">
        {!elementReady && (
          <div className="mb-3 flex items-center gap-2 rounded-2xl bg-slate-50 px-4 py-3 text-xs font-black text-slate-500">
            <Loader2 className="animate-spin" size={15} />
            Chargement sécurisé du paiement...
          </div>
        )}

        <PaymentElement
          onReady={() => {
            setElementReady(true);
            setError("");
          }}
          onChange={(event) => {
            setPaymentComplete(event.complete);

            setError((currentError) =>
              currentError ===
              "Le formulaire de paiement est encore en chargement. Veuillez patienter."
                ? ""
                : currentError
            );
          }}
          options={{
            layout: "tabs",
          }}
        />
      </div>

      {error && (
        <div className="rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm font-bold text-red-700">
          {error}
        </div>
      )}

      {!paymentComplete && elementReady && !error && (
        <div className="rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3 text-xs font-bold leading-5 text-slate-500">
          Remplissez les informations de paiement avant de confirmer.
        </div>
      )}

      <button
        type="submit"
        disabled={!canPay}
        className="inline-flex items-center justify-center gap-2 rounded-full bg-slate-950 px-6 py-4 text-sm font-black text-white shadow-lg transition hover:-translate-y-0.5 hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
      >
        {processing ? <Loader2 className="animate-spin" size={18} /> : null}
        {processing ? "Paiement en cours..." : "Payer maintenant"}
      </button>
    </form>
  );
}

function ReservationContent() {
  const router = useRouter();
  const searchParams = useSearchParams();

  const intervenantId = searchParams.get("intervenant_id");
  const annonceId = searchParams.get("annonce_id");

  const [annonces, setAnnonces] = useState<Annonce[]>([]);
  const [selectedAnnonceId, setSelectedAnnonceId] = useState("");
  const [reservation, setReservation] = useState<Reservation | null>(null);
  const [payment, setPayment] = useState<PaymentIntentPayload | null>(null);
  const [reservationDate, setReservationDate] = useState(getToday());
  const [reservationTime, setReservationTime] = useState("10:00");
  const [guests, setGuests] = useState(1);
  const [note, setNote] = useState("");
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState("");

  const selectedAnnonce = useMemo(() => {
    return annonces.find((item) => String(item.id) === selectedAnnonceId) || null;
  }, [annonces, selectedAnnonceId]);

  const stripeOptions = useMemo<StripeElementsOptions | undefined>(() => {
    if (!payment?.clientSecret) return undefined;

    return {
      clientSecret: payment.clientSecret,
      loader: "auto",
      appearance: {
        theme: "stripe",
        variables: {
          colorPrimary: "#ea580c",
          borderRadius: "16px",
        },
      },
    };
  }, [payment?.clientSecret]);

  useEffect(() => {
    const timer = window.setTimeout(async () => {
      try {
        setLoading(true);
        setError("");

        if (!getToken()) {
          const target = `/reservation${window.location.search}`;
          router.push(`/auth/login?redirect=${encodeURIComponent(target)}`);
          return;
        }

        if (annonceId) {
          const annonce = await fetchAnnonce(annonceId);
          setAnnonces([annonce]);
          setSelectedAnnonceId(String(annonce.id));
          return;
        }

        if (!intervenantId) {
          setError("Aucun intervenant ou annonce n'a été sélectionné.");
          return;
        }

        const items = (await fetchAnnonces())
          .filter(isVisibleAnnonce)
          .filter((annonce) => String(getAnnonceOwnerId(annonce)) === intervenantId);

        setAnnonces(items);
        setSelectedAnnonceId(items[0]?.id ? String(items[0].id) : "");

        if (!items.length) {
          setError(
            "Cet intervenant n'a pas encore d'annonce réservable. Vous pouvez le contacter par message."
          );
        }
      } catch (err) {
        setError(getErrorMessage(err, "Impossible de charger la réservation."));
      } finally {
        setLoading(false);
      }
    }, 0);

    return () => window.clearTimeout(timer);
  }, [annonceId, intervenantId, router]);

  async function handleReserve(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (!selectedAnnonce) {
      setError("Veuillez choisir une prestation avant de réserver.");
      return;
    }

    if (submitting) return;

    try {
      setSubmitting(true);
      setError("");
      setReservation(null);
      setPayment(null);

      const createdReservation = await reserveAnnonce(selectedAnnonce.id, {
        reservation_date: reservationDate,
        reservation_time: reservationTime,
        guests,
        note: note.trim(),
      });

      const intent = await createPaymentIntent(createdReservation.id);

      if (!intent?.clientSecret) {
        throw new Error("Le client_secret Stripe est manquant dans la réponse API.");
      }

      setReservation(createdReservation);
      setPayment(intent);
    } catch (err) {
      setError(
        getErrorMessage(
          err,
          "Impossible de créer la réservation ou le paiement."
        )
      );
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <>
      <Header />

      <main className="min-h-screen bg-[#FFF7ED] px-4 pb-16 pt-32 text-slate-950 sm:pt-36">
        <div className="mx-auto max-w-7xl">
          <Link
            href={intervenantId ? `/intervenants/${intervenantId}` : "/intervenants"}
            className="mb-6 inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-black text-orange-700 shadow-sm transition hover:bg-orange-50"
          >
            <ArrowLeft size={17} />
            Retour
          </Link>

          <section className="mb-8 grid gap-6 lg:grid-cols-[1fr_0.85fr] lg:items-end">
            <div>
              <span className="mb-5 inline-flex items-center gap-2 rounded-full border border-orange-200 bg-white px-4 py-2 text-xs font-black uppercase tracking-[0.18em] text-orange-700 shadow-sm">
                <ShieldCheck size={16} />
                Réservation sécurisée
              </span>
              <h1 className="max-w-3xl text-4xl font-black leading-tight tracking-tight sm:text-6xl">
                Réserver une prestation.
              </h1>
              <p className="mt-5 max-w-2xl text-base font-medium leading-8 text-slate-600">
                Choisissez la prestation, la date et l&apos;heure. Le paiement Stripe
                est initialisé juste après la création de la réservation.
              </p>
            </div>

            <div className="rounded-[2rem] bg-white p-6 shadow-sm">
              <Sparkles className="mb-4 text-orange-700" size={28} />
              <strong className="block text-xl font-black">
                {selectedAnnonce ? getCoachName(selectedAnnonce) : "Gotfit"}
              </strong>
              <p className="mt-2 text-sm font-semibold leading-7 text-slate-500">
                Paiement protégé, suivi de réservation et validation de la
                prestation depuis votre espace client.
              </p>
            </div>
          </section>

          {loading ? (
            <div className="flex items-center justify-center rounded-[2rem] bg-white py-16 text-sm font-black text-orange-700 shadow-sm">
              <Loader2 className="mr-3 animate-spin" size={20} />
              Chargement des prestations...
            </div>
          ) : (
            <div className="grid gap-7 lg:grid-cols-[1fr_430px]">
              <section className="rounded-[2.5rem] bg-white p-6 shadow-[0_24px_80px_rgba(249,115,22,0.14)] sm:p-8">
                {error && (
                  <div className="mb-6 flex items-start gap-3 rounded-[2rem] border border-red-100 bg-red-50 px-5 py-4 text-sm font-bold text-red-700">
                    <X className="mt-0.5 shrink-0" size={18} />
                    <span>{error}</span>
                  </div>
                )}

                {annonces.length > 0 ? (
                  <>
                    <h2 className="text-2xl font-black tracking-tight">
                      Choisir la prestation
                    </h2>

                    <div className="mt-5 grid gap-4">
                      {annonces.map((annonce) => {
                        const active = String(annonce.id) === selectedAnnonceId;

                        return (
                          <button
                            type="button"
                            key={annonce.id}
                            onClick={() => {
                              setSelectedAnnonceId(String(annonce.id));
                              setPayment(null);
                              setReservation(null);
                            }}
                            className={`rounded-[1.5rem] border p-5 text-left transition ${
                              active
                                ? "border-orange-500 bg-orange-50 shadow-lg shadow-orange-600/10"
                                : "border-slate-100 bg-white hover:border-orange-200 hover:bg-orange-50/50"
                            }`}
                          >
                            <div className="flex flex-wrap items-start justify-between gap-4">
                              <div>
                                <strong className="block text-lg font-black text-slate-950">
                                  {getAnnonceTitle(annonce)}
                                </strong>
                                <p className="mt-2 line-clamp-2 text-sm font-semibold leading-7 text-slate-500">
                                  {getAnnonceDescription(annonce) ||
                                    "Prestation proposée par un intervenant Gotfit."}
                                </p>
                              </div>
                              <span className="rounded-full bg-slate-950 px-4 py-2 text-sm font-black text-white">
                                {formatMoney(annonce.price)}
                              </span>
                            </div>

                            <div className="mt-4 flex flex-wrap gap-2 text-xs font-bold text-slate-500">
                              <span className="inline-flex items-center gap-2 rounded-full bg-white px-3 py-2">
                                <UserRound size={14} />
                                {getCoachName(annonce)}
                              </span>
                              <span className="inline-flex items-center gap-2 rounded-full bg-white px-3 py-2">
                                <MapPin size={14} />
                                {getLocation(annonce)}
                              </span>
                              <span className="inline-flex items-center gap-2 rounded-full bg-white px-3 py-2">
                                <Clock3 size={14} />
                                {annonce.duration || 60} min
                              </span>
                            </div>
                          </button>
                        );
                      })}
                    </div>

                    <div className="mt-8 grid gap-4 sm:grid-cols-3">
                      <div className="rounded-[1.5rem] bg-orange-50 p-5">
                        <ShieldCheck className="mb-3 text-orange-700" size={24} />
                        <strong className="block text-sm font-black">
                          Paiement protégé
                        </strong>
                      </div>
                      <div className="rounded-[1.5rem] bg-orange-50 p-5">
                        <Users className="mb-3 text-orange-700" size={24} />
                        <strong className="block text-sm font-black">
                          Suivi client
                        </strong>
                      </div>
                      <div className="rounded-[1.5rem] bg-orange-50 p-5">
                        <CreditCard className="mb-3 text-orange-700" size={24} />
                        <strong className="block text-sm font-black">
                          Stripe sécurisé
                        </strong>
                      </div>
                    </div>
                  </>
                ) : (
                  <div className="rounded-[2rem] bg-orange-50 p-8 text-center">
                    <Sparkles className="mx-auto mb-4 text-orange-700" size={40} />
                    <h2 className="text-2xl font-black">Aucune prestation</h2>
                    <p className="mx-auto mt-3 max-w-md text-sm font-semibold leading-7 text-slate-500">
                      Cet intervenant n&apos;a pas encore publié d&apos;annonce réservable.
                    </p>
                    {intervenantId && (
                      <Link
                        href={`/messages?user_id=${intervenantId}`}
                        className="mt-6 inline-flex rounded-full bg-slate-950 px-6 py-3 text-sm font-black text-white transition hover:bg-slate-800"
                      >
                        Contacter l&apos;intervenant
                      </Link>
                    )}
                  </div>
                )}
              </section>

              <aside className="h-fit rounded-[2.5rem] bg-white p-5 shadow-[0_24px_80px_rgba(249,115,22,0.14)] sm:p-6 lg:sticky lg:top-28">
                <div className="mb-5 rounded-[1.7rem] bg-slate-950 p-5 text-white">
                  <span className="text-xs font-black uppercase tracking-[0.16em] text-orange-200">
                    Total estimé
                  </span>
                  <strong className="mt-1 block text-4xl font-black">
                    {formatMoney(selectedAnnonce?.price)}
                  </strong>
                  <p className="mt-2 text-xs font-semibold leading-5 text-white/55">
                    Les frais de service exacts sont calculés par l&apos;API au moment
                    de la réservation.
                  </p>
                </div>

                {!payment && (
                  <form onSubmit={handleReserve} className="grid gap-4">
                    <div>
                      <label className="mb-2 block text-sm font-black text-slate-700">
                        Date
                      </label>
                      <div className="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <CalendarDays size={18} className="text-slate-400" />
                        <input
                          type="date"
                          min={getToday()}
                          value={reservationDate}
                          onChange={(event) =>
                            setReservationDate(event.target.value)
                          }
                          className="w-full bg-transparent text-sm font-semibold outline-none"
                          required
                        />
                      </div>
                    </div>

                    <div>
                      <label className="mb-2 block text-sm font-black text-slate-700">
                        Heure
                      </label>
                      <div className="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <Clock3 size={18} className="text-slate-400" />
                        <input
                          type="time"
                          value={reservationTime}
                          onChange={(event) =>
                            setReservationTime(event.target.value)
                          }
                          className="w-full bg-transparent text-sm font-semibold outline-none"
                          required
                        />
                      </div>
                    </div>

                    <div>
                      <label className="mb-2 block text-sm font-black text-slate-700">
                        Nombre de personne(s)
                      </label>
                      <input
                        type="number"
                        min={1}
                        max={20}
                        value={guests}
                        onChange={(event) =>
                          setGuests(Math.max(1, Number(event.target.value) || 1))
                        }
                        className="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold outline-none focus:border-orange-400"
                      />
                    </div>

                    <div>
                      <label className="mb-2 block text-sm font-black text-slate-700">
                        Note optionnelle
                      </label>
                      <textarea
                        value={note}
                        onChange={(event) => setNote(event.target.value)}
                        rows={4}
                        placeholder="Objectif, contrainte, adresse exacte..."
                        className="w-full resize-none rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold outline-none focus:border-orange-400"
                      />
                    </div>

                    <button
                      type="submit"
                      disabled={submitting || !selectedAnnonce}
                      className="inline-flex items-center justify-center gap-2 rounded-full bg-orange-600 px-6 py-4 text-sm font-black text-white shadow-lg shadow-orange-600/20 transition hover:-translate-y-0.5 hover:bg-orange-700 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                      {submitting ? (
                        <Loader2 className="animate-spin" size={18} />
                      ) : (
                        <CreditCard size={18} />
                      )}
                      {submitting ? "Création en cours..." : "Réserver et payer"}
                    </button>
                  </form>
                )}

                {payment && reservation && (
                  <div className="grid gap-4">
                    <div className="rounded-2xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700">
                      Réservation créée. Finalisez le paiement pour confirmer.
                    </div>

                    {stripePromise && stripeOptions ? (
                      <Elements
                        key={payment.clientSecret}
                        stripe={stripePromise}
                        options={stripeOptions}
                      >
                        <CheckoutForm
                          key={`${reservation.id}-${payment.clientSecret}`}
                          reservation={reservation}
                          payment={payment}
                        />
                      </Elements>
                    ) : (
                      <div className="rounded-2xl border border-amber-100 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-700">
                        Clé Stripe manquante ou client_secret absent. Vérifiez
                        NEXT_PUBLIC_STRIPE_KEY côté Next.js et la réponse API de
                        création du paiement.
                      </div>
                    )}
                  </div>
                )}
              </aside>
            </div>
          )}
        </div>
      </main>

      <Footer />
    </>
  );
}

export default function ReservationPage() {
  return (
    <Suspense
      fallback={
        <>
          <Header />
          <main className="flex min-h-screen items-center justify-center bg-[#FFF7ED] px-4 pt-28 text-slate-950">
            <div className="inline-flex items-center gap-3 rounded-3xl bg-white px-6 py-5 text-sm font-black text-orange-700 shadow-sm">
              <Loader2 className="animate-spin" size={20} />
              Chargement de la réservation...
            </div>
          </main>
        </>
      }
    >
      <ReservationContent />
    </Suspense>
  );
}
