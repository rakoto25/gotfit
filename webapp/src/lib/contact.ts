import { API_BASE_URL } from "@/lib/api-config";

export type ContactPayload = {
  name: string;
  email: string;
  phone?: string;
  subject: string;
  message: string;
};

export type ContactResponse = {
  success: boolean;
  message: string;
  errors?: Record<string, string[]>;
};

export const sendContactMessage = async (
  payload: ContactPayload
): Promise<ContactResponse> => {
  const response = await fetch(`${API_BASE_URL}/contact`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify(payload),
  });

  const data = await response.json().catch(() => null);

  if (!response.ok) {
    throw new Error(
      data?.message || "Impossible d’envoyer le message pour le moment."
    );
  }

  return data as ContactResponse;
};
