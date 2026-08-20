import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';

import 'api/buildino_api.dart';
import 'screens/home_screen.dart';
import 'screens/login_screen.dart';
import 'session/session_store.dart';

const _apiBaseUrl = String.fromEnvironment(
  'BUILDINO_API_BASE_URL',
  defaultValue: 'http://localhost:8000',
);
const _appVersion = String.fromEnvironment(
  'BUILDINO_APP_VERSION',
  defaultValue: '1.0.0',
);

class BuildinoApp extends StatefulWidget {
  const BuildinoApp({required this.session, super.key});

  final SessionStore session;

  @override
  State<BuildinoApp> createState() => _BuildinoAppState();
}

class _BuildinoAppState extends State<BuildinoApp> {
  late final BuildinoApi _api;

  @override
  void initState() {
    super.initState();
    _api = BuildinoApi(
      baseUrl: _apiBaseUrl,
      deviceId: widget.session.deviceId,
      appVersion: _appVersion,
      platform: defaultTargetPlatform == TargetPlatform.iOS ? 'ios' : 'android',
    );
    _api.token = widget.session.token;
  }

  @override
  void dispose() {
    _api.close();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      debugShowCheckedModeBanner: false,
      title: 'Buildino',
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(
          seedColor: const Color(0xff175cd3),
        ),
        useMaterial3: true,
        inputDecorationTheme: const InputDecorationTheme(
          border: OutlineInputBorder(),
        ),
      ),
      home: Directionality(
        textDirection: TextDirection.rtl,
        child: AnimatedBuilder(
          animation: widget.session,
          builder: (context, _) {
            _api.token = widget.session.token;
            if (!widget.session.authenticated) {
              return LoginScreen(
                api: _api,
                onAuthenticated: widget.session.signIn,
              );
            }

            return HomeScreen(
              api: _api,
              onLogout: () async {
                try {
                  await _api.logout();
                } finally {
                  await widget.session.signOut();
                }
              },
            );
          },
        ),
      ),
    );
  }
}
