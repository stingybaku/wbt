import type { Module } from './moduleSchema';

export type User = {
  id: number;
  email: string;
  name: string;
};

export type LoginResponse = {
  token: string;
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
