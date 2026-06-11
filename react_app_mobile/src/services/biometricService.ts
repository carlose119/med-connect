import * as LocalAuthentication from 'expo-local-authentication';
import AsyncStorage from '@react-native-async-storage/async-storage';

const BIOMETRIC_ENABLED_KEY = 'biometric_enabled';

/**
 * Check if the device supports biometric hardware and has at least one
 * biometric credential enrolled.
 */
export async function isBiometricAvailable(): Promise<boolean> {
  const compatible = await LocalAuthentication.hasHardwareAsync();
  if (!compatible) return false;

  const enrolled = await LocalAuthentication.isEnrolledAsync();
  return enrolled;
}

/**
 * Prompt the user for biometric authentication.
 * Returns true on success, false on failure or cancellation.
 * Does NOT throw — cancellation is treated as a normal failure.
 */
export async function authenticate(): Promise<boolean> {
  const result = await LocalAuthentication.authenticateAsync({
    promptMessage: 'Autenticación biométrica',
    fallbackLabel: 'Usar contraseña',
    cancelLabel: 'Cancelar',
    disableDeviceFallback: false,
  });

  return result.success;
}

/**
 * Enable biometric login for the current user by storing a flag.
 */
export async function enableBiometric(): Promise<void> {
  await AsyncStorage.setItem(BIOMETRIC_ENABLED_KEY, 'true');
}

/**
 * Disable biometric login by removing the flag.
 */
export async function disableBiometric(): Promise<void> {
  await AsyncStorage.removeItem(BIOMETRIC_ENABLED_KEY);
}

/**
 * Check whether the user has enabled biometric login in their settings.
 */
export async function isBiometricEnabled(): Promise<boolean> {
  const value = await AsyncStorage.getItem(BIOMETRIC_ENABLED_KEY);
  return value === 'true';
}