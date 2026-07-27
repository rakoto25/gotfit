"use client";

import Link from "next/link";
import { useEffect, useMemo, useState } from "react";
import {
  ArrowRight,
  BadgeCheck,
  CalendarCheck,
  Clock3,
  Loader2,
  MapPin,
  Search,
  ShieldCheck,
  SlidersHorizontal,
  Sparkles,
  UserRound,
  Wifi,
} from "lucide-react";

import Header from "@/components/layout/Header";
import Footer from "@/components/layout/Footer";
import {
  Annonce,
  fetchAnnonces,
  formatMoney,
  getAnnonceDescription,
  getAnnonceTitle,
  getAssetUrl,
} from "@/lib/marketplace";

function getCoachName(annonce: Annonce) {
  return annonce.intervenant?.name || annonce.user?.name || "Coach Gotfit";
}

function getAnnonceImage(annonce: Annonce) {
  return getAssetUrl(annonce.image_url || annonce.image);
}

function getLocation(annonce: Annonce) {
  if (annonce.is_online) return "En ligne";
  return annonce.city || annonce.location || annonce.address || "Lieu à confirmer";
}

function isVisibleAnnonce(annonce: Annonce) {
  const status = annonce.status?.toLowerCase();
  return !status || ["valide", "approved", "active", "published"].includes(status);
}

