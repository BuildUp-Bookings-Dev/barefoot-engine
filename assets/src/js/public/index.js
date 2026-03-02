import '@braudypedrosa/bp-listings';
import { BPCalendar, BP_Calendar } from '@braudypedrosa/bp-calendar';

if (typeof window !== 'undefined') {
  if (!window.BPCalendar) {
    window.BPCalendar = BPCalendar;
  }

  if (!window.BP_Calendar) {
    window.BP_Calendar = BP_Calendar;
  }
}
