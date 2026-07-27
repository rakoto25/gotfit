"use client";

import Link from "next/link";
import { useParams, useRouter } from "next/navigation";
import { useEffect, useMemo, useState } from "react";
import {
  LiveKitRoom,
  RoomAudioRenderer,
  VideoConference,
} from "@livekit/components-react";
import "@livekit/components-styles";
import {
  AlertTriangle,
  ArrowLeft,
  CheckCircle2,
  Clock,
  Loader2,
  Play,
  ShieldCheck,
  Square,
  Users,
  Video,
  X,
} from "lucide-react";

import Footer from "@/components/layout/Footer";
import Header from "@/components/layout/Header";
import { getCurrentUser, getToken, hasRole } from "@/lib/auth";
import { formatDate } from "@/lib/marketplace";
import {
  endVisioSession,
  fetchVisioSession,
  getParticipantStatusLabel,
  getPaymentStatusLabel,
  getVisioStatusLabel,
  joinVisioSession,
  startVisioSession,
  type VisioJoinPayload,
  type VisioSession,
} from "@/lib/visio";

function messageOf(error: unknown, fallback: string) {
  return error instanceof Error ? error.message : fallback;
}

function statusClass(status?: string | null) {
  if (["live", "paid", "joined", "confirmed"].includes(status || "")) {
    return "border-emerald-100 bg-emerald-50 text-emerald-700";
  }
  if (["open", "reserved", "pending"].includes(status || "")) {
    return "border-orange-100 bg-orange-50 text-orange-700";
  }
  if (["cancelled", "ended", "no_show", "unpaid", "refunded"].includes(status || "")) {
    return "border-red-100 bg-red-50 text-red-700";
  }
  return "border-slate-200 bg-slate-100 text-slate-600";
}

function hasRoomCredentials(payload?: VisioJoinPayload | null) {
  return Boolean(payload?.token && payload?.server_url);
}

function isClientRole(role?: string | null) {
  return ["client", "participant"].includes(role || "");
}

