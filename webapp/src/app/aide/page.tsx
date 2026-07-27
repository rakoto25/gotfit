import Link from "next/link";
import {
  ArrowRight,
  BookOpenCheck,
  CalendarCheck2,
  CircleHelp,
  CreditCard,
  MessageCircleMore,
  MonitorSmartphone,
  PlayCircle,
  Search,
  ShieldCheck,
  UserRoundCheck,
  Video,
} from "lucide-react";

import Footer from "@/components/layout/Footer";
import Header from "@/components/layout/Header";

const tutorials = [
  {
    icon: Search,
    tag: "2 min",
    title: "Trouver le bon coach",
    text: "Utiliser les filtres, comparer les profils et choisir une spécialité.",
    href: "/intervenants",
  },
  {
    icon: CalendarCheck2,
    tag: "3 min",
    title: "Réserver une séance",
    text: "Sélectionner un créneau, confirmer la réservation et l’ajouter au calendrier.",
    href: "/services",
  },
  {
    icon: Video,
    tag: "2 min",
    title: "Rejoindre une visio",
    text: "Tester caméra et micro, puis rejoindre votre coach depuis le navigateur.",
    href: "/visio",
  },
  {
    icon: CreditCard,
    tag: "3 min",
    title: "Comprendre le paiement",
    text: "Voir quand le paiement est débité, validé et reversé au professionnel.",
    href: "/reservations",
  },
];

const faqs = [
  {
    question: "Comment créer mon compte Gotfit ?",
    answer:
      "Choisissez “Créer mon compte”, indiquez si vous êtes client ou coach, acceptez les conditions puis cliquez sur Google. Votre nom et votre email sont récupérés automatiquement : aucun formulaire ni nouveau mot de passe n’est nécessaire.",
  },
  {
    question: "Les coachs sont-ils vérifiés ?",
    answer:
      "Oui. Un profil intervenant reste en attente tant que l’équipe Gotfit n’a pas contrôlé ses informations et documents. Cette validation est requise avant la publication complète du profil.",
  },
  {
    question: "Quand le coach reçoit-il le paiement ?",
    answer:
      "Le paiement est sécurisé lors de la réservation. Le reversement intervient après validation de la prestation, selon les règles de la marketplace Gotfit. Un litige peut être ouvert avant le reversement.",
  },
  {
    question: "Puis-je suivre une séance depuis mon téléphone ?",
    answer:
      "Oui. L’espace visio fonctionne sur navigateur mobile et ordinateur. Autorisez simplement l’accès à la caméra et au microphone lorsque votre appareil le demande.",
  },
  {
    question: "Où retrouver mes réservations et messages ?",
    answer:
      "Une fois connecté, utilisez les raccourcis Planning, Messages et Profil. Sur mobile, ils restent accessibles depuis la barre située en bas de l’écran.",
  },
  {
    question: "Que se passe-t-il si j’utilisais déjà un mot de passe ?",
    answer:
      "Vous pouvez désormais vous connecter avec Google si l’adresse email est identique. L’ancien accès par email reste disponible dans la section “Accès historique” de la page de connexion.",
  },
];

