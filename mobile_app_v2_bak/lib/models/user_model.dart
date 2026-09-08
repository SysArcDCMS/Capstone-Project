/// User — matches the `/api/auth/*` and `/api/users` response fields.
class User {
  final int id;
  final String fullName;
  final String email;
  final String? contactNo;
  final String? address;
  final String role;
  final bool isActive;
  final bool isTeamLeader;
  final String? departmentTeam;

  const User({
    required this.id,
    required this.fullName,
    required this.email,
    this.contactNo,
    this.address,
    required this.role,
    this.isActive = true,
    this.isTeamLeader = false,
    this.departmentTeam,
  });

  bool get isCustomer => role == 'customer';
  bool get isOffsiteStaff => role == 'offsite_staff';
  bool get isEngineer => role == 'engineer';
  bool get isAdmin => role == 'administrator';

  factory User.fromJson(Map<String, dynamic> json) {
    return User(
      id: (json['id'] as num?)?.toInt() ?? 0,
      fullName: json['full_name']?.toString() ?? '',
      email: json['email']?.toString() ?? '',
      contactNo: json['contact_no']?.toString(),
      address: json['address']?.toString(),
      role: (json['role']?.toString() ?? 'customer').toLowerCase(),
      isActive: json['is_active'] == true || json['is_active'] == 1,
      isTeamLeader:
          json['is_team_leader'] == true || json['is_team_leader'] == 1,
      departmentTeam: json['department_team']?.toString(),
    );
  }
}