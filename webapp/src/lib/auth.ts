import type { User } from "@/types/auth";

const TOKEN_KEY = "gotfit_token";
const USER_KEY = "gotfit_user";

export function saveAuth(token: string, user: User) {
  if (typeof window === "undefined") return;

  localStorage.setItem(TOKEN_KEY, token);
  localStorage.setItem(USER_KEY, JSON.stringify(user));
}

export function getToken(): string | null {
  if (typeof window === "undefined") return null;

  return localStorage.getItem(TOKEN_KEY);
}

export function getCurrentUser(): User | null {
  if (typeof window === "undefined") return null;

  const rawUser = localStorage.getItem(USER_KEY);

  if (!rawUser) return null;

  try {
    return JSON.parse(rawUser) as User;
  } catch {
    return null;
  }
}

export function clearAuth() {
  if (typeof window === "undefined") return;

  localStorage.removeItem(TOKEN_KEY);
  localStorage.removeItem(USER_KEY);
}

export function isAuthenticated(): boolean {
  return Boolean(getToken());
}

export function hasRole(user: User | null, role: string): boolean {
  if (!user?.roles?.length) return false;

  return user.roles.some((item) => {
    const roleName = item.name?.toLowerCase();
    const roleSlug = item.slug?.toLowerCase();
    const searchedRole = role.toLowerCase();

    return roleName === searchedRole || roleSlug === searchedRole;
  });
}

export function getPostAuthRoute(user: User): string {
  if (hasRole(user, "admin")) return "/admin/dashboard";
  if (hasRole(user, "intervenant")) return "/intervenant/dashboard";
  if (hasRole(user, "client")) return "/client/dashboard";

  return "/profile";
}
