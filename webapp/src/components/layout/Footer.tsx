import Image from "next/image";
import Link from "next/link";
import {
  ArrowUpRight,
  Mail,
  ShieldCheck,
} from "lucide-react";

const footerGroups = [
  {
    title: "Découvrir",
    links: [
      { label: "Services", href: "/services" },
      { label: "Nos coachs", href: "/intervenants" },
      { label: "Séances visio", href: "/visio" },
      { label: "Annonces", href: "/annonces" },
    ],
  },
  {
    title: "Votre espace",
    links: [
      { label: "Planning", href: "/planning" },
      { label: "Réservations", href: "/reservations" },
      { label: "Messages", href: "/messages" },
      { label: "Profil", href: "/profile" },
    ],
  },
  {
    title: "Besoin d’aide",
    links: [
      { label: "Centre d’aide", href: "/aide" },
      { label: "Nous contacter", href: "/contact" },
      { label: "Confidentialité", href: "/confidentialite" },
      { label: "Mentions légales", href: "/mentions-legales" },
    ],
  },
];

export default function Footer() {
  return (
    <footer className="relative z-10 bg-[var(--ink)] px-4 pb-28 pt-16 text-white sm:px-6 lg:pb-10">
      <div className="mx-auto max-w-7xl">
        <div className="grid gap-12 border-b border-white/10 pb-12 lg:grid-cols-[1.1fr_1.9fr]">
          <div>
            <Link href="/" className="inline-flex rounded-2xl bg-white px-4 py-2">
              <Image
                src="/brand/gotfit-logo.png"
                alt="Gotfit"
                width={145}
                height={54}
                className="h-11 w-auto object-contain"
              />
            </Link>

            <p className="mt-6 max-w-sm text-sm leading-7 text-white/55">
              Des professionnels vérifiés, des réservations simples et un suivi
              humain pour avancer durablement.
            </p>

            <a
              href="mailto:contact@gotfit.tech"
              className="mt-5 inline-flex items-center gap-2 text-sm font-black text-[var(--brand)] hover:text-[var(--brand-soft)]"
            >
              <Mail size={17} />
              contact@gotfit.tech
            </a>
          </div>

          <div className="grid gap-8 sm:grid-cols-3">
            {footerGroups.map((group) => (
              <div key={group.title}>
                <h2 className="text-xs font-black uppercase tracking-[0.18em] text-[var(--brand)]">
                  {group.title}
                </h2>
                <ul className="mt-5 space-y-3">
                  {group.links.map((link) => (
                    <li key={link.href}>
                      <Link
                        href={link.href}
                        className="inline-flex items-center gap-1 text-sm font-semibold text-white/60 transition hover:text-white"
                      >
                        {link.label}
                        <ArrowUpRight size={13} />
                      </Link>
                    </li>
                  ))}
                </ul>
              </div>
            ))}
          </div>
        </div>

        <div className="flex flex-col gap-5 pt-7 text-xs font-semibold text-white/40 sm:flex-row sm:items-center sm:justify-between">
          <p>© {new Date().getFullYear()} Gotfit. Tous droits réservés.</p>

          <div className="flex items-center gap-3">
            <span className="inline-flex items-center gap-2">
              <ShieldCheck size={15} className="text-[var(--brand)]" />
              Paiements sécurisés
            </span>
          </div>
        </div>
      </div>
    </footer>
  );
}
