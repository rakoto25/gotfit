"use client";

import { apiRequest, normalizeArray, type GotfitUser } from "@/lib/marketplace";

export type VisioParticipant = {
  id: number;
  visio_session_id: number;
  user_id: number;
  reservation_id?: number | null;
  role: "coach" | "intervenant" | "client" | "participant" | string;
  status: string;
  payment_status: string;
  amount?: string | number | null;
  currency?: string | null;
  payment_intent_id?: string | null;
  paid_at?: string | null;
  joined_at?: string | null;
  left_at?: string | null;
  cancelled_at?: string | null;
  user?: GotfitUser | null;
};

export type VisioSession = {
  id: number;
  coach_id?: number | null;
  intervenant_id?: number | null;
  reservation_id?: number | null;
  annonce_id?: number | null;
  title: string;
  description?: string | null;
  start_at: string;
  scheduled_at?: string | null;
  duration_minutes: number;
  min_participants: number;
  minimum_participants?: number | null;
  max_participants?: number | null;
  price?: string | number | null;
  currency?: string | null;
  session_type?: "individual" | "group" | string | null;
  status: "draft" | "open" | "confirmed" | "live" | "ended" | "cancelled" | string;
  provider?: string | null;
  provider_room_id?: string | null;
  room_name?: string | null;
  join_url?: string | null;
  started_at?: string | null;
  ended_at?: string | null;
  cancellation_reason?: string | null;
  paid_participants_count?: number;
  reserved_participants_count?: number;
  available_places?: number | null;
  is_confirmed_by_minimum?: boolean;
  coach?: GotfitUser | null;
  intervenant?: GotfitUser | null;
  participants?: VisioParticipant[];
};

export type CreateVisioSessionPayload = {
  title: string;
  description?: string;
  start_at: string;
  duration_minutes: number;
  min_participants: number;
  max_participants?: number | null;
  price: number;
  currency: string;
};

export type VisioJoinPayload = {
  provider?: string | null;
  server_url?: string | null;
  room_name?: string | null;
  join_url?: string | null;
  token?: string | null;
  participant_token?: string | null;
  session?: VisioSession;
  participant?: VisioParticipant;
};

function normalizeParticipant(raw: any): VisioParticipant {
  return {
    ...raw,
    id: Number(raw?.id || 0),
    visio_session_id: Number(raw?.visio_session_id || raw?.session_id || 0),
    user_id: Number(raw?.user_id || raw?.user?.id || 0),
    role: raw?.role || "participant",
    status: raw?.status || "reserved",
    payment_status: raw?.payment_status || (raw?.is_paid ? "paid" : "unpaid"),
  };
}

export function normalizeVisioSession(raw: any): VisioSession {
  const startAt = raw?.start_at || raw?.scheduled_at || raw?.starts_at || raw?.date || new Date().toISOString();
  const minimum = Number(raw?.min_participants ?? raw?.minimum_participants ?? 1);
  const coach = raw?.coach || raw?.intervenant || null;

  return {
    ...raw,
    id: Number(raw?.id || 0),
    coach_id: raw?.coach_id ?? raw?.intervenant_id ?? coach?.id ?? null,
    intervenant_id: raw?.intervenant_id ?? raw?.coach_id ?? coach?.id ?? null,
    title: raw?.title || raw?.name || "Séance visio GotFit",
    start_at: startAt,
    scheduled_at: raw?.scheduled_at || startAt,
    duration_minutes: Number(raw?.duration_minutes || raw?.duration || 60),
    min_participants: minimum,
    minimum_participants: minimum,
    status: raw?.status || "confirmed",
    coach,
    intervenant: raw?.intervenant || raw?.coach || null,
    participants: Array.isArray(raw?.participants)
      ? raw.participants.map(normalizeParticipant)
      : [],
  };
}

function getSessionFromPayload(payload: any): VisioSession | null {
  const raw = payload?.session || payload?.data?.session || payload?.data || payload;
  return raw?.id ? normalizeVisioSession(raw) : null;
}

