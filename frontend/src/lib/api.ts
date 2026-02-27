import type { CreateProjectResponse, FrontendTextsResponse, LoginResponse, ProjectsResponse } from './types';

const apiBase = (import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000/api').replace(/\/$/, '');

type RequestOptions = {
  method?: 'GET' | 'POST' | 'PUT';
  body?: unknown;
  token?: string;
};

async function request<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const response = await fetch(`${apiBase}${path}`, {
    method: options.method || 'GET',
    headers: {
      'Content-Type': 'application/json',
      ...(options.token ? { Authorization: `Bearer ${options.token}` } : {})
    },
    body: options.body ? JSON.stringify(options.body) : undefined
  });

  if (!response.ok) {
    const text = await response.text();
    throw new Error(text || `Request failed with status ${response.status}`);
  }

  return response.json() as Promise<T>;
}

export function login(email: string, password: string): Promise<LoginResponse> {
  return request<LoginResponse>('/auth/login', {
    method: 'POST',
    body: { email, password }
  });
}

export function fetchProjects(token: string): Promise<ProjectsResponse> {
  return request<ProjectsResponse>('/projects', { token });
}

export function createProject(
  token: string,
  payload: { title: string; rights_attestation: boolean }
): Promise<CreateProjectResponse> {
  return request<CreateProjectResponse>('/projects', {
    method: 'POST',
    token,
    body: payload
  });
}

export function fetchFrontendTexts(routeKey: string): Promise<FrontendTextsResponse> {
  return request<FrontendTextsResponse>(`/frontend-texts?route_key=${encodeURIComponent(routeKey)}`);
}
