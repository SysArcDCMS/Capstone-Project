import 'dart:async';

import 'package:flutter/material.dart';
import 'package:geocoding/geocoding.dart';
import 'package:geolocator/geolocator.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';

import '../theme/app_colors.dart';

/// Full-screen Google Maps pin picker used by the complaint form.
///
/// Defaults to Manila (Maynilad service area) when no location is known,
/// then lets the user:
///   - drag the center pin to the exact spot,
///   - tap "Use My Location" (device GPS, geolocator),
///   - search an address/barangay to jump the pin (geocoding).
///
/// Returns a `LatLng` via `Navigator.pop` when confirmed. Requires a real
/// Google Maps API key: set it in AndroidManifest.xml under the
/// `com.google.android.geo.API_KEY` meta-data entry.
class MapPickerScreen extends StatefulWidget {
  const MapPickerScreen({super.key, this.initial});

  /// Pre-fill with the currently selected pin (e.g. when editing).
  final LatLng? initial;

  @override
  State<MapPickerScreen> createState() => _MapPickerScreenState();
}

class _MapPickerScreenState extends State<MapPickerScreen> {
  static const LatLng _defaultCenter = LatLng(14.5995, 120.9842); // Manila

  late final GoogleMapController _mapController;

  LatLng _center = _defaultCenter;
  bool _centerReady = false;
  bool _searching = false;
  String? _searchError;

  final _searchController = TextEditingController();

  @override
  void initState() {
    super.initState();
    if (widget.initial != null) {
      _center = widget.initial!;
    }
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  void _onMapCreated(GoogleMapController controller) {
    _mapController = controller;
    _centerReady = true;
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
      _animateTo(_center);
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
      _animateTo(_center);
    } catch (_) {
      setState(() => _searchError = 'No matching place found for "$query".');
    } finally {
      setState(() => _searching = false);
    }
  }

  void _animateTo(LatLng target) {
    if (!_centerReady) return;
    _mapController.animateCamera(
      CameraUpdate.newLatLngZoom(target, 17),
    );
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
          TextButton.icon(
            onPressed: () => Navigator.pop(context, _center),
            style: TextButton.styleFrom(foregroundColor: Colors.white),
            icon: const Icon(Icons.check_circle_outline, size: 20),
            label: const Text(
              'Use This Location',
              style: TextStyle(fontWeight: FontWeight.w700),
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
                      hintStyle: const TextStyle(fontSize: 13, color: AppColors.textMuted),
                      isDense: true,
                      filled: true,
                      fillColor: AppColors.fieldBg,
                      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(999),
                        borderSide: BorderSide.none,
                      ),
                      prefixIcon: const Icon(Icons.search, size: 20, color: AppColors.textMuted),
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
            child: Stack(
              children: [
                GoogleMap(
                  mapType: MapType.normal,
                  initialCameraPosition: CameraPosition(
                    target: _center,
                    zoom: widget.initial == null ? 13 : 17,
                  ),
                  onMapCreated: _onMapCreated,
                  onCameraMove: (pos) => _center = pos.target,
                  myLocationEnabled: true,
                  myLocationButtonEnabled: true,
                  mapToolbarEnabled: false,
                  tiltGesturesEnabled: false,
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
                            border: Border.all(color: Colors.white, width: 3),
                            boxShadow: [
                              BoxShadow(
                                color: const Color(0xFF0A3B71).withValues(alpha: 0.35),
                                blurRadius: 10,
                                offset: const Offset(0, 3),
                              ),
                            ],
                          ),
                          child: const Icon(Icons.water_drop, color: Colors.white, size: 18),
                        ),
                        Transform.translate(
                          offset: const Offset(0, -6),
                          child: CustomPaint(
                            size: const Size(10, 10),
                            painter: _PinTipPainter(color: AppColors.navy),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),

                // Helper caption
                Positioned(
                  left: 16,
                  right: 16,
                  bottom: 16,
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
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
                        const Icon(Icons.drag_indicator, size: 18, color: AppColors.textMuted),
                        const SizedBox(width: 6),
                        Expanded(
                          child: Text(
                            'Drag the map to place the pin · tap Use My Location for GPS',
                            style: TextStyle(
                              fontSize: 12,
                              color: AppColors.textBody.withValues(alpha: 0.9),
                            ),
                          ),
                        ),
                        IconButton(
                          onPressed: _useMyLocation,
                          tooltip: 'Use my location',
                          icon: const Icon(Icons.gps_fixed, size: 22, color: AppColors.navy),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Small triangle below the pin circle so it reads as a map marker.
class _PinTipPainter extends CustomPainter {
  _PinTipPainter({required this.color});

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