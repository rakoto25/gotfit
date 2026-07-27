import Link from "next/link";
import Header from "@/components/layout/Header";
import Footer from "@/components/layout/Footer";
import {
  AlertTriangle,
  ArrowLeft,
  BadgeCheck,
  CalendarCheck,
  CreditCard,
  FileText,
  LockKeyhole,
  Mail,
  Scale,
  ShieldCheck,
  UserCheck,
  Users,
} from "lucide-react";

const cguSections = [
  {
    title: "Objet des conditions générales",
    icon: FileText,
    content: [
      {
        label: "Présentation",
        value:
          "Les présentes conditions générales d’utilisation ont pour objet de définir les règles d’accès et d’utilisation de la plateforme GotFit, accessible via le site web, la webapp, l’application mobile et les services associés.",
      },
      {
        label: "Service proposé",
        value:
          "GotFit est une plateforme digitale permettant la mise en relation entre clients, intervenants certifiés et structures professionnelles dans les domaines du sport, du mouvement, du bien-être, de la remise en forme et des prestations associées.",
      },
      {
        label: "Acceptation",
        value:
          "L’utilisation de la plateforme implique l’acceptation pleine et entière des présentes conditions générales d’utilisation. Si l’utilisateur n’accepte pas ces conditions, il doit cesser d’utiliser les services GotFit.",
      },
    ],
  },
  {
    title: "Accès à la plateforme",
    icon: UserCheck,
    content: [
      {
        label: "Création de compte",
        value:
          "Certaines fonctionnalités de GotFit nécessitent la création d’un compte utilisateur. L’utilisateur s’engage à fournir des informations exactes, complètes et à jour lors de son inscription.",
      },
      {
        label: "Identifiants",
        value:
          "L’utilisateur est responsable de la confidentialité de ses identifiants de connexion. Toute activité réalisée depuis son compte est présumée avoir été effectuée par lui, sauf preuve contraire.",
      },
      {
        label: "Suspension d’accès",
        value:
          "GotFit se réserve le droit de suspendre ou de supprimer un compte en cas d’utilisation frauduleuse, abusive, contraire aux présentes conditions ou susceptible de porter atteinte au bon fonctionnement de la plateforme.",
      },
    ],
  },
  {
    title: "Profils utilisateurs",
    icon: Users,
    content: [
      {
        label: "Clients",
        value:
          "Les clients peuvent rechercher des prestations, consulter les profils des intervenants, effectuer des réservations, procéder au paiement, échanger via la messagerie et laisser des avis après les séances.",
      },
      {
        label: "Intervenants",
        value:
          "Les intervenants peuvent présenter leurs prestations, gérer leurs disponibilités, recevoir des réservations, communiquer avec les clients et suivre leur activité depuis leur espace personnel.",
      },
      {
        label: "Structures professionnelles",
        value:
          "Les structures professionnelles peuvent publier des besoins ponctuels ou récurrents, sélectionner des profils, gérer des demandes et organiser des prestations selon les fonctionnalités disponibles.",
      },
    ],
  },
  {
    title: "Réservations et prestations",
    icon: CalendarCheck,
    content: [
      {
        label: "Réservation",
        value:
          "La réservation d’une prestation s’effectue selon les disponibilités indiquées par l’intervenant ou la structure concernée. L’utilisateur doit vérifier les informations avant de confirmer sa réservation.",
      },
      {
        label: "Réalisation de la prestation",
        value:
          "L’intervenant est responsable de la bonne réalisation de la prestation qu’il propose. GotFit agit principalement comme plateforme de mise en relation et de facilitation du parcours utilisateur.",
      },
      {
        label: "Annulation ou modification",
        value:
          "Les conditions d’annulation, de modification ou de remboursement peuvent varier selon les prestations, les intervenants ou les règles définies par GotFit. L’utilisateur est invité à consulter les informations affichées au moment de la réservation.",
      },
    ],
  },
  {
    title: "Paiements et frais",
    icon: CreditCard,
    content: [
      {
        label: "Paiement en ligne",
        value:
          "Certaines prestations peuvent être réglées directement en ligne via les moyens de paiement proposés sur la plateforme. Les transactions sont traitées par des prestataires de paiement sécurisés.",
      },
      {
        label: "Prix",
        value:
          "Les prix des prestations sont indiqués sur la plateforme avant validation de la réservation. Ils peuvent inclure des frais de service, commissions ou frais techniques selon les règles appliquées par GotFit.",
      },
      {
        label: "Facturation",
        value:
          "Lorsque cela est applicable, les informations de paiement, les historiques de réservation et les justificatifs peuvent être disponibles depuis l’espace utilisateur.",
      },
    ],
  },
  {
    title: "Obligations des utilisateurs",
    icon: BadgeCheck,
    content: [
      {
        label: "Utilisation loyale",
        value:
          "L’utilisateur s’engage à utiliser GotFit de manière loyale, respectueuse, conforme aux lois en vigueur et aux présentes conditions générales d’utilisation.",
      },
      {
        label: "Informations exactes",
        value:
          "L’utilisateur s’engage à ne pas fournir de fausses informations, usurper une identité, publier des contenus trompeurs ou utiliser la plateforme à des fins frauduleuses.",
      },
      {
        label: "Comportement",
        value:
          "Les échanges entre utilisateurs doivent rester respectueux. Tout propos insultant, discriminatoire, menaçant, abusif ou contraire à la loi peut entraîner une suspension ou suppression du compte.",
      },
    ],
  },
  {
    title: "Contenus, annonces et avis",
    icon: ShieldCheck,
    content: [
      {
        label: "Publication de contenus",
        value:
          "Les utilisateurs peuvent être amenés à publier des annonces, descriptions, photos, documents, avis ou informations relatives à leurs prestations. Ils restent responsables des contenus qu’ils publient.",
      },
      {
        label: "Modération",
        value:
          "GotFit se réserve le droit de modérer, masquer, modifier ou supprimer tout contenu contraire aux présentes conditions, à la loi, à l’ordre public ou aux droits de tiers.",
      },
      {
        label: "Avis",
        value:
          "Les avis publiés doivent refléter une expérience réelle et rester honnêtes, respectueux et pertinents. Les faux avis, avis abusifs ou avis visant à nuire peuvent être supprimés.",
      },
    ],
  },
  {
    title: "Messagerie et communication",
    icon: Mail,
    content: [
      {
        label: "Messagerie interne",
        value:
          "GotFit peut proposer une messagerie permettant les échanges entre clients, intervenants et structures. Cette messagerie doit être utilisée uniquement dans le cadre des services proposés par la plateforme.",
      },
      {
        label: "Notifications",
        value:
          "L’utilisateur accepte de recevoir des notifications liées à son compte, ses réservations, ses messages, ses paiements ou toute information importante relative au service.",
      },
      {
        label: "Usage interdit",
        value:
          "Il est interdit d’utiliser la messagerie pour envoyer du spam, des contenus illicites, des sollicitations abusives ou des messages contraires à la destination de la plateforme.",
      },
    ],
  },
  {
    title: "Données personnelles",
    icon: LockKeyhole,
    content: [
      {
        label: "Traitement des données",
        value:
          "GotFit traite certaines données personnelles nécessaires au fonctionnement de la plateforme, notamment pour la gestion des comptes, réservations, paiements, messages, avis et support utilisateur.",
      },
      {
        label: "Politique de confidentialité",
        value:
          "Les modalités de collecte, d’utilisation, de conservation et de protection des données personnelles sont détaillées dans la politique de confidentialité accessible sur la plateforme.",
      },
      {
        label: "Sécurité",
        value:
          "GotFit met en œuvre des mesures techniques et organisationnelles destinées à protéger les données et les comptes utilisateurs contre les accès non autorisés.",
      },
    ],
  },
  {
    title: "Responsabilité",
    icon: AlertTriangle,
    content: [
      {
        label: "Disponibilité du service",
        value:
          "GotFit s’efforce d’assurer l’accessibilité et le bon fonctionnement de la plateforme, mais ne garantit pas une disponibilité permanente ou sans interruption.",
      },
      {
        label: "Rôle de GotFit",
        value:
          "GotFit facilite la mise en relation et le parcours de réservation, mais ne peut être tenue responsable des manquements propres aux utilisateurs, intervenants ou structures dans l’exécution des prestations.",
      },
      {
        label: "Limitation",
        value:
          "La responsabilité de GotFit ne saurait être engagée en cas de mauvaise utilisation de la plateforme, de force majeure, de panne technique, d’indisponibilité temporaire ou de dommage indirect.",
      },
    ],
  },
  {
    title: "Modification des conditions",
    icon: Scale,
    content: [
      {
        label: "Mise à jour",
        value:
          "GotFit se réserve le droit de modifier les présentes conditions générales d’utilisation afin de les adapter aux évolutions de la plateforme, de la législation ou des services proposés.",
      },
      {
        label: "Information",
        value:
          "Lorsque cela est nécessaire, les utilisateurs peuvent être informés des modifications importantes. La poursuite de l’utilisation de la plateforme vaut acceptation des conditions mises à jour.",
      },
      {
        label: "Version applicable",
        value:
          "La version applicable des conditions générales d’utilisation est celle disponible en ligne au moment de l’utilisation de la plateforme.",
      },
    ],
  },
];

