export const API_BASE_URL = normalizeApiBaseUrl(
  process.env.NEXT_PUBLIC_API_URL || "https://api.gotfit.tech/api"
);

export const API_ORIGIN_URL = API_BASE_URL.replace(/\/api\/?$/, "");

export const LOGO_URL = "/brand/gotfit-logo.png";

export function normalizeApiBaseUrl(url: string) {
  const cleanUrl = String(url || "").trim().replace(/\/+$/, "");

  if (!cleanUrl) {
    return "https://api.gotfit.tech/api";
  }

  return cleanUrl.endsWith("/api") ? cleanUrl : `${cleanUrl}/api`;
}

export function getAssetUrl(url?: string | null) {
  if (!url) return "";

  if (url.startsWith("http://") || url.startsWith("https://")) {
    return url;
  }

  return `${API_ORIGIN_URL}${url.startsWith("/") ? url : `/${url}`}`;
}

export function getNetworkErrorMessage(context = "charger les données") {
  return `Impossible de ${context} depuis l'API Gotfit. Vérifiez que le backend est accessible et que NEXT_PUBLIC_API_URL pointe vers https://api.gotfit.tech/api.`;
}
