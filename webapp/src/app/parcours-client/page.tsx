"use client";

import Link from "next/link";
import { useEffect, useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import {
  ArrowRight,
  CalendarCheck,
  Loader2,
  RefreshCw,
  ShieldCheck,
  UserRound,
  X,
} from "lucide-react";

import Header from "@/components/layout/Header";
import Footer from "@/components/layout/Footer";
import { getCurrentUser, getToken, hasRole } from "@/lib/auth";
import {
  fetchIntervenantReservations,
  type ClientOnboarding,
} from "@/lib/client-journey";
import {
  fetchClientReservations,
  formatDate,
  type GotfitUser,
  type Reservation,
} from "@/lib/marketplace";

type ClientCard = {
  user: GotfitUser;
  reservations: Reservation[];
  onboarding?: ClientOnboarding | null;
};

export default function ClientJourneyIndexPage() {
  const router = useRouter();
  const [clients, setClients] = useState<ClientCard[]>([]);
  const [currentClient, setCurrentClient] = useState<GotfitUser | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const user = useMemo(() => getCurrentUser(), []);
  const isCoach = hasRole(user, "intervenant");
  const isClient = hasRole(user, "client");

  useEffect(() => {
    if (!getToken()) {
      router.replace("/auth/login");
      return;
    }

    loadData();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [router]);

  async function loadData() {
    try {
      setLoading(true);
      setError("");

      if (isCoach) {
        const reservations = await fetchIntervenantReservations();
        const grouped = new Map<number, ClientCard>();

        reservations.forEach((reservation) => {
          const client = reservation.client;
          if (!client?.id) return;

          const existing = grouped.get(client.id);
          if (existing) {
            existing.reservations.push(reservation);
          } else {
            grouped.set(client.id, {
              user: client,
              reservations: [reservation],
              onboarding: (client as any).client_onboarding || null,
            });
          }
        });

        setClients(Array.from(grouped.values()));
        return;
      }

      if (isClient && user) {
        const reservations = await fetchClientReservations();
        setCurrentClient(user as GotfitUser);
        setClients([
          {
            user: user as GotfitUser,
            reservations,
          },
        ]);
      }
    } catch (err) {
      setError(
        err instanceof Error ? err.message : "Impossible de charger le parcours client."
      );
    } finally {
      setLoading(false);
    }
  }

  return (
    <>
      <Header />

      <main className="min-h-screen bg-[#FFF7ED] px-4 pb-16 pt-36 text-slate-950">
        <div className="mx-auto max-w-7xl">
          <section className="mb-8 grid gap-6 lg:grid-cols-[1fr_0.7fr] lg:items-end">
            <div>
              <span className="mb-5 inline-flex items-center gap-2 rounded-full border border-orange-200 bg-white px-4 py-2 text-xs font-black uppercase tracking-[0.18em] text-orange-700 shadow-sm">
                <ShieldCheck size={16} />
                Parcours client / coach
              </span>
              <h1 className="max-w-3xl text-4xl font-black leading-tight tracking-tight sm:text-6xl">
                Notes, historique et onboarding.
              </h1>
              <p className="mt-5 max-w-2xl text-base font-medium leading-8 text-slate-600">
                Retrouvez le suivi des clients, les réservations passées, les
                notes privées ou partagées et les informations d’onboarding.
              </p>
            </div>

            <div className="rounded-[2rem] bg-white p-5 shadow-sm">
              <strong className="block text-3xl font-black">
                {clients.length}
              </strong>
              <span className="mt-1 block text-sm font-bold text-slate-500">
                {isCoach ? "client(s) suivis" : "parcours personnel"}
              </span>
              <button
                type="button"
                onClick={loadData}
                className="mt-4 inline-flex items-center gap-2 rounded-full bg-orange-600 px-4 py-3 text-sm font-black text-white transition hover:bg-orange-700"
              >
                <RefreshCw size={16} />
                Actualiser
              </button>
            </div>
          </section>

          {isClient && currentClient && (
            <div className="mb-6 flex flex-wrap gap-3">
              <Link
                href="/onboarding"
                className="inline-flex items-center gap-2 rounded-full bg-slate-950 px-5 py-3 text-sm font-black text-white shadow-lg transition hover:-translate-y-0.5 hover:bg-slate-800"
              >
                <UserRound size={17} />
                Remplir mon onboarding
              </Link>
            </div>
          )}

          {error && (
            <div className="mb-5 flex items-start gap-3 rounded-[2rem] border border-red-100 bg-red-50 px-5 py-4 text-sm font-bold text-red-700">
              <X className="mt-0.5 shrink-0" size={18} />
              {error}
            </div>
          )}

          {loading ? (
            <div className="flex items-center justify-center rounded-[2rem] bg-white py-16 text-sm font-black text-orange-700 shadow-sm">
              <Loader2 className="mr-3 animate-spin" size={20} />
              Chargement du parcours...
            </div>
          ) : clients.length === 0 ? (
            <div className="rounded-[2rem] bg-white p-10 text-center shadow-sm">
              <h2 className="text-2xl font-black">Aucun client à afficher</h2>
              <p className="mx-auto mt-3 max-w-md text-sm font-semibold leading-6 text-slate-500">
                Les clients apparaîtront ici dès qu’une réservation sera liée au coach.
              </p>
            </div>
          ) : (
            <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
              {clients.map((item) => (
                <Link
                  key={item.user.id}
                  href={`/parcours-client/${item.user.id}`}
                  className="group rounded-[2rem] border border-orange-100 bg-white p-5 shadow-sm transition hover:-translate-y-1 hover:shadow-xl"
                >
                  <div className="mb-5 flex items-center gap-4">
                    <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-orange-100 text-orange-700">
                      <UserRound size={24} />
                    </div>
                    <div className="min-w-0">
                      <h2 className="truncate text-xl font-black">
                        {item.user.name || "Client Gotfit"}
                      </h2>
                      <p className="truncate text-sm font-semibold text-slate-500">
                        {item.user.email || "Email non renseigné"}
                      </p>
                    </div>
                  </div>

                  <div className="grid gap-3">
                    <div className="flex items-center justify-between rounded-2xl bg-orange-50 p-4">
                      <span className="inline-flex items-center gap-2 text-sm font-bold text-slate-600">
                        <CalendarCheck size={17} />
                        Réservations
                      </span>
                      <strong className="font-black">{item.reservations.length}</strong>
                    </div>

                    <div className="rounded-2xl bg-slate-50 p-4 text-sm font-bold text-slate-500">
                      Dernière séance :{" "}
                      {formatDate(item.reservations[0]?.reservation_date)}
                    </div>
                  </div>

                  <span className="mt-5 inline-flex items-center gap-2 text-sm font-black text-orange-700">
                    Ouvrir le dossier
                    <ArrowRight
                      size={17}
                      className="transition group-hover:translate-x-1"
                    />
                  </span>
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
