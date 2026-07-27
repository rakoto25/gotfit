import { getAssetUrl } from "@/lib/api-config";
import type { User } from "@/types/auth";

export type Intervenant = User & {
  phone?: string | null;
  address?: string | null;
  city?: string | null;
  location?: string | null;
  bio?: string | null;
  about?: string | null;
  description?: string | null;
  contenu?: string | null;
  photo?: string | null;
  photo_url?: string | null;
  cover_photo?: string | null;
  cover_photo_url?: string | null;
  account_status?: string | null;
  speciality?: string | null;
  specialty?: string | null;
  service?: string | null;
  services?: string[] | string | null;
  category?: string | null;
  type_prestation?: string | null;
  profession?: string | null;
  job_title?: string | null;
  title?: string | null;
  coach_title?: string | null;
  coach_short_description?: string | null;
  coach_speciality?: string | null;
  coach_experience_years?: number | string | null;
  coach_certifications?: string[] | string | null;
  coach_languages?: string[] | string | null;
  presentation_video?: string | null;
  presentation_video_url?: string | null;
  presentation_video_duration_seconds?: number | string | null;
  rating?: number | string | null;
  reviews_count?: number | string | null;
  created_at?: string | null;
};

export type ApiIntervenantsResponse = {
  success?: boolean;
  status?: number;
  message?: string;
  data?: Intervenant[] | Intervenant;
  intervenant?: Intervenant;
  user?: Intervenant;
  users?: Intervenant[];
  intervenants?: Intervenant[];
};

export function normalizeIntervenants(
  payload: ApiIntervenantsResponse | Intervenant[] | null
): Intervenant[] {
  if (!payload) return [];
  if (Array.isArray(payload)) return payload;
  if (Array.isArray(payload.data)) return payload.data;
  if (payload.data && typeof payload.data === "object") return [payload.data];
  if (payload.intervenant) return [payload.intervenant];
  if (payload.user) return [payload.user];
  if (Array.isArray(payload.intervenants)) return payload.intervenants;
  if (Array.isArray(payload.users)) return payload.users;
  return [];
}

export function normalizeProfile(payload: unknown): Intervenant | null {
  if (!payload || typeof payload !== "object") return null;

  const data = payload as {
    id?: number;
    user?: Intervenant;
    data?: Intervenant | { user?: Intervenant };
    intervenant?: Intervenant;
  };

  if (data.user) return data.user;
  if (data.intervenant) return data.intervenant;
  if (data.data && typeof data.data === "object" && "user" in data.data && data.data.user) {
    return data.data.user;
  }
  if (data.data && typeof data.data === "object" && "id" in data.data) {
    return data.data as Intervenant;
  }
  if (data.id) return data as Intervenant;

  return null;
}

export function cleanText(value?: string | number | null) {
  const text = String(value ?? "").trim();
  return text || null;
}

export function parseList(value?: string[] | string | null) {
  if (!value) return [] as string[];

  const rawItems = Array.isArray(value) ? value : String(value).split(/[;,\n]/);

  return rawItems
    .map((item) => cleanText(item))
    .filter(Boolean) as string[];
}

export function getFullUrl(url?: string | null) {
  return getAssetUrl(url);
}

export function getIntervenantPhoto(intervenant: Intervenant) {
  return getFullUrl(intervenant.photo_url || intervenant.photo);
}

export function getIntervenantCover(intervenant: Intervenant) {
  return getFullUrl(intervenant.cover_photo_url || intervenant.cover_photo);
}

export function getIntervenantVideo(intervenant: Intervenant) {
  return getFullUrl(intervenant.presentation_video_url || intervenant.presentation_video);
}

export function getCoachTitle(intervenant: Intervenant) {
  return (
    cleanText(
      intervenant.coach_title ||
        intervenant.title ||
        intervenant.job_title ||
        intervenant.profession
    ) || "Intervenant Gotfit"
  );
}

export function getCoachSpeciality(intervenant: Intervenant) {
  const services = Array.isArray(intervenant.services)
    ? intervenant.services[0]
    : intervenant.services;

  return (
    cleanText(
      intervenant.coach_speciality ||
        intervenant.speciality ||
        intervenant.specialty ||
        intervenant.service ||
        services ||
        intervenant.category ||
        intervenant.type_prestation ||
        intervenant.profession ||
        intervenant.job_title ||
        intervenant.coach_title
    ) || "Spécialité à compléter"
  );
}

export function getCoachDescription(intervenant: Intervenant) {
  return (
    cleanText(
      intervenant.coach_short_description ||
        intervenant.bio ||
        intervenant.about ||
        intervenant.description ||
        intervenant.contenu
    ) || "Présentation à compléter depuis l’espace profil intervenant."
  );
}

export function getCoachExperience(intervenant: Intervenant) {
  const value = Number(intervenant.coach_experience_years ?? 0);
  if (!Number.isFinite(value) || value <= 0) return null;
  return value <= 1 ? `${value} an d’expérience` : `${value} ans d’expérience`;
}

export function getCoachLanguages(intervenant: Intervenant) {
  return parseList(intervenant.coach_languages);
}

export function getCoachCertifications(intervenant: Intervenant) {
  return parseList(intervenant.coach_certifications);
}

export function getLocation(intervenant: Intervenant) {
  return (
    cleanText(intervenant.location || intervenant.city || intervenant.address) ||
    "Localisation à compléter"
  );
}

export function getRating(intervenant: Intervenant) {
  const value = Number(intervenant.rating ?? 0);
  return Number.isFinite(value) && value > 0 ? value : 0;
}

export function getReviewsCount(intervenant: Intervenant) {
  const value = Number(intervenant.reviews_count ?? 0);
  return Number.isFinite(value) && value > 0 ? value : 0;
}

export function getVideoDurationLabel(intervenant: Intervenant) {
  const value = Number(intervenant.presentation_video_duration_seconds ?? 0);
  if (!Number.isFinite(value) || value <= 0) return "60s max";
  return `${Math.round(value)}s / 60s max`;
}

export function getStatusLabel(status?: string | null) {
  const normalized = String(status || "").toLowerCase();

  const labels: Record<string, string> = {
    approved: "Profil vérifié",
    valide: "Profil vérifié",
    validé: "Profil vérifié",
    active: "Actif",
    pending: "En attente",
    en_attente: "En attente",
    rejected: "Refusé",
    refused: "Refusé",
    inactive: "Inactif",
  };

  return labels[normalized] || "Profil Gotfit";
}

export function isPublicIntervenant(intervenant: Intervenant) {
  const status = String(intervenant.account_status || "").toLowerCase();

  if (["rejected", "refused", "inactive", "disabled", "blocked"].includes(status)) {
    return false;
  }

  if (!intervenant.roles?.length) {
    return true;
  }

  return intervenant.roles.some((role) => {
    const name = String(role.name || "").toLowerCase();
    const slug = String(role.slug || "").toLowerCase();
    return name.includes("intervenant") || slug.includes("intervenant");
  });
}

export function getSpecialityOptions(intervenants: Intervenant[]) {
  const options = intervenants
    .map(getCoachSpeciality)
    .filter((item) => item !== "Spécialité à compléter");

  return ["Tous", ...Array.from(new Set(options)).sort((a, b) => a.localeCompare(b))];
}
