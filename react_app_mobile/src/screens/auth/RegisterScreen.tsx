import React from 'react';
import {
  View,
  Text,
  TextInput,
  TouchableOpacity,
  StyleSheet,
  ActivityIndicator,
  Alert,
  ScrollView,
  KeyboardAvoidingView,
  Platform,
} from 'react-native';
import { useForm, Controller } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import type { ApiError, RegisterForm } from '../../types/auth';
import { useAuth } from '../../contexts/AuthContext';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import type { AuthStackParamList } from '../../navigation/AuthNavigator';

const registerSchema = z
  .object({
    name: z.string().min(1, 'Este campo es obligatorio'),
    email: z.string().email('Ingresa un email válido'),
    password: z.string().min(8, 'La contraseña debe tener al menos 8 caracteres'),
    password_confirmation: z.string().min(1, 'Este campo es obligatorio'),
    identification_number: z.string().min(1, 'Este campo es obligatorio'),
    phone: z.string().min(1, 'Este campo es obligatorio'),
    birth_date: z.string().optional(),
    gender: z.enum(['M', 'F']).optional(),
  })
  .refine((data) => data.password === data.password_confirmation, {
    message: 'Las contraseñas no coinciden',
    path: ['password_confirmation'],
  });

type RegisterFormData = z.infer<typeof registerSchema>;

type NavigationProp = NativeStackNavigationProp<AuthStackParamList, 'Register'>;

interface Props {
  navigation?: NavigationProp;
}

