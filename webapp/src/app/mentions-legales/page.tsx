import Link from "next/link";
import Header from "@/components/layout/Header";
import Footer from "@/components/layout/Footer";
import {
  ArrowLeft,
  Building2,
  FileText,
  Globe,
  Mail,
  MapPin,
  Scale,
  ShieldCheck,
  User,
} from "lucide-react";

const legalBlocks = [
  {
    title: "Éditeur du site",
    icon: Building2,
    content: [
      {
        label: "Nom du site",
        value: "GotFit",
      },
      {
        label: "Responsable de publication",
        value: "GotFit",
      },
      {
        label: "Activité",
        value:
          "Plateforme digitale de mise en relation autour du sport, du mouvement, du bien-être et des prestations associées.",
      },
      {
        label: "Email de contact",
        value: "contact@gotfit.com",
      },
    ],
  },
  {
    title: "Hébergement",
    icon: Globe,
    content: [
      {
        label: "Hébergeur",
        value: "À compléter",
      },
      {
        label: "Adresse",
        value: "À compléter",
      },
      {
        label: "Site web",
        value: "À compléter",
      },
    ],
  },
  {
    title: "Propriété intellectuelle",
    icon: FileText,
    content: [
      {
        label: "Contenus",
        value:
          "L’ensemble des contenus présents sur le site GotFit, incluant notamment les textes, images, logos, éléments graphiques, interfaces, icônes et mises en page, est protégé par les règles relatives à la propriété intellectuelle.",
      },
      {
        label: "Utilisation",
        value:
          "Toute reproduction, représentation, modification, publication, transmission ou adaptation totale ou partielle du site ou de ses contenus, sans autorisation préalable, est interdite.",
      },
    ],
  },
  {
    title: "Données personnelles",
    icon: ShieldCheck,
    content: [
      {
        label: "Collecte",
        value:
          "GotFit peut collecter certaines données personnelles lors de l’utilisation du site, notamment lors de l’inscription, de la connexion, de la réservation, de l’utilisation du formulaire de contact ou de la messagerie.",
      },
      {
        label: "Finalité",
        value:
          "Ces données sont utilisées pour assurer le fonctionnement de la plateforme, la gestion des comptes, les réservations, les paiements, la relation utilisateur et l’amélioration du service.",
      },
      {
        label: "Droits utilisateur",
        value:
          "Conformément à la réglementation applicable, chaque utilisateur peut demander l’accès, la rectification ou la suppression de ses données personnelles en contactant GotFit.",
      },
    ],
  },
  {
    title: "Responsabilité",
    icon: Scale,
    content: [
      {
        label: "Accès au site",
        value:
          "GotFit s’efforce d’assurer l’accessibilité du site et de ses services, mais ne peut garantir une disponibilité permanente, notamment en cas de maintenance, d’incident technique ou de force majeure.",
      },
      {
        label: "Informations",
        value:
          "Les informations diffusées sur le site sont fournies à titre indicatif. GotFit ne saurait être tenue responsable d’une mauvaise utilisation des informations ou services proposés.",
      },
    ],
  },
];

export default function MentionsLegalesPage() {
  return (
    <>
      <Header />

      <main className="min-h-screen overflow-hidden bg-[#fffaf0] text-[#21170b]">
        <section className="relative px-4 pb-16 pt-32 lg:pt-40">
          <div className="pointer-events-none absolute -left-28 top-16 h-96 w-96 rounded-full bg-[#f2d58d]/40 blur-3xl" />
          <div className="pointer-events-none absolute -right-28 top-24 h-[28rem] w-[28rem] rounded-full bg-[#b9872b]/15 blur-3xl" />

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
                  Informations légales
                </span>

                <h1 className="max-w-4xl text-5xl font-black leading-[0.96] tracking-[-0.065em] text-[#21170b] sm:text-6xl lg:text-7xl">
                  Mentions
                  <span className="block text-[#b9872b]">légales.</span>
                </h1>
              </div>

              <p className="max-w-xl text-lg leading-8 text-[#6f5d43]">
                Cette page rassemble les informations relatives à l’éditeur du
                site, à l’hébergement, à la propriété intellectuelle, aux
                données personnelles et aux responsabilités liées à l’utilisation
                de la plateforme GotFit.
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
                  GotFit
                </h2>

                <p className="mt-4 text-sm leading-7 text-[#6f5d43]">
                  Les informations ci-dessous doivent être complétées avec les
                  coordonnées réelles de l’entreprise, de l’hébergeur et du
                  responsable de publication avant la mise en production.
                </p>

                <div className="mt-7 grid gap-3">
                  <div className="flex gap-3 rounded-2xl bg-[#fff8e9] p-3">
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

                  <div className="flex gap-3 rounded-2xl bg-[#fff8e9] p-3">
                    <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white text-[#b9872b] shadow-sm">
                      <MapPin size={18} />
                    </span>

                    <div>
                      <strong className="block text-sm text-[#21170b]">
                        Zone
                      </strong>
                      <span className="text-sm text-[#6f5d43]">
                        France, DOM-TOM et francophonie
                      </span>
                    </div>
                  </div>

                  <div className="flex gap-3 rounded-2xl bg-[#fff8e9] p-3">
                    <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white text-[#b9872b] shadow-sm">
                      <User size={18} />
                    </span>

                    <div>
                      <strong className="block text-sm text-[#21170b]">
                        Responsable
                      </strong>
                      <span className="text-sm text-[#6f5d43]">
                        À compléter
                      </span>
                    </div>
                  </div>
                </div>
              </aside>

              <div className="grid gap-5">
                {legalBlocks.map((block) => {
                  const Icon = block.icon;

                  return (
                    <article
                      key={block.title}
                      className="rounded-[2rem] border border-[#b9872b]/14 bg-white p-7 shadow-[0_18px_50px_rgba(65,42,12,0.06)]"
                    >
                      <div className="mb-6 flex items-center gap-4">
                        <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-[#fff3d6] text-[#b9872b]">
                          <Icon size={22} />
                        </span>

                        <h2 className="text-2xl font-black tracking-[-0.04em] text-[#21170b]">
                          {block.title}
                        </h2>
                      </div>

                      <div className="grid gap-4">
                        {block.content.map((item) => (
                          <div
                            key={`${block.title}-${item.label}`}
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
                  Contact légal
                </p>

                <h2 className="max-w-3xl text-3xl font-black tracking-[-0.05em] text-[#21170b] md:text-5xl">
                  Une question concernant vos données ou les informations
                  légales ?
                </h2>

                <p className="mt-5 max-w-2xl text-base leading-8 text-[#6f5d43]">
                  Vous pouvez contacter l’équipe GotFit pour toute demande liée
                  aux mentions légales, à la confidentialité ou à la gestion de
                  vos informations personnelles.
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