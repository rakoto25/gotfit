"use client";

import { clearAuth, getToken } from "@/lib/auth";
import { API_BASE_URL, getAssetUrl as resolveAssetUrl } from "@/lib/api-config";

export type ApiEnvelope<T> = T & {
  status?: number;
  message?: string;
  errors?: Record<string, string[]>;
};

export type GotfitUser = {
  id: number;
  name: string;
  email?: string | null;
  phone?: string | null;
  bio?: string | null;
  address?: string | null;
  photo?: string | null;
  photo_url?: string | null;
  cover_photo?: string | null;
  cover_photo_url?: string | null;
  account_status?: string | null;
  stripe_account_id?: string | null;
  stripe_onboarding_completed?: boolean;
};

export type Annonce = {
  id: number;
  titre?: string | null;
  title?: string | null;
  contenu?: string | null;
  description?: string | null;
  category?: string | null;
  type_prestation?: string | null;
  price?: string | number | null;
  duration?: string | number | null;
  is_online?: boolean | number | null;
  location?: string | null;
  city?: string | null;
  address?: string | null;
  image?: string | null;
  image_url?: string | null;
  status?: string | null;
  user_id?: number | null;
  intervenant?: GotfitUser | null;
  user?: GotfitUser | null;
  created_at?: string | null;
};

export type Reservation = {
  id: number;
  annonce_id?: number | null;
  client_id?: number | null;
  intervenant_id?: number | null;
  reservation_date?: string | null;
  reservation_time?: string | null;
  guests?: number | null;
  note?: string | null;
  price?: string | number | null;
  service_fee_amount?: string | number | null;
  commission_amount?: string | number | null;
  intervenant_amount?: string | number | null;
  total_client_amount?: string | number | null;
  currency?: string | null;
  status?: string | null;
  is_paid?: boolean | number;
  payment_status?: string | null;
  prestation_status?: string | null;
  payout_status?: string | null;
  payment_intent_id?: string | null;
  stripe_charge_id?: string | null;
  stripe_transfer_id?: string | null;
  paid_at?: string | null;
  validated_at?: string | null;
  validation_deadline?: string | null;
  transferred_at?: string | null;
  disputed_at?: string | null;
  dispute_reason?: string | null;
  refunded_at?: string | null;
  refund_reason?: string | null;
  annonce?: Annonce | null;
  client?: GotfitUser | null;
  intervenant?: GotfitUser | null;
  calendar_url?: string | null;
  visio_session_id?: number | null;
  visio_session?: { id?: number | null; status?: string | null } | null;
  start?: string | null;
  end?: string | null;
};

export type PlanningEvent = Reservation & {
  title?: string | null;
  start?: string | null;
  end?: string | null;
  calendar_url?: string | null;
};

export type ReservationPayload = {
  reservation?: Reservation;
  data?: Reservation;
  already_exists?: boolean;
};

export type PaymentIntentPayload = {
  clientSecret: string;
  client_secret?: string;
  payment_intent_id: string;
  amount: string | number;
  currency: string;
  reservation: Reservation;
};

type RequestOptions = {
  auth?: boolean;
  method?: "GET" | "POST" | "PUT" | "DELETE";
  body?: unknown;
};

function getApiError(payload: ApiEnvelope<unknown> | null, fallback: string) {
  const firstError = payload?.errors
    ? Object.values(payload.errors)[0]?.[0]
    : null;

  return firstError || payload?.message || fallback;
}

export function normalizeArray<T>(payload: unknown, keys: string[] = []): T[] {
  if (Array.isArray(payload)) return payload as T[];

  const data = payload as Record<string, unknown> | null;
  if (!data) return [];

  if (Array.isArray(data.data)) return data.data as T[];

  for (const key of keys) {
    if (Array.isArray(data[key])) return data[key] as T[];
  }

  return [];
}

export function getAssetUrl(url?: string | null) {
  return resolveAssetUrl(url);
}

export function formatMoney(value?: string | number | null, currency = "EUR") {
  if (value === null || value === undefined || value === "") return "0 €";

  const amount = Number(value);
  if (Number.isNaN(amount)) return `${value} €`;

  return new Intl.NumberFormat("fr-FR", {
    style: "currency",
    currency: currency.toUpperCase(),
  }).format(amount);
}

export function formatDate(value?: string | null) {
  if (!value) return "Non défini";

  try {
    return new Intl.DateTimeFormat("fr-FR", {
      day: "2-digit",
      month: "short",
      year: "numeric",
    }).format(new Date(value));
  } catch {
    return value;
  }
}

export function getAnnonceTitle(annonce?: Annonce | null) {
  return annonce?.titre || annonce?.title || "Annonce Gotfit";
}

export function getAnnonceDescription(annonce?: Annonce | null) {
  return annonce?.contenu || annonce?.description || "";
}

