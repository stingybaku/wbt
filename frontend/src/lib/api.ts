import type {
  CreateProjectResponse,
  FrontendTextsResponse,
  LoginResponse,
  ModuleResponse,
  NotificationsResponse,
  ProjectsResponse,
} from './types';
import type { Module } from './moduleSchema';

const apiBase = (import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000/api').replace(/\/$/, '');

// Registered by AuthProvider to clear auth state when a 401 is received.
let unauthorizedHandler: (() => void) | null = null;
export function setUnauthorizedHandler(handler: (() => void) | null) {
  unauthorizedHandler = handler;
}

type RequestOptions = {
  method?: 'GET' | 'POST' | 'PUT' | 'DELETE';
  body?: unknown;
  token?: string;
};

async function request<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const response = await fetch(`${apiBase}${path}`, {
    method: options.method || 'GET',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      ...(options.token ? { Authorization: `Bearer ${options.token}` } : {}),
    },
    body: options.body ? JSON.stringify(options.body) : undefined,
  });

  if (response.status === 401) {
    unauthorizedHandler?.();
    throw new Error('Session expired. Please log in again.');
  }

  if (!response.ok) {
    const text = await response.text();
    throw new Error(text || `Request failed with status ${response.status}`);
  }

  return response.json() as Promise<T>;
}

export function login(email: string, password: string): Promise<LoginResponse> {
  return request<LoginResponse>('/auth/login', {
    method: 'POST',
    body: { email, password },
  });
}

export function logout(token: string): Promise<{ ok: boolean }> {
  return request<{ ok: boolean }>('/auth/logout', { method: 'POST', token });
}

export function changePassword(
  token: string,
  payload: { current_password: string; new_password: string; new_password_confirmation: string }
): Promise<{ ok: boolean }> {
  return request<{ ok: boolean }>('/auth/change-password', { method: 'POST', token, body: payload });
}

export function fetchProjects(token: string): Promise<ProjectsResponse> {
  return request<ProjectsResponse>('/projects', { token });
}

export function fetchProject(token: string, id: string): Promise<{ project: import('./types').Project }> {
  return request<{ project: import('./types').Project }>(`/projects/${id}`, { token });
}

export function createProject(
  token: string,
  payload: { title: string; rights_attestation: boolean }
): Promise<CreateProjectResponse> {
  return request<CreateProjectResponse>('/projects', {
    method: 'POST',
    token,
    body: payload,
  });
}

export function fetchModule(token: string, projectId: string): Promise<ModuleResponse> {
  return request<ModuleResponse>(`/projects/${projectId}/module`, { token });
}

export function updateModule(token: string, projectId: string, moduleJson: Module): Promise<ModuleResponse> {
  return request<ModuleResponse>(`/projects/${projectId}/module`, {
    method: 'PUT',
    token,
    body: { module_json: moduleJson },
  });
}

export function generateModule(
  token: string,
  projectId: string,
  params: { duration_minutes: number; learning_objectives?: string; assessment_count: number }
): Promise<{ status: string }> {
  return request<{ status: string }>(`/projects/${projectId}/module/generate`, {
    method: 'POST',
    token,
    body: params,
  });
}

export type UploadSourceResponse = {
  source_type: 'file' | 'url';
  source_url: string | null;
  character_count: number;
  truncated: boolean;
};

export function uploadSource(
  token: string,
  projectId: string,
  source: { type: 'file'; file: File } | { type: 'url'; url: string },
  onProgress?: (pct: number) => void
): Promise<UploadSourceResponse> {
  return new Promise((resolve, reject) => {
    const form = new FormData();
    form.append('source_type', source.type);
    if (source.type === 'file') {
      form.append('file', source.file);
    } else {
      form.append('url', source.url);
    }

    const xhr = new XMLHttpRequest();
    xhr.open('POST', `${apiBase}/projects/${projectId}/source`);
    xhr.setRequestHeader('Authorization', `Bearer ${token}`);
    xhr.setRequestHeader('Accept', 'application/json');

    if (onProgress) {
      xhr.upload.addEventListener('progress', (e) => {
        if (e.lengthComputable) onProgress(Math.round((e.loaded / e.total) * 100));
      });
    }

    xhr.addEventListener('load', () => {
      if (xhr.status >= 200 && xhr.status < 300) {
        resolve(JSON.parse(xhr.responseText) as UploadSourceResponse);
      } else {
        reject(new Error(xhr.responseText || `Upload failed with status ${xhr.status}`));
      }
    });
    xhr.addEventListener('error', () => reject(new Error('Upload failed')));
    xhr.send(form);
  });
}

export function fetchInvites(token: string, projectId: string): Promise<{ invites: import('./types').ProjectInvite[] }> {
  return request(`/projects/${projectId}/invites`, { token });
}

