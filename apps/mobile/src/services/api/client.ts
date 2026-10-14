import { Platform } from 'react-native';

const configuredApiBaseUrl =
  Platform.OS === 'web' ? process.env.EXPO_PUBLIC_WEB_API_URL : process.env.EXPO_PUBLIC_API_URL;

export const apiBaseUrl = (configuredApiBaseUrl ?? '').replace(/\/$/, '');

type ApiErrorPayload = {
  error?: string;
  message?: string;
  errors?: Record<string, string[]>;
};

async function apiError(response: Response): Promise<Error> {
  let detail = '';

  try {
    const payload = (await response.json()) as ApiErrorPayload;
    detail =
      payload.error ??
      Object.values(payload.errors ?? {})
        .flat()
        .find(Boolean) ??
      payload.message ??
      '';
  } catch {
    // Some infrastructure errors do not return JSON.
  }

  const restriction =
    response.status === 401
      ? 'La sesión venció o las credenciales no son válidas. Volvé a ingresar.'
      : response.status === 403
        ? 'Tu usuario no tiene permiso para realizar esta acción.'
        : response.status === 422
          ? 'El servidor rechazó los datos enviados.'
          : response.status >= 500
            ? 'El servidor no pudo completar la solicitud. Intentá nuevamente.'
            : 'No se pudo completar la solicitud.';

  return new Error(`${restriction} (${response.status})${detail ? ` ${detail}` : ''}`);
}

export async function apiGet<T>(path: string, token?: string): Promise<T> {
  if (!apiBaseUrl) throw new Error('La aplicación no tiene configurada la URL del servidor.');
  const response = await fetch(`${apiBaseUrl}/api/v1${path}`, {
    headers: { Accept: 'application/json', ...(token ? { Authorization: `Bearer ${token}` } : {}) },
  });
  if (!response.ok) throw await apiError(response);
  return (await response.json()) as T;
}

export async function apiPost<T>(path: string, body: unknown, token?: string): Promise<T> {
  if (!apiBaseUrl) throw new Error('La aplicación no tiene configurada la URL del servidor.');
  const response = await fetch(`${apiBaseUrl}/api/v1${path}`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: JSON.stringify(body),
  });
  if (!response.ok) throw await apiError(response);
  return (await response.json()) as T;
}

export async function getHealth(): Promise<{ data: { status: string } }> {
  return apiGet('/health');
}
