import type { LoginForm, RegisterForm, User } from '../types/auth';
import apiClient from './apiClient';
import * as tokenStorage from './tokenStorage';

interface LoginResponse {
  token: string;
  refresh_token: string;
  user: User;
}

interface MeResponse {
  user: User;
}

export async function login(data: LoginForm): Promise<User> {
  const response = await apiClient.post<LoginResponse>('/api/auth/login', data);
  const { token, refresh_token, user } = response.data;

  await tokenStorage.setToken(token);
  await tokenStorage.setRefreshToken(refresh_token);
  await tokenStorage.setUser(user);

  return user;
}

export async function register(data: RegisterForm): Promise<User> {
  const response = await apiClient.post<LoginResponse>('/api/auth/register', data);
  const { token, refresh_token, user } = response.data;

  await tokenStorage.setToken(token);
  await tokenStorage.setRefreshToken(refresh_token);
  await tokenStorage.setUser(user);

  return user;
}

export async function logout(): Promise<void> {
  try {
    await apiClient.post('/api/auth/logout');
  } catch {
    // Even if the API call fails, clear local state
  } finally {
    await tokenStorage.clearAll();
  }
}

export async function me(): Promise<User> {
  const response = await apiClient.get<MeResponse>('/api/auth/me');
  const { user } = response.data;

  // Keep user cache fresh
  await tokenStorage.setUser(user);

  return user;
}