export default function VisioDetailPage() {
  const params = useParams<{ id: string }>();
  const router = useRouter();
  const sessionId = params.id;

  const [session, setSession] = useState<VisioSession | null>(null);
  const [credentials, setCredentials] = useState<VisioJoinPayload | null>(null);
  const [loading, setLoading] = useState(true);
  const [action, setAction] = useState("");
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");

  const user = useMemo(() => getCurrentUser(), []);
  const isCoach = Boolean(
    user &&
      session &&
      (Number(session.coach_id) === Number(user.id) ||
        Number(session.intervenant_id) === Number(user.id) ||
        hasRole(user, "intervenant"))
  );
  const currentParticipant = session?.participants?.find(
    (participant) => Number(participant.user_id) === Number(user?.id)
  );
  const clientParticipants =
    session?.participants?.filter((participant) => isClientRole(participant.role)) || [];
  const paidClientCount =
    session?.paid_participants_count ??
    clientParticipants.filter((participant) => participant.payment_status === "paid").length;

  async function loadSession() {
    if (!getToken()) {
      router.replace("/auth/login");
      return;
    }

    try {
      setLoading(true);
      setError("");
      setSession(await fetchVisioSession(sessionId));
    } catch (err) {
      setError(messageOf(err, "Impossible de charger cette séance visio."));
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    void loadSession();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [sessionId]);

  async function execute(label: string, callback: () => Promise<void>) {
    try {
      setAction(label);
      setError("");
      setSuccess("");
      await callback();
    } catch (err) {
      setError(messageOf(err, "Action impossible pour le moment."));
    } finally {
      setAction("");
    }
  }

  async function joinRoom() {
    await execute("join", async () => {
      const payload = await joinVisioSession(sessionId);
      if (!hasRoomCredentials(payload)) {
        throw new Error("Laravel n’a pas renvoyé le token LiveKit ou l’adresse du serveur.");
      }
      setCredentials(payload);
      if (payload.session) setSession(payload.session);
      setSuccess("Connexion autorisée. Activez votre caméra et votre microphone.");
    });
  }

  async function startRoom() {
    await execute("start", async () => {
      const updated = await startVisioSession(sessionId);
      if (updated) setSession(updated);
      const payload = await joinVisioSession(sessionId);
      if (!hasRoomCredentials(payload)) {
        throw new Error("La séance a démarré, mais les accès LiveKit sont incomplets.");
      }
      setCredentials(payload);
      setSuccess("La séance est démarrée.");
    });
  }

  async function endRoom() {
    await execute("end", async () => {
      const updated = await endVisioSession(sessionId);
      if (updated) setSession(updated);
      setCredentials(null);
      setSuccess("La séance visio est terminée.");
    });
  }

  const clientIsAuthorized = Boolean(
    currentParticipant &&
      currentParticipant.payment_status === "paid" &&
      !["cancelled", "no_show"].includes(currentParticipant.status)
  );
  const canJoin = Boolean(
    session &&
      (isCoach || clientIsAuthorized) &&
      ["open", "confirmed", "live"].includes(session.status)
  );
  const canStart = Boolean(
    session && isCoach && ["open", "confirmed"].includes(session.status)
  );

  return (
    <>
      <Header />
      <main className="min-h-screen bg-[#FFF7ED] px-4 pb-16 pt-36 text-slate-950">
        <div className="mx-auto max-w-7xl">
          <Link
            href="/reservations"
            className="mb-6 inline-flex items-center gap-2 rounded-full border border-orange-200 bg-white px-5 py-3 text-sm font-black text-orange-700"
          >
            <ArrowLeft size={17} /> Retour aux réservations
          </Link>

          {error && (
            <div className="mb-5 flex gap-3 rounded-3xl border border-red-100 bg-red-50 px-5 py-4 text-sm font-bold text-red-700">
              <X size={18} /> {error}
            </div>
          )}
          {success && (
            <div className="mb-5 flex gap-3 rounded-3xl border border-emerald-100 bg-emerald-50 px-5 py-4 text-sm font-bold text-emerald-700">
              <CheckCircle2 size={18} /> {success}
            </div>
          )}

          {loading ? (
            <div className="flex items-center justify-center rounded-3xl bg-white py-20 font-black text-orange-700">
              <Loader2 className="mr-3 animate-spin" size={20} /> Chargement de la visio...
            </div>
          ) : !session ? (
            <div className="rounded-3xl bg-white p-10 text-center">
              <AlertTriangle className="mx-auto mb-4 text-orange-600" size={34} />
              <h1 className="text-2xl font-black">Séance introuvable</h1>
            </div>
          ) : (
            <div className="grid gap-6 lg:grid-cols-[1fr_360px]">
              <section className="rounded-[2rem] bg-white p-5 shadow-sm md:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                  <div>
                    <span className="mb-4 inline-flex items-center gap-2 rounded-full bg-orange-50 px-4 py-2 text-xs font-black uppercase tracking-widest text-orange-700">
                      <Video size={15} /> {session.session_type === "individual" ? "Visio individuelle" : "Séance visio"}
                    </span>
                    <h1 className="text-3xl font-black md:text-5xl">{session.title}</h1>
                  </div>
                  <span className={`rounded-full border px-4 py-2 text-xs font-black ${statusClass(session.status)}`}>
                    {getVisioStatusLabel(session.status)}
                  </span>
                </div>

                <p className="mt-5 text-base font-semibold leading-8 text-slate-600">
                  {session.description || "Séance sécurisée entre le client ayant payé et son intervenant."}
                </p>

                <div className="mt-7 grid gap-3 sm:grid-cols-3">
                  <div className="rounded-3xl bg-orange-50 p-5">
                    <Clock className="mb-3 text-orange-700" size={22} />
                    <strong className="block text-lg font-black">{formatDate(session.start_at)}</strong>
                    <span className="text-xs font-bold text-slate-500">Date prévue</span>
                  </div>
                  <div className="rounded-3xl bg-slate-50 p-5">
                    <Clock className="mb-3 text-orange-700" size={22} />
                    <strong className="block text-lg font-black">{session.duration_minutes} min</strong>
                    <span className="text-xs font-bold text-slate-500">Durée</span>
                  </div>
                  <div className="rounded-3xl bg-slate-50 p-5">
                    <Users className="mb-3 text-orange-700" size={22} />
                    <strong className="block text-lg font-black">{paidClientCount}/{session.min_participants}</strong>
                    <span className="text-xs font-bold text-slate-500">Client(s) payé(s)</span>
                  </div>
                </div>

                {credentials?.token && credentials.server_url ? (
                  <div className="mt-7 overflow-hidden rounded-[2rem] bg-slate-950 p-2" style={{ minHeight: 560 }}>
                    <LiveKitRoom
                      serverUrl={credentials.server_url}
                      token={credentials.token}
                      connect
                      audio
                      video
                      data-lk-theme="default"
                      onDisconnected={() => setCredentials(null)}
                    >
                      <VideoConference />
                      <RoomAudioRenderer />
                    </LiveKitRoom>
                  </div>
                ) : (
                  <div className="mt-7 rounded-[2rem] border border-orange-100 bg-orange-50 p-6">
                    <h2 className="text-xl font-black">Accès à la salle</h2>
                    <p className="mt-2 text-sm font-semibold leading-6 text-slate-600">
                      L’API vérifie la réservation, le paiement et votre identité avant de générer un token LiveKit privé.
                    </p>
                    <div className="mt-5 flex flex-wrap gap-3">
                      {isCoach && canStart && session.status !== "live" && (
                        <button
                          type="button"
                          onClick={startRoom}
                          disabled={Boolean(action)}
                          className="inline-flex items-center gap-2 rounded-full bg-slate-950 px-5 py-3 text-sm font-black text-white disabled:opacity-60"
                        >
                          {action === "start" ? <Loader2 className="animate-spin" size={17} /> : <Play size={17} />}
                          Démarrer la séance
                        </button>
                      )}
                      {canJoin && (
                        <button
                          type="button"
                          onClick={joinRoom}
                          disabled={Boolean(action)}
                          className="inline-flex items-center gap-2 rounded-full bg-orange-600 px-5 py-3 text-sm font-black text-white disabled:opacity-60"
                        >
                          {action === "join" ? <Loader2 className="animate-spin" size={17} /> : <Video size={17} />}
                          Rejoindre la visio
                        </button>
                      )}
                      {isCoach && session.status === "live" && (
                        <button
                          type="button"
                          onClick={endRoom}
                          disabled={Boolean(action)}
                          className="inline-flex items-center gap-2 rounded-full bg-red-600 px-5 py-3 text-sm font-black text-white disabled:opacity-60"
                        >
                          {action === "end" ? <Loader2 className="animate-spin" size={17} /> : <Square size={17} />}
                          Terminer
                        </button>
                      )}
                    </div>

                    {!isCoach && !clientIsAuthorized && (
                      <div className="mt-5 rounded-2xl bg-white px-4 py-3 text-sm font-bold text-amber-700">
                        Votre accès n’est pas encore autorisé. Vérifiez que cette visio est liée à votre réservation payée.
                      </div>
                    )}
                  </div>
                )}
              </section>

              <aside className="grid content-start gap-5">
                <div className="rounded-[2rem] bg-white p-5 shadow-sm">
                  <h2 className="mb-4 flex items-center gap-2 text-xl font-black">
                    <ShieldCheck className="text-orange-700" size={22} /> Intervenant
                  </h2>
                  <strong className="block text-lg font-black">
                    {session.intervenant?.name || session.coach?.name || "Intervenant GotFit"}
                  </strong>
                  <span className="mt-1 block text-sm font-semibold text-slate-500">
                    {session.intervenant?.email || session.coach?.email || "Email non renseigné"}
                  </span>
                </div>

                <div className="rounded-[2rem] bg-white p-5 shadow-sm">
                  <h2 className="mb-4 flex items-center gap-2 text-xl font-black">
                    <Users className="text-orange-700" size={22} /> Participants
                  </h2>
                  <div className="grid gap-3">
                    {session.participants?.length ? (
                      session.participants.map((participant) => (
                        <div key={participant.id} className="rounded-2xl bg-slate-50 p-4">
                          <strong className="block text-sm font-black">
                            {participant.user?.name || `Utilisateur #${participant.user_id}`}
                          </strong>
                          <span className="mt-1 block text-xs font-bold text-slate-500">
                            {participant.role === "intervenant" || participant.role === "coach" ? "Intervenant" : "Client"}
                          </span>
                          <div className="mt-3 flex flex-wrap gap-2">
                            <span className={`rounded-full border px-3 py-1 text-[11px] font-black ${statusClass(participant.status)}`}>
                              {getParticipantStatusLabel(participant.status)}
                            </span>
                            <span className={`rounded-full border px-3 py-1 text-[11px] font-black ${statusClass(participant.payment_status)}`}>
                              {getPaymentStatusLabel(participant.payment_status)}
                            </span>
                          </div>
                        </div>
                      ))
                    ) : (
                      <p className="rounded-2xl bg-slate-50 p-4 text-sm font-bold text-slate-500">
                        Les participants seront ajoutés automatiquement par Laravel après paiement.
                      </p>
                    )}
                  </div>
                </div>
              </aside>
            </div>
          )}
        </div>
      </main>
      <Footer />
    </>
  );
}
