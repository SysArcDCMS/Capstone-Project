// Coercion helpers for API payloads.
//
// A plain `json['x'] as num?` cast is not safe against this backend: PDO hands
// back Postgres `numeric`/`decimal` columns as JSON **strings**, so
// `tbl_incidents.latitude` arrives as `"14.5712000"` and the cast throws a
// TypeError. That failure propagates out of `Incident.fromJson`, which takes
// the whole list down with it — one row with coordinates was enough to make
// every complaint disappear.
//
// These helpers accept a number or a numeric string, so a value the driver
// happens to render as text is read as the number it represents instead of
// failing the parse.

double? jsonAsDouble(dynamic value) {
  if (value is num) return value.toDouble();
  if (value is String) return double.tryParse(value.trim());
  return null;
}

int? jsonAsInt(dynamic value) {
  if (value is num) return value.toInt();
  if (value is String) return int.tryParse(value.trim());
  return null;
}

/// Parses a Laravel relation array, tolerating a missing or null key.
List<T> jsonListOf<T>(dynamic raw, T Function(Map<String, dynamic>) parse) {
  if (raw is! List) return const [];
  return raw
      .whereType<Map<String, dynamic>>()
      .map(parse)
      .toList(growable: false);
}
