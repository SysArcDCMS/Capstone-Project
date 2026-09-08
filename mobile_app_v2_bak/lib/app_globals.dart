import 'package:flutter/material.dart';

/// Global navigator key so non-widget code (e.g. the auth provider's
/// forced logout) can navigate back to the Login screen.
final GlobalKey<NavigatorState> appNavigatorKey = GlobalKey<NavigatorState>();