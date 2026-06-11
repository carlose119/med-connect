import React, {
  createContext,
  useCallback,
  useEffect,
  useReducer,
  type ReactNode,
} from 'react';
import type { LoginForm, RegisterForm, User } from '../types/auth';
import * as authService from '../services/authService';
import * as tokenStorage from '../services/tokenStorage';
import * as biometricService from '../services/biometricService';


// --- State & Types ---

interface AuthState {
  user: User | null;
  token: string | null;
  refreshToken: string | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  biometricAvailable: boolean;
}

type AuthAction =
  | { type: 'INIT_COMPLETE'; user: User | null; token: string | null; refreshToken: string | null; biometricAvailable: boolean }
  | { type: 'LOGIN_SUCCESS'; user: User; token: string; refreshToken: string }
  | { type: 'LOGOUT' }
  | { type: 'SET_LOADING'; isLoading: boolean }
  | { type: 'SET_BIOMETRIC_AVAILABLE'; available: boolean };

function authReducer(state: AuthState, action: AuthAction): AuthState {
  switch (action.type) {
    case 'INIT_COMPLETE':
      return {
        ...state,
        user: action.user,
        token: action.token,
        refreshToken: action.refreshToken,
        isAuthenticated: action.token !== null,
        isLoading: false,
        biometricAvailable: action.biometricAvailable,
      };
    case 'LOGIN_SUCCESS':
      return {
        ...state,
        user: action.user,
        token: action.token,
        refreshToken: action.refreshToken,
        isAuthenticated: true,
        isLoading: false,
      };
    case 'LOGOUT':
      return {
        ...state,
        user: null,
        token: null,
        refreshToken: null,
        isAuthenticated: false,
        isLoading: false,
      };
    case 'SET_LOADING':
      return { ...state, isLoading: action.isLoading };
    case 'SET_BIOMETRIC_AVAILABLE':
      return { ...state, biometricAvailable: action.available };
    default:
      return state;
  }
}

const initialState: AuthState = {
  user: null,
  token: null,
  refreshToken: null,
  isAuthenticated: false,
  isLoading: true,
  biometricAvailable: false,
};

// --- Context ---

interface AuthContextValue extends AuthState {
  login: (data: LoginForm) => Promise<void>;
  register: (data: RegisterForm) => Promise<void>;
  logout: () => Promise<void>;
  loginWithBiometric: () => Promise<void>;
}

export const AuthContext = createContext<AuthContextValue | null>(null);

// --- Provider ---

interface AuthProviderProps {
  children: ReactNode;
}

export function AuthProvider({ children }: AuthProviderProps) {
  const [state, dispatch] = useReducer(authReducer, initialState);

  // Initialize auth state from AsyncStorage on mount
  useEffect(() => {
    async function init() {
      const [token, refreshToken, user, biometricAvailable] = await Promise.all([
        tokenStorage.getToken(),
        tokenStorage.getRefreshToken(),
        tokenStorage.getUser(),
        biometricService.isBiometricAvailable(),
      ]);

      if (token) {
        try {
          // Validate token with /me endpoint
          const freshUser = await authService.me();
          dispatch({
            type: 'INIT_COMPLETE',
            user: freshUser,
            token,
            refreshToken: refreshToken ?? null,
            biometricAvailable,
          });
        } catch (error) {
          // Token is invalid/expired → clear and show login
          await tokenStorage.clearAll();
          dispatch({
            type: 'INIT_COMPLETE',
            user: null,
            token: null,
            refreshToken: null,
            biometricAvailable,
          });
        }
      } else {
        dispatch({
          type: 'INIT_COMPLETE',
          user: null,
          token: null,
          refreshToken: null,
          biometricAvailable,
        });
      }
    }

    init();
  }, []);

  const login = useCallback(async (data: LoginForm) => {
    dispatch({ type: 'SET_LOADING', isLoading: true });
    try {
      const user = await authService.login(data);
      const token = await tokenStorage.getToken();
      const refreshToken = await tokenStorage.getRefreshToken();
      dispatch({
        type: 'LOGIN_SUCCESS',
        user,
        token: token ?? '',
        refreshToken: refreshToken ?? '',
      });
    } catch (err) {
      dispatch({ type: 'SET_LOADING', isLoading: false });
      throw err;
    }
  }, []);

  const register = useCallback(async (data: RegisterForm) => {
    dispatch({ type: 'SET_LOADING', isLoading: true });
    try {
      const user = await authService.register(data);
      const token = await tokenStorage.getToken();
      const refreshToken = await tokenStorage.getRefreshToken();
      dispatch({
        type: 'LOGIN_SUCCESS',
        user,
        token: token ?? '',
        refreshToken: refreshToken ?? '',
      });
    } catch (err) {
      dispatch({ type: 'SET_LOADING', isLoading: false });
      throw err;
    }
  }, []);

  const logout = useCallback(async () => {
    dispatch({ type: 'SET_LOADING', isLoading: true });
    try {
      await authService.logout();
    } finally {
      dispatch({ type: 'LOGOUT' });
    }
  }, []);

  const loginWithBiometric = useCallback(async () => {
    const enabled = await biometricService.isBiometricEnabled();
    if (!enabled) return;

    const success = await biometricService.authenticate();
    if (!success) return;

    const token = await tokenStorage.getToken();
    if (!token) return;

    try {
      const user = await authService.me();
      const refreshToken = await tokenStorage.getRefreshToken();
      dispatch({
        type: 'LOGIN_SUCCESS',
        user,
        token,
        refreshToken: refreshToken ?? '',
      });
    } catch (error) {
      // Token invalid after biometric success → clear and show login
      await tokenStorage.clearAll();
      dispatch({ type: 'LOGOUT' });
      throw error;
    }
  }, []);

  const value: AuthContextValue = {
    ...state,
    login,
    register,
    logout,
    loginWithBiometric,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

// --- Hook ---

export function useAuth(): AuthContextValue {
  const context = React.useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
}