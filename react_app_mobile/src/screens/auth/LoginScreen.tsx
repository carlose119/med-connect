import React, { useState } from 'react';
import {
  View,
  Text,
  TextInput,
  TouchableOpacity,
  StyleSheet,
  ActivityIndicator,
  Alert,
} from 'react-native';
import { useForm, Controller } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import type { ApiError } from '../../types/auth';
import { useAuth } from '../../contexts/AuthContext';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import type { AuthStackParamList } from '../../navigation/AuthNavigator';

const loginSchema = z.object({
  email: z.string().email('Ingresa un email válido'),
  password: z.string().min(8, 'La contraseña debe tener al menos 8 caracteres'),
  remember_me: z.boolean().optional(),
});

type LoginFormData = z.infer<typeof loginSchema>;

interface Props {
  navigation?: NavigationProp;
}

type NavigationProp = NativeStackNavigationProp<AuthStackParamList, 'Login'>;

export default function LoginScreen({ navigation }: Props) {
  const { login, biometricAvailable, loginWithBiometric } = useAuth();
  const [biometricEnabled, setBiometricEnabled] = useState(false);
  const [showBiometric, setShowBiometric] = useState(false);

  const {
    control,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<LoginFormData>({
    resolver: zodResolver(loginSchema),
    mode: 'onBlur',
  });

  React.useEffect(() => {
    async function checkBiometric() {
      const { isBiometricEnabled } = await import('../../services/biometricService');
      const enabled = await isBiometricEnabled();
      setBiometricEnabled(enabled);
      setShowBiometric(biometricAvailable && enabled);
    }
    checkBiometric();
  }, [biometricAvailable]);

  async function onSubmit(data: LoginFormData) {
    try {
      await login(data);
    } catch (err) {
      const error = err as ApiError;
      if (error.code === 'VALIDATION_ERROR' && error.details) {
        Object.entries(error.details).forEach(([field, messages]) => {
          setError(field as keyof LoginFormData, {
            message: messages[0] ?? 'Error de validación',
          });
        });
      } else if (error.code === 'SESSION_EXPIRED') {
        Alert.alert('Sesión expirada', 'Tu sesión expiró. Iniciá sesión de nuevo.');
      } else if (error.code === 'UNAUTHORIZED') {
        setError('password', { message: 'Credenciales inválidas' });
      } else {
        Alert.alert('Error', error.message);
      }
    }
  }

  async function handleBiometric() {
    try {
      await loginWithBiometric();
    } catch {
      // Fallback to password — no error shown
    }
  }

  return (
    <View style={styles.container}>
      <Text style={styles.title}>MedConnect</Text>
      <Text style={styles.subtitle}>Iniciar Sesión</Text>

      <View style={styles.form}>
        <Text style={styles.label}>Email</Text>
        <Controller
          control={control}
          name="email"
          render={({ field: { onChange, onBlur, value } }) => (
            <TextInput
              style={[styles.input, errors.email && styles.inputError]}
              placeholder="tu@email.com"
              placeholderTextColor="#999"
              autoCapitalize="none"
              keyboardType="email-address"
              textContentType="emailAddress"
              onBlur={onBlur}
              onChangeText={onChange}
              value={value}
            />
          )}
        />
        {errors.email && <Text style={styles.errorText}>{errors.email.message}</Text>}

        <Text style={styles.label}>Contraseña</Text>
        <Controller
          control={control}
          name="password"
          render={({ field: { onChange, onBlur, value } }) => (
            <TextInput
              style={[styles.input, errors.password && styles.inputError]}
              placeholder="Tu contraseña"
              placeholderTextColor="#999"
              secureTextEntry
              textContentType="password"
              onBlur={onBlur}
              onChangeText={onChange}
              value={value}
            />
          )}
        />
        {errors.password && <Text style={styles.errorText}>{errors.password.message}</Text>}

        <Controller
          control={control}
          name="remember_me"
          render={({ field: { onChange, value } }) => (
            <TouchableOpacity
              style={styles.checkboxRow}
              onPress={() => onChange(!value)}
              activeOpacity={0.7}
            >
              <View style={[styles.checkbox, value && styles.checkboxChecked]}>
                {value && <Text style={styles.checkmark}>✓</Text>}
              </View>
              <Text style={styles.checkboxLabel}>Recordarme</Text>
            </TouchableOpacity>
          )}
        />

        <TouchableOpacity
          style={[styles.button, isSubmitting && styles.buttonDisabled]}
          onPress={handleSubmit(onSubmit)}
          disabled={isSubmitting}
          activeOpacity={0.8}
        >
          {isSubmitting ? (
            <ActivityIndicator color="#fff" />
          ) : (
            <Text style={styles.buttonText}>Iniciar Sesión</Text>
          )}
        </TouchableOpacity>

        {showBiometric && (
          <TouchableOpacity
            style={styles.biometricButton}
            onPress={handleBiometric}
            activeOpacity={0.7}
          >
            <Text style={styles.biometricIcon}>🔐</Text>
            <Text style={styles.biometricText}>Iniciar con huella</Text>
          </TouchableOpacity>
        )}

        <TouchableOpacity
          style={styles.linkRow}
          onPress={() => navigation?.navigate('Register')}
          activeOpacity={0.7}
        >
          <Text style={styles.linkText}>
            ¿No tenés cuenta? <Text style={styles.linkBold}>Registrate</Text>
          </Text>
        </TouchableOpacity>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f5f5f5',
    paddingHorizontal: 24,
    justifyContent: 'center',
  },
  title: {
    fontSize: 28,
    fontWeight: '700',
    color: '#1a73e8',
    textAlign: 'center',
    marginBottom: 4,
  },
  subtitle: {
    fontSize: 18,
    fontWeight: '600',
    color: '#333',
    textAlign: 'center',
    marginBottom: 32,
  },
  form: {
    backgroundColor: '#fff',
    borderRadius: 16,
    padding: 24,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 8,
    elevation: 4,
  },
  label: {
    fontSize: 14,
    fontWeight: '600',
    color: '#555',
    marginBottom: 6,
  },
  input: {
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 10,
    paddingHorizontal: 14,
    paddingVertical: 12,
    fontSize: 16,
    color: '#222',
    backgroundColor: '#fafafa',
    marginBottom: 4,
  },
  inputError: {
    borderColor: '#e53935',
    backgroundColor: '#fff8f8',
  },
  errorText: {
    fontSize: 12,
    color: '#e53935',
    marginBottom: 12,
    marginTop: 2,
  },
  checkboxRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginVertical: 12,
  },
  checkbox: {
    width: 22,
    height: 22,
    borderRadius: 5,
    borderWidth: 2,
    borderColor: '#1a73e8',
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 10,
  },
  checkboxChecked: {
    backgroundColor: '#1a73e8',
  },
  checkmark: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '700',
  },
  checkboxLabel: {
    fontSize: 15,
    color: '#555',
  },
  button: {
    backgroundColor: '#1a73e8',
    borderRadius: 10,
    paddingVertical: 14,
    alignItems: 'center',
    marginTop: 8,
  },
  buttonDisabled: {
    backgroundColor: '#a8c7fa',
  },
  buttonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '600',
  },
  biometricButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 16,
    paddingVertical: 12,
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 10,
    backgroundColor: '#fafafa',
  },
  biometricIcon: {
    fontSize: 20,
    marginRight: 8,
  },
  biometricText: {
    fontSize: 15,
    color: '#555',
    fontWeight: '500',
  },
  linkRow: {
    alignItems: 'center',
    marginTop: 20,
  },
  linkText: {
    fontSize: 14,
    color: '#777',
  },
  linkBold: {
    color: '#1a73e8',
    fontWeight: '600',
  },
});