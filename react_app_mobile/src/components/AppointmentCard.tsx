import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import type { Appointment } from '../types/appointment';
import Card from './Card';
import StatusBadge from './StatusBadge';
import Button from './Button';

interface AppointmentCardProps {
  appointment: Appointment;
  onCancel?: (id: number) => void;
}

function formatDateTime(isoString: string): string {
  const date = new Date(isoString);
  return date.toLocaleDateString('es-AR', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function canCancel(isoString: string): boolean {
  const appointmentTime = new Date(isoString).getTime();
  const now = Date.now();
  const hoursUntil = (appointmentTime - now) / (1000 * 60 * 60);
  return hoursUntil > 24;
}

export default function AppointmentCard({ appointment, onCancel }: AppointmentCardProps) {
  const isCancellable = canCancel(appointment.start_time);

  return (
    <Card style={styles.card}>
      <View style={styles.row}>
        <Text style={styles.doctorName}>{appointment.doctor.name}</Text>
        <StatusBadge status={appointment.state} />
      </View>
      <Text style={styles.dateTime}>{formatDateTime(appointment.start_time)}</Text>
      {appointment.notes && (
        <Text style={styles.notes} numberOfLines={2}>
          {appointment.notes}
        </Text>
      )}
      {!isCancellable && onCancel && (
        <Text style={styles.warning}>No se puede cancelar dentro de las 24 horas</Text>
      )}
      {isCancellable && onCancel && (
        <Button
          title="Cancelar"
          variant="danger"
          onPress={() => onCancel(appointment.id)}
          style={styles.cancelButton}
        />
      )}
    </Card>
  );
}

const styles = StyleSheet.create({
  card: {
    marginBottom: 12,
  },
  row: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 4,
  },
  doctorName: {
    fontSize: 16,
    fontWeight: '700',
    color: '#222',
    flex: 1,
  },
  dateTime: {
    fontSize: 14,
    color: '#555',
    marginBottom: 4,
  },
  notes: {
    fontSize: 14,
    color: '#666',
    marginTop: 4,
  },
  warning: {
    fontSize: 12,
    color: '#b45309',
    marginTop: 8,
    fontStyle: 'italic',
  },
  cancelButton: {
    marginTop: 12,
  },
});