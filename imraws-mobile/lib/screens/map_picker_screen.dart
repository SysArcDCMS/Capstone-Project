import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:flutter_map_vector_tiles/flutter_map_vector_tiles.dart' as vt;
import 'package:geocoding/geocoding.dart';
import 'package:geolocator/geolocator.dart';
// Only LatLng is wanted here. latlong2 also exports a generic `Path`, which
// would shadow the dart:ui Path that the pin painter below draws with.
import 'package:latlong2/latlong.dart' show LatLng;

import '../theme/app_colors.dart';

/// What the picker hands back: the pin, plus the address it resolved to.
///
/// The address is nullable because it depends on the device having a working
/// geocoder. On a phone without one, [address] stays null and the caller
/// keeps whatever the user typed instead of a silently empty string.
class PickedLocation {
  const PickedLocation({required this.point, this.address});

  final LatLng point;
  final String? address;

  @override
  bool operator ==(Object other) =>
      other is PickedLocation &&
      other.point == point &&
      other.address == address;

  @override
  int get hashCode => Object.hash(point, address);
}

/// Full-screen pin picker used by the complaint form.
///
/// Draws OpenStreetMap vector tiles published by OpenFreeMap. That is the
/// whole reason this screen is not a GoogleMap: OpenFreeMap needs no API key,
/// no billing account and no Google Play Services, and the app still needs
/// location picking on a device that may not have Play Services at all.
///
/// The user:
///   - drags the map to move the centre pin,
///   - taps "Use My Location" (device GPS, geolocator),
///   - searches an address/barangay to jump the pin (geocoding).
///
/// The caption under the pin shows the address the coordinates resolve to
/// (reverse geocoding), falling back to the raw coordinates on devices with
/// no working geocoder.
///
/// Returns a [PickedLocation] - the pin plus its resolved address - via
/// `Navigator.pop` when confirmed.
class MapPickerScreen extends StatefulWidget {
  const MapPickerScreen({super.key, this.initial});

  /// Pre-fill with the currently selected pin (e.g. when editing).
  final LatLng? initial;

  @override
  State<MapPickerScreen> createState() => _MapPickerScreenState();
}

class _MapPickerScreenState extends State<MapPickerScreen> {
  static const LatLng _defaultCenter = LatLng(14.5995, 120.9842); // Manila
  static const double _defaultZoom = 13;

  /// The OpenFreeMap "planet" TileJSON advertises `maxzoom: 14`, so anything
  /// past that is an upscale of a coarser tile: labels go soft and building
  /// outlines blur. Cap the camera at the tiles' native resolution rather than
  /// letting a gesture or a GPS jump request tiles that do not exist.
  static const double _detailZoom = 14;
  static const double _maxZoom = 14;

  /// Panning fires onPositionChanged continuously, so a lookup per event would
  /// flood the platform channel. Only the resting position matters.
  static const Duration _lookupDebounce = Duration(milliseconds: 500);

  /// Upper bound on the last-chance lookup in _confirm(), so a slow geocoder
  /// can never hold the confirm button hostage.
  static const Duration _confirmLookupTimeout = Duration(seconds: 2);

  /// OpenFreeMap's "Liberty" MapLibre style. Public, keyless, no signup.
  static const String _styleUri =
      'https://tiles.openfreemap.org/styles/liberty';

  final MapController _map = MapController();

  /// Whether the style has finished loading, so the app bar can gate the
  /// confirm button.
  final ValueNotifier<bool> _mapReady = ValueNotifier<bool>(false);

  late Future<vt.Style> _styleFuture;
  vt.Style? _style;

  LatLng _center = _defaultCenter;
  bool _searching = false;
  String? _searchError;

  /// Human-readable address of [_center], or null while it is unresolved or
  /// the device has no usable geocoder.
  String? _address;
  bool _resolvingAddress = false;

  /// Set once the platform reports no geocoder, so the UI stops implying an
  /// address is coming and falls back to coordinates.
  bool _geocoderUnavailable = false;

  /// Cached isPresent() result. It is a platform-channel round trip and the
  /// answer cannot change while this screen is open.
  Future<bool>? _geocoderPresent;

