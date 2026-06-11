import React from 'react';
import { View, Text, StyleSheet } from 'react-native';

type AppointmentStatus = 'pending' | 'confirmed' | 'completed' | 'cancelled' | 'no_show';
type PrescriptionStatus = 'active' | 'used' | 'expired' | 'cancelled';

type Status = AppointmentStatus | PrescriptionStatus;

interface StatusBadgeProps {
  status: string;
}

const APPOINTMENT_STYLES: Record<AppointmentStatus, { bg: string; color: string; border: string }> = {
  pending: { bg: '#fffbeb', color: '#b45309', border: '#f59e0b' },
  confirmed: { bg: '#f0fdf4', color: '#15803d', border: '#86efac' },
  completed: { bg: '#f0f9ff', color: '#1e40af', border: '#93c5fd' },
  cancelled: { bg: '#fef2f2', color: '#991b1b', border: '#fecaca' },
  no_show: { bg: '#f5f5f5', color: '#666', border: '#ccc' },
};

const PRESCRIPTION_STYLES: Record<PrescriptionStatus, { bg: string; color: string; border: string }> = {
  active: { bg: '#f0fdf4', color: '#15803d', border: '#86efac' },
  used: { bg: '#f5f5f5', color: '#666', border: '#ccc' },
  expired: { bg: '#fef2f2', color: '#991b1b', border: '#fecaca' },
  cancelled: { bg: '#fef2f2', color: '#991b1b', border: '#fecaca' },
};

const LABELS: Record<string, string> = {
  pending: 'Pendiente',
  confirmed: 'Confirmado',
  completed: 'Completado',
  cancelled: 'Cancelado',
  no_show: 'No asistió',
  active: 'Activa',
  used: 'Usada',
  expired: 'Vencida',
};

function getStyle(status: string): { bg: string; color: string; border: string } {
  if (status in APPOINTMENT_STYLES) {
    return APPOINTMENT_STYLES[status as AppointmentStatus];
  }
  if (status in PRESCRIPTION_STYLES) {
    return PRESCRIPTION_STYLES[status as PrescriptionStatus];
  }
  // Fallback
  return { bg: '#f5f5f5', color: '#666', border: '#ccc' };
}

export default function StatusBadge({ status }: StatusBadgeProps) {
  const style = getStyle(status);
  const label = LABELS[status] ?? status;

  return (
    <View style={[styles.badge, { backgroundColor: style.bg, borderColor: style.border }]}>
      <Text style={[styles.text, { color: style.color }]}>{label}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  badge: {
    borderRadius: 12,
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderWidth: 1,
  },
  text: {
    fontSize: 12,
    fontWeight: '600',
  },
});