export function createInvite(
  token: string,
  projectId: string,
  payload: { email: string; expires_days: number }
): Promise<{ invite: import('./types').ProjectInvite }> {
  return request(`/projects/${projectId}/invites`, { method: 'POST', token, body: payload });
}

export function revokeInvite(token: string, projectId: string, inviteId: string): Promise<{ ok: boolean }> {
  return request(`/projects/${projectId}/invites/${inviteId}`, { method: 'DELETE', token });
}

export type ViewerQuestionAnswer = { selected: string[]; submitted: boolean; isCorrect: boolean };

export function fetchViewerModule(viewerToken: string): Promise<{
  project_title: string;
  module: import('./moduleSchema').Module;
  expires_at: string;
  progress: {
    current_screen_index: number;
    total_screens: number;
    completed_at: string | null;
    question_answers: Record<string, ViewerQuestionAnswer>;
    visited_screens: number[];
  } | null;
}> {
  return request(`/view/${viewerToken}`);
}

export function verifyViewer(viewerToken: string, email: string, name: string): Promise<{ ok: boolean }> {
  return request(`/view/${viewerToken}/verify`, { method: 'POST', body: { email, name } });
}

export async function downloadCertificate(token: string, projectId: string, inviteId: string): Promise<void> {
  const response = await fetch(`${apiBase}/projects/${projectId}/invites/${inviteId}/certificate`, {
    headers: {
      Authorization: `Bearer ${token}`,
      Accept: 'application/pdf',
    },
  });
  if (!response.ok) throw new Error('Certificate not available yet.');
  const blob = await response.blob();
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'certificate.pdf';
  a.click();
  URL.revokeObjectURL(url);
}

export function updateViewerProgress(
  viewerToken: string,
  payload: {
    current_screen_index: number;
    total_screens: number;
    question_answers?: Record<string, ViewerQuestionAnswer>;
    visited_screens?: number[];
  }
): Promise<{ ok: boolean }> {
  return request(`/view/${viewerToken}/progress`, { method: 'POST', body: payload });
}

// ─── Report ───────────────────────────────────────────────────────────────────

export type ReportViewerRow = {
  name: string | null;
  email: string;
  progress: { current: number; total: number } | null;
  score: { correct: number; total: number } | null;
  completed_at: string | null;
  last_active_at: string | null;
  has_certificate: boolean;
};

export type ReportQuestion = {
  screen_id: string;
  prompt: string;
  pct_correct: number | null;
  respondents: number;
  choices: { id: string; text: string; is_correct: boolean; pick_count: number }[];
};

export type ProjectReport = {
  summary: {
    invited: number;
    started: number;
    completed: number;
    avg_score: number | null;
  };
  viewers: ReportViewerRow[];
  questions: ReportQuestion[];
};

export function fetchProjectReport(token: string, projectId: string): Promise<ProjectReport> {
  return request<ProjectReport>(`/projects/${projectId}/report`, { token });
}

export function fetchNotifications(token: string): Promise<NotificationsResponse> {
  return request<NotificationsResponse>('/notifications', { token });
}

export function markNotificationRead(token: string, id: string): Promise<{ ok: boolean }> {
  return request<{ ok: boolean }>(`/notifications/${id}/read`, { method: 'POST', token });
}

export function markAllNotificationsRead(token: string): Promise<{ ok: boolean }> {
  return request<{ ok: boolean }>('/notifications/read-all', { method: 'POST', token });
}

export function fetchFrontendTexts(routeKey: string): Promise<FrontendTextsResponse> {
  return request<FrontendTextsResponse>(`/frontend-texts?route_key=${encodeURIComponent(routeKey)}`);
}

export type UnsplashPhoto = {
  id: string;
  alt_description: string | null;
  description: string | null;
  urls: { regular: string; small: string; thumb: string };
  user: { name: string };
  links: { html: string };
};

export type UnsplashSearchResponse = {
  total: number;
  total_pages: number;
  results: UnsplashPhoto[];
};

export function searchUnsplash(
  token: string,
  query: string,
  page = 1
): Promise<UnsplashSearchResponse> {
  const params = new URLSearchParams({ q: query, page: String(page) });
  return request<UnsplashSearchResponse>(`/unsplash/search?${params}`, { token });
}

export type PexelsPhoto = {
  id: string;
  url: string;
  thumb: string;
  photographer: string;
  photographer_url: string;
  alt: string;
};

export type PexelsSearchResponse = {
  photos: PexelsPhoto[];
  total_pages: number;
  page: number;
};

export function searchPexels(
  token: string,
  query: string,
  page = 1
): Promise<PexelsSearchResponse> {
  const params = new URLSearchParams({ q: query, page: String(page) });
  return request<PexelsSearchResponse>(`/pexels/search?${params}`, { token });
}
