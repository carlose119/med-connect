import React from 'react';
import {
  TouchableOpacity,
  Text,
  ActivityIndicator,
  StyleSheet,
  ViewStyle,
} from 'react-native';

type ButtonVariant = 'primary' | 'secondary' | 'danger';

interface ButtonProps {
  title: string;
  onPress: () => void;
  loading?: boolean;
  variant?: ButtonVariant;
  disabled?: boolean;
  style?: ViewStyle;
}

const VARIANT_STYLES: Record<ButtonVariant, { bg: string; color: string; border?: string }> = {
  primary: { bg: '#1a73e8', color: '#fff' },
  secondary: { bg: '#fff', color: '#1a73e8', border: '#1a73e8' },
  danger: { bg: '#e53935', color: '#fff' },
};

export default function Button({
  title,
  onPress,
  loading = false,
  variant = 'primary',
  disabled = false,
  style,
}: ButtonProps) {
  const variantStyle = VARIANT_STYLES[variant];

  return (
    <TouchableOpacity
      style={[
        styles.button,
        { backgroundColor: variantStyle.bg },
        variantStyle.border && { borderWidth: 1, borderColor: variantStyle.border },
        disabled && styles.disabled,
        style,
      ]}
      onPress={onPress}
      disabled={disabled || loading}
      activeOpacity={0.8}
    >
      {loading ? (
        <ActivityIndicator color={variantStyle.color} />
      ) : (
        <Text style={[styles.text, { color: variantStyle.color }]}>{title}</Text>
      )}
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  button: {
    borderRadius: 10,
    paddingVertical: 14,
    alignItems: 'center',
    justifyContent: 'center',
    minHeight: 48,
  },
  text: {
    fontSize: 16,
    fontWeight: '600',
  },
  disabled: {
    opacity: 0.5,
  },
});