  /// Panning fires onPositionChanged continuously, so a lookup per event would
  /// flood the platform channel. Only the resting position matters.
  Timer? _lookupDebounceTimer;

  /// Monotonic token. Each lookup captures the current value and a response is
  /// only applied if the token still matches, so a slow reply for an old pin
  /// cannot overwrite the address of the pin the user has since dragged to.
  int _lookupToken = 0;

  final _searchController = TextEditingController();

  @override
  void initState() {
    super.initState();
    if (widget.initial != null) {
      _center = widget.initial!;
      // Label the pre-filled pin straight away. Deferred to a microtask so the
      // first frame builds with a placeholder instead of blocking on a
      // platform-channel round trip.
      scheduleMicrotask(_resolveAddress);
    }
    _styleFuture = _loadStyle();
  }

  Future<vt.Style> _loadStyle() async {
    final style = await const vt.StyleReader(uri: _styleUri).read();
    _style = style;
    if (!mounted) {
      // Left before the style landed: release it here, since dispose() has
      // already run and cannot see it.
      style.dispose();
      return style;
    }
    _mapReady.value = true;
    return style;
  }

  @override
  void dispose() {
    _mapReady.dispose();
    // Releases the tile providers and sprite images the style opened.
    _style?.dispose();
    _searchController.dispose();
    // Bumping the token orphans any lookup still in flight, so a late reply
    // cannot call setState after this State is gone.
    _lookupToken++;
    _lookupDebounceTimer?.cancel();
    super.dispose();
  }

  Future<bool> _geocoderIsPresent() =>
      _geocoderPresent ??= Geocoding().isPresent();

  /// Composes a readable one-line address from a placemark.
  ///
  /// The platform geocoder hands back a dozen nullable components, and how
  /// populated each one is depends on the device's geocoding data. Rural
  /// coordinates routinely resolve to nothing more specific than a
  /// municipality, so the most specific populated components are preferred and
  /// the rest are appended. Duplicates are dropped because locality and
  /// subAdministrativeArea frequently hold the same value.
  static String? _composeAddress(Placemark p) {
    final parts = <String>[];

    void add(String? value) {
      final v = value?.trim();
      if (v == null || v.isEmpty) return;
      if (parts.any((existing) => existing.toLowerCase() == v.toLowerCase())) {
        return;
      }
      parts.add(v);
    }

    // Street first, then increasingly broader geography.
    final street = p.subThoroughfare?.trim();
    final thoroughfare = p.thoroughfare?.trim();
    if ((thoroughfare?.isNotEmpty ?? false) && (street?.isNotEmpty ?? false)) {
      add('$street, $thoroughfare');
    } else {
      add(thoroughfare);
    }
    add(p.subLocality);
    add(p.locality);
    add(p.subAdministrativeArea);
    add(p.administrativeArea);
    add(p.country);

    // Three parts is the most that stays readable in a 12px caption.
    if (parts.isEmpty) return null;
    return parts.take(3).join(', ');
  }

  /// Resolves [_center] to a readable address.
  ///
  /// Callers that fire on every panning event should use [_scheduleLookup]
  /// instead. This performs the lookup immediately.
  Future<String?> _lookupAddress(LatLng at) async {
    if (!await _geocoderIsPresent()) return null;
    final results = await Geocoding().placemarkFromCoordinates(
      at.latitude,
      at.longitude,
    );
    if (results.isEmpty) return null;
    return _composeAddress(results.first);
  }

  /// Debounced reverse geocode, for use while the map is moving.
  void _scheduleLookup() {
    _lookupDebounceTimer?.cancel();
    _lookupDebounceTimer = Timer(_lookupDebounce, _resolveAddress);
  }

  Future<void> _resolveAddress() async {
    if (_geocoderUnavailable) return;

    final at = _center;
    final token = ++_lookupToken;

    // _AddressLine prefers a resolved address over the spinner, so raising it
    // here is harmless when an address is already on screen.
    if (mounted) {
      setState(() => _resolvingAddress = true);
    }

    String? resolved;
    var absent = false;
    try {
      if (await _geocoderIsPresent()) {
        final results = await Geocoding().placemarkFromCoordinates(
          at.latitude,
          at.longitude,
        );
        if (results.isNotEmpty) resolved = _composeAddress(results.first);
      } else {
        absent = true;
      }
    } catch (_) {
      // A failed lookup is not worth interrupting the user for: the pin is
      // still valid, it just has no readable name here. Note this is treated
      // as a data gap, not a missing capability, so the UI keeps trying on
      // the next move.
    }

    // Discarded: the user moved on, or the screen closed.
    if (!mounted || token != _lookupToken) return;

    setState(() {
      _resolvingAddress = false;
      _address = resolved;
      if (absent) _geocoderUnavailable = true;
    });
  }

