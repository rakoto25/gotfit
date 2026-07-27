"use client";

import { apiRequest, normalizeArray, Reservation, GotfitUser } from "@/lib/marketplace";

export type ClientNote = {
  id: number;
  client_id: number;
  intervenant_id?: number | null;
  reservation_id?: number | null;
  author_id: number;
  visibility: "private" | "shared";
  title?: string | null;
  content: string;
  is_pinned?: boolean;
  created_at?: string | null;
  updated_at?: string | null;
  author?: Pick<GotfitUser, "id" | "name" | "email"> | null;
  intervenant?: Pick<GotfitUser, "id" | "name" | "email"> | null;
  reservation?: {
    id: number;
    reservation_date?: string | null;
    reservation_time?: string | null;
    status?: string | null;
  } | null;
};

export type ClientOnboarding = {
  id?: number;
  client_id?: number;
  goals?: string[] | null;
  level?: string | null;
  training_preferences?: Record<string, unknown> | null;
  availability?: Record<string, unknown> | null;
  health_constraints?: Record<string, unknown> | null;
  measurements?: Record<string, unknown> | null;
  lifestyle?: Record<string, unknown> | null;
  emergency_contact?: Record<string, unknown> | null;
  answers?: Record<string, unknown> | null;
  is_completed?: boolean;
  completed_at?: string | null;
  created_at?: string | null;
  updated_at?: string | null;
};

export type ClientHistory = {
  client?: GotfitUser;
  summary?: {
    reservations_count?: number;
    completed_reservations_count?: number;
    paid_reservations_count?: number;
    notes_count?: number;
  };
  reservations?: Reservation[];
};

export type SaveOnboardingPayload = {
  goals?: string[];
  level?: string;
  training_preferences?: Record<string, unknown>;
  availability?: Record<string, unknown>;
  health_constraints?: Record<string, unknown>;
  measurements?: Record<string, unknown>;
  lifestyle?: Record<string, unknown>;
  emergency_contact?: Record<string, unknown>;
  answers?: Record<string, unknown>;
  is_completed?: boolean;
};

export async function fetchMyOnboarding() {
  const payload = await apiRequest<{ onboarding?: ClientOnboarding }>(
    "/client/onboarding",
    { auth: true }
  );

  return payload.onboarding || null;
}

export async function saveMyOnboarding(body: SaveOnboardingPayload) {
  const payload = await apiRequest<{ onboarding?: ClientOnboarding }>(
    "/client/onboarding",
    {
      method: "PUT",
      auth: true,
      body,
    }
  );

  return payload.onboarding || null;
}

export async function fetchClientHistory(clientId: number | string) {
  const payload = await apiRequest<ClientHistory>(`/clients/${clientId}/history`, {
    auth: true,
  });

  return {
    client: payload.client,
    summary: payload.summary,
    reservations: normalizeArray<Reservation>(payload, ["reservations"]),
  };
}

export async function fetchClientNotes(clientId: number | string) {
  const payload = await apiRequest<{ notes?: ClientNote[] }>(
    `/clients/${clientId}/notes`,
    { auth: true }
  );

  return normalizeArray<ClientNote>(payload, ["notes"]);
}

export async function fetchClientOnboarding(clientId: number | string) {
  const payload = await apiRequest<{ onboarding?: ClientOnboarding | null }>(
    `/clients/${clientId}/onboarding`,
    { auth: true }
  );

  return payload.onboarding || null;
}

export async function createClientNote(
  clientId: number | string,
  body: {
    title?: string;
    content: string;
    visibility: "private" | "shared";
    reservation_id?: number | null;
    is_pinned?: boolean;
  }
) {
  const payload = await apiRequest<{ note?: ClientNote }>(
    `/clients/${clientId}/notes`,
    {
      method: "POST",
      auth: true,
      body,
    }
  );

  if (!payload.note?.id) {
    throw new Error("La note a été enregistrée, mais la réponse API est incomplète.");
  }

  return payload.note;
}

export async function updateClientNote(
  noteId: number | string,
  body: Partial<Pick<ClientNote, "title" | "content" | "visibility" | "is_pinned">>
) {
  const payload = await apiRequest<{ note?: ClientNote }>(`/client-notes/${noteId}`, {
    method: "PUT",
    auth: true,
    body,
  });

  return payload.note || null;
}

export async function deleteClientNote(noteId: number | string) {
  return apiRequest(`/client-notes/${noteId}`, {
    method: "DELETE",
    auth: true,
  });
}

export async function fetchIntervenantReservations() {
  const payload = await apiRequest<{ reservations?: Reservation[] }>(
    "/reservation/intervenant",
    { auth: true }
  );

  return normalizeArray<Reservation>(payload, ["reservations"]);
}
