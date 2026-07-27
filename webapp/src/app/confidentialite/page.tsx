import Link from "next/link";
import Header from "@/components/layout/Header";
import Footer from "@/components/layout/Footer";
import {
  ArrowLeft,
  Cookie,
  Database,
  FileText,
  LockKeyhole,
  Mail,
  ShieldCheck,
  UserCheck,
  Users,
} from "lucide-react";

const privacySections = [
  {
    title: "Données collectées",
    icon: Database,
    content: [
      {
        label: "Données d’identification",
        value:
          "Lors de l’utilisation de GotFit, certaines informations peuvent être collectées, comme le nom, le prénom, l’adresse email, le numéro de téléphone, le rôle utilisateur, ainsi que les informations nécessaires à la création et à la gestion du compte.",
      },
      {
        label: "Données liées aux services",
        value:
          "GotFit peut traiter des informations relatives aux réservations, prestations, paiements, messages, avis, documents, disponibilités et interactions entre clients, intervenants et structures professionnelles.",
      },
      {
        label: "Données techniques",
        value:
          "Certaines données techniques peuvent être collectées automatiquement, comme l’adresse IP, le type d’appareil, le navigateur utilisé, les pages consultées ou les journaux techniques nécessaires à la sécurité et au bon fonctionnement du service.",
      },
    ],
  },
  {
    title: "Utilisation des données",
    icon: UserCheck,
    content: [
      {
        label: "Gestion du compte",
        value:
          "Les données sont utilisées pour créer, administrer et sécuriser les comptes utilisateurs, permettre la connexion, gérer les profils et adapter l’expérience selon le rôle de chaque utilisateur.",
      },
      {
        label: "Fonctionnement de la plateforme",
        value:
          "Les informations permettent d’assurer la recherche de prestations, la réservation de séances, la gestion des paiements, la messagerie interne, les notifications, les avis et le suivi des activités.",
      },
      {
        label: "Amélioration du service",
        value:
          "GotFit peut utiliser certaines données pour améliorer l’expérience utilisateur, optimiser les fonctionnalités, corriger les erreurs techniques et renforcer la qualité de la plateforme.",
      },
    ],
  },
  {
    title: "Partage des données",
    icon: Users,
    content: [
      {
        label: "Entre utilisateurs",
        value:
          "Certaines informations nécessaires peuvent être partagées entre clients, intervenants ou structures professionnelles afin de permettre la réservation, la communication, le suivi et la bonne réalisation des prestations.",
      },
      {
        label: "Prestataires techniques",
        value:
          "GotFit peut faire appel à des prestataires techniques pour l’hébergement, l’envoi d’emails, le paiement, la sécurité ou la maintenance. Ces prestataires n’ont accès aux données que dans la limite nécessaire à leur mission.",
      },
      {
        label: "Obligations légales",
        value:
          "Certaines données peuvent être communiquées lorsqu’une obligation légale, réglementaire, judiciaire ou administrative l’exige.",
      },
    ],
  },
  {
    title: "Sécurité des données",
    icon: LockKeyhole,
    content: [
      {
        label: "Protection",
        value:
          "GotFit met en place des mesures techniques et organisationnelles destinées à protéger les données personnelles contre l’accès non autorisé, la perte, l’altération, la divulgation ou l’utilisation abusive.",
      },
      {
        label: "Accès limité",
        value:
          "L’accès aux données est limité aux personnes ou services qui en ont besoin pour assurer le fonctionnement, la sécurité, l’administration ou l’amélioration de la plateforme.",
      },
      {
        label: "Paiement sécurisé",
        value:
          "Les opérations de paiement sont traitées via des solutions sécurisées. GotFit ne conserve pas directement les informations bancaires sensibles des utilisateurs.",
      },
    ],
  },
  {
    title: "Durée de conservation",
    icon: FileText,
    content: [
      {
        label: "Comptes utilisateurs",
        value:
          "Les données liées au compte sont conservées tant que le compte est actif, puis peuvent être archivées pendant une durée nécessaire au respect des obligations légales, fiscales, comptables ou de preuve.",
      },
      {
        label: "Messages et réservations",
        value:
          "Les données liées aux échanges, réservations, paiements et prestations peuvent être conservées afin d’assurer le suivi du service, la gestion des litiges, la facturation et la conformité légale.",
      },
      {
        label: "Suppression",
        value:
          "L’utilisateur peut demander la suppression de ses données personnelles, sous réserve des informations que GotFit doit conserver pour respecter ses obligations légales.",
      },
    ],
  },
  {
    title: "Cookies et traceurs",
    icon: Cookie,
    content: [
      {
        label: "Cookies nécessaires",
        value:
          "GotFit peut utiliser des cookies ou technologies similaires nécessaires au fonctionnement du site, à la sécurité, à la connexion utilisateur et à la mémorisation de certaines préférences.",
      },
      {
        label: "Mesure et amélioration",
        value:
          "Des outils de mesure d’audience peuvent être utilisés afin de comprendre l’utilisation de la plateforme et d’améliorer les services proposés.",
      },
      {
        label: "Gestion du consentement",
        value:
          "Lorsque la réglementation l’exige, les cookies non essentiels sont soumis au consentement préalable de l’utilisateur.",
      },
    ],
  },
];

