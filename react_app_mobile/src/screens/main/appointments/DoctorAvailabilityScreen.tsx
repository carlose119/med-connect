import React, { useState, useCallback } from 'react';
import { View, Text, SectionList, StyleSheet, Platform } from 'react-native';
import DateTimePicker, { DateTimePickerEvent } from '@react-native-community/datetimepicker';
import { useRoute, useNavigation } from '@react-navigation/native';
import type { RouteProp } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import type { CitasStackParamList } from '../../../navigation/TabNavigator';
import { getAvailability } from '../../../services/doctorService';
import SlotChip from '../../../components/SlotChip';
import LoadingSpinner from '../../../components/LoadingSpinner';
import EmptyState from '../../../components/EmptyState';
import ErrorBanner from '../../../components/ErrorBanner';
import type { DoctorAvailability } from '../../../types/doctor';

type RouteProps = RouteProp<CitasStackParamList, 'DoctorAvailability'>;
type NavigationProp = NativeStackNavigationProp<CitasStackParamList, 'DoctorAvailability'>;

interface SlotSection {
  title: string;
  data: DoctorAvailability[];
}

function formatTime(iso: string): string {
  const d = new Date(iso);
  return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
}

function formatDate(iso: string): string {
  const d = new Date(iso);
  return d.toLocaleDateString('es-AR', { day: '2-digit', month: 'long', year: 'numeric' });
}

export default function DoctorAvailabilityScreen() {
  const route = useRoute<RouteProps>();
  const navigation = useNavigation<NavigationProp>();
  const { doctorId, doctorName } = route.params;

  const [date, setDate] = useState(new Date());
  const [showPicker, setShowPicker] = useState(false);
  const [slots, setSlots] = useState<DoctorAvailability[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const loadSlots = useCallback(async (selectedDate: Date) => {
    setLoading(true);
    setError(null);
    try {
      const dateStr = selectedDate.toISOString().split('T')[0];
      const data = await getAvailability(doctorId, dateStr);
      setSlots(data);
    } catch {
      setError('No se pudieron cargar los horarios disponibles.');
    } finally {
      setLoading(false);
    }
  }, [doctorId]);

  // Load initial slots
  React.useEffect(() => { loadSlots(date); }, [loadSlots, date]);

  const handleDateChange = (event: DateTimePickerEvent, selectedDate?: Date) => {
    setShowPicker(Platform.OS === 'ios');
    if (selectedDate) {
      setDate(selectedDate);
      loadSlots(selectedDate);
    }
  };

  const handleSlotPress = (slot: DoctorAvailability) => {
    navigation.push('BookAppointment', { doctorId, startTime: slot.start_time });
  };

  // Group slots by date (usually one date, but support multi-day)
  const sections: SlotSection[] = [{ title: formatDate(date.toISOString()), data: slots }];

  return (
    <View style={styles.container}>
      <View style={styles.dateRow}>
        <Text style={styles.dateLabel}>Fecha:</Text>
        <DateTimePicker
          value={date}
          mode="date"
          display={Platform.OS === 'ios' ? 'spinner' : 'default'}
          onChange={handleDateChange}
          minimumDate={new Date()}
        />
      </View>

      {loading ? (
        <LoadingSpinner />
      ) : error ? (
        <ErrorBanner message={error} onRetry={() => loadSlots(date)} />
      ) : (
        <SectionList
          sections={sections}
          keyExtractor={(item, index) => item.start_time + index}
          renderItem={({ item }) => (
            <View style={styles.chipWrapper}>
              <SlotChip
                time={item.start_time}
                selected={false}
                onPress={() => handleSlotPress(item)}
              />
            </View>
          )}
          renderSectionHeader={() => null}
          ListEmptyComponent={<EmptyState message="No hay horarios disponibles para esta fecha." />}
          contentContainerStyle={styles.list}
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f5f5f5' },
  dateRow: { flexDirection: 'row', alignItems: 'center', backgroundColor: '#fff', padding: 16, borderBottomWidth: 1, borderBottomColor: '#eee' },
  dateLabel: { fontSize: 15, fontWeight: '600', color: '#333', marginRight: 8 },
  list: { padding: 16 },
  chipWrapper: { flexDirection: 'row', marginBottom: 8 },
});