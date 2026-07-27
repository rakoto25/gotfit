import Link from "next/link";
import {
  ArrowRight,
  BadgeCheck,
  CalendarCheck2,
  Check,
  CirclePlay,
  Clock3,
  CreditCard,
  HeartHandshake,
  MessageCircleMore,
  ShieldCheck,
  Sparkles,
  Star,
  TrendingUp,
  UsersRound,
  Video,
} from "lucide-react";

import Footer from "@/components/layout/Footer";
import Header from "@/components/layout/Header";

const trustPoints = [
  { icon: BadgeCheck, label: "Profils coach vérifiés" },
  { icon: ShieldCheck, label: "Paiements sécurisés" },
  { icon: Video, label: "Séances visio intégrées" },
  { icon: MessageCircleMore, label: "Suivi centralisé" },
];

const steps = [
  {
    number: "01",
    title: "Définissez votre besoin",
    text: "Sport, nutrition, récupération ou mieux-être : filtrez selon votre objectif et vos disponibilités.",
  },
  {
    number: "02",
    title: "Choisissez votre coach",
    text: "Comparez les approches, les profils vérifiés, les avis et les formats de séance.",
  },
  {
    number: "03",
    title: "Progressez au même endroit",
    text: "Réservation, paiement, visio, messages et notes de suivi restent réunis dans votre espace.",
  },
];

const features = [
  {
    icon: CalendarCheck2,
    title: "Réserver sans friction",
    text: "Créneaux lisibles, planning synchronisé et rappels avant chaque séance.",
    href: "/services",
    accent: "bg-[#eef2ea]",
  },
  {
    icon: Video,
    title: "Passer en visio",
    text: "Des séances individuelles ou en petit groupe, sur mobile comme sur navigateur.",
    href: "/visio",
    accent: "bg-[#f6ead0]",
  },
  {
    icon: TrendingUp,
    title: "Suivre son parcours",
    text: "Questionnaire de départ, historique et notes partagées pour visualiser les progrès.",
    href: "/parcours-client",
    accent: "bg-[#e7edf1]",
  },
  {
    icon: MessageCircleMore,
    title: "Garder le lien",
    text: "Une messagerie continue avec votre coach et toutes les informations utiles.",
    href: "/messages",
    accent: "bg-[#f1e8e5]",
  },
];

