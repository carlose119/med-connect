import React from 'react';
import { Text, View, StyleSheet } from 'react-native';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import AppointmentsScreen from '../screens/main/appointments/AppointmentsScreen';
import DoctorListScreen from '../screens/main/appointments/DoctorListScreen';
import DoctorDetailScreen from '../screens/main/appointments/DoctorDetailScreen';
import DoctorAvailabilityScreen from '../screens/main/appointments/DoctorAvailabilityScreen';
import BookAppointmentScreen from '../screens/main/appointments/BookAppointmentScreen';
import MedicalHistoryScreen from '../screens/main/history/MedicalHistoryScreen';
import MedicalNoteDetailScreen from '../screens/main/history/MedicalNoteDetailScreen';
import PrescriptionsScreen from '../screens/main/prescriptions/PrescriptionsScreen';
import PrescriptionDetailScreen from '../screens/main/prescriptions/PrescriptionDetailScreen';
import ProfileScreen from '../screens/main/profile/ProfileScreen';

// Param list types
export type CitasStackParamList = {
  Appointments: undefined;
  DoctorList: undefined;
  DoctorDetail: { doctorId: number };
  DoctorAvailability: { doctorId: number; doctorName: string };
  BookAppointment: { doctorId: number; startTime: string };
};

export type HistorialStackParamList = {
  MedicalHistory: undefined;
  MedicalNoteDetail: { noteId: number };
};

export type RecetasStackParamList = {
  Prescriptions: undefined;
  PrescriptionDetail: { prescriptionId: number };
};

export type ProfileStackParamList = {
  Profile: undefined;
};

const Tab = createBottomTabNavigator();
const CitasStackNav = createNativeStackNavigator<CitasStackParamList>();
const HistorialStackNav = createNativeStackNavigator<HistorialStackParamList>();
const RecetasStackNav = createNativeStackNavigator<RecetasStackParamList>();
const ProfileStackNav = createNativeStackNavigator<ProfileStackParamList>();

const tabScreenOptions = {
  tabBarActiveTintColor: '#1a73e8',
  tabBarInactiveTintColor: '#999',
  tabBarStyle: { borderTopWidth: 1, borderTopColor: '#eee' },
  headerShown: false,
};

// Simple icon component (emoji-based for v1)
function TabIcon({ emoji }: { emoji: string }) {
  return <Text style={{ fontSize: 22 }}>{emoji}</Text>;
}

function CitasStack() {
  return (
    <CitasStackNav.Navigator screenOptions={{ presentation: 'card' }}>
      <CitasStackNav.Screen
        name="Appointments"
        component={AppointmentsScreen}
        options={{ title: 'Mis Citas' }}
      />
      <CitasStackNav.Screen
        name="DoctorList"
        component={DoctorListScreen}
        options={{ title: 'Buscar doctor' }}
      />
      <CitasStackNav.Screen
        name="DoctorDetail"
        component={DoctorDetailScreen}
        options={{ title: 'Doctor' }}
      />
      <CitasStackNav.Screen
        name="DoctorAvailability"
        component={DoctorAvailabilityScreen}
        options={{ title: 'Disponibilidad' }}
      />
      <CitasStackNav.Screen
        name="BookAppointment"
        component={BookAppointmentScreen}
        options={{ title: 'Reservar' }}
      />
    </CitasStackNav.Navigator>
  );
}

function HistorialStack() {
  return (
    <HistorialStackNav.Navigator screenOptions={{ presentation: 'card' }}>
      <HistorialStackNav.Screen
        name="MedicalHistory"
        component={MedicalHistoryScreen}
        options={{ title: 'Historial Clínico' }}
      />
      <HistorialStackNav.Screen
        name="MedicalNoteDetail"
        component={MedicalNoteDetailScreen}
        options={{ title: 'Nota médica' }}
      />
    </HistorialStackNav.Navigator>
  );
}

function RecetasStack() {
  return (
    <RecetasStackNav.Navigator screenOptions={{ presentation: 'card' }}>
      <RecetasStackNav.Screen
        name="Prescriptions"
        component={PrescriptionsScreen}
        options={{ title: 'Recetas' }}
      />
      <RecetasStackNav.Screen
        name="PrescriptionDetail"
        component={PrescriptionDetailScreen}
        options={{ title: 'Receta' }}
      />
    </RecetasStackNav.Navigator>
  );
}

function ProfileStack() {
  return (
    <ProfileStackNav.Navigator screenOptions={{ presentation: 'card' }}>
      <ProfileStackNav.Screen
        name="Profile"
        component={ProfileScreen}
        options={{ title: 'Mi Perfil' }}
      />
    </ProfileStackNav.Navigator>
  );
}

export default function TabNavigator() {
  return (
    <Tab.Navigator screenOptions={tabScreenOptions}>
      <Tab.Screen
        name="CitasTab"
        component={CitasStack}
        options={{ title: 'Citas', tabBarIcon: () => <TabIcon emoji="📅" /> }}
      />
      <Tab.Screen
        name="HistorialTab"
        component={HistorialStack}
        options={{ title: 'Historial', tabBarIcon: () => <TabIcon emoji="📋" /> }}
      />
      <Tab.Screen
        name="RecetasTab"
        component={RecetasStack}
        options={{ title: 'Recetas', tabBarIcon: () => <TabIcon emoji="💊" /> }}
      />
      <Tab.Screen
        name="PerfilTab"
        component={ProfileStack}
        options={{ title: 'Perfil', tabBarIcon: () => <TabIcon emoji="👤" /> }}
      />
    </Tab.Navigator>
  );
}