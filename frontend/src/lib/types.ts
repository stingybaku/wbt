import type { Module } from './moduleSchema';

export type User = {
  id: number;
  email: string;
  name: string;
};

export type LoginResponse = {
  token: string;
  must_change_password: boolean;
  user: User;
};

export type Project = {
  id: string;
  title: string;
  rights_attestation: boolean;
  source_type: 'file' | 'url' | null;
  source_url: string | null;
  source_character_count: number | null;
  cover_image_url: string | null;
  module_status: 'generating' | 'generated' | 'failed' | null;
  source_summary: string | null;
  duration_minutes: number | null;
  screen_count: number | null;
  invite_count: number;
  created_at: string;
  updated_at: string;
};

export type ProjectsResponse = {
  projects: Project[];
};

export type CreateProjectResponse = {
  project: Project;
};

export type ModuleStatus = 'generating' | 'generated' | 'failed';

export type ModuleRecord = {
  id: string;
  project_id: string;
  status: ModuleStatus;
  module_json: Module | null;
  generation_error: string | null;
  created_at: string;
  updated_at: string;
};

export type ModuleResponse = {
  module: ModuleRecord | null;
};

export type FrontendTextsResponse = {
  route_key: string;
  texts: Record<string, string>;
};

export type InviteProgress = {
  current_screen_index: number;
  total_screens: number;
  percent: number;
  completed_at: string | null;
  last_active_at: string | null;
};

export type ProjectInvite = {
  id: string;
  email: string;
  expires_days: number;
  expires_at: string;
  revoked_at: string | null;
  is_active: boolean;
  created_at: string;
  progress: InviteProgress | null;
};

export type AppNotification = {
  id: string;
  type: 'generation_complete' | 'generation_failed' | 'general';
  title: string;
  body: string;
  project_id: string | null;
  read_at: string | null;
  created_at: string;
};

export type NotificationsResponse = {
  notifications: AppNotification[];
  unread_count: number;
};

// ─── Billing ──────────────────────────────────────────────────────────────────

export type Plan = {
  id: string;
  name: string;
  type: 'one_time' | 'subscription';
  price_cents: number;
  billing_period: 'monthly' | 'annual' | null;
  module_limit: number;
  seat_limit: number;
  rollover: boolean;
  paypal_plan_id: string | null;
};

export type BillingSlot = {
  id: string | null;
  title: string | null;
  seats_used: number;
  seats_limit: number;
};

export type BillingTransaction = {
  id: string;
  plan_name: string;
  plan_type: 'one_time' | 'subscription';
  amount_formatted: string;
  amount_cents: number;
  status: 'completed' | 'refunded';
  description: string | null;
  payment_reference: string | null;
  created_at: string;
};

export type BillingSubscription = {
  id: string;
  plan: Pick<Plan, 'id' | 'name' | 'type' | 'module_limit' | 'seat_limit' | 'billing_period' | 'rollover'>;
  status: 'active' | 'pending' | 'cancelled';
  modules_used: number;
  modules_available: number;
  seats_used: number;
  seats_available: number;
  current_period_end: string | null;
  cancelled_at: string | null;
  slots?: BillingSlot[];
};
