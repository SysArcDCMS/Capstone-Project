import 'api_config.dart';
import 'api_service.dart';

/// Availability statuses supported by the backend (see API spec §2.3).
class AvailabilityStatus {
  static const available = 'available';
  static const onDuty = 'on_duty';
  static const unavailable = 'unavailable';
  static const onBreak = 'on_break';

  static const all = [available, onDuty, unavailable, onBreak];

  static String label(String status) {
    switch (status) {
      case onDuty:
        return 'On Duty';
      case unavailable:
        return 'Unavailable';
      case onBreak:
        return 'On Break';
      default:
        return 'Available';
    }
  }
}

class AvailabilityService {
  final ApiService _api = ApiService.instance;

  /// GET /api/availability/me — returns the current status string.
  Future<String> getMyAvailability() async {
    final data = await _api.get(ApiConfig.availabilityMe);
    final availability =
        data is Map<String, dynamic> ? data['data'] : null;
    if (availability is Map<String, dynamic>) {
      return availability['status']?.toString() ?? AvailabilityStatus.unavailable;
    }
    return AvailabilityStatus.unavailable;
  }

  /// POST /api/availability — toggles own status.
  Future<void> updateAvailability(String status) async {
    await _api.post(ApiConfig.availability, data: {'status': status});
  }
}