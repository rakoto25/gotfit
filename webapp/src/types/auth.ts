export type Role = {
  id: number;
  name: string;
  slug?: string;
};

export type User = {
  id: number;
  name: string;
  email: string;
  phone?: string | null;
  address?: string | null;
  city?: string | null;
  location?: string | null;
  bio?: string | null;
  photo?: string | null;
  photo_url?: string | null;
  cover_photo?: string | null;
  cover_photo_url?: string | null;
  account_status?: string | null;
  coach_title?: string | null;
  coach_short_description?: string | null;
  coach_speciality?: string | null;
  coach_experience_years?: number | string | null;
  coach_certifications?: string[] | string | null;
  coach_languages?: string[] | string | null;
  presentation_video?: string | null;
  presentation_video_url?: string | null;
  presentation_video_duration_seconds?: number | string | null;
  roles?: Role[];
};

export type LoginResponse = {
  token: string;
  user: User;
};

export type RegisterPayload = {
  name: string;
  email: string;
  password: string;
  password_confirmation?: string;
  phone?: string;
  address?: string;
  role?: "client" | "intervenant" | "structure";
};

export type LoginPayload = {
  email: string;
  password: string;
};