const summaryItems = [
  "Créer un compte avec des informations exactes",
  "Utiliser la plateforme de manière loyale et respectueuse",
  "Respecter les règles de réservation et de paiement",
  "Ne pas publier de contenus trompeurs, abusifs ou illicites",
  "Protéger ses identifiants de connexion",
  "Consulter la politique de confidentialité pour les données personnelles",
];

export default function CGUPage() {
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
                  <Scale size={15} />
                  Conditions générales
                </span>

                <h1 className="max-w-4xl text-5xl font-black leading-[0.96] tracking-[-0.065em] text-[#21170b] sm:text-6xl lg:text-7xl">
                  Conditions générales
                  <span className="block text-[#b9872b]">
                    d’utilisation.
                  </span>
                </h1>
              </div>

              <p className="max-w-xl text-lg leading-8 text-[#6f5d43]">
                Ces conditions définissent les règles d’utilisation de la
                plateforme GotFit, les droits et obligations des utilisateurs,
                ainsi que les responsabilités liées aux services proposés.
              </p>
            </div>
          </div>
        </section>

        <section className="px-4 pb-20">
          <div className="mx-auto max-w-7xl">
            <div className="grid gap-6 lg:grid-cols-[0.75fr_1.25fr]">
              <aside className="h-fit rounded-[2rem] border border-[#b9872b]/14 bg-white p-7 shadow-[0_24px_70px_rgba(65,42,12,0.08)]">
                <div className="mb-6 flex h-14 w-14 items-center justify-center rounded-2xl bg-[#fff3d6] text-[#b9872b]">
                  <FileText size={26} />
                </div>

                <h2 className="text-2xl font-black tracking-[-0.04em] text-[#21170b]">
                  CGU GotFit
                </h2>

                <p className="mt-4 text-sm leading-7 text-[#6f5d43]">
                  Cette page présente les règles générales applicables aux
                  utilisateurs de GotFit. Elle doit être vérifiée et complétée
                  avec les informations juridiques exactes avant publication
                  officielle.
                </p>

                <div className="mt-7 rounded-[1.5rem] border border-[#b9872b]/14 bg-[#fff8e9] p-5">
                  <h3 className="text-sm font-black uppercase tracking-[0.14em] text-[#b9872b]">
                    En résumé
                  </h3>

                  <div className="mt-4 grid gap-3">
                    {summaryItems.map((item) => (
                      <div key={item} className="flex gap-3">
                        <span className="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-white text-[#b9872b] shadow-sm">
                          <ShieldCheck size={14} />
                        </span>

                        <p className="text-sm font-bold leading-6 text-[#5d4b2f]">
                          {item}
                        </p>
                      </div>
                    ))}
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

                <div className="mt-5 rounded-[1.5rem] border border-[#b9872b]/14 bg-[#fff8e9] p-5">
                  <h3 className="text-sm font-black uppercase tracking-[0.14em] text-[#b9872b]">
                    Contact
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
              </aside>

              <div className="grid gap-5">
                {cguSections.map((section) => {
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
              </div>
            </div>
          </div>
        </section>

        <section className="px-4 pb-24">
          <div className="mx-auto max-w-7xl rounded-[2.5rem] border border-[#b9872b]/14 bg-gradient-to-br from-[#fff8e9] via-white to-[#f2d58d]/40 p-8 shadow-[0_30px_90px_rgba(65,42,12,0.09)] md:p-12">
            <div className="grid gap-8 lg:grid-cols-[1fr_0.7fr] lg:items-center">
              <div>
                <p className="mb-3 text-sm font-black uppercase tracking-[0.2em] text-[#b9872b]">
                  Besoin d’aide ?
                </p>

                <h2 className="max-w-3xl text-3xl font-black tracking-[-0.05em] text-[#21170b] md:text-5xl">
                  Une question concernant l’utilisation de GotFit ?
                </h2>

                <p className="mt-5 max-w-2xl text-base leading-8 text-[#6f5d43]">
                  Contactez l’équipe GotFit pour toute question relative aux
                  conditions générales, à votre compte, à une réservation ou à
                  l’utilisation de la plateforme.
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