export async function apiRequest<T>(
  endpoint: string,
  options: RequestOptions = {}
): Promise<ApiEnvelope<T>> {
  const isFormData =
    typeof FormData !== "undefined" && options.body instanceof FormData;

  const headers: HeadersInit = {
    Accept: "application/json",
  };

  if (options.body !== undefined && !isFormData) {
    headers["Content-Type"] = "application/json";
  }

  if (options.auth) {
    const token = getToken();

    if (!token) {
      throw new Error("Veuillez vous connecter pour continuer.");
    }

    headers.Authorization = `Bearer ${token}`;
  }

  const url = `${API_BASE_URL}${endpoint.startsWith("/") ? endpoint : `/${endpoint}`}`;

  const response = await fetch(url, {
    method: options.method || "GET",
    headers,
    body:
      options.body === undefined
        ? undefined
        : isFormData
          ? (options.body as BodyInit)
          : JSON.stringify(options.body),
  });

  const payload = (await response.json().catch(() => null)) as
    | ApiEnvelope<T>
    | null;

  if (response.status === 401) {
    clearAuth();
    throw new Error("Session expirée. Veuillez vous reconnecter.");
  }

  if (!response.ok) {
    throw new Error(getApiError(payload, "Une erreur est survenue."));
  }

  return (payload || {}) as ApiEnvelope<T>;
}

export async function fetchAnnonces() {
  const payload = await apiRequest<{
    annonces?: Annonce[];
    data?: Annonce[];
  }>("/annonces");

  return normalizeArray<Annonce>(payload, ["annonces"]);
}

export async function fetchAnnonce(id: string | number) {
  try {
    const payload = await apiRequest<{
      annonce?: Annonce;
      data?: Annonce;
    }>(`/annonces/${id}/detail`);

    const annonce = payload.annonce || payload.data;

    if (!annonce?.id) {
      throw new Error("Annonce introuvable.");
    }

    return annonce;
  } catch (error) {
    const annonces = await fetchAnnonces();
    const annonce = annonces.find((item) => String(item.id) === String(id));

    if (!annonce) {
      throw error;
    }

    return annonce;
  }
}

export async function reserveAnnonce(
  annonceId: string | number,
  body: {
    reservation_date: string;
    reservation_time: string;
    guests: number;
    note?: string;
  }
) {
  const payload = await apiRequest<ReservationPayload>(
    `/annonces/${annonceId}/reserve`,
    {
      method: "PUT",
      auth: true,
      body,
    }
  );

  const reservation = payload.reservation || payload.data;

  if (!reservation?.id) {
    throw new Error("La réservation a été créée, mais son ID est introuvable.");
  }

  return reservation;
}

export async function createPaymentIntent(reservationId: number) {
  const payload = await apiRequest<PaymentIntentPayload>(
    "/create-payment-intent",
    {
      method: "POST",
      auth: true,
      body: {
        reservation_id: reservationId,
      },
    }
  );

  const clientSecret = payload.clientSecret || payload.client_secret;

  if (!clientSecret) {
    throw new Error("Le paiement a été initialisé, mais la clé Stripe est introuvable.");
  }

  return { ...payload, clientSecret };
}

export async function reserveAndCreatePaymentIntent(
  annonceId: string | number,
  body: {
    reservation_date: string;
    reservation_time: string;
    guests: number;
    note?: string;
  }
) {
  const reservation = await reserveAnnonce(annonceId, body);
  const paymentIntent = await createPaymentIntent(reservation.id);

  return {
    reservation,
    paymentIntent,
  };
}

export async function fetchClientReservations() {
  const payload = await apiRequest<{
    reservations?: Reservation[];
    data?: Reservation[];
  }>("/reservation/client", {
    auth: true,
  });

  return normalizeArray<Reservation>(payload, ["reservations"]);
}

export async function fetchIntervenantReservations() {
  const payload = await apiRequest<{
    reservations?: Reservation[];
    data?: Reservation[];
  }>("/reservation/intervenant", {
    auth: true,
  });

  return normalizeArray<Reservation>(payload, ["reservations"]);
}

export async function fetchPlanning(params?: { from?: string; to?: string }) {
  const search = new URLSearchParams();

  if (params?.from) search.set("from", params.from);
  if (params?.to) search.set("to", params.to);

  const endpoint = `/planning${search.toString() ? `?${search.toString()}` : ""}`;
  const payload = await apiRequest<{
    reservations?: PlanningEvent[];
    data?: PlanningEvent[];
  }>(endpoint, {
    auth: true,
  });

  return normalizeArray<PlanningEvent>(payload, ["reservations"]);
}

export async function finishReservation(reservationId: number) {
  return apiRequest<{ reservation?: Reservation }>(
    `/reservation/${reservationId}/terminer`,
    {
      method: "PUT",
      auth: true,
    }
  );
}

