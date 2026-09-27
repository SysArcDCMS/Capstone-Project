// This is a command-line entrypoint: stdout is its interface, not a stray
// leftover, so the avoid_print lint does not apply here.
// ignore_for_file: avoid_print

import 'dart:io';

/// Launches the app with the backend address already filled in.
///
/// A physical device cannot reach the developer machine through localhost or
/// through the emulator's 10.0.2.2 alias, so the LAN address of whichever
/// adapter carries normal traffic is injected as `API_URL`.
///
/// Override the detected address by exporting API_URL, e.g. when a VPN makes
/// the automatic choice ambiguous. Any extra arguments are forwarded to
/// `flutter run`.
Future<void> main(List<String> args) async {
  // Run from the Flutter project root, not from whatever directory this script
  // happened to be invoked in. Without this, `flutter run` starts outside the
  // project and fails with "No pubspec.yaml file found" — which is why
  // `dart run imraws-mobile/tool/run.dart` from the repo root did not work.
  final projectRoot = File(Platform.script.toFilePath()).parent.parent.path;

  final override = Platform.environment['API_URL'];
  final apiUrl = (override != null && override.isNotEmpty)
      ? override
      : 'http://${await detectLanIp()}:8000/api';

  print('🚀 API base URL: $apiUrl');
  if (override != null && override.isNotEmpty) {
    print('   (from the API_URL environment variable)');
  }

  final executable = Platform.isWindows ? 'flutter.bat' : 'flutter';

  final process = await Process.start(
    executable,
    ['run', '--dart-define=API_URL=$apiUrl', ...args],
    workingDirectory: projectRoot,
    mode: ProcessStartMode.inheritStdio,
  );

  exit(await process.exitCode);
}

/// Best guess at the IPv4 address a phone on the same network could reach.
///
/// Loopback is useless here, and so are the 169.254.x.x self-assigned
/// addresses Windows hands to disconnected adapters: they sort first often
/// enough to be picked, and no other machine can route to them. Anything
/// outside RFC 1918 is deprioritised for the same reason.
Future<String> detectLanIp() async {
  final candidates = <String>[];

  for (final interface in await NetworkInterface.list()) {
    for (final address in interface.addresses) {
      if (address.type != InternetAddressType.IPv4) continue;
      if (address.isLoopback) continue;
      if (_isLinkLocal(address.address)) continue;

      candidates.add(address.address);
    }
  }

  if (candidates.isEmpty) {
    print('⚠️  No usable IPv4 address found. Pair with: adb reverse tcp:8000 tcp:8000');
    return '127.0.0.1';
  }

  // Prefer a private range, which is what a home or campus Wi-Fi hands out.
  final preferred =
      candidates.where((ip) => _isPrivate(ip)).toList(growable: false);
  final chosen = preferred.isNotEmpty ? preferred : candidates;

  if (candidates.length > 1) {
    print('ℹ️  IPv4 candidates: ${candidates.join(', ')} → using ${chosen.first}');
  }

  return chosen.first;
}

/// 169.254.0.0/16 — "this host has no address yet" range, never routable
/// from another device.
bool _isLinkLocal(String ip) => ip.startsWith('169.254.');

/// RFC 1918: 10/8, 172.16/12, 192.168/16.
bool _isPrivate(String ip) {
  final parts = ip.split('.');
  if (parts.length != 4) return false;

  final a = int.tryParse(parts[0]);
  final b = int.tryParse(parts[1]);
  if (a == null || b == null) return false;

  if (a == 10) return true;
  if (a == 192 && b == 168) return true;
  if (a == 172 && b >= 16 && b <= 31) return true;

  return false;
}
