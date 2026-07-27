import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  reactCompiler: true,
  images: {
    remotePatterns: [
      {
        protocol: "https",
        hostname: "api.gotfit.tech",
      },
      {
        protocol: "https",
        hostname: "admin.gotfit.tech",
      },
      {
        protocol: "https",
        hostname: "gotfit.tech",
      },
    ],
  },
};

export default nextConfig;
