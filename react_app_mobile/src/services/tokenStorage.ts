import AsyncStorage from '@react-native-async-storage/async-storage';
import type { User } from '../types/auth';

const KEYS = {
  AUTH_TOKEN: 'auth_token',
  AUTH_REFRESH_TOKEN: 'auth_refresh_token',
  AUTH_USER: 'auth_user',
} as const;

// Token

export async function getToken(): Promise<string | null> {
  try {
    return await AsyncStorage.getItem(KEYS.AUTH_TOKEN);
  } catch {
    return null;
  }
}

export async function setToken(token: string): Promise<void> {
  await AsyncStorage.setItem(KEYS.AUTH_TOKEN, token);
}

// Refresh Token

export async function getRefreshToken(): Promise<string | null> {
  try {
    return await AsyncStorage.getItem(KEYS.AUTH_REFRESH_TOKEN);
  } catch {
    return null;
  }
}

export async function setRefreshToken(refreshToken: string): Promise<void> {
  await AsyncStorage.setItem(KEYS.AUTH_REFRESH_TOKEN, refreshToken);
}

// Set both tokens at once (used after refresh)
export async function setTokens(
  token: string,
  refreshToken: string,
): Promise<void> {
  await Promise.all([
    AsyncStorage.setItem(KEYS.AUTH_TOKEN, token),
    AsyncStorage.setItem(KEYS.AUTH_REFRESH_TOKEN, refreshToken),
  ]);
}

// User

export async function getUser(): Promise<User | null> {
  try {
    const raw = await AsyncStorage.getItem(KEYS.AUTH_USER);
    if (!raw) return null;
    return JSON.parse(raw) as User;
  } catch {
    return null;
  }
}

export async function setUser(user: User): Promise<void> {
  await AsyncStorage.setItem(KEYS.AUTH_USER, JSON.stringify(user));
}

// Clear all auth data (used on logout or refresh failure)

export async function clearAll(): Promise<void> {
  await Promise.all([
    AsyncStorage.removeItem(KEYS.AUTH_TOKEN),
    AsyncStorage.removeItem(KEYS.AUTH_REFRESH_TOKEN),
    AsyncStorage.removeItem(KEYS.AUTH_USER),
  ]);
}