export const apiBaseUrl = (process.env.EXPO_PUBLIC_API_URL ?? '').replace(/\/$/, '');

export async function apiGet<T>(path: string, token?: string): Promise<T> {
  if (!apiBaseUrl) throw new Error('API URL is not configured');
  const response = await fetch(`${apiBaseUrl}/api/v1${path}`, {
    headers: { Accept: 'application/json', ...(token ? { Authorization: `Bearer ${token}` } : {}) },
  });
  if (!response.ok) throw new Error(`API request failed: ${response.status}`);
  return (await response.json()) as T;
}

export async function getHealth(): Promise<{ data: { status: string } }> {
  return apiGet('/health');
}