export default function RegisterScreen({ navigation }: Props) {
  const { register: registerUser } = useAuth();

  const {
    control,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<RegisterFormData>({
    resolver: zodResolver(registerSchema),
    mode: 'onBlur',
  });

  async function onSubmit(data: RegisterFormData) {
    try {
      const payload: RegisterForm = {
        name: data.name,
        email: data.email,
        password: data.password,
        password_confirmation: data.password_confirmation,
        identification_number: data.identification_number,
        phone: data.phone,
        birth_date: data.birth_date,
        gender: data.gender,
      };
      await registerUser(payload);
    } catch (err) {
      const error = err as ApiError;
      if (error.code === 'VALIDATION_ERROR' && error.details) {
        Object.entries(error.details).forEach(([field, messages]) => {
          setError(field as keyof RegisterFormData, {
            message: messages[0] ?? 'Error de validación',
          });
        });
      } else if (error.code === 'SESSION_EXPIRED') {
        Alert.alert('Sesión expirada', 'Tu sesión expiró. Iniciá sesión de nuevo.');
      } else {
        Alert.alert('Error', error.message);
      }
    }
  }

  function Field({
    name,
    label,
    control,
    error,
    placeholder,
    keyboardType,
    secureTextEntry,
    autoCapitalize,
    multiline,
    value,
    onChange,
    onBlur,
  }: {
    name: keyof RegisterFormData;
    label: string;
    control: ReturnType<typeof useForm<RegisterFormData>>['control'];
    error?: string;
    placeholder?: string;
    keyboardType?: 'default' | 'email-address' | 'phone-pad' | 'numeric';
    secureTextEntry?: boolean;
    autoCapitalize?: 'none' | 'words' | 'sentences';
    multiline?: boolean;
    value?: string;
    onChange?: (...args: unknown[]) => void;
    onBlur?: (...args: unknown[]) => void;
  }) {
    return (
      <View style={styles.field}>
        <Text style={styles.label}>{label}</Text>
        <Controller
          control={control}
          name={name}
          render={({ field: { onChange: onCtrlChange, onBlur: onCtrlBlur, value: ctrlValue } }) => (
            <TextInput
              style={[
                styles.input,
                error && styles.inputError,
                multiline && styles.inputMultiline,
              ]}
              placeholder={placeholder}
              placeholderTextColor="#999"
              keyboardType={keyboardType ?? 'default'}
              secureTextEntry={secureTextEntry}
              autoCapitalize={autoCapitalize ?? 'sentences'}
              multiline={multiline}
              onBlur={onCtrlBlur}
              onChangeText={onCtrlChange}
              value={ctrlValue}
            />
          )}
        />
        {error && <Text style={styles.errorText}>{error}</Text>}
      </View>
    );
  }

  return (
    <KeyboardAvoidingView
      style={{ flex: 1 }}
      behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
    >
      <ScrollView
        contentContainerStyle={styles.scrollContent}
        keyboardShouldPersistTaps="handled"
      >
        <View style={styles.container}>
          <Text style={styles.title}>Crear Cuenta</Text>

          <View style={styles.form}>
            <Field
              name="name"
              label="Nombre completo *"
              control={control}
              error={errors.name?.message}
              placeholder="Juan Pérez"
              autoCapitalize="words"
            />

            <Field
              name="email"
              label="Email *"
              control={control}
              error={errors.email?.message}
              placeholder="juan@email.com"
              keyboardType="email-address"
              autoCapitalize="none"
            />

            <Field
              name="identification_number"
              label="Número de identificación *"
              control={control}
              error={errors.identification_number?.message}
              placeholder="12345678"
              keyboardType="numeric"
            />

            <Field
              name="phone"
              label="Teléfono *"
              control={control}
              error={errors.phone?.message}
              placeholder="+54 11 1234 5678"
              keyboardType="phone-pad"
            />

            <Field
              name="password"
              label="Contraseña *"
              control={control}
              error={errors.password?.message}
              placeholder="Mínimo 8 caracteres"
              secureTextEntry
              autoCapitalize="none"
            />

            <Field
              name="password_confirmation"
              label="Confirmar contraseña *"
              control={control}
              error={errors.password_confirmation?.message}
              placeholder="Repetí tu contraseña"
              secureTextEntry
              autoCapitalize="none"
            />

            <Field
              name="birth_date"
              label="Fecha de nacimiento (opcional)"
              control={control}
              error={errors.birth_date?.message}
              placeholder="YYYY-MM-DD"
            />

            {/* Gender select */}
            <View style={styles.field}>
              <Text style={styles.label}>Género (opcional)</Text>
              <Controller
                control={control}
                name="gender"
                render={({ field: { onChange, value } }) => (
                  <View style={styles.genderRow}>
                    {(['M', 'F'] as const).map((g) => (
                      <TouchableOpacity
                        key={g}
                        style={[styles.genderOption, value === g && styles.genderSelected]}
                        onPress={() => onChange(g)}
                        activeOpacity={0.7}
                      >
                        <Text
                          style={[
                            styles.genderText,
                            value === g && styles.genderTextSelected,
                          ]}
                        >
                          {g === 'M' ? 'Masculino' : 'Femenino'}
                        </Text>
                      </TouchableOpacity>
                    ))}
                  </View>
                )}
              />
              {errors.gender && <Text style={styles.errorText}>{errors.gender.message}</Text>}
            </View>

            <TouchableOpacity
              style={[styles.button, isSubmitting && styles.buttonDisabled]}
              onPress={handleSubmit(onSubmit)}
              disabled={isSubmitting}
              activeOpacity={0.8}
            >
              {isSubmitting ? (
                <ActivityIndicator color="#fff" />
              ) : (
                <Text style={styles.buttonText}>Registrarse</Text>
              )}
            </TouchableOpacity>

            <TouchableOpacity
              style={styles.linkRow}
              onPress={() => navigation?.goBack()}
              activeOpacity={0.7}
            >
              <Text style={styles.linkText}>¿Ya tenés cuenta? </Text>
              <Text style={styles.linkBold}>Iniciar Sesión</Text>
            </TouchableOpacity>
          </View>
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  scrollContent: {
    flexGrow: 1,
  },
  container: {
    flex: 1,
    backgroundColor: '#f5f5f5',
    paddingHorizontal: 24,
    paddingVertical: 32,
  },
  title: {
    fontSize: 26,
    fontWeight: '700',
    color: '#1a73e8',
    textAlign: 'center',
    marginBottom: 24,
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
  field: {
    marginBottom: 14,
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
  },
  inputError: {
    borderColor: '#e53935',
    backgroundColor: '#fff8f8',
  },
  inputMultiline: {
    minHeight: 80,
    textAlignVertical: 'top',
    paddingTop: 12,
  },
  errorText: {
    fontSize: 12,
    color: '#e53935',
    marginTop: 4,
  },
  genderRow: {
    flexDirection: 'row',
    gap: 12,
  },
  genderOption: {
    flex: 1,
    paddingVertical: 12,
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 10,
    alignItems: 'center',
    backgroundColor: '#fafafa',
  },
  genderSelected: {
    backgroundColor: '#1a73e8',
    borderColor: '#1a73e8',
  },
  genderText: {
    fontSize: 15,
    color: '#555',
    fontWeight: '500',
  },
  genderTextSelected: {
    color: '#fff',
  },
  button: {
    backgroundColor: '#1a73e8',
    borderRadius: 10,
    paddingVertical: 14,
    alignItems: 'center',
    marginTop: 16,
  },
  buttonDisabled: {
    backgroundColor: '#a8c7fa',
  },
  buttonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '600',
  },
  linkRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 20,
    marginBottom: 8,
  },
  linkText: {
    fontSize: 14,
    color: '#777',
  },
  linkBold: {
    fontSize: 14,
    color: '#1a73e8',
    fontWeight: '600',
  },
});