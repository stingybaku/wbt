export type User = {
  id: number;
  email: string;
};

export type LoginResponse = {
  token: string;
  user: User;
};

export type Project = {
  id: string;
  title: string;
  rights_attestation: boolean;
  created_at: string;
  updated_at: string;
};

export type ProjectsResponse = {
  projects: Project[];
};

export type CreateProjectResponse = {
  project: Project;
};
