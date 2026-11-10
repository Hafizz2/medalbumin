import type { ConfigContext, ExpoConfig } from 'expo/config';

/**
 * One codebase for Android and iOS.
 * Bundle IDs below must match what you register in App Store Connect and Google Play Console.
 */
export default ({ config }: ConfigContext): ExpoConfig => ({
  ...config,
  name: 'MedAlbumin',
  slug: 'mednexus',
  scheme: 'medalbumin',
  version: '1.0.0',
  orientation: 'portrait',
  icon: './assets/icon.png',
  userInterfaceStyle: 'automatic',
  backgroundColor: '#F3F6FA',
  // Splash screen is configured by the expo-splash-screen plugin below.
  ios: {
    bundleIdentifier: 'com.medalbumin.app',
    buildNumber: '1',
    supportsTablet: true,
    usesAppleSignIn: true,
    config: { usesNonExemptEncryption: false },
    infoPlist: {
      NSPhotoLibraryUsageDescription: 'MedAlbumin uses your photos only when you choose a profile picture or a student ID photo.',
      NSCameraUsageDescription: 'MedAlbumin uses the camera only when you take a photo of your student ID.',
      ITSAppUsesNonExemptEncryption: false,
    },
    privacyManifests: {
      NSPrivacyAccessedAPITypes: [
        { NSPrivacyAccessedAPIType: 'NSPrivacyAccessedAPICategoryUserDefaults', NSPrivacyAccessedAPITypeReasons: ['CA92.1'] },
        { NSPrivacyAccessedAPIType: 'NSPrivacyAccessedAPICategoryFileTimestamp', NSPrivacyAccessedAPITypeReasons: ['C617.1'] },
      ],
    },
  },
  android: {
    package: 'com.medalbumin.app',
    versionCode: 1,
    adaptiveIcon: {
      foregroundImage: './assets/adaptive-icon.png',
      backgroundColor: '#FFFFFF',
    },
    // Only what the app needs; Play Console's data-safety form follows from this list.
    permissions: ['android.permission.INTERNET', 'android.permission.ACCESS_NETWORK_STATE', 'android.permission.POST_NOTIFICATIONS', 'android.permission.VIBRATE'],
    blockedPermissions: ['android.permission.RECORD_AUDIO', 'android.permission.READ_EXTERNAL_STORAGE', 'android.permission.WRITE_EXTERNAL_STORAGE'],
  },
  plugins: [
    'expo-router',
    'expo-font',
    'expo-secure-store',
    'expo-localization',
    'expo-apple-authentication',
    'expo-video',
    [
      'expo-notifications',
      { color: '#017F8E' },
    ],
    [
      'expo-image-picker',
      {
        photosPermission: 'MedAlbumin uses your photos only when you choose a profile picture or a student ID photo.',
        cameraPermission: 'MedAlbumin uses the camera only when you take a photo of your student ID.',
        microphonePermission: false,
      },
    ],
    [
      'expo-splash-screen',
      { image: './assets/splash-icon.png', imageWidth: 160, backgroundColor: '#062A5E' },
    ],
    // Home screen widget (streak, today's goal, exam countdown). Needs a development or store build — not Expo Go.
    [
      'expo-widgets',
      {
        groupIdentifier: 'group.com.medalbumin.app',
        // Android widgets are still marked experimental in expo-widgets, so they are opt-in.
        enableAndroid: true,
        widgets: [
          {
            name: 'StreakWidget',
            displayName: 'MedAlbumin streak',
            description: 'Your study streak, today’s goal and days to your exam',
            ios: {
              supportedFamilies: ['systemSmall', 'systemMedium', 'accessoryCircular', 'accessoryRectangular'],
              initialLayout: './src/widgets/StreakWidget.ios.tsx',
            },
            android: {
              // Default drop size is 4×2; it can shrink to a 2×1 strip or grow to fill the screen,
              // and the layout adapts to the exact size (see src/widgets/StreakWidget.android.tsx).
              minWidth: 110,
              minHeight: 50,
              targetCellWidth: 4,
              targetCellHeight: 2,
              resizeMode: 'both',
              initialLayout: './src/widgets/StreakWidget.android.tsx',
            },
          },
        ],
      },
    ],
  ],
  experiments: { typedRoutes: true },
  extra: {
    apiUrl: process.env.EXPO_PUBLIC_API_URL,
    eas: {
      projectId: '22bce7c8-060a-4083-a707-b7ae5d34b36f',
    },
  },
});
