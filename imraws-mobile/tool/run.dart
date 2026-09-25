import 'dart:io';

Future<void> main(List<String> args) async {
  String? localIp;

  // 1. Scan network interfaces to find your computer's current local IPv4
  for (var interface in await NetworkInterface.list()) {
    for (var addr in interface.addresses) {
      if (addr.type == InternetAddressType.IPv4 && !addr.isLoopback) {
        localIp = addr.address;
        break;
      }
    }
    if (localIp != null) break;
  }

  // Fallback if no network is found
  localIp ??= '127.0.0.1';
  print('🚀 Auto-detected local IP: $localIp');

  final apiUrl = 'http://$localIp:8000/api';

  // 2. Use flutter.bat on Windows, flutter on macOS/Linux
  final executable = Platform.isWindows ? 'flutter.bat' : 'flutter';

  final process = await Process.start(
    executable,
    ['run', '--dart-define=API_URL=$apiUrl', ...args],
    mode: ProcessStartMode.inheritStdio,
  );

  exit(await process.exitCode);
}