/// Minimal user reference — the trimmed `id,full_name` shape Laravel returns
/// for eager-loaded relations, e.g. `assignments.teamLeader:id,full_name`.
class UserRef {
  final int id;
  final String fullName;

  const UserRef({required this.id, required this.fullName});

  factory UserRef.fromJson(Map<String, dynamic> json) {
    return UserRef(
      id: (json['id'] as num?)?.toInt() ?? 0,
      fullName: json['full_name']?.toString() ?? '',
    );
  }
}
