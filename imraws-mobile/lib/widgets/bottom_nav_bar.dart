import 'package:flutter/material.dart';
import '../theme/app_colors.dart';

enum NavTab { home, settings }

class MayniladBottomNav extends StatelessWidget {
  final NavTab active;
  final VoidCallback onHome;
  final VoidCallback onReport;
  final VoidCallback onSettings;
  final bool isCustomer;

  const MayniladBottomNav({
    super.key,
    required this.active,
    required this.onHome,
    required this.onReport,
    required this.onSettings,
    this.isCustomer = true,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 64,
      color: AppColors.navy,
      child: Stack(
        clipBehavior: Clip.none,
        alignment: Alignment.center,
        children: [
          // Home + Settings tabs
          Positioned.fill(
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceAround,
              children: [
                _NavItem(
                  icon: Icons.home_outlined,
                  iconActive: Icons.home,
                  label: 'Home',
                  active: active == NavTab.home,
                  onTap: onHome,
                ),
                if (isCustomer) const SizedBox(width: 64), // space for FAB
                _NavItem(
                  icon: Icons.settings_outlined,
                  iconActive: Icons.settings,
                  label: 'Settings',
                  active: active == NavTab.settings,
                  onTap: onSettings,
                ),
              ],
            ),
          ),

          // Floating Action Button
          if (isCustomer)
            Positioned(
              top: -22,
              child: Column(
                children: [
                  GestureDetector(
                    onTap: onReport,
                    child: Container(
                      width: 54,
                      height: 54,
                      decoration: BoxDecoration(
                        gradient: const LinearGradient(
                          colors: [Color(0xFF2E86DE), AppColors.navy],
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                        ),
                        shape: BoxShape.circle,
                        border: Border.all(color: Colors.white, width: 3),
                        boxShadow: [
                          BoxShadow(
                            color: AppColors.navy.withOpacity(0.55),
                            blurRadius: 18,
                            offset: const Offset(0, 4),
                          ),
                        ],
                      ),
                      child: const Icon(Icons.add, color: Colors.white, size: 26),
                    ),
                  ),
                  const SizedBox(height: 4),
                  const Text(
                    'Report',
                    style: TextStyle(
                      color: Colors.white70,
                      fontSize: 8.5,
                      fontWeight: FontWeight.w600,
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

class _NavItem extends StatelessWidget {
  final IconData icon;
  final IconData iconActive;
  final String label;
  final bool active;
  final VoidCallback onTap;

  const _NavItem({
    required this.icon,
    required this.iconActive,
    required this.label,
    required this.active,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final color = active ? Colors.white : Colors.white38;
    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(active ? iconActive : icon, color: color, size: 22),
            const SizedBox(height: 3),
            Text(
              label,
              style: TextStyle(
                color: color,
                fontSize: 9,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
