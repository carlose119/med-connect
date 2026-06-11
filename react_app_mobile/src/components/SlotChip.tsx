import React from 'react';
import { TouchableOpacity, Text, StyleSheet } from 'react-native';

interface SlotChipProps {
  time: string; // ISO8601 datetime string
  selected: boolean;
  onPress: () => void;
}

/** Extract HH:MM display from ISO8601 datetime. */
function formatTime(isoString: string): string {
  // Handles both "2024-01-01T10:00:00Z" and "10:00" formats
  const parts = isoString.split('T');
  const timePart = parts.length > 1 ? parts[1] : isoString;
  return timePart.substring(0, 5);
}

export default function SlotChip({ time, selected, onPress }: SlotChipProps) {
  return (
    <TouchableOpacity
      style={[styles.chip, selected && styles.chipSelected]}
      onPress={onPress}
      activeOpacity={0.7}
    >
      <Text style={[styles.text, selected && styles.textSelected]}>
        {formatTime(time)}
      </Text>
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  chip: {
    borderRadius: 20,
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderWidth: 1.5,
    borderColor: '#ddd',
    backgroundColor: '#fff',
  },
  chipSelected: {
    backgroundColor: '#1e40af',
    borderColor: '#1e40af',
  },
  text: {
    fontSize: 14,
    color: '#333',
    fontWeight: '500',
  },
  textSelected: {
    color: '#fff',
  },
});