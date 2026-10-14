import { Platform } from 'react-native';
import * as SecureStore from 'expo-secure-store';

function webStorage(): Storage {
  if (typeof globalThis.sessionStorage === 'undefined') {
    throw new Error('El almacenamiento de sesión no está disponible.');
  }

  return globalThis.sessionStorage;
}

export const tokenStorage = {
  async deleteItem(key: string): Promise<void> {
    if (Platform.OS === 'web') {
      webStorage().removeItem(key);
      return;
    }

    await SecureStore.deleteItemAsync(key);
  },
  async getItem(key: string): Promise<string | null> {
    if (Platform.OS === 'web') return webStorage().getItem(key);

    return SecureStore.getItemAsync(key);
  },
  async setItem(key: string, value: string): Promise<void> {
    if (Platform.OS === 'web') {
      webStorage().setItem(key, value);
      return;
    }

    await SecureStore.setItemAsync(key, value);
  },
};