  /// Confirms the pin, waiting briefly for an address if none has landed yet.
  ///
  /// By the time the user has read the caption and tapped, the debounced
  /// lookup has usually already resolved. This covers the case where they
  /// confirm inside the debounce window, so the address handed back is never
  /// missing purely because of timing.
  Future<void> _confirm() async {
    final at = _center;

    if (_address == null && !_geocoderUnavailable) {
      _lookupDebounceTimer?.cancel();
      final token = ++_lookupToken;
      try {
        final resolved =
            await _lookupAddress(at).timeout(_confirmLookupTimeout);
        if (mounted && token == _lookupToken) _address = resolved;
      } catch (_) {
        // Keep whatever we had; coordinates are still a usable answer.
      }
    }

    if (!mounted) return;
    Navigator.pop(context, PickedLocation(point: at, address: _address));
  }

  Future<void> _useMyLocation() async {
    try {
      final permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        final granted = await Geolocator.requestPermission();
        if (granted == LocationPermission.denied ||
            granted == LocationPermission.deniedForever) {
          _showError('Location permission denied.');
          return;
        }
      }
      final pos = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          timeLimit: Duration(seconds: 15),
        ),
      ).timeout(const Duration(seconds: 20));

      setState(() => _center = LatLng(pos.latitude, pos.longitude));
      _map.move(_center, _detailZoom);
      // A GPS fix lands instantly, so skip the debounce and label it now.
      _lookupDebounceTimer?.cancel();
      _resolveAddress();
    } on TimeoutException {
      _showError('Could not get your location. Drag the pin instead.');
    } catch (_) {
      _showError('Could not get your location. Drag the pin instead.');
    }
  }

  Future<void> _suggestPins() async {
    final query = _searchController.text.trim();
    if (query.isEmpty) {
      _showError('Type an address, barangay, or landmark to search.');
      return;
    }
    setState(() {
      _searching = true;
      _searchError = null;
    });
    try {
      final results = await Geocoding().locationFromAddress(query);
      if (results.isEmpty) throw const FormatException('no results');
      final r = results.first;
      setState(() => _center = LatLng(r.latitude, r.longitude));
      _map.move(_center, _detailZoom);
      // move() is not a gesture, so onPositionChanged will not fire the
      // lookup. Label the destination explicitly.
      _lookupDebounceTimer?.cancel();
      _resolveAddress();
    } catch (_) {
      setState(() => _searchError = 'No matching place found for "$query".');
    } finally {
      if (mounted) setState(() => _searching = false);
    }
  }

  void _showError(String message) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
      content: Text(message),
      backgroundColor: AppColors.red,
    ));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.pageBg,
      appBar: AppBar(
        title: const Text('Set Location on Map'),
        actions: [
          // Confirming without a loaded map would silently return the
          // default centre, so hold the button until the style is ready.
          ValueListenableBuilder<bool>(
            valueListenable: _mapReady,
            builder: (context, ready, _) => !ready
                ? const SizedBox(width: 8)
                : TextButton.icon(
                    onPressed: _confirm,
                    style: TextButton.styleFrom(foregroundColor: Colors.white),
                    icon: const Icon(Icons.check_circle_outline, size: 20),
                    label: const Text(
                      'Use This Location',
                      style: TextStyle(fontWeight: FontWeight.w700),
                    ),
                  ),
          ),
          const SizedBox(width: 8),
        ],
      ),
      body: Column(
        children: [
          // ── Search / suggest pins ────────────────────────────────
          Container(
            color: Colors.white,
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
            child: Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: _searchController,
                    textInputAction: TextInputAction.search,
                    onSubmitted: (_) => _suggestPins(),
                    decoration: InputDecoration(
                      hintText: 'Suggest pins: search address / barangay',
                      hintStyle: const TextStyle(
                          fontSize: 13, color: AppColors.textMuted),
                      isDense: true,
                      filled: true,
                      fillColor: AppColors.fieldBg,
                      contentPadding: const EdgeInsets.symmetric(
                          horizontal: 14, vertical: 12),
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(999),
                        borderSide: BorderSide.none,
                      ),
                      prefixIcon: const Icon(Icons.search,
                          size: 20, color: AppColors.textMuted),
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                IconButton(
                  onPressed: _searching ? null : _suggestPins,
                  icon: _searching
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.my_location, color: AppColors.navy),
                  tooltip: 'Find address',
                ),
              ],
            ),
          ),
          if (_searchError != null)
            Container(
              width: double.infinity,
              color: const Color(0xFFFEF2F2),
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              child: Text(
                _searchError!,
                style: const TextStyle(fontSize: 12, color: AppColors.red),
              ),
            ),

          // ── Map ───────────────────────────────────────────────────
          Expanded(
            child: FutureBuilder<vt.Style>(
              future: _styleFuture,
              builder: (context, snapshot) {
                if (snapshot.hasError) {
                  return _MapMessage(
                    icon: Icons.cloud_off,
                    message:
                        'Could not load the map.\nCheck your connection and try again.',
                    onRetry: () {
                      setState(() {
                        _mapReady.value = false;
                        _styleFuture = _loadStyle();
                      });
                    },
                  );
                }

                final style = snapshot.data;
                if (style == null) {
                  return const Center(child: CircularProgressIndicator());
                }

                return Stack(
                  children: [
                    FlutterMap(
                      mapController: _map,
                      options: MapOptions(
                        initialCenter: _center,
                        initialZoom:
                            widget.initial == null ? _defaultZoom : _detailZoom,
                        minZoom: 3,
                        maxZoom: _maxZoom,
                        onPositionChanged: (camera, hasGesture) {
                          // Only follow the pin while the user is panning, so
                          // a programmatic moveTo is not immediately undone.
                          if (!hasGesture) return;
                          _center = camera.center;

                          // Drop the now-stale address instead of letting the
                          // caption describe a pin the user has already dragged
                          // away from. The guard collapses this to one rebuild
                          // per drag rather than one per pan frame: once the
                          // address is cleared and the spinner is up, nothing
                          // changes until a lookup comes back.
                          if (!_geocoderUnavailable &&
                              (_address != null || !_resolvingAddress)) {
                            setState(() {
                              _address = null;
                              _resolvingAddress = true;
                            });
                          }

                          // Panning fires this continuously, so the actual
                          // lookup is debounced: only the resting position is
                          // worth a platform-channel round trip.
                          _scheduleLookup();
                        },
                      ),
                      children: [
                        vt.VectorTileLayer(
                          theme: style.theme,
                          tileProviders: style.providers,
                          rasterSources: style.rasterSources,
                          sprites: style.sprites,
                        ),
                        // Required by the tile providers' terms of use.
                        SimpleAttributionWidget(
                          source: Text(
                            style.attributions.map((a) => a.text).join(' · '),
                            style: const TextStyle(fontSize: 10),
                          ),
                          backgroundColor: Colors.white.withValues(alpha: 0.8),
                        ),
                      ],
                    ),

                    // Center pin (not a Marker so it stays exactly centered)
                    IgnorePointer(
                      child: Center(
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Container(
                              width: 34,
                              height: 34,
                              decoration: BoxDecoration(
                                color: AppColors.navy,
                                shape: BoxShape.circle,
                                border:
                                    Border.all(color: Colors.white, width: 3),
                                boxShadow: [
                                  BoxShadow(
                                    color: const Color(0xFF0A3B71)
                                        .withValues(alpha: 0.35),
                                    blurRadius: 10,
                                    offset: const Offset(0, 3),
                                  ),
                                ],
                              ),
                              child: const Icon(Icons.water_drop,
                                  color: Colors.white, size: 18),
                            ),
                            Transform.translate(
                              offset: const Offset(0, -6),
                              child: const CustomPaint(
                                size: Size(10, 10),
                                painter: _PinTipPainter(color: AppColors.navy),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),

                    // Live caption: what the pin currently points at.
                    Positioned(
                      left: 16,
                      right: 16,
                      bottom: 40,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 14, vertical: 10),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(12),
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withValues(alpha: 0.08),
                              blurRadius: 12,
                              offset: const Offset(0, 2),
                            ),
                          ],
                        ),
                        child: Row(
                          children: [
                            const Icon(Icons.drag_indicator,
                                size: 18, color: AppColors.textMuted),
                            const SizedBox(width: 6),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  _AddressLine(
                                    address: _address,
                                    resolving: _resolvingAddress,
                                    unavailable: _geocoderUnavailable,
                                    center: _center,
                                  ),
                                  if (_address != null)
                                    Text(
                                      '${_center.latitude.toStringAsFixed(5)}, '
                                      '${_center.longitude.toStringAsFixed(5)}',
                                      style: TextStyle(
                                        fontSize: 11,
                                        color: AppColors.textMuted
                                            .withValues(alpha: 0.9),
                                      ),
                                    ),
                                ],
                              ),
                            ),
                            IconButton(
                              onPressed: _useMyLocation,
                              tooltip: 'Use my location',
                              icon: const Icon(Icons.gps_fixed,
                                  size: 22, color: AppColors.navy),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

/// The primary caption line: the resolved address, a spinner while it is
/// being looked up, or coordinates when the device has no geocoder.
class _AddressLine extends StatelessWidget {
  const _AddressLine({
    required this.address,
    required this.resolving,
    required this.unavailable,
    required this.center,
  });

  final String? address;
  final bool resolving;
  final bool unavailable;
  final LatLng center;

  @override
  Widget build(BuildContext context) {
    final coords =
        '${center.latitude.toStringAsFixed(5)}, ${center.longitude.toStringAsFixed(5)}';

    final resolved = address;
    if (resolved != null) {
      return Text(
        resolved,
        maxLines: 2,
        overflow: TextOverflow.ellipsis,
        style: const TextStyle(
          fontSize: 12.5,
          height: 1.25,
          color: AppColors.textDark,
          fontWeight: FontWeight.w600,
        ),
      );
    }

    if (unavailable) {
      return Text(
        coords,
        style: TextStyle(
          fontSize: 12,
          color: AppColors.textBody.withValues(alpha: 0.9),
        ),
      );
    }

    if (resolving) {
      return Row(
        children: [
          const SizedBox(
            width: 11,
            height: 11,
            child: CircularProgressIndicator(strokeWidth: 1.6),
          ),
          const SizedBox(width: 8),
          Text(
            'Finding address…',
            style: TextStyle(
              fontSize: 12,
              color: AppColors.textMuted.withValues(alpha: 0.9),
            ),
          ),
        ],
      );
    }

    // Idle before the first lookup fires.
    return Text(
      'Drag the map to place the pin',
      style: TextStyle(
        fontSize: 12,
        color: AppColors.textBody.withValues(alpha: 0.9),
      ),
    );
  }
}

/// Full-bleed message used while the style loads or when it cannot.
class _MapMessage extends StatelessWidget {
  const _MapMessage({
    required this.icon,
    required this.message,
    this.onRetry,
  });

  final IconData icon;
  final String message;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 40, color: AppColors.textMuted),
            const SizedBox(height: 12),
            Text(
              message,
              textAlign: TextAlign.center,
              style: const TextStyle(
                  fontSize: 13, color: AppColors.textBody, height: 1.5),
            ),
            if (onRetry != null) ...[
              const SizedBox(height: 16),
              OutlinedButton(
                  onPressed: onRetry, child: const Text('Try again')),
            ],
          ],
        ),
      ),
    );
  }
}

/// Small triangle below the pin circle so it reads as a map marker.
class _PinTipPainter extends CustomPainter {
  const _PinTipPainter({required this.color});

  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    final path = Path()
      ..moveTo(0, 0)
      ..lineTo(size.width / 2, size.height)
      ..lineTo(size.width, 0)
      ..close();
    canvas.drawPath(path, Paint()..color = color);
  }

  @override
  bool shouldRepaint(_PinTipPainter oldDelegate) => oldDelegate.color != color;
}
