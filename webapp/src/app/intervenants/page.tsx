"use client";

import Link from "next/link";
import { useEffect, useMemo, useState } from "react";
import {
  ArrowRight,
  BadgeCheck,
  CalendarCheck,
  CheckCircle2,
  ChevronDown,
  HeartPulse,
  Loader2,
  MapPin,
  MessageCircle,
  Search,
  ShieldCheck,
  SlidersHorizontal,
  Sparkles,
  Star,
  UserRound,
  UsersRound,
  Video,
  X,
} from "lucide-react";

import Header from "@/components/layout/Header";
import Footer from "@/components/layout/Footer";
import { getToken } from "@/lib/auth";
import { API_BASE_URL } from "@/lib/api-config";
import {
  getCoachDescription,
  getCoachExperience,
  getCoachSpeciality,
  getIntervenantPhoto,
  getIntervenantVideo,
  getLocation,
  getRating,
  getReviewsCount,
  getSpecialityOptions,
  getStatusLabel,
  isPublicIntervenant,
  normalizeIntervenants,
  type ApiIntervenantsResponse,
  type Intervenant,
} from "@/lib/intervenants";

const sortOptions = [
  { label: "Recommandés", value: "recommended" },
  { label: "Mieux notés", value: "rating" },
  { label: "Nom A-Z", value: "name" },
  { label: "Expérience", value: "experience" },
];

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

async function fetchIntervenants(): Promise<Intervenant[]> {
  const token = getToken();

  const headers: HeadersInit = {
    Accept: "application/json",
  };

  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  const response = await fetch(`${API_BASE_URL}/intervenants`, {
    method: "GET",
    headers,
    cache: "no-store",
  });

  const payload = (await response.json().catch(() => null)) as
    | ApiIntervenantsResponse
    | Intervenant[]
    | null;

  if (!response.ok) {
    throw new Error((!Array.isArray(payload) ? payload?.message : undefined) || `Erreur API intervenants : ${response.status}`);
  }

  return normalizeIntervenants(payload).filter(isPublicIntervenant);
}