export function getVisioStatusLabel(status?: string | null) {
  const labels: Record<string, string> = {
    draft: "Brouillon",
    open: "Ouverte",
    confirmed: "Confirmée",
    live: "En direct",
    ended: "Terminée",
    cancelled: "Annulée",
  };
  return status ? labels[status] || status : "Non défini";
}

export function getParticipantStatusLabel(status?: string | null) {
  const labels: Record<string, string> = {
    invited: "Invité",
    reserved: "Réservé",
    confirmed: "Confirmé",
    paid: "Payé",
    joined: "Connecté",
    left: "Sorti",
    cancelled: "Annulé",
    no_show: "Absent",
  };
  return status ? labels[status] || status : "Non défini";
}

export function getPaymentStatusLabel(status?: string | null) {
  const labels: Record<string, string> = {
    not_required: "Non requis",
    unpaid: "Non payé",
    pending: "En attente",
    paid: "Payé",
    refunded: "Remboursé",
  };
  return status ? labels[status] || status : "Non défini";
}

export async function fetchVisioSessions(mine = false) {
  const endpoint = mine ? "/visio/my-sessions" : "/visio/sessions";
  let payload: any;
  try {
    payload = await apiRequest<any>(endpoint, { auth: mine });
  } catch (error) {
    if (!mine) throw error;
    payload = await apiRequest<any>("/visio/sessions?mine=1&upcoming=0", { auth: true });
  }

  const list = normalizeArray<any>(payload, ["sessions", "visio_sessions"]);
  return list.map(normalizeVisioSession).filter((item) => item.id > 0);
}

export async function fetchVisioSession(id: string | number) {
  const payload = await apiRequest<any>(`/visio/sessions/${id}`, { auth: true });
  const session = getSessionFromPayload(payload);
  if (!session) throw new Error("Séance visio introuvable.");
  return session;
}

export async function createVisioSession(body: CreateVisioSessionPayload) {
  const payload = await apiRequest<any>("/visio/sessions", {
    method: "POST",
    auth: true,
    body,
  });
  const session = getSessionFromPayload(payload);
  if (!session) throw new Error("La séance a été créée, mais la réponse API est incomplète.");
  return session;
}

export async function reserveVisioSession(id: string | number) {
  const payload = await apiRequest<any>(`/visio/sessions/${id}/reserve`, {
    method: "POST",
    auth: true,
  });
  return {
    ...payload,
    session: getSessionFromPayload(payload) || undefined,
    participant: payload?.participant ? normalizeParticipant(payload.participant) : undefined,
  };
}

export async function startVisioSession(id: string | number) {
  const payload = await apiRequest<any>(`/visio/sessions/${id}/start`, {
    method: "POST",
    auth: true,
  });
  return getSessionFromPayload(payload);
}

export async function joinVisioSession(id: string | number): Promise<VisioJoinPayload> {
  const payload = await apiRequest<any>(`/visio/sessions/${id}/join`, {
    method: "POST",
    auth: true,
  });
  return {
    ...payload,
    token: payload.token || payload.participant_token || payload.data?.token || payload.data?.participant_token || null,
    participant_token: payload.participant_token || payload.token || payload.data?.participant_token || payload.data?.token || null,
    server_url: payload.server_url || payload.data?.server_url || null,
    room_name: payload.room_name || payload.data?.room_name || payload.session?.room_name || null,
    provider: payload.provider || payload.data?.provider || "livekit",
    session: getSessionFromPayload(payload) || undefined,
    participant: payload.participant ? normalizeParticipant(payload.participant) : undefined,
  };
}

export async function endVisioSession(id: string | number) {
  const payload = await apiRequest<any>(`/visio/sessions/${id}/end`, {
    method: "POST",
    auth: true,
  });
  return getSessionFromPayload(payload);
}

export async function markVisioParticipantPaid(sessionId: string | number, participantId: string | number) {
  return apiRequest<any>(`/visio/sessions/${sessionId}/participants/${participantId}/paid`, {
    method: "POST",
    auth: true,
  });
}
