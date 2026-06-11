import React, { useState, useCallback } from 'react';
import { View, Text, StyleSheet, Alert } from 'react-native';
import { useRoute, useNavigation, CommonActions } from '@react-navigation/native';
import type { RouteProp } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import type { CitasStackParamList } from '../../../navigation/TabNavigator';
import { bookAppointment } from '../../../services/appointmentService';
import Card from '../../../components/Card';
import Button from '../../../components/Button';
import type { ApiError } from '../../../types/auth';

type RouteProps = RouteProp<CitasStackParamList, 'BookAppointment'>;
type NavigationProp = NativeStackNavigationProp<CitasStackParamList, 'BookAppointment'>;

function formatDateTime(iso: string): { date: string; time: string } {
  const d = new Date(iso);
  return {
    date: d.toLocaleDateString('es-AR', { day: '2-digit', month: 'long', year: 'numeric' }),
    time: `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`,
  };
}

export default function BookAppointmentScreen() {
  const route = useRoute<RouteProps>();
  const navigation = useNavigation<NavigationProp>();
  const { doctorId, startTime } = route.params;

  const [loading, setLoading] = useState(false);
  const { date, time } = formatDateTime(startTime);

  const handleConfirm = useCallback(async () => {
    setLoading(true);
    try {
      await bookAppointment({ doctor_id: doctorId, start_time: startTime });
      Alert.alert('Cita reservada', 'Tu cita fue reservada exitosamente.', [
        {
          text: 'OK',
          onPress: () => {
            navigation.dispatch(
              CommonActions.reset({
                index: 0,
                routes: [{ name: 'Appointments' }],
              }),
            );
          },
        },
      ]);
    } catch (err) {
      const error = err as ApiError;
      const msg = error.message ?? '';
      if (typeof msg === 'string' && msg.includes('409')) {
        Alert.alert('Horario ocupado', 'Este horario ya no está disponible. Buscá otro horario.');
        navigation.goBack();
      } else {
        Alert.alert('Error', msg || 'No se pudo reservar la cita.');
      }
    } finally {
      setLoading(false);
    }
  }, [doctorId, startTime, navigation]);

  return (
    <View style={styles.container}>
      <Card>
        <Text style={styles.title}>Confirmar cita</Text>
        <View style={styles.row}>
          <Text style={styles.label}>Fecha:</Text>
          <Text style={styles.value}>{date}</Text>
        </View>
        <View style={styles.row}>
          <Text style={styles.label}>Hora:</Text>
          <Text style={styles.value}>{time}</Text>
        </View>
        <Text style={styles.note}>
          Una vez confirmada, podés cancelar hasta 24h antes del turno.
        </Text>
      </Card>

      <View style={styles.buttonWrapper}>
        <Button
          title={loading ? 'Reservando...' : 'Confirmar cita'}
          onPress={handleConfirm}
          loading={loading}
          disabled={loading}
          variant="primary"
        />
        <View style={{ height: 12 }} />
        <Button
          title="Cancelar"
          onPress={() => navigation.goBack()}
          variant="secondary"
          disabled={loading}
        />
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f5f5f5', padding: 16 },
  title: { fontSize: 18, fontWeight: '700', color: '#1a1a1a', marginBottom: 16, textAlign: 'center' },
  row: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: 12 },
  label: { fontSize: 15, color: '#666' },
  value: { fontSize: 15, fontWeight: '600', color: '#1a1a1a' },
  note: { fontSize: 13, color: '#999', marginTop: 8, textAlign: 'center' },
  buttonWrapper: { marginTop: 24 },
});