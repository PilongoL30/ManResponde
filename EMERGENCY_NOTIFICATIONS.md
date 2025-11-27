# 🚨 Enhanced Emergency Notification System for iBantay

## Overview
The emergency notification system has been significantly enhanced to ensure responders cannot miss critical alerts. The system now includes aggressive alarm sounds, strong vibration patterns, and various bypass mechanisms.

## Key Enhancements

### 🔊 Audio Features
- **Sound File**: `emergency_siren.caf` (loud alarm sound)
- **Volume**: Maximum volume override
- **iOS**: Critical alerts that bypass silent mode and Do Not Disturb
- **Android**: Custom siren sound with insistent playback (repeats until dismissed)
- **Bypass Settings**: Forces sound even when device is muted

### 📳 Vibration Features
- **Pattern**: Ultra-aggressive 8-burst pattern
- **Timing**: `[0, 1000, 200, 1000, 200, 1000, 200, 1000, 200, 1000]` milliseconds
- **Android**: Custom vibrate timings with strong bursts
- **Force Vibration**: Overrides device vibration settings

### 🚨 Visual Features
- **Android LED**: Red flashing light
- **Color**: Bright red (#FF0000) notifications
- **Heads-up**: Forces notification to appear on top of other apps
- **Full Screen**: Attempts to show full-screen notification on Android
- **Persistent**: Notifications stick and don't auto-dismiss

### ⚡ Priority & Bypass Features
- **Priority**: Maximum/Critical priority level
- **iOS**: Bypasses Focus modes and Do Not Disturb
- **Android**: PRIORITY_MAX with heads-up display
- **Screen Wake**: Attempts to wake up the device screen
- **Channel**: Emergency critical channel ID
- **Interruption Level**: Critical (iOS 15+)

### 📱 Platform-Specific Features

#### Android Enhancements:
- `channel_id`: "emergency_critical"
- `notification_priority`: "PRIORITY_MAX"
- `ongoing`: true (persistent notification)
- `auto_cancel`: false (must be manually dismissed)
- `only_alert_once`: false (allows repeated alerts)
- `wake_screen`: true (wake device)
- `full_screen_intent`: true (full screen notification)
- `insistent`: true (repeating alarm sound)
- `bypass_dnd`: true (bypass Do Not Disturb)

#### iOS Enhancements:
- `critical`: 1 (critical alert)
- `interruption-level`: "critical" (iOS 15+)
- `apns-priority`: "10" (highest priority)
- `relevance-score`: 1.0 (maximum relevance)
- Custom sound: `emergency_siren.caf`
- Volume: 1.0 (maximum)

## Configuration Constants

```php
// Enhanced notification settings
define('FCM_NOTIFICATION_SOUND', 'emergency_siren');
define('FCM_NOTIFICATION_CHANNEL_ID', 'emergency_critical');
define('FCM_VIBRATION_PATTERN', [0, 1000, 200, 1000, 200, 1000, 200, 1000, 200, 1000]);
define('FCM_NOTIFICATION_COLOR', '#FF0000');
```

## Usage

The enhanced emergency notifications are automatically used when:
1. A report is approved in the dashboard
2. `send_emergency_fcm_notification()` is called for responders
3. The notification target has `role: 'responder'` in Firestore

## Testing

Use `test_emergency_enhanced.php` to test the enhanced notification system:
1. Enter a responder's UID
2. Select emergency type
3. Send test notification
4. Responder should experience:
   - Loud siren alarm
   - Strong vibration (8 bursts)
   - Screen wake-up
   - Heads-up notification
   - Bypassed silent/DND modes

## Expected User Experience

### What Responders Will Experience:
1. **Immediate Alert**: Device wakes up and shows notification
2. **Loud Alarm**: Siren sound at maximum volume
3. **Strong Vibration**: 8 powerful vibration bursts
4. **Visual Cues**: Red flashing LED (Android) and prominent notification
5. **Persistent**: Notification stays until manually dismissed
6. **Bypass**: Works even in silent mode or Do Not Disturb

### Impossible to Miss Because:
- Maximum volume override
- Screen wake-up
- Strong vibration pattern
- Bypasses silent modes
- Persistent display
- Full-screen intent (Android)
- Critical priority (iOS)

## Technical Notes

- All enhancements are backward compatible
- Uses FCM V1 API for best reliability
- Responder-only targeting (checks `role: 'responder'`)
- Comprehensive error logging for debugging
- Platform-specific optimizations for Android and iOS
