import {
  createContext,
  useContext,
  useEffect,
  useMemo,
  useState,
  type PropsWithChildren,
} from 'react';
import type { Capabilities } from '../contracts/capabilities';
import { apiGet, apiPost } from '../services/api/client';
import { tokenStorage } from './tokenStorage';

type AuthState = { token: string; capabilities: Capabilities; deviceId: string } | null;
type AuthContextValue = {
  state: AuthState;
  ready: boolean;
  login: (email: string, password: string) => Promise<void>;
  refresh: () => Promise<void>;
  logout: () => Promise<void>;
};
const AuthContext = createContext<AuthContextValue>({
  state: null,
  ready: false,
  login: async () => {},
  refresh: async () => {},
  logout: async () => {},
});
const tokenKey = 'app-cobranzas.token';
const deviceKey = 'app-cobranzas.device';

async function capabilities(token: string): Promise<Capabilities> {
  const tenants = await apiGet<{ data: { id: string }[] }>('/tenants', token);
  if (!tenants.data.length) throw new Error('No tenés una empresa activa.');
  await apiPost('/tenant/select', { tenant_id: tenants.data[0].id }, token);
  return (await apiGet<{ data: Capabilities }>('/me/capabilities', token)).data;
}

async function device(token: string): Promise<string> {
  let identifier = await tokenStorage.getItem(deviceKey);
  identifier ??= `expo-${globalThis.crypto.randomUUID()}`;
  await tokenStorage.setItem(deviceKey, identifier);
  return (
    await apiPost<{ data: { id: string } }>(
      '/mobile-devices',
      { device_identifier: identifier },
      token,
    )
  ).data.id;
}

export function AuthProvider({ children }: PropsWithChildren) {
  const [state, setState] = useState<AuthState>(null);
  const [ready, setReady] = useState(false);
  useEffect(() => {
    tokenStorage
      .getItem(tokenKey)
      .then(async (token) => {
        if (token)
          setState({
            token,
            capabilities: await capabilities(token),
            deviceId: await device(token),
          });
      })
      .catch(() => tokenStorage.deleteItem(tokenKey))
      .finally(() => setReady(true));
  }, []);
  const value = useMemo(
    () => ({
      state,
      ready,
      login: async (email: string, password: string) => {
        const data = await apiPost<{ data: { token: string } }>('/auth/login', {
          email,
          password,
        });
        const next = {
          token: data.data.token,
          capabilities: await capabilities(data.data.token),
          deviceId: await device(data.data.token),
        };
        await tokenStorage.setItem(tokenKey, next.token);
        setState(next);
      },
      refresh: async () => {
        if (state)
          setState({
            ...state,
            capabilities: await capabilities(state.token),
            deviceId: await device(state.token),
          });
      },
      logout: async () => {
        try {
          if (state) await apiPost('/auth/logout', {}, state.token);
        } finally {
          await tokenStorage.deleteItem(tokenKey);
          setState(null);
        }
      },
    }),
    [state, ready],
  );
  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}
export const useAuth = () => useContext(AuthContext);