export default function IntervenantsPage() {
  const [intervenants, setIntervenants] = useState<Intervenant[]>([]);
  const [loading, setLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState("");

  const [search, setSearch] = useState("");
  const [selectedSpeciality, setSelectedSpeciality] = useState("Tous");
  const [sortBy, setSortBy] = useState("recommended");
  const [verifiedOnly, setVerifiedOnly] = useState(false);

  useEffect(() => {
    let mounted = true;

    async function loadIntervenants() {
      try {
        setLoading(true);
        setErrorMessage("");

        const items = await fetchIntervenants();

        if (mounted) {
          setIntervenants(items);
        }
      } catch (error) {
        console.error("Erreur chargement intervenants:", error);

        if (mounted) {
          setIntervenants([]);
          setErrorMessage(
            error instanceof Error
              ? error.message
              : "Impossible de charger les intervenants depuis l’API pour le moment."
          );
        }
      } finally {
        if (mounted) {
          setLoading(false);
        }
      }
    }

    loadIntervenants();

    return () => {
      mounted = false;
    };
  }, []);

  const specialityOptions = useMemo(
    () => getSpecialityOptions(intervenants),
    [intervenants]
  );

  const filteredIntervenants = useMemo(() => {
    const cleanSearch = search.trim().toLowerCase();

    const filtered = intervenants.filter((intervenant) => {
      const speciality = getCoachSpeciality(intervenant);
      const description = getCoachDescription(intervenant);
      const location = getLocation(intervenant);
      const experience = getCoachExperience(intervenant) || "";

      const matchesSearch =
        !cleanSearch ||
        [
          intervenant.name,
          intervenant.email,
          speciality,
          description,
          location,
          experience,
          intervenant.coach_title,
          intervenant.coach_certifications,
          intervenant.coach_languages,
        ]
          .flat()
          .join(" ")
          .toLowerCase()
          .includes(cleanSearch);

      const matchesSpeciality =
        selectedSpeciality === "Tous" || speciality === selectedSpeciality;

      const matchesVerified =
        !verifiedOnly ||
        ["approved", "valide", "validé", "active"].includes(
          String(intervenant.account_status || "").toLowerCase()
        );

      return matchesSearch && matchesSpeciality && matchesVerified;
    });

    if (sortBy === "rating") {
      filtered.sort((a, b) => getRating(b) - getRating(a));
    }

    if (sortBy === "experience") {
      filtered.sort(
        (a, b) =>
          Number(b.coach_experience_years || 0) -
          Number(a.coach_experience_years || 0)
      );
    }

    if (sortBy === "name") {
      filtered.sort((a, b) =>
        String(a.name || "").localeCompare(String(b.name || ""))
      );
    }

    return filtered;
  }, [intervenants, search, selectedSpeciality, sortBy, verifiedOnly]);

  function resetFilters() {
    setSearch("");
    setSelectedSpeciality("Tous");
    setSortBy("recommended");
    setVerifiedOnly(false);
  }

  return (
    <>
      <Header />

      <main className="min-h-screen bg-[#FFF7ED] text-slate-950">
        <section className="relative overflow-hidden px-4 pb-14 pt-36 sm:pt-40 lg:pb-20">
          <div className="absolute left-1/2 top-0 h-[520px] w-[520px] -translate-x-1/2 rounded-full bg-orange-300/30 blur-3xl" />
          <div className="absolute -left-40 top-56 h-80 w-80 rounded-full bg-amber-200/40 blur-3xl" />
          <div className="absolute -right-40 top-72 h-96 w-96 rounded-full bg-orange-400/20 blur-3xl" />

          <div className="relative mx-auto max-w-7xl">
            <div className="grid gap-10 lg:grid-cols-[1.05fr_0.95fr] lg:items-center">
              <div>
                <span className="mb-5 inline-flex items-center gap-2 rounded-full border border-orange-200 bg-white/80 px-4 py-2 text-xs font-black uppercase tracking-[0.18em] text-orange-700 shadow-sm backdrop-blur">
                  <UsersRound size={16} />
                  Intervenants Gotfit
                </span>

                <h1 className="max-w-4xl text-4xl font-black leading-[1.05] tracking-tight text-slate-950 sm:text-5xl lg:text-7xl">
                  Trouvez l’intervenant idéal pour votre bien-être.
                </h1>

                <p className="mt-6 max-w-2xl text-base font-medium leading-8 text-slate-600 sm:text-lg">
                  Les spécialités, titres, expériences, certifications, langues et
                  vidéos de présentation viennent maintenant directement de l’API
                  Laravel. Plus de liste de spécialités codée en dur côté front.
                </p>

                <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                  <Link
                    href="#liste-intervenants"
                    className="inline-flex items-center justify-center gap-2 rounded-full bg-orange-600 px-7 py-4 text-sm font-black text-white shadow-xl shadow-orange-600/20 transition hover:-translate-y-0.5 hover:bg-orange-700"
                  >
                    Voir les profils
                    <ArrowRight size={18} />
                  </Link>

                  <Link
                    href="/auth/register"
                    className="inline-flex items-center justify-center gap-2 rounded-full border border-orange-200 bg-white px-7 py-4 text-sm font-black text-orange-700 shadow-sm transition hover:-translate-y-0.5 hover:bg-orange-50"
                  >
                    Devenir intervenant
                  </Link>
                </div>
              </div>

              <div className="rounded-[2.5rem] border border-white/80 bg-white/70 p-4 shadow-[0_24px_80px_rgba(249,115,22,0.14)] backdrop-blur-xl">
                <div className="rounded-[2rem] bg-white p-6">
                  <div className="mb-6 flex items-center justify-between">
                    <div>
                      <p className="text-sm font-black uppercase tracking-[0.18em] text-orange-600">
                        Priorité 5
                      </p>
                      <h2 className="mt-2 text-3xl font-black tracking-tight">
                        Profil coach complet
                      </h2>
                    </div>

                    <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-orange-100 text-orange-700">
                      <Sparkles size={26} />
                    </div>
                  </div>

                  <div className="grid gap-4">
                    <div className="rounded-2xl bg-orange-50 p-5">
                      <div className="mb-3 flex items-center gap-3">
                        <BadgeCheck className="text-orange-600" size={22} />
                        <strong className="text-sm font-black">
                          Profils enrichis depuis l’API
                        </strong>
                      </div>
                      <p className="text-sm font-semibold leading-7 text-slate-500">
                        Les cartes affichent coach_speciality,
                        coach_experience_years, coach_certifications,
                        coach_languages et la vidéo si elle existe.
                      </p>
                    </div>

                    <div className="grid gap-3 sm:grid-cols-3">
                      <div className="rounded-2xl bg-slate-950 p-4 text-white">
                        <strong className="block text-2xl font-black">
                          {intervenants.length}
                        </strong>
                        <span className="mt-1 block text-xs font-semibold text-white/60">
                          Profils
                        </span>
                      </div>

                      <div className="rounded-2xl bg-orange-100 p-4">
                        <strong className="block text-2xl font-black text-orange-700">
                          {Math.max(specialityOptions.length - 1, 0)}
                        </strong>
                        <span className="mt-1 block text-xs font-semibold text-slate-500">
                          Spécialités API
                        </span>
                      </div>

                      <div className="rounded-2xl bg-orange-100 p-4">
                        <strong className="block text-2xl font-black text-orange-700">
                          {intervenants.filter((item) => getIntervenantVideo(item)).length}
                        </strong>
                        <span className="mt-1 block text-xs font-semibold text-slate-500">
                          Vidéos
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section id="liste-intervenants" className="px-4 py-8 lg:py-12">
          <div className="mx-auto max-w-7xl">
            <div className="mb-6 rounded-[2rem] border border-orange-100 bg-white p-4 shadow-sm sm:p-5">
              <div className="grid gap-3 lg:grid-cols-[1.3fr_0.75fr_0.65fr_auto]">
                <div className="flex items-center gap-3 rounded-2xl border border-orange-100 bg-orange-50 px-4 py-3">
                  <Search className="shrink-0 text-orange-500" size={20} />
                  <input
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    placeholder="Rechercher par nom, spécialité, ville, certification..."
                    className="w-full bg-transparent text-sm font-bold text-slate-800 outline-none placeholder:text-slate-400"
                  />
                </div>

                <div className="relative">
                  <select
                    value={selectedSpeciality}
                    onChange={(event) => setSelectedSpeciality(event.target.value)}
                    className="h-full w-full appearance-none rounded-2xl border border-orange-100 bg-orange-50 px-4 py-3 pr-10 text-sm font-bold text-slate-700 outline-none transition focus:border-orange-500"
                  >
                    {specialityOptions.map((item) => (
                      <option key={item} value={item}>
                        {item}
                      </option>
                    ))}
                  </select>

                  <ChevronDown
                    size={18}
                    className="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-orange-500"
                  />
                </div>

                <div className="relative">
                  <select
                    value={sortBy}
                    onChange={(event) => setSortBy(event.target.value)}
                    className="h-full w-full appearance-none rounded-2xl border border-orange-100 bg-orange-50 px-4 py-3 pr-10 text-sm font-bold text-slate-700 outline-none transition focus:border-orange-500"
                  >
                    {sortOptions.map((item) => (
                      <option key={item.value} value={item.value}>
                        {item.label}
                      </option>
                    ))}
                  </select>

                  <ChevronDown
                    size={18}
                    className="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-orange-500"
                  />
                </div>

                <button
                  type="button"
                  onClick={() => setVerifiedOnly((value) => !value)}
                  className={`inline-flex items-center justify-center gap-2 rounded-2xl px-4 py-3 text-sm font-black transition ${
                    verifiedOnly
                      ? "bg-orange-600 text-white shadow-lg shadow-orange-600/20"
                      : "bg-slate-950 text-white hover:bg-slate-800"
                  }`}
                >
                  <ShieldCheck size={18} />
                  Vérifiés
                </button>
              </div>

              <div className="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="inline-flex items-center gap-2 text-sm font-bold text-slate-500">
                  <SlidersHorizontal size={17} />
                  {filteredIntervenants.length} résultat(s) trouvé(s)
                </div>

                {(search || selectedSpeciality !== "Tous" || verifiedOnly) && (
                  <button
                    type="button"
                    onClick={resetFilters}
                    className="inline-flex w-fit items-center gap-2 rounded-full bg-orange-50 px-4 py-2 text-sm font-black text-orange-700 transition hover:bg-orange-100"
                  >
                    <X size={16} />
                    Réinitialiser
                  </button>
                )}
              </div>
            </div>

            {errorMessage && (
              <div className="mb-6 rounded-3xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-bold text-red-700">
                {errorMessage}
              </div>
            )}

            {loading ? (
              <div className="flex min-h-[320px] items-center justify-center rounded-[2rem] bg-white">
                <div className="inline-flex items-center gap-3 rounded-3xl bg-orange-50 px-6 py-5 text-sm font-black text-orange-700">
                  <Loader2 className="animate-spin" size={20} />
                  Chargement des intervenants...
                </div>
              </div>
            ) : filteredIntervenants.length ? (
              <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                {filteredIntervenants.map((intervenant) => {
                  const photo = getIntervenantPhoto(intervenant);
                  const speciality = getCoachSpeciality(intervenant);
                  const description = getCoachDescription(intervenant);
                  const location = getLocation(intervenant);
                  const rating = getRating(intervenant);
                  const reviewsCount = getReviewsCount(intervenant);
                  const video = getIntervenantVideo(intervenant);
                  const experience = getCoachExperience(intervenant);
                  const profileUrl = `/intervenants/${intervenant.id}`;
                  const reservationUrl = buildReservationUrl(intervenant);
                  const messageUrl = buildMessageUrl(intervenant);

                  return (
                    <article
                      key={intervenant.id}
                      className="group overflow-hidden rounded-[2rem] border border-orange-100 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-[0_24px_80px_rgba(249,115,22,0.16)]"
                    >
                      <div className="relative h-36 bg-gradient-to-br from-orange-100 via-orange-200 to-orange-400">
                        <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(255,255,255,0.8),transparent_35%)]" />

                        <span className="absolute left-4 top-4 rounded-full bg-white/90 px-3 py-1 text-xs font-black text-orange-700 shadow-sm backdrop-blur">
                          {speciality}
                        </span>

                        <span className="absolute right-4 top-4 inline-flex items-center gap-1 rounded-full bg-slate-950/90 px-3 py-1 text-xs font-black text-white shadow-sm backdrop-blur">
                          <Star size={13} className="fill-white" />
                          {rating ? rating.toFixed(1) : "Nouveau"}
                        </span>
                      </div>

                      <div className="relative px-5 pb-5">
                        <div className="-mt-12 mb-4 flex items-end justify-between gap-3">
                          <div className="h-24 w-24 overflow-hidden rounded-[1.6rem] border-4 border-white bg-orange-100 shadow-xl">
                            {photo ? (
                              <img
                                src={photo}
                                alt={intervenant.name || "Intervenant Gotfit"}
                                className="h-full w-full object-cover"
                              />
                            ) : (
                              <div className="flex h-full w-full items-center justify-center text-orange-700">
                                <UserRound size={38} />
                              </div>
                            )}
                          </div>

                          <span className="mb-1 inline-flex items-center gap-1 rounded-full bg-orange-50 px-3 py-1 text-xs font-black text-orange-700">
                            <CheckCircle2 size={14} />
                            {getStatusLabel(intervenant.account_status)}
                          </span>
                        </div>

                        <h3 className="text-2xl font-black tracking-tight text-slate-950">
                          {intervenant.name || "Intervenant Gotfit"}
                        </h3>

                        <div className="mt-2 flex items-center gap-2 text-sm font-bold text-slate-500">
                          <MapPin size={16} className="text-orange-500" />
                          {location}
                        </div>

                        <div className="mt-4 flex flex-wrap gap-2">
                          {experience && (
                            <span className="rounded-full bg-orange-50 px-3 py-1 text-xs font-black text-orange-700">
                              {experience}
                            </span>
                          )}

                          {video && (
                            <span className="inline-flex items-center gap-1 rounded-full bg-slate-950 px-3 py-1 text-xs font-black text-white">
                              <Video size={13} />
                              Vidéo 60s
                            </span>
                          )}
                        </div>

                        <p className="mt-4 line-clamp-3 min-h-[4.6rem] whitespace-pre-line text-sm font-semibold leading-7 text-slate-500">
                          {description}
                        </p>

                        <div className="mt-5 flex items-center justify-between rounded-2xl bg-orange-50 p-3">
                          <div className="flex items-center gap-2">
                            <div className="flex h-9 w-9 items-center justify-center rounded-full bg-white text-orange-600">
                              <Star size={17} className="fill-orange-500" />
                            </div>

                            <div>
                              <strong className="block text-sm font-black">
                                {rating ? `${rating.toFixed(1)} / 5` : "Nouveau"}
                              </strong>
                              <span className="text-xs font-bold text-slate-500">
                                {reviewsCount} avis
                              </span>
                            </div>
                          </div>

                          <div className="flex h-9 w-9 items-center justify-center rounded-full bg-white text-orange-600">
                            <HeartPulse size={18} />
                          </div>
                        </div>

                        <div className="mt-5 grid grid-cols-2 gap-2">
                          <Link
                            href={profileUrl}
                            className="inline-flex items-center justify-center gap-2 rounded-2xl border border-orange-100 bg-white px-4 py-3 text-sm font-black text-orange-700 transition hover:bg-orange-50"
                          >
                            Profil
                          </Link>

                          <Link
                            href={getProtectedUrl(reservationUrl)}
                            className="inline-flex items-center justify-center gap-2 rounded-2xl bg-orange-600 px-4 py-3 text-sm font-black text-white shadow-lg shadow-orange-600/20 transition hover:bg-orange-700"
                          >
                            Réserver
                            <CalendarCheck size={17} />
                          </Link>
                        </div>

                        <Link
                          href={getProtectedUrl(messageUrl)}
                          className="mt-2 inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-slate-950 px-4 py-3 text-sm font-black text-white transition hover:bg-slate-800"
                        >
                          <MessageCircle size={17} />
                          Contacter
                        </Link>
                      </div>
                    </article>
                  );
                })}
              </div>
            ) : (
              <div className="rounded-[2rem] bg-white p-10 text-center shadow-sm">
                <div className="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-2xl bg-orange-100 text-orange-700">
                  <Search size={28} />
                </div>

                <h3 className="text-2xl font-black tracking-tight">
                  Aucun intervenant trouvé
                </h3>

                <p className="mx-auto mt-3 max-w-md text-sm font-semibold leading-7 text-slate-500">
                  Aucun intervenant n’est disponible pour le moment, ou vos filtres
                  sont trop restrictifs.
                </p>

                <button
                  type="button"
                  onClick={resetFilters}
                  className="mt-6 inline-flex items-center justify-center gap-2 rounded-full bg-orange-600 px-6 py-3 text-sm font-black text-white shadow-lg shadow-orange-600/20 transition hover:bg-orange-700"
                >
                  Réinitialiser les filtres
                </button>
              </div>
            )}
          </div>
        </section>

        <section className="px-4 pb-24 pt-12">
          <div className="mx-auto max-w-7xl overflow-hidden rounded-[2.5rem] bg-slate-950 p-6 text-white shadow-[0_24px_80px_rgba(15,23,42,0.18)] sm:p-10">
            <div className="grid gap-8 lg:grid-cols-[1fr_0.8fr] lg:items-center">
              <div>
                <span className="mb-4 inline-flex rounded-full bg-white/10 px-4 py-2 text-xs font-black uppercase tracking-[0.18em] text-orange-200">
                  Gotfit Pro
                </span>

                <h2 className="max-w-2xl text-3xl font-black leading-tight tracking-tight sm:text-5xl">
                  Vous êtes coach, nutritionniste ou praticien bien-être ?
                </h2>

                <p className="mt-5 max-w-xl text-sm font-semibold leading-7 text-white/60">
                  Créez votre compte intervenant, complétez votre spécialité, vos
                  certifications, vos langues, votre expérience et votre vidéo de
                  présentation 60 secondes maximum.
                </p>
              </div>

              <div className="grid gap-3 sm:grid-cols-2">
                <Link
                  href="/auth/register"
                  className="inline-flex items-center justify-center gap-2 rounded-2xl bg-orange-600 px-6 py-4 text-sm font-black text-white shadow-lg shadow-orange-600/20 transition hover:-translate-y-0.5 hover:bg-orange-700"
                >
                  Devenir intervenant
                  <ArrowRight size={17} />
                </Link>

                <Link
                  href="/#contact"
                  className="inline-flex items-center justify-center gap-2 rounded-2xl bg-white/10 px-6 py-4 text-sm font-black text-white transition hover:-translate-y-0.5 hover:bg-white/15"
                >
                  Nous contacter
                </Link>
              </div>
            </div>
          </div>
        </section>
      </main>

      <Footer />
    </>
  );
}
