import Link from "next/link";
import {
  ArrowRight,
  BadgeCheck,
  Dumbbell,
  HeartPulse,
  Leaf,
  MessageCircle,
  Salad,
  Search,
  ShieldCheck,
  Sparkles,
  Star,
  Timer,
  UserRound,
  UsersRound,
  Zap,
} from "lucide-react";

import Header from "@/components/layout/Header";
import Footer from "@/components/layout/Footer";

const services = [
  {
    title: "Coaching sportif",
    description:
      "Programmes personnalisés pour améliorer votre condition physique, votre posture et votre énergie au quotidien.",
    icon: Dumbbell,
    tag: "Performance",
    href: "/intervenants?service=coaching",
  },
  {
    title: "Pilates",
    description:
      "Séances axées sur le renforcement profond, la mobilité, la respiration et l’équilibre du corps.",
    icon: Sparkles,
    tag: "Posture",
    href: "/intervenants?service=pilates",
  },
  {
    title: "Yoga",
    description:
      "Accompagnement doux pour réduire le stress, gagner en souplesse et retrouver un meilleur alignement.",
    icon: Leaf,
    tag: "Équilibre",
    href: "/intervenants?service=yoga",
  },
  {
    title: "Nutrition",
    description:
      "Conseils adaptés à votre rythme de vie pour mieux manger, mieux récupérer et progresser durablement.",
    icon: Salad,
    tag: "Bien-être",
    href: "/intervenants?service=nutrition",
  },
  {
    title: "Réathlétisation",
    description:
      "Reprise progressive après une pause, une blessure ou une baisse de forme, avec un suivi sécurisé.",
    icon: HeartPulse,
    tag: "Reprise",
    href: "/intervenants?service=reathetisation",
  },
  {
    title: "Suivi personnalisé",
    description:
      "Un accompagnement complet avec objectifs, messages, réservations et historique depuis votre espace Gotfit.",
    icon: UserRound,
    tag: "Premium",
    href: "/auth/register",
  },
];

const steps = [
  {
    title: "Choisissez votre besoin",
    description:
      "Sélectionnez le type d’accompagnement qui correspond à votre objectif.",
    icon: Search,
  },
  {
    title: "Trouvez un intervenant",
    description:
      "Comparez les profils, spécialités, disponibilités et expériences.",
    icon: UsersRound,
  },
  {
    title: "Réservez facilement",
    description:
      "Planifiez votre séance depuis la webapp et suivez votre accompagnement.",
    icon: Timer,
  },
];

const benefits = [
  "Intervenants qualifiés",
  "Réservation simple",
  "Messagerie intégrée",
  "Suivi personnalisé",
  "Paiement sécurisé",
  "Espace client dédié",
];

