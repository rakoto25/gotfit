import type { Metadata, Viewport } from "next";
import "@livekit/components-styles";
import "./globals.css";

export const metadata: Metadata = {
  metadataBase: new URL(
    process.env.NEXT_PUBLIC_SITE_URL || "https://gotfit.tech"
  ),
  title: {
    default: "Gotfit — Le bon coach, au bon rythme",
    template: "%s | Gotfit",
  },
  description:
    "Trouvez un coach vérifié, réservez vos séances et centralisez visio, paiements, messages et suivi dans votre espace Gotfit.",
  keywords: [
    "coach sportif",
    "bien-être",
    "réservation coach",
    "coaching visio",
    "suivi sportif",
  ],
  openGraph: {
    type: "website",
    locale: "fr_FR",
    siteName: "Gotfit",
    title: "Gotfit — Le bon coach, au bon rythme",
    description:
      "Des coachs vérifiés, des réservations simples et un suivi continu.",
    images: [
      {
        url: "/og.png",
        width: 1731,
        height: 909,
        alt: "Gotfit — Le bon coach. Le bon rythme. De vrais progrès.",
      },
    ],
  },
  twitter: {
    card: "summary_large_image",
    title: "Gotfit — Le bon coach, au bon rythme",
    description:
      "Des coachs vérifiés, des réservations simples et un suivi continu.",
    images: ["/og.png"],
  },
};

export const viewport: Viewport = {
  themeColor: "#15211b",
  colorScheme: "light",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="fr" className="h-full antialiased">
      <body className="flex min-h-full flex-col">{children}</body>
    </html>
  );
}
