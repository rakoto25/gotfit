import Image from "next/image";
import Link from "next/link";
import type { ReactNode } from "react";
import {
  CalendarCheck2,
  CheckCircle2,
  MessageCircleMore,
  ShieldCheck,
  Sparkles,
} from "lucide-react";

type AuthShellProps = {
  eyebrow: string;
  title: string;
  description: string;
  children: ReactNode;
};

const assurances = [
  {
    icon: CalendarCheck2,
    title: "Réservez simplement",
    text: "Choisissez un expert et un créneau en quelques instants.",
  },
  {
    icon: MessageCircleMore,
    title: "Restez accompagné",
    text: "Messages, visio et suivi sont réunis dans le même espace.",
  },
  {
    icon: ShieldCheck,
    title: "Payez sereinement",
    text: "Les prestations et reversements sont sécurisés.",
  },
];

export default function AuthShell({
  eyebrow,
  title,
  description,
  children,
}: AuthShellProps) {
  return (
    <main className="relative min-h-screen overflow-hidden bg-[var(--canvas)] px-4 py-8 text-[var(--ink)] sm:px-6 lg:px-8">
      <div className="pointer-events-none absolute left-[-8rem] top-[-10rem] h-96 w-96 rounded-full bg-[var(--brand)]/15 blur-3xl" />
      <div className="pointer-events-none absolute bottom-[-12rem] right-[-8rem] h-[28rem] w-[28rem] rounded-full bg-[var(--sage)]/25 blur-3xl" />

      <div className="relative mx-auto grid min-h-[calc(100vh-4rem)] max-w-7xl overflow-hidden rounded-[2rem] border border-black/5 bg-white shadow-[0_32px_100px_rgba(23,32,27,0.12)] lg:grid-cols-[1.05fr_0.95fr]">
        <section className="relative hidden overflow-hidden bg-[var(--ink)] p-12 text-white lg:flex lg:flex-col">
          <div className="absolute inset-0 auth-grid opacity-25" />
          <div className="absolute -right-32 top-24 h-80 w-80 rounded-full border border-white/10" />
          <div className="absolute -right-16 top-40 h-52 w-52 rounded-full border border-[var(--brand)]/50" />

          <Link href="/" className="relative z-10 inline-flex w-fit">
            <span className="rounded-2xl bg-white px-5 py-3 shadow-lg">
              <Image
                src="/brand/gotfit-logo.png"
                alt="Gotfit"
                width={170}
                height={64}
                priority
                className="h-12 w-auto object-contain"
              />
            </span>
          </Link>

          <div className="relative z-10 my-auto max-w-xl py-14">
            <span className="mb-6 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/10 px-4 py-2 text-xs font-black uppercase tracking-[0.18em] text-[var(--brand-soft)]">
              <Sparkles size={15} />
              {eyebrow}
            </span>

            <h1 className="text-balance text-5xl font-black leading-[0.98] tracking-[-0.045em] xl:text-6xl">
              Votre mieux-être,
              <span className="block text-[var(--brand)]">bien accompagné.</span>
            </h1>

            <p className="mt-6 max-w-lg text-base leading-8 text-white/65">
              Une seule plateforme pour trouver le bon coach, réserver, échanger
              et progresser à votre rythme.
            </p>

            <div className="mt-10 grid gap-3">
              {assurances.map(({ icon: Icon, title: itemTitle, text }) => (
                <div
                  key={itemTitle}
                  className="flex items-start gap-4 rounded-2xl border border-white/10 bg-white/[0.06] p-4 backdrop-blur-sm"
                >
                  <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-[var(--brand)] text-[var(--ink)]">
                    <Icon size={19} />
                  </span>
                  <div>
                    <strong className="text-sm font-black">{itemTitle}</strong>
                    <p className="mt-1 text-xs leading-5 text-white/55">{text}</p>
                  </div>
                </div>
              ))}
            </div>
          </div>

          <p className="relative z-10 flex items-center gap-2 text-xs font-bold text-white/45">
            <CheckCircle2 size={15} className="text-[var(--brand)]" />
            Données protégées · Paiements sécurisés · Support humain
          </p>
        </section>

        <section className="flex items-center justify-center p-6 sm:p-10 lg:p-14">
          <div className="w-full max-w-lg">
            <Link href="/" className="mb-10 inline-flex lg:hidden">
              <Image
                src="/brand/gotfit-logo.png"
                alt="Gotfit"
                width={155}
                height={56}
                priority
                className="h-12 w-auto object-contain"
              />
            </Link>

            <span className="text-xs font-black uppercase tracking-[0.2em] text-[var(--brand-strong)]">
              {eyebrow}
            </span>
            <h2 className="mt-3 text-balance text-4xl font-black tracking-[-0.04em] sm:text-5xl">
              {title}
            </h2>
            <p className="mt-4 max-w-md text-sm leading-7 text-slate-600">
              {description}
            </p>

            <div className="mt-8">{children}</div>
          </div>
        </section>
      </div>
    </main>
  );
}
