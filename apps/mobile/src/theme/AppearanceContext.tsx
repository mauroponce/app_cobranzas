import {
  createContext,
  useContext,
  useEffect,
  useMemo,
  useState,
  type PropsWithChildren,
} from 'react';
import { useColorScheme } from 'react-native';
import { PaperProvider } from 'react-native-paper';
import { getMetadata, setMetadata } from '../database/database';
import { darkTheme, lightTheme } from './theme';

export type AppearancePreference = 'system' | 'light' | 'dark';
const AppearanceContext = createContext<{
  preference: AppearancePreference;
  setPreference: (value: AppearancePreference) => void;
  isDark: boolean;
}>({ preference: 'system', setPreference: () => {}, isDark: false });

export function AppearanceProvider({ children }: PropsWithChildren) {
  const systemScheme = useColorScheme();
  const [preference, updatePreference] = useState<AppearancePreference>('system');
  useEffect(() => {
    getMetadata('appearance')
      .then((saved) => {
        if (saved === 'light' || saved === 'dark' || saved === 'system') updatePreference(saved);
      })
      .catch(() => {});
  }, []);
  const isDark = preference === 'system' ? systemScheme === 'dark' : preference === 'dark';
  const value = useMemo(
    () => ({
      preference,
      isDark,
      setPreference: (next: AppearancePreference) => {
        updatePreference(next);
        setMetadata('appearance', next).catch(() => {});
      },
    }),
    [preference, isDark],
  );
  return (
    <AppearanceContext.Provider value={value}>
      <PaperProvider theme={isDark ? darkTheme : lightTheme}>{children}</PaperProvider>
    </AppearanceContext.Provider>
  );
}

export function useAppearance() {
  return useContext(AppearanceContext);
}