export default function HomePage() {
  return (
    <main className="min-h-screen bg-[var(--canvas)] text-[var(--ink)]">
      <Header />

      <section className="relative overflow-hidden px-4 pb-20 pt-36 sm:px-6 lg:pb-28 lg:pt-40">
        <div className="pointer-events-none absolute left-[-10rem] top-20 h-[30rem] w-[30rem] rounded-full bg-[var(--brand)]/12 blur-3xl" />
        <div className="pointer-events-none absolute right-[-10rem] top-4 h-[36rem] w-[36rem] rounded-full bg-[var(--sage)]/40 blur-3xl" />

        <div className="relative mx-auto grid max-w-7xl items-center gap-14 lg:grid-cols-[1.02fr_0.98fr]">
          <div>
            <span className="inline-flex items-center gap-2 rounded-full border border-[var(--brand)]/35 bg-white/70 px-4 py-2 text-xs font-black uppercase tracking-[0.18em] text-[var(--brand-strong)] shadow-sm backdrop-blur">
              <Sparkles size={15} />
              L’accompagnement qui vous ressemble
            </span>

            <h1 className="mt-7 max-w-3xl text-balance text-5xl font-black leading-[0.95] tracking-[-0.055em] sm:text-6xl lg:text-7xl">
              Le bon coach.
              <span className="mt-2 block text-[var(--brand-strong)]">
                Le bon rythme.
              </span>
              De vrais progrès.
            </h1>

            <p className="mt-7 max-w-xl text-lg leading-8 text-slate-600">
              Gotfit réunit des professionnels du sport et du mieux-être pour
              vous aider à passer de l’intention à une routine durable.
            </p>

            <div className="mt-9 flex flex-col gap-3 sm:flex-row">
              <Link
                href="/intervenants"
                className="gotfit-button gotfit-button-dark px-7 py-4"
              >
                Trouver mon coach
                <ArrowRight size={18} />
              </Link>
              <Link
                href="/auth/register"
                className="gotfit-button border border-[var(--ink)]/15 bg-white px-7 py-4 text-[var(--ink)] shadow-sm hover:border-[var(--brand)]"
              >
                Je suis professionnel
              </Link>
            </div>

            <p className="mt-5 flex items-center gap-2 text-sm font-bold text-slate-500">
              <span className="grid h-6 w-6 place-items-center rounded-full bg-white shadow-sm">
                G
              </span>
              Compte créé en quelques secondes avec Google.
            </p>
          </div>

          <div className="relative mx-auto w-full max-w-[37rem]">
            <div className="absolute -inset-5 rotate-2 rounded-[2.5rem] bg-[var(--brand)]/25" />
            <div className="relative overflow-hidden rounded-[2.25rem] border border-white bg-white p-5 shadow-[0_35px_90px_rgba(21,33,27,0.16)] sm:p-7">
              <div className="flex items-center justify-between">
                <div>
                  <span className="text-xs font-black uppercase tracking-[0.16em] text-slate-400">
                    Mon espace
                  </span>
                  <h2 className="mt-1 text-2xl font-black">Bonjour Amina 👋</h2>
                </div>
                <span className="grid h-12 w-12 place-items-center rounded-full bg-[var(--sage)] text-sm font-black">
                  AD
                </span>
              </div>

              <div className="mt-7 rounded-[1.6rem] bg-[var(--ink)] p-5 text-white">
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <span className="text-xs font-bold text-[var(--brand)]">
                      Prochaine séance
                    </span>
                    <h3 className="mt-2 text-xl font-black">
                      Renforcement & mobilité
                    </h3>
                    <p className="mt-1 text-sm text-white/55">
                      Avec Sarah M. · Coach certifiée
                    </p>
                  </div>
                  <span className="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-white/10">
                    <Video size={20} />
                  </span>
                </div>

                <div className="mt-5 flex flex-wrap items-center gap-3 text-xs font-bold">
                  <span className="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-2">
                    <CalendarCheck2 size={14} />
                    Mardi 18:30
                  </span>
                  <span className="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-2">
                    <Clock3 size={14} />
                    45 min
                  </span>
                </div>
              </div>

              <div className="mt-5 grid gap-4 sm:grid-cols-[1.25fr_0.75fr]">
                <div className="rounded-[1.5rem] border border-slate-100 bg-slate-50 p-5">
                  <div className="flex items-center justify-between">
                    <strong className="text-sm font-black">Votre semaine</strong>
                    <span className="text-xs font-bold text-slate-400">
                      3 objectifs
                    </span>
                  </div>
                  <div className="mt-5 flex items-end gap-2">
                    {[36, 58, 42, 82, 64, 30, 48].map((height, index) => (
                      <span
                        key={`${height}-${index}`}
                        className={`flex-1 rounded-full ${
                          index === 3 ? "bg-[var(--brand)]" : "bg-[var(--sage)]"
                        }`}
                        style={{ height }}
                      />
                    ))}
                  </div>
                  <div className="mt-3 flex justify-between text-[10px] font-black uppercase text-slate-400">
                    <span>Lun</span>
                    <span>Dim</span>
                  </div>
                </div>

                <div className="grid gap-3">
                  <div className="rounded-[1.5rem] bg-[#f6ead0] p-4">
                    <CreditCard size={19} />
                    <strong className="mt-3 block text-sm font-black">
                      Paiement protégé
                    </strong>
                    <span className="mt-1 block text-xs text-slate-600">
                      Jusqu’à validation
                    </span>
                  </div>
                  <div className="rounded-[1.5rem] bg-[#eef2ea] p-4">
                    <HeartHandshake size={19} />
                    <strong className="mt-3 block text-sm font-black">
                      Suivi continu
                    </strong>
                  </div>
                </div>
              </div>
            </div>

            <div className="absolute -bottom-5 -left-4 flex items-center gap-3 rounded-2xl border border-white bg-white px-4 py-3 shadow-xl sm:-left-8">
              <span className="grid h-10 w-10 place-items-center rounded-xl bg-amber-100 text-amber-700">
                <Star size={18} fill="currentColor" />
              </span>
              <div>
                <strong className="block text-sm font-black">Séance validée</strong>
                <span className="text-xs font-semibold text-slate-500">
                  Votre avis compte
                </span>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section className="border-y border-black/5 bg-white px-4 py-6 sm:px-6">
        <div className="mx-auto grid max-w-7xl grid-cols-2 gap-4 lg:grid-cols-4">
          {trustPoints.map(({ icon: Icon, label }) => (
            <div
              key={label}
              className="flex items-center justify-center gap-2 text-center text-xs font-black text-slate-600 sm:text-sm"
            >
              <Icon size={18} className="shrink-0 text-[var(--brand-strong)]" />
              {label}
            </div>
          ))}
        </div>
      </section>

      <section
        id="fonctionnement"
        className="scroll-mt-28 px-4 py-24 sm:px-6 lg:py-32"
      >
        <div className="mx-auto max-w-7xl">
          <div className="max-w-3xl">
            <span className="text-xs font-black uppercase tracking-[0.2em] text-[var(--brand-strong)]">
              Simple par conception
            </span>
            <h2 className="mt-4 text-balance text-4xl font-black tracking-[-0.045em] sm:text-5xl">
              Moins de gestion. Plus d’élan.
            </h2>
            <p className="mt-5 max-w-2xl text-base leading-8 text-slate-600">
              De la première recherche au suivi après séance, chaque étape reste
              claire et accessible.
            </p>
          </div>

          <div className="mt-12 grid gap-4 lg:grid-cols-3">
            {steps.map((step) => (
              <article
                key={step.number}
                className="group rounded-[2rem] border border-black/5 bg-white p-7 shadow-[0_18px_55px_rgba(21,33,27,0.06)] transition hover:-translate-y-1 hover:shadow-[0_24px_65px_rgba(21,33,27,0.10)]"
              >
                <div className="flex items-center justify-between">
                  <span className="text-4xl font-black text-[var(--brand)]">
                    {step.number}
                  </span>
                  <span className="grid h-10 w-10 place-items-center rounded-full bg-[var(--canvas)] transition group-hover:bg-[var(--ink)] group-hover:text-white">
                    <ArrowRight size={18} />
                  </span>
                </div>
                <h3 className="mt-10 text-xl font-black">{step.title}</h3>
                <p className="mt-3 text-sm leading-7 text-slate-600">
                  {step.text}
                </p>
              </article>
            ))}
          </div>
        </div>
      </section>

      <section className="bg-white px-4 py-24 sm:px-6 lg:py-32">
        <div className="mx-auto max-w-7xl">
          <div className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div className="max-w-3xl">
              <span className="text-xs font-black uppercase tracking-[0.2em] text-[var(--brand-strong)]">
                Tout votre parcours
              </span>
              <h2 className="mt-4 text-balance text-4xl font-black tracking-[-0.045em] sm:text-5xl">
                Une expérience continue, avant et après la séance.
              </h2>
            </div>
            <Link
              href="/services"
              className="inline-flex items-center gap-2 text-sm font-black text-[var(--ink)] underline decoration-[var(--brand)] decoration-2 underline-offset-4"
            >
              Explorer les services
              <ArrowRight size={17} />
            </Link>
          </div>

          <div className="mt-12 grid gap-4 md:grid-cols-2">
            {features.map(
              ({ icon: Icon, title, text, href, accent }, index) => (
                <Link
                  key={title}
                  href={href}
                  className={`${accent} group relative min-h-72 overflow-hidden rounded-[2rem] p-7 sm:p-9 ${
                    index === 0 || index === 3 ? "md:translate-y-5" : ""
                  }`}
                >
                  <div className="absolute -bottom-16 -right-16 h-48 w-48 rounded-full border-[2rem] border-white/30 transition duration-500 group-hover:scale-110" />
                  <span className="grid h-12 w-12 place-items-center rounded-2xl bg-white/75 shadow-sm">
                    <Icon size={22} />
                  </span>
                  <h3 className="mt-12 max-w-md text-2xl font-black tracking-tight">
                    {title}
                  </h3>
                  <p className="mt-3 max-w-md text-sm leading-7 text-slate-600">
                    {text}
                  </p>
                  <span className="absolute bottom-7 right-7 grid h-11 w-11 place-items-center rounded-full bg-[var(--ink)] text-white transition group-hover:rotate-[-12deg]">
                    <ArrowRight size={18} />
                  </span>
                </Link>
              )
            )}
          </div>
        </div>
      </section>

      <section className="px-4 py-24 sm:px-6 lg:py-32">
        <div className="mx-auto grid max-w-7xl overflow-hidden rounded-[2.5rem] bg-[var(--ink)] text-white lg:grid-cols-2">
          <div className="relative p-8 sm:p-12 lg:p-16">
            <div className="auth-grid absolute inset-0 opacity-20" />
            <div className="relative">
              <span className="inline-flex items-center gap-2 text-xs font-black uppercase tracking-[0.18em] text-[var(--brand)]">
                <UsersRound size={16} />
                Pour les professionnels
              </span>
              <h2 className="mt-5 text-balance text-4xl font-black tracking-[-0.045em] sm:text-5xl">
                Concentrez-vous sur vos clients, Gotfit organise le reste.
              </h2>
              <p className="mt-6 max-w-xl text-sm leading-7 text-white/60">
                Profil, vidéo de présentation, réservations, visio, paiements,
                messages et suivi client dans un espace unique.
              </p>
              <Link
                href="/auth/register"
                className="gotfit-button gotfit-button-brand mt-8 px-7 py-4"
              >
                Créer mon profil coach
                <ArrowRight size={18} />
              </Link>
            </div>
          </div>

          <div className="grid content-center gap-3 bg-white/[0.05] p-8 sm:p-12">
            {[
              "Recevez des demandes qualifiées",
              "Pilotez votre planning et vos séances",
              "Centralisez les notes et l’historique client",
              "Sécurisez paiements et reversements",
            ].map((item) => (
              <div
                key={item}
                className="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/[0.05] p-4 text-sm font-bold"
              >
                <span className="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-[var(--brand)] text-[var(--ink)]">
                  <Check size={15} strokeWidth={3} />
                </span>
                {item}
              </div>
            ))}
          </div>
        </div>
      </section>

      <section className="px-4 pb-24 sm:px-6 lg:pb-32">
        <div className="mx-auto max-w-5xl text-center">
          <span className="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-[var(--brand)]">
            <CirclePlay size={25} />
          </span>
          <h2 className="mt-6 text-balance text-4xl font-black tracking-[-0.045em] sm:text-5xl">
            Prêt à commencer avec le bon accompagnement ?
          </h2>
          <p className="mx-auto mt-5 max-w-2xl text-base leading-8 text-slate-600">
            Créez votre compte avec Google et trouvez votre prochain coach dès
            maintenant.
          </p>
          <div className="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
            <Link
              href="/auth/register"
              className="gotfit-button gotfit-button-dark px-8 py-4"
            >
              Commencer gratuitement
              <ArrowRight size={18} />
            </Link>
            <Link
              href="/aide"
              className="gotfit-button border border-slate-200 bg-white px-8 py-4 text-[var(--ink)]"
            >
              Découvrir le fonctionnement
            </Link>
          </div>
        </div>
      </section>

      <Footer />
    </main>
  );
}
