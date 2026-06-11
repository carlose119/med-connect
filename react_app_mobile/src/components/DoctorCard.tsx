import React from 'react';
import { View, Text, TouchableOpacity, StyleSheet } from 'react-native';
import type { Doctor } from '../types/doctor';
import Card from './Card';

interface DoctorCardProps {
  doctor: Doctor;
  onPress: (doctor: Doctor) => void;
}

function getInitials(name: string): string {
  return name.trim().charAt(0).toUpperCase();
}

export default function DoctorCard({ doctor, onPress }: DoctorCardProps) {
  return (
    <TouchableOpacity onPress={() => onPress(doctor)} activeOpacity={0.8}>
      <Card style={styles.card}>
        <View style={styles.row}>
          <View style={styles.avatar}>
            <Text style={styles.avatarText}>{getInitials(doctor.name)}</Text>
          </View>
          <View style={styles.info}>
            <Text style={styles.name}>{doctor.name}</Text>
            <Text style={styles.specialty}>{doctor.specialty.name}</Text>
          </View>
        </View>
        {doctor.bio && (
          <Text style={styles.bio} numberOfLines={2}>
            {doctor.bio}
          </Text>
        )}
      </Card>
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  card: {
    marginBottom: 12,
  },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 8,
  },
  avatar: {
    width: 48,
    height: 48,
    borderRadius: 999,
    backgroundColor: '#e3f2fd',
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 12,
  },
  avatarText: {
    fontSize: 18,
    fontWeight: '700',
    color: '#1e40af',
  },
  info: {
    flex: 1,
  },
  name: {
    fontSize: 16,
    fontWeight: '700',
    color: '#222',
  },
  specialty: {
    fontSize: 14,
    color: '#555',
    marginTop: 2,
  },
  bio: {
    fontSize: 14,
    color: '#666',
    lineHeight: 20,
  },
});