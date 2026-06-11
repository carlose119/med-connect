import React, { useEffect, useState, useCallback } from 'react';
import { View, Text, FlatList, StyleSheet, TouchableOpacity } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import type { RecetasStackParamList } from '../../../navigation/TabNavigator';
import { getPrescriptions } from '../../../services/prescriptionService';
import Card from '../../../components/Card';
import StatusBadge from '../../../components/StatusBadge';
import LoadingSpinner from '../../../components/LoadingSpinner';
import EmptyState from '../../../components/EmptyState';
import ErrorBanner from '../../../components/ErrorBanner';
import type { Prescription } from '../../../types/prescription';

type NavigationProp = NativeStackNavigationProp<RecetasStackParamList, 'Prescriptions'>;

function formatDate(iso: string): string {
  const d = new Date(iso);
  return d.toLocaleDateString('es-AR', { day: '2-digit', month: 'short', year: 'numeric' });
}

export default function PrescriptionsScreen() {
  const navigation = useNavigation<NavigationProp>();
  const [prescriptions, setPrescriptions] = useState<Prescription[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await getPrescriptions();
      setPrescriptions(data);
    } catch {
      setError('No se pudieron cargar las recetas.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  if (loading) return <LoadingSpinner />;
  if (error) return <ErrorBanner message={error} onRetry={load} />;

  return (
    <View style={styles.container}>
      <FlatList
        data={prescriptions}
        keyExtractor={(item) => String(item.id)}
        renderItem={({ item }) => (
          <TouchableOpacity
            style={styles.cardWrapper}
            onPress={() => navigation.push('PrescriptionDetail', { prescriptionId: item.id })}
            activeOpacity={0.7}
          >
            <Card>
              <View style={styles.row}>
                <View style={styles.rowLeft}>
                  <Text style={styles.code}>{item.unique_code}</Text>
                  <Text style={styles.doctor}>{item.doctor?.name ?? 'Doctor'}</Text>
                  <Text style={styles.date}>{formatDate(item.issued_at)}</Text>
                </View>
                <View style={styles.rowRight}>
                  <StatusBadge status={item.status} />
                  <Text style={styles.arrow}>›</Text>
                </View>
              </View>
            </Card>
          </TouchableOpacity>
        )}
        ListEmptyComponent={<EmptyState message="Aún no tenés recetas médicas." />}
        contentContainerStyle={styles.list}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f5f5f5' },
  list: { padding: 16 },
  cardWrapper: { marginBottom: 12 },
  row: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  rowLeft: { flex: 1 },
  rowRight: { flexDirection: 'row', alignItems: 'center', marginLeft: 8 },
  code: { fontSize: 14, fontWeight: '700', color: '#1a1a1a', marginBottom: 2 },
  doctor: { fontSize: 14, color: '#555', marginBottom: 2 },
  date: { fontSize: 12, color: '#999' },
  arrow: { fontSize: 22, color: '#ccc', marginLeft: 8 },
});