const rights = [
  "Droit d’accès aux données personnelles",
  "Droit de rectification des informations inexactes",
  "Droit à l’effacement dans les conditions prévues par la loi",
  "Droit d’opposition à certains traitements",
  "Droit à la limitation du traitement",
  "Droit à la portabilité des données lorsque cela est applicable",
];

export default function ConfidentialitePage() {
  return (
    <>
      <Header />

      <main className="min-h-screen overflow-hidden bg-[#fffaf0] text-[#21170b]">
        <section className="relative px-4 pb-16 pt-32 lg:pt-40">
          <div className="pointer-events-none absolute -left-28 top-16 h-96 w-96 rounded-full bg-[#f2d58d]/40 blur-3xl" />
          <div className="pointer-events-none absolute -right-28 top-24 h-[28rem] w-[28rem] rounded-full bg-[#b9872b]/15 blur-3xl" />
          <div className="pointer-events-none absolute bottom-0 left-1/2 h-80 w-80 -translate-x-1/2 rounded-full bg-white blur-3xl" />

          <div className="relative mx-auto max-w-7xl">
            <Link
              href="/"
              className="mb-8 inline-flex items-center gap-2 rounded-full border border-[#b9872b]/15 bg-white/80 px-4 py-2 text-sm font-black text-[#9b6b19] shadow-[0_12px_35px_rgba(65,42,12,0.08)] transition hover:-translate-y-0.5 hover:border-[#b9872b]/40"
            >
              <ArrowLeft size={16} />
              Retour à l’accueil
            </Link>

            <div className="grid gap-10 lg:grid-cols-[0.95fr_1.05fr] lg:items-end">
              <div>
                <span className="mb-5 inline-flex items-center gap-2 rounded-full border border-[#b9872b]/20 bg-[#fff8e9] px-4 py-2 text-xs font-black uppercase tracking-[0.16em] text-[#9b6b19]">
                  <ShieldCheck size={15} />
                  Protection des données
                </span>

                <h1 className="max-w-4xl text-5xl font-black leading-[0.96] tracking-[-0.065em] text-[#21170b] sm:text-6xl lg:text-7xl">
                  Politique de
                  <span className="block text-[#b9872b]">
                    confidentialité.
                  </span>
                </h1>
              </div>

              <p className="max-w-xl text-lg leading-8 text-[#6f5d43]">
                Cette politique explique comment GotFit collecte, utilise,
                protège et conserve les données personnelles des utilisateurs de
                la plateforme.
              </p>
            </div>
          </div>
        </section>

        <section className="px-4 pb-20">
          <div className="mx-auto max-w-7xl">
            <div className="grid gap-6 lg:grid-cols-[0.75fr_1.25fr]">
              <aside className="h-fit rounded-[2rem] border border-[#b9872b]/14 bg-white p-7 shadow-[0_24px_70px_rgba(65,42,12,0.08)]">
                <div className="mb-6 flex h-14 w-14 items-center justify-center rounded-2xl bg-[#fff3d6] text-[#b9872b]">
                  <ShieldCheck size={26} />
                </div>

                <h2 className="text-2xl font-black tracking-[-0.04em] text-[#21170b]">
                  Confidentialité GotFit
                </h2>

                <p className="mt-4 text-sm leading-7 text-[#6f5d43]">
                  GotFit accorde une importance particulière à la protection des
                  données personnelles. Cette page doit être complétée avec les
                  informations exactes de l’entreprise avant mise en production.
                </p>

                <div className="mt-7 rounded-[1.5rem] border border-[#b9872b]/14 bg-[#fff8e9] p-5">
                  <h3 className="text-sm font-black uppercase tracking-[0.14em] text-[#b9872b]">
                    Contact données personnelles
                  </h3>

                  <div className="mt-4 flex gap-3">
                    <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white text-[#b9872b] shadow-sm">
                      <Mail size={18} />
                    </span>

                    <div>
                      <strong className="block text-sm text-[#21170b]">
                        Email
                      </strong>
                      <span className="text-sm text-[#6f5d43]">
                        contact@gotfit.com
                      </span>
                    </div>
                  </div>
                </div>

                <div className="mt-5 rounded-[1.5rem] border border-[#b9872b]/14 bg-white p-5">
                  <h3 className="text-sm font-black uppercase tracking-[0.14em] text-[#b9872b]">
                    Dernière mise à jour
                  </h3>

                  <p className="mt-3 text-sm leading-7 text-[#6f5d43]">
                    À compléter avant publication officielle.
                  </p>
                </div>
              </aside>

              <div className="grid gap-5">
                {privacySections.map((section) => {
                  const Icon = section.icon;

                  return (
                    <article
                      key={section.title}
                      className="rounded-[2rem] border border-[#b9872b]/14 bg-white p-7 shadow-[0_18px_50px_rgba(65,42,12,0.06)]"
                    >
                      <div className="mb-6 flex items-center gap-4">
                        <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-[#fff3d6] text-[#b9872b]">
                          <Icon size={22} />
                        </span>

                        <h2 className="text-2xl font-black tracking-[-0.04em] text-[#21170b]">
                          {section.title}
                        </h2>
                      </div>

                      <div className="grid gap-4">
                        {section.content.map((item) => (
                          <div
                            key={`${section.title}-${item.label}`}
                            className="rounded-2xl bg-[#fffaf0] p-5"
                          >
                            <h3 className="mb-2 text-sm font-black uppercase tracking-[0.12em] text-[#b9872b]">
                              {item.label}
                            </h3>

                            <p className="text-sm leading-7 text-[#6f5d43]">
                              {item.value}
                            </p>
                          </div>
                        ))}
                      </div>
                    </article>
                  );
                })}

                <article className="rounded-[2rem] border border-[#b9872b]/14 bg-white p-7 shadow-[0_18px_50px_rgba(65,42,12,0.06)]">
                  <div className="mb-6 flex items-center gap-4">
                    <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-[#fff3d6] text-[#b9872b]">
                      <ShieldCheck size={22} />
                    </span>

                    <h2 className="text-2xl font-black tracking-[-0.04em] text-[#21170b]">
                      Vos droits
                    </h2>
                  </div>

                  <p className="mb-5 text-sm leading-7 text-[#6f5d43]">
                    Conformément à la réglementation applicable, vous pouvez
                    exercer plusieurs droits concernant vos données personnelles.
                  </p>

                  <div className="grid gap-3 md:grid-cols-2">
                    {rights.map((right) => (
                      <div
                        key={right}
                        className="flex gap-3 rounded-2xl bg-[#fffaf0] p-4"
                      >
                        <span className="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-[#fff3d6] text-[#b9872b]">
                          <ShieldCheck size={15} />
                        </span>

                        <p className="text-sm font-bold leading-6 text-[#5d4b2f]">
                          {right}
                        </p>
                      </div>
                    ))}
                  </div>
                </article>
              </div>
            </div>
          </div>
        </section>

        <section className="px-4 pb-24">
          <div className="mx-auto max-w-7xl rounded-[2.5rem] border border-[#b9872b]/14 bg-gradient-to-br from-[#fff8e9] via-white to-[#f2d58d]/40 p-8 shadow-[0_30px_90px_rgba(65,42,12,0.09)] md:p-12">
            <div className="grid gap-8 lg:grid-cols-[1fr_0.7fr] lg:items-center">
              <div>
                <p className="mb-3 text-sm font-black uppercase tracking-[0.2em] text-[#b9872b]">
                  Demande confidentialité
                </p>

                <h2 className="max-w-3xl text-3xl font-black tracking-[-0.05em] text-[#21170b] md:text-5xl">
                  Besoin d’accéder, corriger ou supprimer vos données ?
                </h2>

                <p className="mt-5 max-w-2xl text-base leading-8 text-[#6f5d43]">
                  Contactez l’équipe GotFit pour toute question relative à la
                  confidentialité, à vos droits ou au traitement de vos données
                  personnelles.
                </p>
              </div>

              <Link
                href="/contact"
                className="inline-flex min-h-14 items-center justify-center gap-2 rounded-full bg-gradient-to-r from-[#f2d58d] to-[#b9872b] px-7 text-sm font-black text-[#1b1206] shadow-[0_18px_45px_rgba(185,135,43,0.22)] transition hover:-translate-y-1"
              >
                Contacter GotFit
                <ArrowLeft size={18} className="rotate-180" />
              </Link>
            </div>
          </div>
        </section>
      </main>

      <Footer />
    </>
  );
}