export default function ServicesPage() {
  return (
    <>
      <Header />

      <main className="min-h-screen bg-[#FFF7ED] text-slate-950">
        <section className="relative overflow-hidden px-4 pb-16 pt-36 sm:pt-40 lg:pb-24">
          <div className="absolute left-1/2 top-0 h-[520px] w-[520px] -translate-x-1/2 rounded-full bg-orange-300/30 blur-3xl" />
          <div className="absolute -left-40 top-48 h-80 w-80 rounded-full bg-amber-200/40 blur-3xl" />
          <div className="absolute -right-40 top-72 h-96 w-96 rounded-full bg-orange-400/20 blur-3xl" />

          <div className="relative mx-auto max-w-7xl">
            <div className="mx-auto max-w-4xl text-center">
              <span className="mb-5 inline-flex items-center gap-2 rounded-full border border-orange-200 bg-white/80 px-4 py-2 text-xs font-black uppercase tracking-[0.18em] text-orange-700 shadow-sm backdrop-blur">
                <Sparkles size={16} />
                Services Gotfit
              </span>

              <h1 className="text-4xl font-black leading-[1.05] tracking-tight text-slate-950 sm:text-5xl lg:text-7xl">
                Des services bien-être pensés pour votre rythme.
              </h1>

              <p className="mx-auto mt-6 max-w-2xl text-base font-medium leading-8 text-slate-600 sm:text-lg">
                Trouvez rapidement un accompagnement adapté : coaching, Pilates,
                yoga, nutrition, reprise sportive et suivi personnalisé.
              </p>

              <div className="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row">
                <Link
                  href="/intervenants"
                  className="inline-flex w-full items-center justify-center gap-2 rounded-full bg-orange-600 px-7 py-4 text-sm font-black text-white shadow-xl shadow-orange-600/20 transition hover:-translate-y-0.5 hover:bg-orange-700 sm:w-auto"
                >
                  Trouver un intervenant
                  <ArrowRight size={18} />
                </Link>

                <Link
                  href="/auth/register"
                  className="inline-flex w-full items-center justify-center gap-2 rounded-full border border-orange-200 bg-white px-7 py-4 text-sm font-black text-orange-700 shadow-sm transition hover:-translate-y-0.5 hover:bg-orange-50 sm:w-auto"
                >
                  Créer un compte
                </Link>
              </div>
            </div>

            <div className="mt-14 grid gap-4 rounded-[2rem] border border-white/80 bg-white/70 p-3 shadow-[0_24px_80px_rgba(249,115,22,0.12)] backdrop-blur-xl sm:grid-cols-3">
              <div className="rounded-[1.5rem] bg-white p-6">
                <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-orange-100 text-orange-700">
                  <BadgeCheck size={24} />
                </div>
                <strong className="block text-2xl font-black">
                  + Services
                </strong>
                <p className="mt-2 text-sm font-semibold leading-6 text-slate-500">
                  Plusieurs spécialités bien-être disponibles depuis une seule
                  plateforme.
                </p>
              </div>

              <div className="rounded-[1.5rem] bg-white p-6">
                <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-orange-100 text-orange-700">
                  <ShieldCheck size={24} />
                </div>
                <strong className="block text-2xl font-black">Sécurisé</strong>
                <p className="mt-2 text-sm font-semibold leading-6 text-slate-500">
                  Compte personnel, réservation, paiement et suivi dans votre
                  espace.
                </p>
              </div>

              <div className="rounded-[1.5rem] bg-white p-6">
                <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-orange-100 text-orange-700">
                  <Zap size={24} />
                </div>
                <strong className="block text-2xl font-black">Rapide</strong>
                <p className="mt-2 text-sm font-semibold leading-6 text-slate-500">
                  Trouvez, réservez et échangez avec un intervenant sans perdre
                  de temps.
                </p>
              </div>
            </div>
          </div>
        </section>

        <section className="px-4 py-10 lg:py-16">
          <div className="mx-auto max-w-7xl">
            <div className="mb-10 flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
              <div>
                <span className="mb-3 inline-flex rounded-full bg-orange-100 px-4 py-2 text-xs font-black uppercase tracking-[0.18em] text-orange-700">
                  Nos accompagnements
                </span>
                <h2 className="max-w-2xl text-3xl font-black tracking-tight text-slate-950 sm:text-5xl">
                  Choisissez le service qui correspond à votre objectif.
                </h2>
              </div>

              <p className="max-w-md text-sm font-semibold leading-7 text-slate-500">
                Chaque service peut être adapté à votre niveau, votre planning
                et vos besoins spécifiques.
              </p>
            </div>

            <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
              {services.map((service) => {
                const Icon = service.icon;

                return (
                  <Link
                    key={service.title}
                    href={service.href}
                    className="group rounded-[2rem] border border-orange-100 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-[0_24px_70px_rgba(249,115,22,0.14)]"
                  >
                    <div className="mb-6 flex items-start justify-between gap-4">
                      <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-orange-100 text-orange-700 transition group-hover:bg-orange-600 group-hover:text-white">
                        <Icon size={26} />
                      </div>

                      <span className="rounded-full bg-orange-50 px-3 py-1 text-xs font-black text-orange-700">
                        {service.tag}
                      </span>
                    </div>

                    <h3 className="text-2xl font-black tracking-tight text-slate-950">
                      {service.title}
                    </h3>

                    <p className="mt-3 text-sm font-semibold leading-7 text-slate-500">
                      {service.description}
                    </p>

                    <div className="mt-6 inline-flex items-center gap-2 text-sm font-black text-orange-700">
                      Découvrir
                      <ArrowRight
                        size={17}
                        className="transition group-hover:translate-x-1"
                      />
                    </div>
                  </Link>
                );
              })}
            </div>
          </div>
        </section>

        <section className="px-4 py-10 lg:py-16">
          <div className="mx-auto grid max-w-7xl gap-6 lg:grid-cols-[0.8fr_1.2fr]">
            <div className="rounded-[2.5rem] bg-slate-950 p-7 text-white shadow-[0_24px_80px_rgba(15,23,42,0.18)] sm:p-10">
              <span className="mb-5 inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-2 text-xs font-black uppercase tracking-[0.18em] text-orange-200">
                <Star size={16} />
                Expérience premium
              </span>

              <h2 className="text-3xl font-black leading-tight tracking-tight sm:text-5xl">
                Une webapp simple, claire et pensée pour le suivi.
              </h2>

              <p className="mt-5 text-sm font-semibold leading-7 text-white/60">
                Gotfit centralise les services, les profils, les réservations,
                les paiements et les messages pour simplifier l’expérience
                client et intervenant.
              </p>

              <div className="mt-8 grid gap-3">
                {benefits.map((item) => (
                  <div
                    key={item}
                    className="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/[0.05] px-4 py-3"
                  >
                    <span className="flex h-8 w-8 items-center justify-center rounded-full bg-orange-500 text-white">
                      <BadgeCheck size={16} />
                    </span>
                    <span className="text-sm font-bold text-white/80">
                      {item}
                    </span>
                  </div>
                ))}
              </div>
            </div>

            <div className="grid gap-5">
              {steps.map((step, index) => {
                const Icon = step.icon;

                return (
                  <div
                    key={step.title}
                    className="rounded-[2rem] border border-orange-100 bg-white p-6 shadow-sm"
                  >
                    <div className="flex flex-col gap-5 sm:flex-row sm:items-center">
                      <div className="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-orange-100 text-orange-700">
                        <Icon size={28} />
                      </div>

                      <div className="flex-1">
                        <span className="mb-1 block text-xs font-black uppercase tracking-[0.18em] text-orange-500">
                          Étape {index + 1}
                        </span>

                        <h3 className="text-2xl font-black tracking-tight text-slate-950">
                          {step.title}
                        </h3>

                        <p className="mt-2 text-sm font-semibold leading-7 text-slate-500">
                          {step.description}
                        </p>
                      </div>

                      <div className="hidden text-4xl font-black text-orange-100 sm:block">
                        0{index + 1}
                      </div>
                    </div>
                  </div>
                );
              })}

              <div className="rounded-[2rem] bg-orange-600 p-6 text-white shadow-xl shadow-orange-600/20">
                <div className="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                  <div>
                    <h3 className="text-2xl font-black tracking-tight">
                      Prêt à commencer ?
                    </h3>
                    <p className="mt-2 text-sm font-semibold text-white/75">
                      Créez votre compte et accédez à votre espace personnalisé.
                    </p>
                  </div>

                  <Link
                    href="/auth/register"
                    className="inline-flex items-center justify-center gap-2 rounded-full bg-white px-6 py-3 text-sm font-black text-orange-700 transition hover:bg-orange-50"
                  >
                    Je m’inscris
                    <ArrowRight size={17} />
                  </Link>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section className="px-4 pb-24 pt-10">
          <div className="mx-auto max-w-7xl overflow-hidden rounded-[2.5rem] bg-white p-6 shadow-[0_24px_80px_rgba(249,115,22,0.12)] sm:p-10">
            <div className="grid gap-8 lg:grid-cols-[1fr_0.8fr] lg:items-center">
              <div>
                <span className="mb-4 inline-flex rounded-full bg-orange-100 px-4 py-2 text-xs font-black uppercase tracking-[0.18em] text-orange-700">
                  Gotfit
                </span>

                <h2 className="max-w-2xl text-3xl font-black leading-tight tracking-tight text-slate-950 sm:text-5xl">
                  Réservez plus simplement, progressez plus sereinement.
                </h2>

                <p className="mt-5 max-w-xl text-sm font-semibold leading-7 text-slate-500">
                  Gotfit vous accompagne dans la recherche du bon service et dans
                  le suivi de vos séances, que vous soyez client ou intervenant.
                </p>
              </div>

              <div className="grid gap-3 sm:grid-cols-2">
                <Link
                  href="/intervenants"
                  className="inline-flex items-center justify-center gap-2 rounded-2xl bg-slate-950 px-6 py-4 text-sm font-black text-white transition hover:-translate-y-0.5 hover:bg-slate-800"
                >
                  Voir les intervenants
                  <ArrowRight size={17} />
                </Link>

                <Link
                  href="/#contact"
                  className="inline-flex items-center justify-center gap-2 rounded-2xl bg-orange-100 px-6 py-4 text-sm font-black text-orange-700 transition hover:-translate-y-0.5 hover:bg-orange-200"
                >
                  <MessageCircle size={17} />
                  Nous contacter
                </Link>
              </div>
            </div>
          </div>
        </section>
      </main>

      <Footer />
    </>
  );
}