export default function AnnoncesPage() {
  const [annonces, setAnnonces] = useState<Annonce[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [query, setQuery] = useState("");
  const [category, setCategory] = useState("all");

  useEffect(() => {
    async function loadAnnonces() {
      try {
        setLoading(true);
        setError("");
        setAnnonces((await fetchAnnonces()).filter(isVisibleAnnonce));
      } catch (err) {
        setError(
          err instanceof Error
            ? err.message
            : "Impossible de charger les annonces."
        );
      } finally {
        setLoading(false);
      }
    }

    loadAnnonces();
  }, []);

  const categories = useMemo(() => {
    const values = annonces
      .map((annonce) => annonce.category)
      .filter((value): value is string => Boolean(value));

    return Array.from(new Set(values));
  }, [annonces]);

  const filteredAnnonces = useMemo(() => {
    const cleanQuery = query.trim().toLowerCase();

    return annonces.filter((annonce) => {
      const haystack = [
        getAnnonceTitle(annonce),
        getAnnonceDescription(annonce),
        annonce.category,
        annonce.type_prestation,
        getCoachName(annonce),
        getLocation(annonce),
      ]
        .filter(Boolean)
        .join(" ")
        .toLowerCase();

      const matchesQuery = !cleanQuery || haystack.includes(cleanQuery);
      const matchesCategory =
        category === "all" || annonce.category === category;

      return matchesQuery && matchesCategory;
    });
  }, [annonces, category, query]);

  return (
    <>
      <Header />

      <main className="min-h-screen bg-[#FFF7ED] text-slate-950">
        <section className="relative overflow-hidden px-4 pb-12 pt-36 sm:pt-40">
          <div className="absolute left-1/2 top-0 h-[520px] w-[520px] -translate-x-1/2 rounded-full bg-orange-300/25 blur-3xl" />
          <div className="absolute -right-32 top-56 h-80 w-80 rounded-full bg-amber-300/25 blur-3xl" />

          <div className="relative mx-auto max-w-7xl">
            <div className="grid gap-8 lg:grid-cols-[1fr_0.85fr] lg:items-end">
              <div>
                <span className="mb-5 inline-flex items-center gap-2 rounded-full border border-orange-200 bg-white/80 px-4 py-2 text-xs font-black uppercase tracking-[0.18em] text-orange-700 shadow-sm">
                  <Sparkles size={16} />
                  Marketplace Gotfit
                </span>

                <h1 className="max-w-4xl text-4xl font-black leading-[1.05] tracking-tight text-slate-950 sm:text-6xl">
                  Réservez un coach, payez en sécurité.
                </h1>

                <p className="mt-6 max-w-2xl text-base font-medium leading-8 text-slate-600">
                  Découvrez les prestations des intervenants Gotfit. Le paiement
                  est encaissé par la plateforme, puis reversé au coach après la
                  validation de la prestation.
                </p>
              </div>

              <div className="grid gap-3 rounded-[2rem] border border-white/80 bg-white/75 p-3 shadow-[0_24px_80px_rgba(249,115,22,0.12)] backdrop-blur-xl sm:grid-cols-3 lg:grid-cols-1">
                <div className="rounded-[1.4rem] bg-white p-5">
                  <ShieldCheck className="mb-3 text-orange-700" size={24} />
                  <strong className="block text-sm font-black">
                    Paiement sécurisé
                  </strong>
                  <p className="mt-1 text-xs font-semibold leading-5 text-slate-500">
                    Stripe Connect protège le client et le coach.
                  </p>
                </div>

                <div className="rounded-[1.4rem] bg-white p-5">
                  <BadgeCheck className="mb-3 text-orange-700" size={24} />
                  <strong className="block text-sm font-black">
                    Validation prestation
                  </strong>
                  <p className="mt-1 text-xs font-semibold leading-5 text-slate-500">
                    Le coach est payé après validation.
                  </p>
                </div>

                <div className="rounded-[1.4rem] bg-white p-5">
                  <CalendarCheck className="mb-3 text-orange-700" size={24} />
                  <strong className="block text-sm font-black">
                    Litige possible
                  </strong>
                  <p className="mt-1 text-xs font-semibold leading-5 text-slate-500">
                    Le reversement peut être bloqué en cas de souci.
                  </p>
                </div>
              </div>
            </div>

            <div className="mt-10 rounded-[2rem] border border-orange-100 bg-white p-4 shadow-sm">
              <div className="grid gap-3 md:grid-cols-[1fr_240px]">
                <div className="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                  <Search size={19} className="text-slate-400" />
                  <input
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    placeholder="Rechercher une prestation, une ville, un coach..."
                    className="w-full bg-transparent text-sm font-semibold text-slate-950 outline-none placeholder:text-slate-400"
                  />
                </div>

                <div className="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                  <SlidersHorizontal size={19} className="text-slate-400" />
                  <select
                    value={category}
                    onChange={(event) => setCategory(event.target.value)}
                    className="w-full bg-transparent text-sm font-black text-slate-700 outline-none"
                  >
                    <option value="all">Toutes catégories</option>
                    {categories.map((item) => (
                      <option key={item} value={item}>
                        {item}
                      </option>
                    ))}
                  </select>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section className="px-4 pb-16">
          <div className="mx-auto max-w-7xl">
            {loading && (
              <div className="flex items-center justify-center rounded-[2rem] bg-white py-16 text-sm font-black text-orange-700 shadow-sm">
                <Loader2 className="mr-3 animate-spin" size={20} />
                Chargement des annonces...
              </div>
            )}

            {error && (
              <div className="rounded-[2rem] border border-red-100 bg-red-50 px-5 py-4 text-sm font-bold text-red-700">
                {error}
              </div>
            )}

            {!loading && !error && filteredAnnonces.length === 0 && (
              <div className="rounded-[2rem] bg-white p-10 text-center shadow-sm">
                <h2 className="text-2xl font-black">Aucune annonce trouvée</h2>
                <p className="mx-auto mt-3 max-w-md text-sm font-semibold leading-6 text-slate-500">
                  Essayez une autre recherche ou revenez plus tard, les
                  intervenants ajoutent régulièrement de nouvelles prestations.
                </p>
              </div>
            )}

            <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
              {filteredAnnonces.map((annonce) => {
                const image = getAnnonceImage(annonce);

                return (
                  <Link
                    key={annonce.id}
                    href={`/annonces/${annonce.id}`}
                    className="group overflow-hidden rounded-[2rem] border border-orange-100 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-[0_24px_70px_rgba(249,115,22,0.14)]"
                  >
                    <div className="relative h-56 bg-gradient-to-br from-orange-100 to-amber-50">
                      {image ? (
                        <img
                          src={image}
                          alt={getAnnonceTitle(annonce)}
                          className="h-full w-full object-cover"
                        />
                      ) : (
                        <div className="flex h-full w-full items-center justify-center text-orange-700">
                          <Sparkles size={42} />
                        </div>
                      )}

                      <div className="absolute left-4 top-4 rounded-full bg-white/95 px-3 py-1 text-xs font-black text-orange-700 shadow-sm">
                        {annonce.category || "Coaching"}
                      </div>

                      {annonce.is_online ? (
                        <div className="absolute right-4 top-4 flex items-center gap-1 rounded-full bg-slate-950/90 px-3 py-1 text-xs font-black text-white">
                          <Wifi size={13} />
                          Online
                        </div>
                      ) : null}
                    </div>

                    <div className="p-6">
                      <div className="mb-4 flex items-center justify-between gap-3">
                        <div className="flex items-center gap-2 text-xs font-black text-slate-500">
                          <UserRound size={15} />
                          {getCoachName(annonce)}
                        </div>
                        <strong className="text-lg font-black text-orange-700">
                          {formatMoney(annonce.price)}
                        </strong>
                      </div>

                      <h2 className="text-2xl font-black tracking-tight text-slate-950">
                        {getAnnonceTitle(annonce)}
                      </h2>

                      <p className="mt-3 line-clamp-3 text-sm font-semibold leading-7 text-slate-500">
                        {getAnnonceDescription(annonce) ||
                          "Prestation proposée par un intervenant Gotfit."}
                      </p>

                      <div className="mt-5 grid gap-2 text-xs font-bold text-slate-500">
                        <span className="inline-flex items-center gap-2">
                          <MapPin size={15} />
                          {getLocation(annonce)}
                        </span>
                        <span className="inline-flex items-center gap-2">
                          <Clock3 size={15} />
                          {annonce.duration || 60} min
                        </span>
                      </div>

                      <div className="mt-6 inline-flex items-center gap-2 text-sm font-black text-orange-700">
                        Voir et réserver
                        <ArrowRight
                          size={17}
                          className="transition group-hover:translate-x-1"
                        />
                      </div>
                    </div>
                  </Link>
                );
              })}
            </div>
          </div>
        </section>
      </main>

      <Footer />
    </>
  );
}
