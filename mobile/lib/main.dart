import 'package:flutter/material.dart';

import 'src/app.dart';
import 'src/session/session_store.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final session = SessionStore();
  await session.restore();
  runApp(BuildinoApp(session: session));
}
