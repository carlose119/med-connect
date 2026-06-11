export type PrescriptionStatus = 'active' | 'used' | 'expired' | 'cancelled';

export interface PrescriptionItem {
  id: number;
  medicine: string;
  dosage: string;
  frequency: string;
  duration?: string;
  instructions?: string;
}

export interface Prescription {
  id: number;
  appointment_id?: number;
  doctor_id: number;
  patient_id: number;
  unique_code: string;
  issued_at: string;
  status: PrescriptionStatus;
  cancellation_reason?: string;
  doctor: { id: number; name: string };
  items: PrescriptionItem[];
}