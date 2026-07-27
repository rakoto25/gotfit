"use client";

import Image from "next/image";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useEffect, useState } from "react";
import {
  CalendarDays,
  CircleHelp,
  LogIn,
  Menu,
  MessageCircleMore,
  Search,
  UserRound,
  X,
} from "lucide-react";

import { getCurrentUser } from "@/lib/auth";
import type { User } from "@/types/auth";

const navLinks = [
  { label: "Services", href: "/services" },
  { label: "Annonces", href: "/annonces" },
  { label: "Trouver un coach", href: "/intervenants" },
  { label: "Visio", href: "/visio" },
  { label: "Centre d’aide", href: "/aide" },
  { label: "Contact", href: "/contact" },
];

function isActiveRoute(pathname: string, href: string) {
  return pathname === href || pathname.startsWith(`${href}/`);
}

export default function Header() {
  const pathname = usePathname();
  const [user, setUser] = useState<User | null>(null);
  const [menuOpen, setMenuOpen] = useState(false);

  useEffect(() => {
    const syncUser = () => setUser(getCurrentUser());
    const timer = window.setTimeout(syncUser, 0);

    window.addEventListener("storage", syncUser);
    window.addEventListener("gotfit:auth", syncUser);

    return () => {
      window.clearTimeout(timer);
      window.removeEventListener("storage", syncUser);
      window.removeEventListener("gotfit:auth", syncUser);
    };
  }, []);

  useEffect(() => {
    document.body.style.overflow = menuOpen ? "hidden" : "";
    return () => {
      document.body.style.overflow = "";
    };
  }, [menuOpen]);

  return (
    <>
      <header className="fixed inset-x-0 top-0 z-50 px-3 pt-3 sm:px-5">
        <div className="mx-auto flex h-[4.6rem] max-w-7xl items-center justify-between gap-4 rounded-[1.4rem] border border-white/80 bg-white/90 px-4 shadow-[0_16px_55px_rgba(21,33,27,0.10)] backdrop-blur-xl sm:px-5">
          <Link href="/" aria-label="Accueil Gotfit" className="shrink-0">
            <Image
              src="/brand/gotfit-logo.png"
              alt="Gotfit"
              width={132}
              height={50}
              priority
              className="h-10 w-auto object-contain"
            />
          </Link>

          <nav
            aria-label="Navigation principale"
            className="hidden items-center gap-1 lg:flex"
          >
            {navLinks.map((item) => {
              const active = isActiveRoute(pathname, item.href);

              return (
                <Link
                  key={item.href}
                  href={item.href}
                  onClick={() => setMenuOpen(false)}
                  aria-current={active ? "page" : undefined}
                  className={`rounded-full px-3 py-2.5 text-[13px] font-black transition xl:px-4 xl:text-sm ${
                    active
                      ? "bg-[var(--ink)] text-white"
                      : "text-slate-600 hover:bg-[var(--canvas)] hover:text-[var(--ink)]"
                  }`}
                >
                  {item.label}
                </Link>
              );
            })}
          </nav>

          <div className="hidden items-center gap-2 lg:flex">
            {user ? (
              <>
                <Link
                  href="/messages"
                  className="grid h-10 w-10 place-items-center rounded-full border border-slate-200 text-slate-700 transition hover:border-[var(--brand)] hover:bg-[var(--canvas)]"
                  aria-label="Messages"
                >
                  <MessageCircleMore size={18} />
                </Link>
                <Link
                  href="/profile"
                  className="gotfit-button gotfit-button-dark min-h-10 px-4 py-2"
                >
                  <UserRound size={17} />
                  {user.name?.split(" ")[0] || "Mon espace"}
                </Link>
              </>
            ) : (
              <>
                <Link
                  href="/auth/login"
                  className="rounded-full px-4 py-2.5 text-sm font-black text-[var(--ink)] transition hover:bg-[var(--canvas)]"
                >
                  Connexion
                </Link>
                <Link
                  href="/auth/register"
                  className="gotfit-button gotfit-button-dark min-h-10 px-5 py-2"
                >
                  S’inscrire
                </Link>
              </>
            )}
          </div>

          <button
            type="button"
            onClick={() => setMenuOpen(true)}
            className="grid h-11 w-11 place-items-center rounded-full bg-[var(--ink)] text-white lg:hidden"
            aria-label="Ouvrir le menu"
            aria-expanded={menuOpen}
          >
            <Menu size={21} />
          </button>
        </div>
      </header>

      {menuOpen && (
        <div className="fixed inset-0 z-[70] bg-[var(--ink)]/45 backdrop-blur-sm lg:hidden">
          <button
            type="button"
            className="absolute inset-0 cursor-default"
            onClick={() => setMenuOpen(false)}
            aria-label="Fermer le menu"
          />

          <aside
            aria-label="Menu mobile"
            className="relative ml-auto flex h-full w-[90%] max-w-sm flex-col bg-white p-5 shadow-2xl"
          >
            <div className="flex items-center justify-between">
              <Image
                src="/brand/gotfit-logo.png"
                alt="Gotfit"
                width={130}
                height={50}
                className="h-10 w-auto object-contain"
              />
              <button
                type="button"
                onClick={() => setMenuOpen(false)}
                className="grid h-10 w-10 place-items-center rounded-full bg-[var(--canvas)] text-[var(--ink)]"
                aria-label="Fermer le menu"
              >
                <X size={20} />
              </button>
            </div>

            <div className="mt-7 rounded-3xl bg-[var(--ink)] p-6 text-white">
              <span className="text-xs font-black uppercase tracking-[0.16em] text-[var(--brand)]">
                Votre prochain pas
              </span>
              <p className="mt-3 text-2xl font-black leading-tight">
                Trouvez l’accompagnement qui vous ressemble.
              </p>
            </div>

            <nav className="mt-5 grid gap-1">
              {navLinks.map((item) => (
                <Link
                  key={item.href}
                  href={item.href}
                  onClick={() => setMenuOpen(false)}
                  className={`flex items-center justify-between rounded-2xl px-4 py-3.5 text-sm font-black ${
                    isActiveRoute(pathname, item.href)
                      ? "bg-[var(--canvas)] text-[var(--brand-strong)]"
                      : "text-slate-700"
                  }`}
                >
                  {item.label}
                  <span aria-hidden="true">→</span>
                </Link>
              ))}
            </nav>

            <div className="mt-auto grid gap-2">
              <Link
                href={user ? "/profile" : "/auth/login"}
                onClick={() => setMenuOpen(false)}
                className="gotfit-button gotfit-button-dark w-full"
              >
                {user ? <UserRound size={18} /> : <LogIn size={18} />}
                {user ? "Ouvrir mon espace" : "Se connecter"}
              </Link>
              {!user && (
                <Link
                  href="/auth/register"
                  onClick={() => setMenuOpen(false)}
                  className="gotfit-button gotfit-button-brand w-full"
                >
                  Créer mon compte
                </Link>
              )}
            </div>
          </aside>
        </div>
      )}

      <nav
        aria-label="Raccourcis mobiles"
        className="fixed bottom-3 left-3 right-3 z-40 grid grid-cols-4 gap-1 rounded-[1.4rem] border border-white/80 bg-white/92 p-2 shadow-[0_20px_60px_rgba(21,33,27,0.16)] backdrop-blur-xl lg:hidden"
      >
        {[
          { href: "/annonces", label: "Explorer", icon: Search },
          {
            href: user ? "/planning" : "/auth/login",
            label: "Planning",
            icon: CalendarDays,
          },
          { href: "/aide", label: "Aide", icon: CircleHelp },
          {
            href: user ? "/profile" : "/auth/login",
            label: user ? "Profil" : "Connexion",
            icon: user ? UserRound : LogIn,
          },
        ].map(({ href, label, icon: Icon }, index) => (
          <Link
            key={`${href}-${label}`}
            href={href}
            className={`flex flex-col items-center justify-center gap-1 rounded-2xl py-2.5 text-[10px] font-black ${
              index === 1
                ? "bg-[var(--ink)] text-white"
                : "text-slate-600"
            }`}
          >
            <Icon size={17} />
            {label}
          </Link>
        ))}
      </nav>
    </>
  );
}