export function getReservationCalendarUrl(reservationId: number) {
  return `${API_BASE_URL}/reservation/${reservationId}/calendar.ics`;
}

export function isReservationPaid(reservation: Reservation) {
  return (
    reservation.payment_status === "paid" ||
    reservation.is_paid === true ||
    reservation.is_paid === 1
  );
}

export function isReservationOnline(reservation: Reservation) {
  const type = (reservation.annonce?.type_prestation || "").toLowerCase();
  return Boolean(
    reservation.annonce?.is_online ||
    ["online", "en_ligne", "en ligne", "visio", "video"].includes(type)
  );
}

export function canAccessReservationVisio(reservation: Reservation) {
  const status = reservation.prestation_status || reservation.status || "";
  return (
    isReservationPaid(reservation) &&
    isReservationOnline(reservation) &&
    Boolean(reservation.visio_session_id || reservation.visio_session?.id) &&
    !["cancelled", "annulee", "refunded", "remboursee"].includes(status)
  );
}

export function canAddReservationToCalendar(reservation: Reservation) {
  const paymentPaid = isReservationPaid(reservation);
  const status = reservation.prestation_status || reservation.status || "";

  return (
    paymentPaid &&
    !["cancelled", "annulee", "refunded", "remboursee", "payment_failed"].includes(status)
  );
}

export function getReservationVisioHref(reservation: Reservation) {
  const sessionId = reservation.visio_session_id || reservation.visio_session?.id;
  return sessionId ? `/visio/${sessionId}` : "/visio";
}

function escapeIcsText(value: string) {
  return value
    .replace(/\\/g, "\\\\")
    .replace(/\n/g, "\\n")
    .replace(/,/g, "\\,")
    .replace(/;/g, "\\;");
}

function getReservationDateTime(reservation: Reservation) {
  const date = reservation.reservation_date || reservation.start?.slice(0, 10);
  const time = reservation.reservation_time || reservation.start?.slice(11, 19) || "09:00:00";
  if (!date) return null;

  const start = new Date(`${date}T${time}`);
  if (Number.isNaN(start.getTime())) return null;

  const duration = Number(reservation.annonce?.duration || 60);
  const end = reservation.end ? new Date(reservation.end) : new Date(start.getTime() + duration * 60_000);
  return { start, end };
}

function toIcsUtc(value: Date) {
  return value.toISOString().replace(/[-:]/g, "").replace(/\.\d{3}Z$/, "Z");
}

function createReservationIcs(reservation: Reservation) {
  const range = getReservationDateTime(reservation);
  if (!range) {
    throw new Error("La date ou l’heure de cette réservation est incomplète.");
  }

  const title = getAnnonceTitle(reservation.annonce);
  const online = Boolean(reservation.annonce?.is_online);
  const location = online
    ? "Séance en ligne Gotfit"
    : reservation.annonce?.location || reservation.annonce?.address || reservation.annonce?.city || "Gotfit";
  const description = online
    ? "Retrouvez l’accès à la séance depuis votre espace Gotfit, rubrique Visio."
    : "Réservation Gotfit confirmée.";

  return [
    "BEGIN:VCALENDAR",
    "VERSION:2.0",
    "PRODID:-//Gotfit//Reservation//FR",
    "CALSCALE:GREGORIAN",
    "METHOD:PUBLISH",
    "BEGIN:VEVENT",
    `UID:gotfit-reservation-${reservation.id}@gotfit.tech`,
    `DTSTAMP:${toIcsUtc(new Date())}`,
    `DTSTART:${toIcsUtc(range.start)}`,
    `DTEND:${toIcsUtc(range.end)}`,
    `SUMMARY:${escapeIcsText(title)}`,
    `DESCRIPTION:${escapeIcsText(description)}`,
    `LOCATION:${escapeIcsText(location)}`,
    "END:VEVENT",
    "END:VCALENDAR",
    "",
  ].join("\r\n");
}

function saveCalendarBlob(blob: Blob, reservationId: number) {
  const objectUrl = URL.createObjectURL(blob);
  const anchor = document.createElement("a");
  anchor.href = objectUrl;
  anchor.download = `gotfit-reservation-${reservationId}.ics`;
  document.body.appendChild(anchor);
  anchor.click();
  anchor.remove();
  window.setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
}

