"use client";

import Script from "next/script";
import { useRouter } from "next/navigation";
import { useCallback, useEffect, useRef, useState } from "react";
import { LoaderCircle, ShieldCheck } from "lucide-react";

import { getPostAuthRoute, saveAuth } from "@/lib/auth";
import { API_BASE_URL } from "@/lib/api-config";
import type { User } from "@/types/auth";

type RegisterRole = "client" | "intervenant";

type GoogleCredentialResponse = {
  credential: string;
  select_by?: string;
};

type GoogleAccountsId = {
  initialize: (options: {
    client_id: string;
    callback: (response: GoogleCredentialResponse) => void;
    context?: "signin" | "signup" | "use";
    auto_select?: boolean;
    cancel_on_tap_outside?: boolean;
  }) => void;
  renderButton: (
    element: HTMLElement,
    options: {
      type: "standard";
      theme: "outline";
      size: "large";
      text: "signin_with" | "signup_with" | "continue_with";
      shape: "pill";
      logo_alignment: "left";
      width: number;
    }
  ) => void;
};

declare global {
  interface Window {
    google?: {
      accounts: {
        id: GoogleAccountsId;
      };
    };
  }
}

type GoogleAuthResponse = {
  token?: string;
  user?: User;
  message?: string;
  errors?: Record<string, string[]>;
};

type GoogleSignInButtonProps = {
  flow: "login" | "register";
  role?: RegisterRole;
  onError?: (message: string) => void;
};

function getResponseError(result: GoogleAuthResponse | null) {
  const validationMessage = result?.errors
    ? Object.values(result.errors)[0]?.[0]
    : null;

  return (
    validationMessage ||
    result?.message ||
    "La connexion Google n’a pas pu aboutir. Veuillez réessayer."
  );
}

export default function GoogleSignInButton({
  flow,
  role = "client",
  onError,
}: GoogleSignInButtonProps) {
  const router = useRouter();
  const buttonRef = useRef<HTMLDivElement>(null);
  const [loading, setLoading] = useState(false);
  const [scriptError, setScriptError] = useState(false);

  const clientId = process.env.NEXT_PUBLIC_GOOGLE_CLIENT_ID;

  const handleCredential = useCallback(
    async ({ credential }: GoogleCredentialResponse) => {
      if (loading) return;

      setLoading(true);
      onError?.("");

      try {
        const response = await fetch(`${API_BASE_URL}/auth/google`, {
          method: "POST",
          headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
          },
          body: JSON.stringify({
            credential,
            role,
            device_name: "gotfit-webapp",
          }),
        });

        const result = (await response.json().catch(() => null)) as
          | GoogleAuthResponse
          | null;

        if (!response.ok || !result?.token || !result.user) {
          throw new Error(getResponseError(result));
        }

        saveAuth(result.token, result.user);
        window.dispatchEvent(new Event("gotfit:auth"));
        router.replace(getPostAuthRoute(result.user));
      } catch (error) {
        onError?.(
          error instanceof Error
            ? error.message
            : "Une erreur est survenue pendant la connexion Google."
        );
      } finally {
        setLoading(false);
      }
    },
    [loading, onError, role, router]
  );

  const initializeGoogleButton = useCallback(() => {
    if (!clientId || !window.google?.accounts.id || !buttonRef.current) {
      return;
    }

    buttonRef.current.replaceChildren();

    window.google.accounts.id.initialize({
      client_id: clientId,
      callback: handleCredential,
      context: flow === "register" ? "signup" : "signin",
      auto_select: false,
      cancel_on_tap_outside: true,
    });

    window.google.accounts.id.renderButton(buttonRef.current, {
      type: "standard",
      theme: "outline",
      size: "large",
      text: flow === "register" ? "signup_with" : "continue_with",
      shape: "pill",
      logo_alignment: "left",
      width: 320,
    });
  }, [clientId, flow, handleCredential]);

  useEffect(() => {
    initializeGoogleButton();
  }, [initializeGoogleButton]);

  if (!clientId) {
    return (
      <div className="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold leading-6 text-amber-900">
        Ajoutez <code>NEXT_PUBLIC_GOOGLE_CLIENT_ID</code> pour activer la
        connexion Google.
      </div>
    );
  }

  if (scriptError) {
    return (
      <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold leading-6 text-red-700">
        Le service Google n’a pas pu être chargé. Vérifiez votre connexion puis
        actualisez la page.
      </div>
    );
  }

  return (
    <div className="space-y-3">
      <Script
        id="google-identity-services"
        src="https://accounts.google.com/gsi/client"
        strategy="afterInteractive"
        onReady={initializeGoogleButton}
        onError={() => setScriptError(true)}
      />

      <div className="relative flex min-h-11 justify-center">
        <div
          ref={buttonRef}
          className={loading ? "pointer-events-none opacity-30" : ""}
          aria-label="Continuer avec Google"
        />

        {loading && (
          <div className="absolute inset-0 flex items-center justify-center gap-2 rounded-full bg-white text-sm font-black text-[var(--ink)]">
            <LoaderCircle className="animate-spin" size={18} />
            Connexion sécurisée…
          </div>
        )}
      </div>

      <p className="flex items-center justify-center gap-2 text-center text-xs font-semibold text-slate-500">
        <ShieldCheck size={14} className="text-[var(--brand)]" />
        Gotfit ne reçoit jamais votre mot de passe Google.
      </p>
    </div>
  );
}