export default function HelpPage() {
  return (
    <main className="min-h-screen bg-[var(--canvas)] text-[var(--ink)]">
      <Header />

      <section className="relative overflow-hidden px-4 pb-20 pt-36 sm:px-6 lg:pb-24 lg:pt-44">
        <div className="pointer-events-none absolute right-[-8rem] top-16 h-[28rem] w-[28rem] rounded-full bg-[var(--brand)]/15 blur-3xl" />
        <div className="relative mx-auto max-w-5xl text-center">
          <span className="inline-flex items-center gap-2 rounded-full border border-[var(--brand)]/35 bg-white px-4 py-2 text-xs font-black uppercase tracking-[0.18em] text-[var(--brand-strong)] shadow-sm">
            <CircleHelp size={16} />
            Centre d’aide Gotfit
          </span>
          <h1 className="mx-auto mt-7 max-w-4xl text-balance text-5xl font-black leading-[0.98] tracking-[-0.055em] sm:text-6xl">
            Une réponse claire, au bon moment.
          </h1>
          <p className="mx-auto mt-6 max-w-2xl text-base leading-8 text-slate-600">
            Tutoriels rapides, réponses fréquentes et accès direct à l’équipe
            Gotfit pour avancer sans blocage.
          </p>
        </div>
      </section>

      <section className="px-4 pb-24 sm:px-6">
        <div className="mx-auto max-w-7xl">
          <div className="grid gap-4 md:grid-cols-3">
            {[
              {
                icon: MonitorSmartphone,
                title: "Web & mobile",
                text: "Les mêmes repères sur ordinateur et smartphone.",
              },
              {
                icon: ShieldCheck,
                title: "Sécurité",
                text: "Des explications simples sur comptes et paiements.",
              },
              {
                icon: MessageCircleMore,
                title: "Support humain",
                text: "Une équipe disponible lorsque le guide ne suffit pas.",
              },
            ].map(({ icon: Icon, title, text }) => (
              <article
                key={title}
                className="flex items-start gap-4 rounded-[1.75rem] border border-black/5 bg-white p-6 shadow-[0_16px_45px_rgba(21,33,27,0.06)]"
              >
                <span className="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-[var(--canvas)]">
                  <Icon size={21} />
                </span>
                <div>
                  <h2 className="text-base font-black">{title}</h2>
                  <p className="mt-2 text-sm leading-6 text-slate-600">{text}</p>
                </div>
              </article>
            ))}
          </div>
        </div>
      </section>

      <section className="bg-white px-4 py-24 sm:px-6">
        <div className="mx-auto max-w-7xl">
          <div className="max-w-3xl">
            <span className="inline-flex items-center gap-2 text-xs font-black uppercase tracking-[0.2em] text-[var(--brand-strong)]">
              <PlayCircle size={17} />
              Guides express
            </span>
            <h2 className="mt-4 text-4xl font-black tracking-[-0.045em] sm:text-5xl">
              Les gestes essentiels
            </h2>
          </div>

          <div className="mt-12 grid gap-4 md:grid-cols-2">
            {tutorials.map(({ icon: Icon, tag, title, text, href }) => (
              <Link
                key={title}
                href={href}
                className="group flex min-h-64 flex-col rounded-[2rem] border border-slate-100 bg-[var(--canvas)] p-7 transition hover:-translate-y-1 hover:border-[var(--brand)]"
              >
                <div className="flex items-center justify-between">
                  <span className="grid h-12 w-12 place-items-center rounded-2xl bg-white shadow-sm">
                    <Icon size={22} />
                  </span>
                  <span className="rounded-full bg-white px-3 py-1.5 text-xs font-black text-slate-500">
                    {tag}
                  </span>
                </div>
                <h3 className="mt-8 text-2xl font-black tracking-tight">
                  {title}
                </h3>
                <p className="mt-3 max-w-lg text-sm leading-7 text-slate-600">
                  {text}
                </p>
                <span className="mt-auto inline-flex items-center gap-2 pt-6 text-sm font-black">
                  Ouvrir le guide
                  <ArrowRight
                    size={17}
                    className="transition group-hover:translate-x-1"
                  />
                </span>
              </Link>
            ))}
          </div>
        </div>
      </section>

      <section className="px-4 py-24 sm:px-6 lg:py-32">
        <div className="mx-auto grid max-w-7xl gap-12 lg:grid-cols-[0.7fr_1.3fr]">
          <div>
            <span className="inline-flex items-center gap-2 text-xs font-black uppercase tracking-[0.2em] text-[var(--brand-strong)]">
              <BookOpenCheck size={17} />
              Questions fréquentes
            </span>
            <h2 className="mt-4 text-4xl font-black tracking-[-0.045em]">
              Les réponses les plus utiles
            </h2>
            <p className="mt-5 text-sm leading-7 text-slate-600">
              Vous ne trouvez pas votre réponse ? Écrivez-nous, nous vous
              orienterons rapidement.
            </p>
            <Link
              href="/contact"
              className="gotfit-button gotfit-button-dark mt-7 px-6"
            >
              Contacter l’équipe
              <ArrowRight size={17} />
            </Link>
          </div>

          <div className="space-y-3">
            {faqs.map((item, index) => (
              <details
                key={item.question}
                className="group rounded-2xl border border-black/5 bg-white shadow-[0_12px_35px_rgba(21,33,27,0.05)]"
                open={index === 0}
              >
                <summary className="flex list-none items-center justify-between gap-5 px-5 py-5 text-left text-sm font-black sm:px-6">
                  {item.question}
                  <span className="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-[var(--canvas)] text-lg transition group-open:rotate-45">
                    +
                  </span>
                </summary>
                <p className="border-t border-slate-100 px-5 py-5 text-sm leading-7 text-slate-600 sm:px-6">
                  {item.answer}
                </p>
              </details>
            ))}
          </div>
        </div>
      </section>

      <section className="px-4 pb-24 sm:px-6 lg:pb-32">
        <div className="mx-auto flex max-w-7xl flex-col gap-8 rounded-[2.5rem] bg-[var(--ink)] p-8 text-white sm:p-12 lg:flex-row lg:items-center lg:justify-between">
          <div className="max-w-2xl">
            <span className="inline-flex items-center gap-2 text-xs font-black uppercase tracking-[0.18em] text-[var(--brand)]">
              <UserRoundCheck size={17} />
              Prêt à démarrer
            </span>
            <h2 className="mt-4 text-3xl font-black tracking-tight sm:text-4xl">
              Votre compte Gotfit se crée avec Google, sans formulaire.
            </h2>
          </div>
          <Link
            href="/auth/register"
            className="gotfit-button gotfit-button-brand shrink-0 px-7 py-4"
          >
            Créer mon compte
            <ArrowRight size={18} />
          </Link>
        </div>
      </section>

      <Footer />
    </main>
  );
}
