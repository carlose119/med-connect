import axios, { type AxiosError } from 'axios';
import type { ApiError, ApiErrorCode } from '../types/auth';
import * as tokenStorage from './tokenStorage';

const BASE_URL =
  String(
    (globalThis as { process?: { env?: Record<string, string> } })
      .process?.env?.EXPO_PUBLIC_API_URL ?? 'http://localhost:8000',
  );

const apiClient = axios.create({
  baseURL: BASE_URL,
  timeout: 15000,
});

// --- Request interceptor: inject Bearer token ---

apiClient.interceptors.request.use(async (config) => {
  const token = await tokenStorage.getToken();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// --- Response interceptor: error normalization + 401 refresh/retry ---

let isRefreshing = false;
let refreshQueue: Array<(token: string) => void> = [];

function normalizeError(error: unknown): ApiError {
  const axiosError = error as AxiosError<{
    message?: string;
    errors?: Record<string, string[]>;
  }>;

  // Network / transport error
  if (axiosError.request && !axiosError.response) {
    return {
      code: 'NETWORK_ERROR',
      message: 'Error de conexión. Verifica tu internet.',
    };
  }

  const status = axiosError.response?.status;
  const data = axiosError.response?.data;

  switch (status) {
    case 422:
      return {
        code: 'VALIDATION_ERROR',
        message: 'Error de validación',
        details: data?.errors,
      };
    case 401:
      return {
        code: 'UNAUTHORIZED',
        message: 'Credenciales inválidas',
      };
    case 500:
    case 502:
    case 503:
    case 504:
      return {
        code: 'SERVER_ERROR',
        message: data?.message ?? 'Error del servidor. Intenta más tarde.',
      };
    default:
      return {
        code: 'SERVER_ERROR',
        message: data?.message ?? 'Ocurrió un error inesperado.',
      };
  }
}

apiClient.interceptors.response.use(
  (response) => response,
  async (error) => {
    const originalRequest = error.config as typeof error.config & {
      _retry?: boolean;
    };

    // 401 → attempt refresh (but not for the refresh request itself)
    if (
      error.response?.status === 401 &&
      !originalRequest._retry &&
      !originalRequest.url?.includes('/auth/refresh')
    ) {
      if (isRefreshing) {
        // Queue the request until refresh completes
        return new Promise((resolve) => {
          refreshQueue.push((token: string) => {
            originalRequest.headers.Authorization = `Bearer ${token}`;
            resolve(apiClient(originalRequest));
          });
        });
      }

      originalRequest._retry = true;
      isRefreshing = true;

      try {
        const refreshToken = await tokenStorage.getRefreshToken();
        if (!refreshToken) throw new Error('No refresh token');

        const response = await axios.post(`${BASE_URL}/api/auth/refresh`, {
          refresh_token: refreshToken,
        });

        const { token, refresh_token } = response.data as {
          token: string;
          refresh_token: string;
        };
        await tokenStorage.setTokens(token, refresh_token);

        // Retry all queued requests
        refreshQueue.forEach((cb) => cb(token));
        refreshQueue = [];

        originalRequest.headers.Authorization = `Bearer ${token}`;
        return apiClient(originalRequest);
      } catch {
        // Refresh failed → clear tokens and signal session expiry
        await tokenStorage.clearAll();
        refreshQueue.forEach((cb) => cb(''));
        refreshQueue = [];
        return Promise.reject<ApiError>({
          code: 'SESSION_EXPIRED',
          message: 'Tu sesión expiró.',
        });
      } finally {
        isRefreshing = false;
      }
    }

    return Promise.reject(normalizeError(error));
  },
);

export default apiClient;