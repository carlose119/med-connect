export type AppointmentState = 'pending' | 'confirmed' | 'completed' | 'cancelled' | 'no_show';

export interface Appointment {
  id: number;
  doctor_id: number;
  patient_id: number;
  state: AppointmentState;
  start_time: string;
  end_time: string;
  notes?: string;
  cancellation_reason?: string;
  doctor: { id: number; name: string };
  created_at: string;
}

export interface AppointmentCreate {
  doctor_id: number;
  start_time: string;
}