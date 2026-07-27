"use client";

import Link from "next/link";
import { useEffect, useMemo, useState } from "react";
import { useParams, useRouter } from "next/navigation";
import {
  ArrowLeft,
  BadgeCheck,
  CalendarCheck,
  CheckCircle2,
  HeartPulse,
  Loader2,
  Mail,
  MapPin,
  MessageCircle,
  Phone,
  ShieldCheck,
  Star,
  UserRound,
  Video,
} from "lucide-react";

import Header from "@/components/layout/Header";
import Footer from "@/components/layout/Footer";
import { getToken } from "@/lib/auth";
import { API_BASE_URL } from "@/lib/api-config";
import {
  getCoachCertifications,
  getCoachDescription,
  getCoachExperience,
  getCoachLanguages,
  getCoachSpeciality,
  getCoachTitle,
  getIntervenantCover,
  getIntervenantPhoto,
  getIntervenantVideo,
  getLocation,
  getRating,
  getReviewsCount,
  getStatusLabel,
  getVideoDurationLabel,
  isPublicIntervenant,
  normalizeIntervenants,
  type ApiIntervenantsResponse,
  type Intervenant,
} from "@/lib/intervenants";

function buildAuthUrl(targetUrl: string) {
  return `/auth/login?redirect=${encodeURIComponent(targetUrl)}`;
}

function buildReservationUrl(intervenant: Intervenant) {
  return `/reservation?intervenant_id=${intervenant.id}`;
}

function buildMessageUrl(intervenant: Intervenant) {
  return `/messages?user_id=${intervenant.id}`;
}

function getProtectedUrl(targetUrl: string) {
  return getToken() ? targetUrl : buildAuthUrl(targetUrl);
}

async function fetchIntervenantById(id: string): Promise<Intervenant | null> {
  const token = getToken();

  const headers: HeadersInit = {
    Accept: "application/json",
  };

  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  try {
    const detailResponse = await fetch(`${API_BASE_URL}/intervenants/${id}`, {
      method: "GET",
      headers,
      cache: "no-store",
    });

    const detailPayload = (await detailResponse.json().catch(() => null)) as
      | ApiIntervenantsResponse
      | Intervenant[]
      | null;

    if (detailResponse.ok) {
      const items = normalizeIntervenants(detailPayload);
      const found = items.find((item) => String(item.id) === String(id));

      if (found && isPublicIntervenant(found)) {
        return found;
      }
    }
  } catch (error) {
    console.warn("Endpoint détail intervenant indisponible:", error);
  }

  const listResponse = await fetch(`${API_BASE_URL}/intervenants`, {
    method: "GET",
    headers,
    cache: "no-store",
  });

  const payload = (await listResponse.json().catch(() => null)) as
    | ApiIntervenantsResponse
    | Intervenant[]
    | null;

  if (!listResponse.ok) {
    throw new Error((!Array.isArray(payload) ? payload?.message : undefined) || `Erreur API intervenants : ${listResponse.status}`);
  }

  return (
    normalizeIntervenants(payload).find(
      (item) => String(item.id) === String(id) && isPublicIntervenant(item)
    ) || null
  );
}

function InfoPill({ children }: { children: React.ReactNode }) {
  return (
    <span className="inline-flex items-center gap-2 rounded-full bg-orange-50 px-3 py-1.5 text-xs font-black text-orange-700">
      {children}
    </span>
  );
}

