import 'package:flutter/material.dart';
import '../theme/app_colors.dart';

class MayniladLogo extends StatelessWidget {
  final double size;

  const MayniladLogo({super.key, this.size = 44});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: Colors.white,
        shape: BoxShape.circle,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.22),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Center(
        child: CustomPaint(
          size: Size(size * 0.65, size * 0.65),
          painter: _WaterDropPainter(),
        ),
      ),
    );
  }
}

class _WaterDropPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final dropPaint = Paint()..color = AppColors.navy;
    final wavePaint = Paint()
      ..color = Colors.white
      ..style = PaintingStyle.stroke
      ..strokeWidth = size.width * 0.06
      ..strokeCap = StrokeCap.round;
    final highlightPaint = Paint()
      ..color = Colors.white.withOpacity(0.45);

    // Water drop path
    final path = Path();
    final cx = size.width / 2;
    path.moveTo(cx, 0);
    path.cubicTo(cx, 0, 0, size.height * 0.45, 0, size.height * 0.68);
    path.arcToPoint(
      Offset(size.width, size.height * 0.68),
      radius: Radius.circular(size.width),
      clockwise: true,
    );
    path.cubicTo(
      size.width, size.height * 0.45,
      cx, 0,
      cx, 0,
    );
    canvas.drawPath(path, dropPaint);

    // Wave line
    final wavePath = Path();
    wavePath.moveTo(size.width * 0.15, size.height * 0.72);
    wavePath.quadraticBezierTo(
      size.width * 0.38, size.height * 0.58,
      cx, size.height * 0.72,
    );
    wavePath.quadraticBezierTo(
      size.width * 0.62, size.height * 0.86,
      size.width * 0.85, size.height * 0.72,
    );
    canvas.drawPath(wavePath, wavePaint);

    // Highlight ellipse
    canvas.drawOval(
      Rect.fromCenter(
        center: Offset(cx, size.height * 0.3),
        width: size.width * 0.28,
        height: size.height * 0.16,
      ),
      highlightPaint,
    );
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