export async function downloadReservationCalendar(reservation: Reservation) {
  if (!canAddReservationToCalendar(reservation)) {
    throw new Error("Le calendrier sera disponible après confirmation du paiement.");
  }

  const token = getToken();

  try {
    const response = await fetch(
      reservation.calendar_url || getReservationCalendarUrl(reservation.id),
      {
        headers: {
          Accept: "text/calendar",
          ...(token ? { Authorization: `Bearer ${token}` } : {}),
        },
      }
    );

    if (!response.ok) {
      throw new Error(`Calendrier API indisponible (${response.status}).`);
    }

    const content = await response.blob();
    saveCalendarBlob(
      content.type ? content : new Blob([content], { type: "text/calendar;charset=utf-8" }),
      reservation.id
    );
    return;
  } catch {
    const fallback = createReservationIcs(reservation);
    saveCalendarBlob(
      new Blob([fallback], { type: "text/calendar;charset=utf-8" }),
      reservation.id
    );
  }
}

export async function confirmPrestation(reservationId: number) {
  return apiRequest<{ reservation?: Reservation }>(
    `/reservation/${reservationId}/confirm-prestation`,
    {
      method: "POST",
      auth: true,
    }
  );
}

export async function disputePrestation(reservationId: number, reason: string) {
  return apiRequest<{ reservation?: Reservation }>(
    `/reservation/${reservationId}/dispute`,
    {
      method: "POST",
      auth: true,
      body: {
        reason,
      },
    }
  );
}

export type MessageUser = Pick<
  GotfitUser,
  "id" | "name" | "email" | "photo_url" | "photo"
> & {
  role?: string | null;
};

export type MessageReaction = {
  id?: number;
  message_id?: number;
  user_id?: number;
  reaction: "like" | "dislike" | "love" | "haha" | "wow" | "sad" | "angry";
  user?: MessageUser | null;
};

export type MessageItem = {
  id: number;
  conversation_id: number;
  sender_id: number;
  parent_id?: number | null;
  message?: string | null;
  type?: "text" | "image" | "video" | "mixed" | string | null;
  media_url?: string | null;
  media_full_url?: string | null;
  media_type?: "image" | "video" | string | null;
  sender?: MessageUser | null;
  parent?: MessageItem | null;
  reactions?: MessageReaction[];
  created_at?: string | null;
  updated_at?: string | null;
};

export type Conversation = {
  id: number;
  client_id?: number | null;
  intervenant_id?: number | null;
  client?: MessageUser | null;
  intervenant?: MessageUser | null;
  messages?: MessageItem[];
  created_at?: string | null;
  updated_at?: string | null;
};

export type MessageContact = MessageUser & {
  role?: string | null;
};

export async function fetchMessageContacts() {
  const payload = await apiRequest<{
    contacts?: MessageContact[];
    data?: MessageContact[];
  }>("/message/contacts", {
    auth: true,
  });

  return normalizeArray<MessageContact>(payload, ["contacts"]);
}

export async function fetchConversations() {
  const payload = await apiRequest<{
    conversations?: Conversation[];
    data?: Conversation[];
  }>("/conversation", {
    auth: true,
  });

  return normalizeArray<Conversation>(payload, ["conversations"]);
}

export async function createConversation(otherUserId: string | number) {
  const payload = await apiRequest<{ conversation?: Conversation }>(
    `/conversation/${otherUserId}`,
    {
      method: "POST",
      auth: true,
    }
  );

  if (!payload.conversation?.id) {
    throw new Error("Impossible de créer ou récupérer la conversation.");
  }

  return payload.conversation;
}

export async function fetchMessages(conversationId: string | number) {
  const payload = await apiRequest<{
    messages?: MessageItem[];
    data?: MessageItem[];
  }>(`/message/${conversationId}`, {
    auth: true,
  });

  return normalizeArray<MessageItem>(payload, ["messages"]);
}

export async function sendConversationMessage(
  conversationId: string | number,
  body: {
    message?: string;
    parent_id?: number | null;
    media?: File | null;
  }
) {
  const formData = new FormData();

  if (body.message?.trim()) {
    formData.append("message", body.message.trim());
  }

  if (body.parent_id) {
    formData.append("parent_id", String(body.parent_id));
  }

  if (body.media) {
    formData.append("media", body.media);
  }

  const payload = await apiRequest<{ message?: MessageItem }>(
    `/message/${conversationId}`,
    {
      method: "POST",
      auth: true,
      body: formData,
    }
  );

  if (!payload.message?.id) {
    throw new Error("Le message a été envoyé, mais la réponse API est incomplète.");
  }

  return payload.message;
}

export async function reactToMessage(
  messageId: string | number,
  reaction: MessageReaction["reaction"]
) {
  return apiRequest<{ message?: MessageItem; reaction?: MessageReaction }>(
    `/message/${messageId}/reaction`,
    {
      method: "POST",
      auth: true,
      body: {
        reaction,
      },
    }
  );
}

export async function removeMessageReaction(messageId: string | number) {
  return apiRequest<{ message?: MessageItem }>(`/message/${messageId}/reaction`, {
    method: "DELETE",
    auth: true,
  });
}
