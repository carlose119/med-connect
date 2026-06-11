export interface Specialty {
  id: number;
  name: string;
}

export interface Doctor {
  id: number;
  name: string;
  specialty: Specialty;
  bio?: string;
  license_number?: string;
}

export interface DoctorAvailability {
  start_time: string;
  end_time: string;
}