export default function IntervenantDetailPage() {
  const params = useParams();
  const router = useRouter();

  const id = useMemo(() => {
    const rawId = params?.id;

    if (Array.isArray(rawId)) {
      return rawId[0];
    }

    return rawId || "";
  }, [params]);

  const [intervenant, setIntervenant] = useState<Intervenant | null>(null);
  const [loading, setLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState("");

  useEffect(() => {
    if (!id) return;

    let mounted = true;

    async function loadIntervenant() {
      try {
        setLoading(true);
        setErrorMessage("");

        const item = await fetchIntervenantById(String(id));

        if (!mounted) return;

        if (!item) {
          setIntervenant(null);
          setErrorMessage("Cet intervenant est introuvable ou non disponible.");
          return;
        }

        setIntervenant(item);
      } catch (error) {
        console.error("Erreur chargement détail intervenant:", error);

        if (mounted) {
          setIntervenant(null);
          setErrorMessage(
            error instanceof Error
              ? error.message
              : "Impossible de charger le profil de cet intervenant pour le moment."
          );
        }
      } finally {
        if (mounted) {
          setLoading(false);
        }
      }
    }

    loadIntervenant();

    return () => {
      mounted = false;
    };
  }, [id]);

  const photo = intervenant ? getIntervenantPhoto(intervenant) : "";
  const cover = intervenant ? getIntervenantCover(intervenant) : "";
  const video = intervenant ? getIntervenantVideo(intervenant) : "";
  const speciality = intervenant ? getCoachSpeciality(intervenant) : "";
  const coachTitle = intervenant ? getCoachTitle(intervenant) : "";
  const description = intervenant ? getCoachDescription(intervenant) : "";
  const location = intervenant ? getLocation(intervenant) : "";
  const rating = intervenant ? getRating(intervenant) : 0;
  const reviewsCount = intervenant ? getReviewsCount(intervenant) : 0;
  const experience = intervenant ? getCoachExperience(intervenant) : null;
  const certifications = intervenant ? getCoachCertifications(intervenant) : [];
  const languages = intervenant ? getCoachLanguages(intervenant) : [];
  const reservationUrl = intervenant ? buildReservationUrl(intervenant) : "/intervenants";
  const messageUrl = intervenant ? buildMessageUrl(intervenant) : "/intervenants";

  return (
    <>
      <Header />

      <main className="min-h-screen bg-[#FFF7ED] pt-28 text-slate-950 sm:pt-32">
        <section className="px-4 pb-20">
          <div className="mx-auto max-w-7xl">
            <button
              type="button"
              onClick={() => router.back()}
              className="mb-6 inline-flex items-center gap-2 rounded-full border border-orange-100 bg-white px-5 py-3 text-sm font-black text-orange-700 shadow-sm transition hover:bg-orange-50"
            >
              <ArrowLeft size={18} />
              Retour
            </button>

            {loading ? (
              <div className="flex min-h-[420px] items-center justify-center rounded-[2.5rem] bg-white shadow-sm">
                <div className="inline-flex items-center gap-3 rounded-3xl bg-orange-50 px-6 py-5 text-sm font-black text-orange-700">
                  <Loader2 className="animate-spin" size={20} />
                  Chargement du profil intervenant...
                </div>
              </div>
            ) : errorMessage || !intervenant ? (
              <div className="rounded-[2.5rem] bg-white p-10 text-center shadow-sm">
                <div className="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-2xl bg-orange-100 text-orange-700">
                  <UserRound size={30} />
                </div>

                <h1 className="text-3xl font-black tracking-tight">
                  Profil introuvable
                </h1>

                <p className="mx-auto mt-3 max-w-md text-sm font-semibold leading-7 text-slate-500">
                  {errorMessage ||
                    "Le profil demandé n’existe pas ou n’est plus disponible."}
                </p>

                <Link
                  href="/intervenants"
                  className="mt-6 inline-flex items-center justify-center rounded-full bg-orange-600 px-6 py-3 text-sm font-black text-white shadow-lg shadow-orange-600/20 transition hover:bg-orange-700"
                >
                  Voir tous les intervenants
                </Link>
              </div>
            ) : (
              <div className="overflow-hidden rounded-[2.5rem] border border-orange-100 bg-white shadow-[0_24px_90px_rgba(249,115,22,0.12)]">
                <div className="relative h-72 bg-gradient-to-br from-orange-100 via-orange-200 to-orange-500 sm:h-96">
                  {cover ? (
                    <img
                      src={cover}
                      alt={`Couverture ${intervenant.name || "intervenant"}`}
                      className="h-full w-full object-cover"
                    />
                  ) : (
                    <>
                      <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(255,255,255,0.9),transparent_34%)]" />
                      <div className="absolute -left-20 top-10 h-72 w-72 rounded-full bg-white/30 blur-3xl" />
                      <div className="absolute -right-20 bottom-0 h-80 w-80 rounded-full bg-orange-700/20 blur-3xl" />
                    </>
                  )}

                  <div className="absolute inset-0 bg-gradient-to-t from-slate-950/70 via-slate-950/10 to-transparent" />

                  <div className="absolute bottom-6 left-5 right-5 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div className="flex flex-col gap-5 sm:flex-row sm:items-end">
                      <div className="h-32 w-32 overflow-hidden rounded-[2rem] border-4 border-white bg-orange-100 shadow-xl">
                        {photo ? (
                          <img
                            src={photo}
                            alt={intervenant.name || "Intervenant Gotfit"}
                            className="h-full w-full object-cover"
                          />
                        ) : (
                          <div className="flex h-full w-full items-center justify-center text-orange-700">
                            <UserRound size={54} />
                          </div>
                        )}
                      </div>

                      <div className="text-white">
                        <div className="mb-3 flex flex-wrap gap-2">
                          <span className="inline-flex items-center gap-2 rounded-full bg-white/90 px-4 py-2 text-xs font-black uppercase tracking-[0.14em] text-orange-700 shadow-sm backdrop-blur">
                            <BadgeCheck size={15} />
                            {speciality}
                          </span>

                          <span className="inline-flex items-center gap-2 rounded-full bg-slate-950/90 px-4 py-2 text-xs font-black text-white shadow-sm backdrop-blur">
                            <Star size={15} className="fill-white" />
                            {rating ? `${rating.toFixed(1)} / 5` : "Nouveau"}
                          </span>
                        </div>

                        <h1 className="text-4xl font-black tracking-tight sm:text-6xl">
                          {intervenant.name || "Intervenant Gotfit"}
                        </h1>

                        <p className="mt-3 text-base font-bold text-white/80">
                          {coachTitle}
                        </p>
                      </div>
                    </div>

                    <div className="flex flex-wrap gap-3">
                      <Link
                        href={getProtectedUrl(reservationUrl)}
                        className="inline-flex items-center justify-center gap-2 rounded-full bg-orange-600 px-6 py-4 text-sm font-black text-white shadow-lg shadow-orange-600/20 transition hover:bg-orange-700"
                      >
                        Réserver
                        <CalendarCheck size={18} />
                      </Link>

                      <Link
                        href={getProtectedUrl(messageUrl)}
                        className="inline-flex items-center justify-center gap-2 rounded-full bg-white px-6 py-4 text-sm font-black text-slate-950 transition hover:bg-orange-50"
                      >
                        Contacter
                        <MessageCircle size={18} />
                      </Link>
                    </div>
                  </div>
                </div>

                <div className="grid gap-8 p-5 sm:p-8 lg:grid-cols-[0.9fr_1.6fr] lg:p-10">
                  <aside className="relative">
                    <div className="rounded-[2rem] border border-orange-100 bg-white p-5 shadow-xl lg:sticky lg:top-28">
                      <div className="text-center">
                        <p className="text-sm font-black uppercase tracking-[0.16em] text-orange-600">
                          {speciality}
                        </p>
                        <h2 className="mt-2 text-3xl font-black tracking-tight text-slate-950">
                          {coachTitle}
                        </h2>
                      </div>

                      <div className="mt-5 grid gap-3">
                        <div className="flex items-center gap-3 rounded-2xl bg-orange-50 p-4">
                          <MapPin className="shrink-0 text-orange-600" size={20} />
                          <span className="text-sm font-bold text-slate-600">
                            {location}
                          </span>
                        </div>

                        {intervenant.phone && (
                          <a
                            href={`tel:${intervenant.phone}`}
                            className="flex items-center gap-3 rounded-2xl bg-orange-50 p-4 transition hover:bg-orange-100"
                          >
                            <Phone className="shrink-0 text-orange-600" size={20} />
                            <span className="text-sm font-bold text-slate-600">
                              {intervenant.phone}
                            </span>
                          </a>
                        )}

                        {intervenant.email && (
                          <a
                            href={`mailto:${intervenant.email}`}
                            className="flex items-center gap-3 rounded-2xl bg-orange-50 p-4 transition hover:bg-orange-100"
                          >
                            <Mail className="shrink-0 text-orange-600" size={20} />
                            <span className="break-all text-sm font-bold text-slate-600">
                              {intervenant.email}
                            </span>
                          </a>
                        )}
                      </div>

                      <div className="mt-5 grid grid-cols-2 gap-3">
                        <Link
                          href={getProtectedUrl(reservationUrl)}
                          className="inline-flex items-center justify-center gap-2 rounded-2xl bg-orange-600 px-4 py-3 text-sm font-black text-white shadow-lg shadow-orange-600/20 transition hover:bg-orange-700"
                        >
                          Réserver
                          <CalendarCheck size={17} />
                        </Link>

                        <Link
                          href={getProtectedUrl(messageUrl)}
                          className="inline-flex items-center justify-center gap-2 rounded-2xl bg-slate-950 px-4 py-3 text-sm font-black text-white transition hover:bg-slate-800"
                        >
                          Contacter
                          <MessageCircle size={17} />
                        </Link>
                      </div>
                    </div>
                  </aside>

                  <section>
                    <div className="grid gap-4 sm:grid-cols-3">
                      <div className="rounded-3xl bg-orange-50 p-5">
                        <div className="mb-3 flex h-11 w-11 items-center justify-center rounded-2xl bg-white text-orange-600">
                          <Star size={22} className="fill-orange-500" />
                        </div>

                        <strong className="block text-3xl font-black">
                          {rating ? rating.toFixed(1) : "Nouveau"}
                        </strong>

                        <span className="mt-1 block text-sm font-bold text-slate-500">
                          {reviewsCount} avis client
                        </span>
                      </div>

                      <div className="rounded-3xl bg-orange-50 p-5">
                        <div className="mb-3 flex h-11 w-11 items-center justify-center rounded-2xl bg-white text-orange-600">
                          <HeartPulse size={22} />
                        </div>

                        <strong className="block text-2xl font-black">
                          {experience || "À compléter"}
                        </strong>

                        <span className="mt-1 block text-sm font-bold text-slate-500">
                          Expérience
                        </span>
                      </div>

                      <div className="rounded-3xl bg-orange-50 p-5">
                        <div className="mb-3 flex h-11 w-11 items-center justify-center rounded-2xl bg-white text-orange-600">
                          <ShieldCheck size={22} />
                        </div>

                        <strong className="block text-xl font-black">
                          Profil
                        </strong>

                        <span className="mt-2 inline-flex items-center gap-1 rounded-full bg-white px-3 py-1 text-xs font-black text-orange-700">
                          <CheckCircle2 size={14} />
                          {getStatusLabel(intervenant.account_status)}
                        </span>
                      </div>
                    </div>

                    <div className="mt-6 rounded-[2rem] border border-orange-100 bg-white p-6 shadow-sm sm:p-8">
                      <span className="mb-4 inline-flex rounded-full bg-orange-50 px-4 py-2 text-xs font-black uppercase tracking-[0.16em] text-orange-700">
                        Présentation
                      </span>

                      <h2 className="text-3xl font-black tracking-tight">
                        À propos de {intervenant.name || "cet intervenant"}
                      </h2>

                      <p className="mt-5 whitespace-pre-line text-base font-semibold leading-8 text-slate-600">
                        {description}
                      </p>

                      <div className="mt-5 flex flex-wrap gap-2">
                        <InfoPill>
                          <BadgeCheck size={14} />
                          {speciality}
                        </InfoPill>

                        {experience && (
                          <InfoPill>
                            <HeartPulse size={14} />
                            {experience}
                          </InfoPill>
                        )}

                        {video && (
                          <InfoPill>
                            <Video size={14} />
                            Vidéo disponible
                          </InfoPill>
                        )}
                      </div>
                    </div>

                    {video && (
                      <div className="mt-6 overflow-hidden rounded-[2rem] border border-orange-100 bg-slate-950 p-4 shadow-sm">
                        <div className="mb-3 flex flex-wrap items-center justify-between gap-2 px-2 text-white">
                          <span className="inline-flex items-center gap-2 text-sm font-black">
                            <Video size={18} />
                            Vidéo de présentation
                          </span>
                          <span className="rounded-full bg-white/10 px-3 py-1 text-xs font-black text-white/70">
                            {getVideoDurationLabel(intervenant)}
                          </span>
                        </div>

                        <video
                          controls
                          src={video}
                          className="aspect-video w-full rounded-[1.5rem] bg-black object-cover"
                        />
                      </div>
                    )}

                    <div className="mt-6 grid gap-6 lg:grid-cols-2">
                      <div className="rounded-[2rem] border border-orange-100 bg-orange-50 p-6 sm:p-8">
                        <span className="mb-4 inline-flex rounded-full bg-white px-4 py-2 text-xs font-black uppercase tracking-[0.16em] text-orange-700">
                          Certifications
                        </span>

                        <div className="flex flex-wrap gap-2">
                          {certifications.length ? (
                            certifications.map((item) => (
                              <span
                                key={item}
                                className="rounded-full bg-white px-3 py-1.5 text-xs font-black text-slate-700"
                              >
                                {item}
                              </span>
                            ))
                          ) : (
                            <p className="text-sm font-semibold leading-7 text-slate-600">
                              Certifications à compléter dans le profil intervenant.
                            </p>
                          )}
                        </div>
                      </div>

                      <div className="rounded-[2rem] border border-orange-100 bg-orange-50 p-6 sm:p-8">
                        <span className="mb-4 inline-flex rounded-full bg-white px-4 py-2 text-xs font-black uppercase tracking-[0.16em] text-orange-700">
                          Langues
                        </span>

                        <div className="flex flex-wrap gap-2">
                          {languages.length ? (
                            languages.map((item) => (
                              <span
                                key={item}
                                className="rounded-full bg-white px-3 py-1.5 text-xs font-black text-slate-700"
                              >
                                {item}
                              </span>
                            ))
                          ) : (
                            <p className="text-sm font-semibold leading-7 text-slate-600">
                              Langues à compléter dans le profil intervenant.
                            </p>
                          )}
                        </div>
                      </div>
                    </div>
                  </section>
                </div>
              </div>
            )}
          </div>
        </section>
      </main>

      <Footer />
    </>
  );
}
